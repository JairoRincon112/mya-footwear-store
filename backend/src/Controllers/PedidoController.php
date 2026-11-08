<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Pedido;

/**
 * Pedidos: el cliente compra y consulta los suyos; el personal los gestiona.
 */
final class PedidoController extends Controller
{
    /**
     * POST /api/pedidos (cliente)
     * {
     *   "nombre_contacto": "...", "telefono": "...", "direccion": "...", "ciudad": "...", "notas": "...",
     *   "items": [ { "variante_id": 12, "cantidad": 2 } ]
     * }
     * Solo se reciben variantes y cantidades: el precio lo pone el servidor.
     */
    public function crear(Request $request): void
    {
        $cuerpo = $request->cuerpo();
        $items = $cuerpo['items'] ?? null;
        $carritoValido = is_array($items) && $items !== [] && count($items) <= 50;

        // Se reportan juntos los errores del formulario de envío y del carrito.
        try {
            $envio = Validator::validar($cuerpo, [
                'nombre_contacto' => 'requerido|texto|min:2|max:120',
                'telefono' => 'requerido|telefono',
                'direccion' => 'requerido|texto|min:5|max:255',
                'ciudad' => 'requerido|texto|min:2|max:80',
                'notas' => 'texto|max:500',
            ]);
        } catch (HttpException $error) {
            $errores = $error->errores();
            if (!$carritoValido) {
                $errores['items'] = 'Tu carrito está vacío.';
            }
            throw HttpException::validacion($errores);
        }

        if (!$carritoValido) {
            throw HttpException::validacion(['items' => 'Tu carrito está vacío.']);
        }

        $pedidos = new Pedido();
        $id = $pedidos->crear($this->usuarioId($request), [
            'nombre_contacto' => $envio['nombre_contacto'],
            'telefono' => $envio['telefono'],
            'direccion' => $envio['direccion'],
            'ciudad' => $envio['ciudad'],
            'notas' => $envio['notas'] ?? null,
        ], $this->cantidades($items), $this->codigoCupon($cuerpo));

        Response::creado($pedidos->buscar($id), "¡Gracias por tu compra! Tu pedido #$id fue registrado.");
    }

    /**
     * POST /api/pedidos/cotizar (cliente) { items: [...], cupon: "BIENVENIDA10" }
     * Vista previa con precios actuales (ofertas incluidas), stock y descuento del cupón.
     * No guarda nada.
     */
    public function cotizar(Request $request): void
    {
        $cuerpo = $request->cuerpo();
        $items = $cuerpo['items'] ?? null;
        if (!is_array($items) || $items === [] || count($items) > 50) {
            throw HttpException::validacion(['items' => 'Tu carrito está vacío.']);
        }

        $calculo = (new Pedido())->cotizar($this->cantidades($items), $this->codigoCupon($cuerpo), $this->usuarioId($request));

        // Solo se devuelve lo que el cliente necesita ver.
        $calculo['lineas'] = array_map(static fn (array $linea): array => [
            'variante_id' => $linea['variante_id'],
            'descripcion' => $linea['descripcion'],
            'cantidad' => $linea['cantidad'],
            'precio' => $linea['precio'],
            'precio_regular' => $linea['precio_regular'],
            'subtotal' => $linea['subtotal'],
        ], $calculo['lineas']);

        Response::ok($calculo);
    }

    /**
     * Valida los items y agrupa las cantidades por variante (por si llega repetida).
     *
     * @return array<int, int> variante_id => cantidad
     */
    private function cantidades(array $items): array
    {
        $cantidades = [];
        foreach ($items as $item) {
            $linea = Validator::validar(is_array($item) ? $item : [], [
                'variante_id' => 'requerido|entero|minimo:1',
                // Solo el formato; el máximo por producto es una regla de negocio de Pedido.
                'cantidad' => 'requerido|entero|minimo:1',
            ]);
            $cantidades[$linea['variante_id']] = ($cantidades[$linea['variante_id']] ?? 0) + $linea['cantidad'];
        }
        return $cantidades;
    }

