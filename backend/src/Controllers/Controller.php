<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;

/**
 * Clase base de los controladores.
 * Un controlador recibe la petición, valida los datos, llama al modelo y responde en JSON.
 * No escribe SQL: eso es responsabilidad de los modelos.
 */
abstract class Controller
{
    /** Lee un id numérico de la ruta (ej. /api/productos/{id}); si no es válido responde 404. */
    protected function idDeRuta(Request $request, string $nombre = 'id'): int
    {
        $valor = $request->parametro($nombre);
        if (!ctype_digit($valor) || (int) $valor < 1) {
            throw HttpException::noEncontrado();
        }
        return (int) $valor;
    }

    /** Id del usuario autenticado (las rutas protegidas siempre lo tienen). */
    protected function usuarioId(Request $request): int
    {
        return (int) ($request->usuario['id'] ?? 0);
    }
}
