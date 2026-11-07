<?php

declare(strict_types=1);

/**
 * ROUTER FRONTAL: todas las peticiones a la API entran por este archivo
 * (el .htaccess de esta carpeta las redirige aquí).
 *
 * Flujo: CORS -> Request -> Router -> Middlewares -> Controlador -> Modelo -> Respuesta JSON
 */

use App\Config\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\CorsMiddleware;

// Este archivo vive en public/mya-api/; el código de la API está en backend/.
$backend = dirname(__DIR__, 2) . '/backend';

require $backend . '/vendor/autoload.php';

// Los errores nunca se muestran en la respuesta: se registran en el log de Apache/PHP.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Cualquier advertencia de PHP se trata como error para no ocultar fallos.
set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if (!(error_reporting() & $nivel)) {
        return false; // Error silenciado a propósito con @
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

try {
    Config::cargar($backend . '/.env');
    date_default_timezone_set(Config::get('APP_TIMEZONE', 'America/Bogota'));
    CorsMiddleware::aplicar();

    $router = new Router();
    (require $backend . '/src/Config/routes.php')($router);
    $router->despachar(Request::desdeGlobales());
} catch (HttpException $error) {
    Response::error($error->getMessage(), $error->estado(), $error->errores());
} catch (PDOException $error) {
    error_log((string) $error);
    // 23000 = violación de una restricción (por ejemplo un valor UNIQUE repetido).
    if ($error->getCode() === '23000') {
        Response::error('El registro entra en conflicto con datos existentes.', 409);
    } else {
        Response::error(Config::booleano('APP_DEBUG') ? $error->getMessage() : 'Error de base de datos.', 500);
    }
} catch (Throwable $error) {
    error_log((string) $error);
    Response::error(Config::booleano('APP_DEBUG') ? $error->getMessage() : 'Ocurrió un error interno. Intenta de nuevo.', 500);
}