    private function codigoCupon(array $cuerpo): ?string
    {
        if (!isset($cuerpo['cupon']) || $cuerpo['cupon'] === '' || $cuerpo['cupon'] === null) {
            return null;
        }
        return Validator::validar($cuerpo, ['cupon' => 'codigo'])['cupon'];
    }

    /** GET /api/pedidos/mios (cliente) */
    public function mios(Request $request): void
    {
        [$pagina, $porPagina] = $this->paginacion($request, 10);
        $resultado = (new Pedido())->listar(['cliente_id' => $this->usuarioId($request)], $pagina, $porPagina);
        Response::ok($this->conPaginas($resultado, $pagina, $porPagina));
    }

    /** GET /api/pedidos?estado=&q=&desde=&hasta=&pagina= (personal) */
    public function index(Request $request): void
    {
        [$pagina, $porPagina] = $this->paginacion($request, 15);
        $estado = $request->query('estado');
        $filtros = [
            'estado' => in_array($estado, Pedido::ESTADOS, true) ? $estado : null,
            'q' => mb_substr($request->query('q') ?? '', 0, 100) ?: null,
            'desde' => $this->fecha($request->query('desde')),
            'hasta' => $this->fecha($request->query('hasta')),
        ];

        $resultado = (new Pedido())->listar($filtros, $pagina, $porPagina);
        Response::ok($this->conPaginas($resultado, $pagina, $porPagina));
    }

    /** GET /api/pedidos/{id} — el cliente solo puede ver los suyos. */
    public function mostrar(Request $request): void
    {
        $pedidos = new Pedido();
        $pedido = $pedidos->buscar($this->idDeRuta($request));

        if ($pedido === null || (!$request->esPersonal() && $pedido['cliente']['id'] !== $this->usuarioId($request))) {
            // Se responde 404 también cuando es de otro cliente, para no revelar que existe.
            throw HttpException::noEncontrado('El pedido no existe.');
        }

        Response::ok($pedido);
    }

    /** PATCH /api/pedidos/{id}/estado (personal) { "estado": "pagado" } */
    public function cambiarEstado(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $datos = Validator::validar($request->cuerpo(), [
            'estado' => 'requerido|en:' . implode(',', Pedido::ESTADOS),
        ]);

        $pedidos = new Pedido();
        $pedidos->cambiarEstado($id, $datos['estado'], $this->usuarioId($request), true);

        $mensaje = $datos['estado'] === 'cancelado'
            ? 'Pedido cancelado. El stock fue devuelto al inventario.'
            : "Pedido marcado como {$datos['estado']}.";
        Response::ok($pedidos->buscar($id), $mensaje);
    }

    /** PATCH /api/pedidos/{id}/cancelar (cliente) — solo sus pedidos pendientes. */
    public function cancelarPropio(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $pedidos = new Pedido();
        $pedidos->cancelarPorCliente($id, $this->usuarioId($request));
        Response::ok($pedidos->buscar($id), 'Tu pedido fue cancelado.');
    }

    /** @return array{0: int, 1: int} */
    private function paginacion(Request $request, int $porDefecto): array
    {
        $pagina = max(1, (int) ($request->query('pagina') ?? 1));
        $porPagina = min(50, max(1, (int) ($request->query('por_pagina') ?? $porDefecto)));
        return [$pagina, $porPagina];
    }

    private function conPaginas(array $resultado, int $pagina, int $porPagina): array
    {
        return $resultado + ['pagina' => $pagina, 'por_pagina' => $porPagina, 'paginas' => (int) ceil($resultado['total'] / $porPagina)];
    }

    /** Acepta solo fechas AAAA-MM-DD válidas. */
    private function fecha(?string $valor): ?string
    {
        if ($valor === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }
        [$anio, $mes, $dia] = array_map('intval', explode('-', $valor));
        return checkdate($mes, $dia, $anio) ? $valor : null;
    }
}
