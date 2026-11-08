<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Modelo de pedidos y su detalle.
 *
 * Al crear un pedido, todo ocurre en UNA transacción:
 *   1. Se bloquea cada variante (SELECT ... FOR UPDATE) y se verifica el stock.
 *   2. El precio se toma de la base de datos, nunca del navegador.
 *   3. Se guarda el pedido, su detalle, se descuenta el stock y se registra la venta.
 * Si algo falla, no se guarda nada.
 */
final class Pedido extends Model
{
    public const ESTADOS = ['pendiente', 'pagado', 'enviado', 'entregado', 'cancelado'];

    /** Cambios de estado permitidos: el pedido avanza en orden y solo se cancela antes de enviarse. */
    public const TRANSICIONES = [
        'pendiente' => ['pagado', 'cancelado'],
        'pagado' => ['enviado', 'cancelado'],
        'enviado' => ['entregado'],
        'entregado' => [],
        'cancelado' => [],
    ];

    /** Regla de negocio: un pedido no puede llevar más de esta cantidad de la misma variante. */
    public const MAXIMO_UNIDADES_POR_LINEA = 10;

    /**
     * Calcula el pedido con los precios vigentes (incluidas ofertas) y el cupón.
     * La usan la vista previa del checkout (POST /api/pedidos/cotizar) y la compra real,
     * así ambos montos siempre coinciden.
     *
     * Con $bloquear = true (dentro de una transacción) bloquea las variantes y el cupón
     * con SELECT ... FOR UPDATE para que nadie más los modifique mientras se compra.
     *
     * @param array<int, int> $cantidades variante_id => cantidad
     * @return array{lineas: list<array<string, mixed>>, errores: list<string>, subtotal: float,
     *               descuento: float, total: float, cupon: ?array, error_cupon: ?string}
     */
    public function cotizar(array $cantidades, ?string $codigoCupon, int $clienteId, bool $bloquear = false): array
    {
        $this->validarCantidades($cantidades);

        $lineas = [];
        $errores = [];

        foreach ($cantidades as $varianteId => $cantidad) {
            $variante = $this->obtenerUno(
                'SELECT v.id, v.stock, v.activo AS variante_activa,
                        p.nombre, p.slug, p.precio AS precio_regular, p.activo AS producto_activo,
                        ' . Producto::SQL_PRECIO_VIGENTE . ' AS precio,
                        t.valor AS talla, c.nombre AS color
                   FROM variantes v
                   JOIN productos p ON p.id = v.producto_id
                   JOIN tallas t ON t.id = v.talla_id
                   JOIN colores c ON c.id = v.color_id
                  WHERE v.id = :id' . ($bloquear ? ' FOR UPDATE' : ''),
                ['id' => $varianteId]
            );

            if ($variante === null || !$variante['variante_activa'] || !$variante['producto_activo']) {
                $errores[] = 'Un producto de tu carrito ya no está disponible.';
                continue;
            }

            $descripcion = sprintf('%s - %s - Talla %s', $variante['nombre'], $variante['color'], self::formatearTalla($variante['talla']));
            if ((int) $variante['stock'] < $cantidad) {
                $errores[] = "$descripcion: solo quedan {$variante['stock']} unidades.";
                continue;
            }

            $precio = (float) $variante['precio'];
            $lineas[] = [
                'variante_id' => (int) $variante['id'],
                'descripcion' => $descripcion,
                'slug' => $variante['slug'],
                'cantidad' => $cantidad,
                'precio' => $precio,
                'precio_regular' => (float) $variante['precio_regular'],
                'subtotal' => round($precio * $cantidad, 2),
                'stock_resultante' => (int) $variante['stock'] - $cantidad,
            ];
        }

        $subtotal = (float) array_sum(array_column($lineas, 'subtotal'));
        $descuento = 0.0;
        $cupon = null;
        $errorCupon = null;

        if ($codigoCupon !== null && $codigoCupon !== '') {
            $cupones = new Cupon();
            $cupon = $cupones->buscarPorCodigo($codigoCupon, $bloquear);
            if ($cupon === null) {
                $errorCupon = 'El cupón no existe.';
            } else {
                $errorCupon = $cupones->motivoInvalido($cupon, $subtotal, $clienteId);
                if ($errorCupon === null) {
                    $descuento = $cupones->calcularDescuento($cupon, $subtotal);
                } else {
                    $cupon = null;
                }
            }
        }

        return [
            'lineas' => $lineas,
            'errores' => array_values(array_unique($errores)),
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'total' => $subtotal - $descuento,
            'cupon' => $cupon === null ? null : [
                'id' => $cupon['id'],
                'codigo' => $cupon['codigo'],
                'descripcion' => $cupon['descripcion'],
                'tipo' => $cupon['tipo'],
                'valor' => $cupon['valor'],
            ],
            'error_cupon' => $errorCupon,
        ];
    }

