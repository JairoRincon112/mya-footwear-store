<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router de la API: asocia cada combinación método + ruta con un controlador.
 *
 * Ejemplo:
 *   $router->get('/api/productos/{producto}', [ProductoController::class, 'mostrar']);
 *
 * Las partes entre llaves son parámetros y llegan al controlador en $request->parametros.
 * Antes del controlador se ejecutan los middlewares de la ruta (autenticación, roles).
 */
final class Router
{
    /** @var list<array{metodo: string, patron: string, accion: array{0: class-string, 1: string}, middlewares: list<object>}> */
    private array $rutas = [];

    public function get(string $ruta, array $accion, array $middlewares = []): void
    {
        $this->agregar('GET', $ruta, $accion, $middlewares);
    }

    public function post(string $ruta, array $accion, array $middlewares = []): void
    {
        $this->agregar('POST', $ruta, $accion, $middlewares);
    }

    public function put(string $ruta, array $accion, array $middlewares = []): void
    {
        $this->agregar('PUT', $ruta, $accion, $middlewares);
    }

    public function patch(string $ruta, array $accion, array $middlewares = []): void
    {
        $this->agregar('PATCH', $ruta, $accion, $middlewares);
    }

    public function delete(string $ruta, array $accion, array $middlewares = []): void
    {
        $this->agregar('DELETE', $ruta, $accion, $middlewares);
    }

    private function agregar(string $metodo, string $ruta, array $accion, array $middlewares): void
    {
        // /api/productos/{id} se convierte en la expresión regular #^/api/productos/(?P<id>[^/]+)$#
        $patron = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $ruta);

        $this->rutas[] = [
            'metodo' => $metodo,
            'patron' => '#^' . $patron . '$#',
            'accion' => $accion,
            'middlewares' => $middlewares,
        ];
    }

    public function despachar(Request $request): void
    {
        $rutaExisteConOtroMetodo = false;

        foreach ($this->rutas as $ruta) {
            if (!preg_match($ruta['patron'], $request->ruta, $coincidencias)) {
                continue;
            }

            if ($ruta['metodo'] !== $request->metodo) {
                $rutaExisteConOtroMetodo = true;
                continue;
            }

            // Solo se guardan los parámetros con nombre ({id}, {producto}, ...).
            $request->parametros = array_map(
                'rawurldecode',
                array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY)
            );

            foreach ($ruta['middlewares'] as $middleware) {
                $middleware->handle($request);
            }

            [$claseControlador, $metodo] = $ruta['accion'];
            (new $claseControlador())->$metodo($request);
            return;
        }

        if ($rutaExisteConOtroMetodo) {
            throw new HttpException(405, 'Método no permitido para esta ruta.');
        }
        throw HttpException::noEncontrado('La ruta solicitada no existe.');
    }
}
