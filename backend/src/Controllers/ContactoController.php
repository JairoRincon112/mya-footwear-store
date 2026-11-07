<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\MensajeContacto;

/**
 * Formulario de contacto: el público escribe y el personal lee los mensajes en el panel.
 */
final class ContactoController extends Controller
{
    /** POST /api/contacto */
    public function crear(Request $request): void
    {
        $datos = Validator::validar($request->cuerpo(), [
            'nombre' => 'requerido|texto|min:2|max:120',
            'email' => 'requerido|email|max:150',
            'telefono' => 'telefono',
            'mensaje' => 'requerido|texto|min:10|max:2000',
        ]);

        (new MensajeContacto())->crear([
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'telefono' => $datos['telefono'] ?? null,
            'mensaje' => $datos['mensaje'],
        ]);

        Response::creado(null, 'Gracias por escribirnos. Te responderemos pronto.');
    }

    /** GET /api/contacto?leido=0|1&pagina= (personal) */
    public function index(Request $request): void
    {
        $pagina = max(1, (int) ($request->query('pagina') ?? 1));
        $porPagina = min(50, max(1, (int) ($request->query('por_pagina') ?? 15)));
        $leido = $request->query('leido');

        $resultado = (new MensajeContacto())->listar(
            in_array($leido, ['0', '1'], true) ? $leido === '1' : null,
            $pagina,
            $porPagina
        );

        Response::ok($resultado + ['pagina' => $pagina, 'por_pagina' => $porPagina, 'paginas' => (int) ceil($resultado['total'] / $porPagina)]);
    }

    /** PATCH /api/contacto/{id}/leido { "leido": true } (personal) */
    public function marcarLeido(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $mensajes = new MensajeContacto();
        if (!$mensajes->existe($id)) {
            throw HttpException::noEncontrado('El mensaje no existe.');
        }

        $datos = Validator::validar($request->cuerpo(), ['leido' => 'requerido|booleano']);
        $mensajes->marcarLeido($id, $datos['leido']);

        Response::ok(['id' => $id, 'leido' => $datos['leido'], 'no_leidos' => $mensajes->contarNoLeidos()], 'Mensaje actualizado.');
    }
}