    /**
     * @param array{nombre_contacto: string, telefono: string, direccion: string, ciudad: string, notas: ?string} $envio
     * @param array<int, int> $cantidades variante_id => cantidad
     */
    public function crear(int $clienteId, array $envio, array $cantidades, ?string $codigoCupon = null): int
    {
        return Database::transaccion(function () use ($clienteId, $envio, $cantidades, $codigoCupon): int {
            $calculo = $this->cotizar($cantidades, $codigoCupon, $clienteId, true);

            if ($calculo['errores'] !== []) {
                throw new HttpException(409, 'Algunos productos de tu carrito cambiaron.', ['items' => implode(' ', $calculo['errores'])]);
            }
            if ($calculo['error_cupon'] !== null) {
                throw HttpException::validacion(['cupon' => $calculo['error_cupon']], 'El cupón no se pudo aplicar.');
            }

            $lineas = $calculo['lineas'];
            $cupon = $calculo['cupon'];

            $this->ejecutar(
                'INSERT INTO pedidos (cliente_id, cupon_id, cupon_codigo, subtotal, descuento, total,
                                      nombre_contacto, telefono, direccion, ciudad, notas)
                 VALUES (:cliente, :cupon, :codigo, :subtotal, :descuento, :total,
                         :nombre, :telefono, :direccion, :ciudad, :notas)',
                [
                    'cliente' => $clienteId,
                    'cupon' => $cupon['id'] ?? null,
                    'codigo' => $cupon['codigo'] ?? null,
                    'subtotal' => number_format($calculo['subtotal'], 2, '.', ''),
                    'descuento' => number_format($calculo['descuento'], 2, '.', ''),
                    'total' => number_format($calculo['total'], 2, '.', ''),
                    'nombre' => $envio['nombre_contacto'],
                    'telefono' => $envio['telefono'],
                    'direccion' => $envio['direccion'],
                    'ciudad' => $envio['ciudad'],
                    'notas' => $envio['notas'],
                ]
            );
            $pedidoId = (int) $this->db->lastInsertId();

            if ($cupon !== null) {
                (new Cupon())->sumarUso($cupon['id']);
            }

            $variantes = new Variante();
            foreach ($lineas as $linea) {
                $this->ejecutar(
                    'INSERT INTO pedido_detalle (pedido_id, variante_id, descripcion, cantidad, precio_unitario, subtotal)
                     VALUES (:pedido, :variante, :descripcion, :cantidad, :precio, :subtotal)',
                    [
                        'pedido' => $pedidoId,
                        'variante' => $linea['variante_id'],
                        'descripcion' => $linea['descripcion'],
                        'cantidad' => $linea['cantidad'],
                        'precio' => number_format($linea['precio'], 2, '.', ''),
                        'subtotal' => number_format($linea['subtotal'], 2, '.', ''),
                    ]
                );
                // Inventario se encarga del stock; el pedido solo lo solicita.
                $variantes->descontarPorVenta($linea['variante_id'], $linea['cantidad'], $clienteId, $pedidoId);
            }

            return $pedidoId;
        });
    }

    /**
     * @param array{cliente_id?: int, estado?: ?string, q?: ?string, desde?: ?string, hasta?: ?string} $filtros
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        $condiciones = [];
        $parametros = [];

        if (!empty($filtros['cliente_id'])) {
            $condiciones[] = 'pe.cliente_id = :cliente';
            $parametros['cliente'] = $filtros['cliente_id'];
        }
        if (!empty($filtros['estado'])) {
            $condiciones[] = 'pe.estado = :estado';
            $parametros['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['q'])) {
            // Busca por número de pedido (#15 o 15) o por nombre/correo del cliente.
            $numero = ltrim($filtros['q'], '#');
            if (ctype_digit($numero)) {
                $condiciones[] = 'pe.id = :numero';
                $parametros['numero'] = (int) $numero;
            } else {
                $condiciones[] = '(u.nombre LIKE :busqueda OR u.email LIKE :busqueda_email)';
                $parametros['busqueda'] = '%' . addcslashes($filtros['q'], '%_\\') . '%';
                $parametros['busqueda_email'] = $parametros['busqueda'];
            }
        }
        if (!empty($filtros['desde'])) {
            $condiciones[] = 'pe.creado_en >= :desde';
            $parametros['desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'pe.creado_en <= :hasta';
            $parametros['hasta'] = $filtros['hasta'] . ' 23:59:59';
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        $desde = 'FROM pedidos pe JOIN usuarios u ON u.id = pe.cliente_id LEFT JOIN usuarios ve ON ve.id = pe.vendedor_id';

        $total = (int) $this->obtenerUno("SELECT COUNT(*) AS total $desde $where", $parametros)['total'];

        $filas = $this->obtenerTodos(
            "SELECT pe.id, pe.estado, pe.total, pe.ciudad, pe.creado_en,
                    u.id AS cliente_id, u.nombre AS cliente, u.email AS cliente_email,
                    ve.nombre AS vendedor,
                    (SELECT COALESCE(SUM(d.cantidad), 0) FROM pedido_detalle d WHERE d.pedido_id = pe.id) AS unidades
             $desde $where
             ORDER BY pe.creado_en DESC, pe.id DESC
             LIMIT :limite OFFSET :desplazamiento",
            $parametros + ['limite' => $porPagina, 'desplazamiento' => ($pagina - 1) * $porPagina]
        );

        $items = array_map(static fn (array $fila): array => [
            'id' => (int) $fila['id'],
            'estado' => $fila['estado'],
            'total' => (float) $fila['total'],
            'ciudad' => $fila['ciudad'],
            'unidades' => (int) $fila['unidades'],
            'creado_en' => $fila['creado_en'],
            'cliente' => ['id' => (int) $fila['cliente_id'], 'nombre' => $fila['cliente'], 'email' => $fila['cliente_email']],
            'vendedor' => $fila['vendedor'],
        ], $filas);

        return ['items' => $items, 'total' => $total];
    }

    /** @return array<string, mixed>|null Pedido con cliente, vendedor y detalle */
    public function buscar(int $id): ?array
    {
        $pedido = $this->obtenerUno(
            'SELECT pe.*, u.nombre AS cliente_nombre, u.email AS cliente_email, ve.nombre AS vendedor_nombre
               FROM pedidos pe
               JOIN usuarios u ON u.id = pe.cliente_id
               LEFT JOIN usuarios ve ON ve.id = pe.vendedor_id
              WHERE pe.id = :id',
            ['id' => $id]
        );
        if ($pedido === null) {
            return null;
        }

        $detalle = array_map(static fn (array $fila): array => [
            'id' => (int) $fila['id'],
            'variante_id' => (int) $fila['variante_id'],
            'descripcion' => $fila['descripcion'],
            'cantidad' => (int) $fila['cantidad'],
            'precio_unitario' => (float) $fila['precio_unitario'],
            'subtotal' => (float) $fila['subtotal'],
            'sku' => $fila['sku'],
            'slug' => $fila['slug'],
            'imagen' => $fila['imagen'],
        ], $this->obtenerTodos(
            'SELECT d.id, d.variante_id, d.descripcion, d.cantidad, d.precio_unitario, d.subtotal,
                    v.sku, p.slug,
                    (SELECT i.ruta FROM producto_imagenes i WHERE i.producto_id = p.id ORDER BY i.orden, i.id LIMIT 1) AS imagen
               FROM pedido_detalle d
               JOIN variantes v ON v.id = d.variante_id
               JOIN productos p ON p.id = v.producto_id
              WHERE d.pedido_id = :id
              ORDER BY d.id',
            ['id' => $id]
        ));

        return [
            'id' => (int) $pedido['id'],
            'estado' => $pedido['estado'],
            'estados_siguientes' => self::TRANSICIONES[$pedido['estado']],
            'subtotal' => (float) $pedido['subtotal'],
            'descuento' => (float) $pedido['descuento'],
            'cupon' => $pedido['cupon_codigo'],
            'total' => (float) $pedido['total'],
            'nombre_contacto' => $pedido['nombre_contacto'],
            'telefono' => $pedido['telefono'],
            'direccion' => $pedido['direccion'],
            'ciudad' => $pedido['ciudad'],
            'notas' => $pedido['notas'],
            'creado_en' => $pedido['creado_en'],
            'actualizado_en' => $pedido['actualizado_en'],
            'cliente' => ['id' => (int) $pedido['cliente_id'], 'nombre' => $pedido['cliente_nombre'], 'email' => $pedido['cliente_email']],
            'vendedor' => $pedido['vendedor_nombre'],
            'detalle' => $detalle,
            // Último intento de pago en línea (Wompi), o null si nunca se intentó
            'pago' => (new Pago())->ultimoDePedido((int) $pedido['id']),
        ];
    }

    /**
     * Cambia el estado respetando TRANSICIONES. Si se cancela, devuelve el stock
     * de cada producto y lo registra como devolución en el inventario.
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $usuarioId, bool $esPersonal): void
    {
        Database::transaccion(function () use ($id, $nuevoEstado, $usuarioId, $esPersonal): void {
            $pedido = $this->obtenerUno('SELECT estado, cupon_id FROM pedidos WHERE id = :id FOR UPDATE', ['id' => $id]);
            if ($pedido === null) {
                throw HttpException::noEncontrado('El pedido no existe.');
            }

            if (!in_array($nuevoEstado, self::TRANSICIONES[$pedido['estado']], true)) {
                throw HttpException::validacion(
                    ['estado' => "Un pedido $pedido[estado] no puede pasar a $nuevoEstado."],
                    'Cambio de estado no permitido.'
                );
            }

            if ($nuevoEstado === 'cancelado') {
                $this->devolverStock($id, $usuarioId);
                if ($pedido['cupon_id'] !== null) {
                    (new Cupon())->restarUso((int) $pedido['cupon_id']);
                }
            }

            // El primer miembro del personal que gestiona el pedido queda como su vendedor.
            $this->ejecutar(
                'UPDATE pedidos
                    SET estado = :estado,
                        vendedor_id = CASE WHEN :personal = 1 THEN COALESCE(vendedor_id, :usuario) ELSE vendedor_id END
                  WHERE id = :id',
                ['estado' => $nuevoEstado, 'personal' => $esPersonal ? 1 : 0, 'usuario' => $usuarioId, 'id' => $id]
            );
        });
    }

    private function devolverStock(int $pedidoId, int $usuarioId): void
    {
        $variantes = new Variante();
        $lineas = $this->obtenerTodos(
            'SELECT variante_id, cantidad FROM pedido_detalle WHERE pedido_id = :pedido',
            ['pedido' => $pedidoId]
        );

        foreach ($lineas as $linea) {
            $variantes->devolverPorCancelacion((int) $linea['variante_id'], (int) $linea['cantidad'], $usuarioId, $pedidoId);
        }
    }

    /**
     * Cancelación hecha por el propio cliente: solo sobre sus pedidos y solo mientras
     * están pendientes. Si el pedido es de otra persona se responde como si no existiera.
     */
    public function cancelarPorCliente(int $id, int $clienteId): void
    {
        Database::transaccion(function () use ($id, $clienteId): void {
            $pedido = $this->obtenerUno('SELECT cliente_id, estado FROM pedidos WHERE id = :id FOR UPDATE', ['id' => $id]);
            if ($pedido === null || (int) $pedido['cliente_id'] !== $clienteId) {
                throw HttpException::noEncontrado('El pedido no existe.');
            }
            if ($pedido['estado'] !== 'pendiente') {
                throw HttpException::validacion(['estado' => 'Solo puedes cancelar pedidos pendientes. Escríbenos si necesitas ayuda.']);
            }

            $this->cambiarEstado($id, 'cancelado', $clienteId, false);
        });
    }

    /**
     * Regla de negocio: máximo de unidades por variante en un mismo pedido.
     * Se revisa sobre las cantidades ya agrupadas, por si la misma variante llega repetida.
     *
     * @param array<int, int> $cantidades variante_id => cantidad
     */
    private function validarCantidades(array $cantidades): void
    {
        foreach ($cantidades as $cantidad) {
            if ($cantidad > self::MAXIMO_UNIDADES_POR_LINEA) {
                throw HttpException::validacion(
                    ['items' => 'Puedes llevar máximo ' . self::MAXIMO_UNIDADES_POR_LINEA . ' unidades de cada producto por pedido.'],
                    'Revisa las cantidades de tu carrito.'
                );
            }
        }
    }
}
