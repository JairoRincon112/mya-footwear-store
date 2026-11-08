<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Modelo de la tabla variantes: cada combinación talla + color de un producto,
 * con su SKU y su stock. Todo cambio de stock queda registrado en movimientos_inventario.
 */
final class Variante extends Model
{
    /** Movimientos que el personal puede registrar a mano (venta y devolución por cancelación las genera Pedido). */
    public const TIPOS_MANUALES = ['entrada', 'ajuste', 'devolucion'];

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $variante = $this->obtenerUno(
            'SELECT v.id, v.producto_id, v.sku, v.stock, v.activo,
                    t.id AS talla_id, t.valor AS talla_valor,
                    c.id AS color_id, c.nombre AS color_nombre, c.hex AS color_hex
               FROM variantes v
               JOIN tallas t ON t.id = v.talla_id
               JOIN colores c ON c.id = v.color_id
              WHERE v.id = :id',
            ['id' => $id]
        );

        if ($variante === null) {
            return null;
        }

        return [
            'id' => (int) $variante['id'],
            'producto_id' => (int) $variante['producto_id'],
            'sku' => $variante['sku'],
            'stock' => (int) $variante['stock'],
            'activo' => (bool) $variante['activo'],
            'talla' => ['id' => (int) $variante['talla_id'], 'valor' => self::formatearTalla($variante['talla_valor'])],
            'color' => ['id' => (int) $variante['color_id'], 'nombre' => $variante['color_nombre'], 'hex' => $variante['color_hex']],
        ];
    }

    public function existeCombinacion(int $productoId, int $tallaId, int $colorId): bool
    {
        return $this->obtenerUno(
            'SELECT 1 FROM variantes WHERE producto_id = :producto AND talla_id = :talla AND color_id = :color',
            ['producto' => $productoId, 'talla' => $tallaId, 'color' => $colorId]
        ) !== null;
    }

    public function skuDisponible(string $sku, ?int $exceptoId = null): bool
    {
        return $this->obtenerUno(
            'SELECT 1 FROM variantes WHERE sku = :sku AND id <> :excepto',
            ['sku' => $sku, 'excepto' => $exceptoId ?? 0]
        ) === null;
    }

    /**
     * Crea la variante y, si tiene stock inicial, registra la entrada de inventario.
     * Debe llamarse dentro de Database::transaccion().
     */
    public function crear(int $productoId, int $tallaId, int $colorId, string $sku, int $stock, int $usuarioId): int
    {
        $this->ejecutar(
            'INSERT INTO variantes (producto_id, talla_id, color_id, sku, stock)
             VALUES (:producto, :talla, :color, :sku, :stock)',
            ['producto' => $productoId, 'talla' => $tallaId, 'color' => $colorId, 'sku' => $sku, 'stock' => $stock]
        );
        $id = (int) $this->db->lastInsertId();

        if ($stock > 0) {
            $this->registrarMovimiento($id, $usuarioId, 'entrada', $stock, $stock, 'Stock inicial');
        }

        return $id;
    }

    public function actualizar(int $id, string $sku, bool $activo): void
    {
        $this->ejecutar(
            'UPDATE variantes SET sku = :sku, activo = :activo WHERE id = :id',
            ['sku' => $sku, 'activo' => $activo ? 1 : 0, 'id' => $id]
        );
    }

    /**
     * Movimiento manual de inventario (entrada, ajuste o devolución) hecho por el personal.
     * Aplica las reglas del inventario y suma o resta unidades de forma segura:
     * SELECT ... FOR UPDATE bloquea la fila hasta terminar la transacción, así dos
     * personas no pueden modificar el mismo stock al mismo tiempo.
     *
     * @return int Stock resultante
     */
    public function ajustarStock(int $id, int $cantidad, string $tipo, ?string $motivo, int $usuarioId): int
    {
        $this->validarMovimientoManual($cantidad, $tipo, $motivo);

        return Database::transaccion(function () use ($id, $cantidad, $tipo, $motivo, $usuarioId): int {
            $stock = $this->stockBloqueado($id);

            $nuevoStock = $stock + $cantidad;
            if ($nuevoStock < 0) {
                throw HttpException::validacion(
                    ['cantidad' => "No puedes descontar más unidades de las disponibles ($stock)."]
                );
            }

            $this->guardarStock($id, $nuevoStock);
            $this->registrarMovimiento($id, $usuarioId, $tipo, $cantidad, $nuevoStock, $motivo);

            return $nuevoStock;
        });
    }

    /**
     * Descuenta las unidades vendidas en un pedido y registra el movimiento "venta".
     * Gestión de pedidos la llama dentro de su propia transacción, así que si algo
     * falla después, el descuento también se deshace.
     *
     * @return int Stock resultante
     */
    public function descontarPorVenta(int $id, int $cantidad, int $usuarioId, int $pedidoId): int
    {
        return Database::transaccion(function () use ($id, $cantidad, $usuarioId, $pedidoId): int {
            $stock = $this->stockBloqueado($id);
            if ($stock < $cantidad) {
                throw new HttpException(409, 'Algunos productos de tu carrito cambiaron.', [
                    'items' => "Un producto de tu carrito solo tiene $stock unidades disponibles.",
                ]);
            }

            $nuevoStock = $stock - $cantidad;
            $this->guardarStock($id, $nuevoStock);
            $this->registrarMovimiento($id, $usuarioId, 'venta', -$cantidad, $nuevoStock, "Pedido #$pedidoId", $pedidoId);

            return $nuevoStock;
        });
    }

    /**
     * Devuelve al inventario las unidades de un pedido cancelado y registra el movimiento "devolucion".
     *
     * @return int Stock resultante
     */
    public function devolverPorCancelacion(int $id, int $cantidad, int $usuarioId, int $pedidoId): int
    {
        return Database::transaccion(function () use ($id, $cantidad, $usuarioId, $pedidoId): int {
            $nuevoStock = $this->stockBloqueado($id) + $cantidad;
            $this->guardarStock($id, $nuevoStock);
            $this->registrarMovimiento($id, $usuarioId, 'devolucion', $cantidad, $nuevoStock, "Pedido #$pedidoId cancelado", $pedidoId);

            return $nuevoStock;
        });
    }

    /**
     * Reglas de un movimiento manual: la cantidad no puede ser cero, solo el tipo "ajuste"
     * puede restar unidades y todo ajuste debe explicar su motivo (queda en el historial).
     */
    private function validarMovimientoManual(int $cantidad, string $tipo, ?string $motivo): void
    {
        if (!in_array($tipo, self::TIPOS_MANUALES, true)) {
            throw HttpException::validacion(['tipo' => 'Tipo de movimiento no válido.']);
        }
        if ($cantidad === 0) {
            throw HttpException::validacion(['cantidad' => 'La cantidad no puede ser cero.']);
        }
        if ($tipo !== 'ajuste' && $cantidad < 0) {
            throw HttpException::validacion(['cantidad' => 'Para descontar unidades usa el tipo "ajuste".']);
        }
        if ($tipo === 'ajuste' && trim((string) $motivo) === '') {
            throw HttpException::validacion(['motivo' => 'Explica el motivo del ajuste.']);
        }
    }

    /**
     * Lee el stock bloqueando la fila (SELECT ... FOR UPDATE) hasta que termine la transacción,
     * así dos personas no pueden modificar el mismo stock al mismo tiempo.
     */
    private function stockBloqueado(int $id): int
    {
        $fila = $this->obtenerUno('SELECT stock FROM variantes WHERE id = :id FOR UPDATE', ['id' => $id]);
        if ($fila === null) {
            throw HttpException::noEncontrado('La variante no existe.');
        }

        return (int) $fila['stock'];
    }

    private function guardarStock(int $id, int $stock): void
    {
        $this->ejecutar('UPDATE variantes SET stock = :stock WHERE id = :id', ['stock' => $stock, 'id' => $id]);
    }

    /** Registra un cambio de stock en el historial (movimientos_inventario). */
    private function registrarMovimiento(
        int $varianteId,
        ?int $usuarioId,
        string $tipo,
        int $cantidad,
        int $stockResultante,
        ?string $motivo,
        ?int $pedidoId = null,
    ): void {
        $this->ejecutar(
            'INSERT INTO movimientos_inventario (variante_id, usuario_id, pedido_id, tipo, cantidad, stock_resultante, motivo)
             VALUES (:variante, :usuario, :pedido, :tipo, :cantidad, :resultante, :motivo)',
            [
                'variante' => $varianteId,
                'usuario' => $usuarioId,
                'pedido' => $pedidoId,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'resultante' => $stockResultante,
                'motivo' => $motivo,
            ]
        );
    }

    /**
     * Inventario: todas las variantes con su producto, para el panel.
     *
     * @param array{q?: ?string, marca?: ?string, bajo_stock?: bool} $filtros
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listarInventario(array $filtros, int $pagina, int $porPagina, int $umbral): array
    {
        $condiciones = [];
        $parametros = [];

        if (!empty($filtros['q'])) {
            $condiciones[] = '(p.nombre LIKE :busqueda OR v.sku LIKE :busqueda_sku)';
            $parametros['busqueda'] = '%' . addcslashes($filtros['q'], '%_\\') . '%';
            $parametros['busqueda_sku'] = $parametros['busqueda'];
        }
        if (!empty($filtros['marca'])) {
            $condiciones[] = 'm.slug = :marca';
            $parametros['marca'] = $filtros['marca'];
        }
        if (!empty($filtros['bajo_stock'])) {
            // Solo lo que se vende: variante y producto activos con pocas unidades.
            $condiciones[] = 'v.stock <= :umbral AND v.activo = 1 AND p.activo = 1';
            $parametros['umbral'] = $umbral;
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        $orden = !empty($filtros['bajo_stock']) ? 'v.stock ASC, p.nombre, t.orden' : 'p.nombre, c.nombre, t.orden';
        $desde = 'FROM variantes v
                  JOIN productos p ON p.id = v.producto_id
                  JOIN marcas m ON m.id = p.marca_id
                  JOIN tallas t ON t.id = v.talla_id
                  JOIN colores c ON c.id = v.color_id';

        $total = (int) $this->obtenerUno("SELECT COUNT(*) AS total $desde $where", $parametros)['total'];

        $filas = $this->obtenerTodos(
            "SELECT v.id, v.sku, v.stock, v.activo, p.id AS producto_id, p.nombre AS producto, p.slug,
                    p.activo AS producto_activo, m.nombre AS marca, t.valor AS talla, c.nombre AS color, c.hex
             $desde $where
             ORDER BY $orden
             LIMIT :limite OFFSET :desplazamiento",
            $parametros + ['limite' => $porPagina, 'desplazamiento' => ($pagina - 1) * $porPagina]
        );

        $items = array_map(static fn (array $fila): array => [
            'id' => (int) $fila['id'],
            'sku' => $fila['sku'],
            'stock' => (int) $fila['stock'],
            'activo' => (bool) $fila['activo'],
            'producto' => [
                'id' => (int) $fila['producto_id'],
                'nombre' => $fila['producto'],
                'slug' => $fila['slug'],
                'activo' => (bool) $fila['producto_activo'],
                'marca' => $fila['marca'],
            ],
            'talla' => self::formatearTalla($fila['talla']),
            'color' => ['nombre' => $fila['color'], 'hex' => $fila['hex']],
        ], $filas);

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<array<string, mixed>> Últimos movimientos de inventario de una variante */
    public function movimientos(int $varianteId, int $limite = 50): array
    {
        return array_map(static fn (array $fila): array => [
            'id' => (int) $fila['id'],
            'tipo' => $fila['tipo'],
            'cantidad' => (int) $fila['cantidad'],
            'stock_resultante' => (int) $fila['stock_resultante'],
            'motivo' => $fila['motivo'],
            'pedido_id' => $fila['pedido_id'] === null ? null : (int) $fila['pedido_id'],
            'usuario' => $fila['usuario'],
            'creado_en' => $fila['creado_en'],
        ], $this->obtenerTodos(
            'SELECT mv.id, mv.tipo, mv.cantidad, mv.stock_resultante, mv.motivo, mv.pedido_id, mv.creado_en,
                    u.nombre AS usuario
               FROM movimientos_inventario mv
               LEFT JOIN usuarios u ON u.id = mv.usuario_id
              WHERE mv.variante_id = :variante
              ORDER BY mv.creado_en DESC, mv.id DESC
              LIMIT :limite',
            ['variante' => $varianteId, 'limite' => $limite]
        ));
    }
}
