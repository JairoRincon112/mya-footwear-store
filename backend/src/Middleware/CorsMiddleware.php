<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Config;

/**
 * CORS: permite que el frontend (otro origen, por ejemplo http://localhost:5173)
 * consuma la API. Solo se autorizan los orígenes listados en CORS_ORIGINS del .env.
 */
final class CorsMiddleware
{
    public static function aplicar(): void
    {
        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origen !== '' && in_array($origen, Config::lista('CORS_ORIGINS'), true)) {
            header("Access-Control-Allow-Origin: $origen");
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 600');
        }

        // El navegador envía una petición OPTIONS ("preflight") antes de la real.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
