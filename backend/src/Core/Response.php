<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuestas JSON de la API con un formato uniforme:
 *   éxito: { "ok": true,  "data": ..., "mensaje": "..." }
 *   error: { "ok": false, "mensaje": "...", "errores": { "campo": "detalle" } }
 */
final class Response
{
    public static function json(array $contenido, int $estado = 200): void
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function ok(mixed $datos = null, ?string $mensaje = null, int $estado = 200): void
    {
        $contenido = ['ok' => true, 'data' => $datos];
        if ($mensaje !== null) {
            $contenido['mensaje'] = $mensaje;
        }
        self::json($contenido, $estado);
    }

    public static function creado(mixed $datos, string $mensaje): void
    {
        self::ok($datos, $mensaje, 201);
    }

    /** @param array<string, string> $errores */
    public static function error(string $mensaje, int $estado, array $errores = []): void
    {
        $contenido = ['ok' => false, 'mensaje' => $mensaje];
        if ($errores !== []) {
            $contenido['errores'] = $errores;
        }
        self::json($contenido, $estado);
    }
}
