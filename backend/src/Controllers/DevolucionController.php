<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Devolucion;

/**
 * Devoluciones: el cliente las solicita sobre un pedido entregado y el personal las atiende.
 * Este controlador solo valida el formato; las reglas están en el modelo Devolucion.
 */
final class DevolucionController extends Controller
{
    private const MENSAJES = [
        'aprobada' => 'Devolución aprobada. Avísale al cliente cómo enviar el producto.',
        'rechazada' => 'Devolución rechazada.',
        'recibida' => 'Producto recibido. Las unidades volvieron al inventario.',
    ];

    /** POST /api/pedidos/{id}/devoluciones (cliente dueño del pedido) */
    public function solicitar(Request $request): void
    {
        $pedidoId = $this->idDeRuta($request);
        $datos = Validator::validar($request->cuerpo(), [
            'detalle_id' => 'requerido|entero|minimo:1',
            'tipo' => 'requerido|en:' . implode(',', Devolucion::TIPOS),
            'cantidad' => 'requerido|entero|minimo:1|maximo:10',
            'descripcion' => 'requerido|texto|min:10|max:500',
        ]);

        $devoluciones = new Devolucion();
        $id = $devoluciones->solicitar(
            $this->usuarioId($request),
            $pedidoId,
            $datos['detalle_id'],
            $datos['tipo'],
            $datos['cantidad'],
            $datos['descripcion']
        );

        Response::creado(
            $devoluciones->buscar($id),
            "Recibimos tu solicitud de devolución #$id. Te responderemos pronto."
        );
    }

    /** GET /api/devoluciones?estado=&tipo=&q=&pagina= (personal) */
    public function index(Request $request): void
    {
        $pagina = max(1, (int) ($request->query('pagina') ?? 1));
        $porPagina = min(50, max(1, (int) ($request->query('por_pagina') ?? 15)));
        $estado = $request->query('estado');
        $tipo = $request->query('tipo');

        $resultado = (new Devolucion())->listar([
            'estado' => in_array($estado, Devolucion::ESTADOS, true) ? $estado : null,
            'tipo' => in_array($tipo, Devolucion::TIPOS, true) ? $tipo : null,
            'q' => trim((string) $request->query('q')) ?: null,
        ], $pagina, $porPagina);

        Response::ok($resultado + [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'paginas' => (int) ceil($resultado['total'] / $porPagina),
        ]);
    }

    /** PATCH /api/devoluciones/{id}/estado { "estado": "aprobada", "respuesta": "..." } (personal) */
    public function cambiarEstado(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $datos = Validator::validar($request->cuerpo(), [
            'estado' => 'requerido|en:aprobada,rechazada,recibida',
            'respuesta' => 'texto|max:500',
        ]);

        $devoluciones = new Devolucion();
        $devoluciones->cambiarEstado($id, $datos['estado'], $datos['respuesta'] ?? null, $this->usuarioId($request));

        $devolucion = $this->encontrado($devoluciones->buscar($id), 'La devolución no existe.');
        Response::ok($devolucion, self::MENSAJES[$datos['estado']]);
    }
}
