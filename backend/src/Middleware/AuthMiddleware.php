<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Jwt;
use App\Core\Request;
use App\Models\Usuario;

/**
 * Autenticación por JWT y control de roles.
 *
 *   AuthMiddleware::roles('admin')              -> solo administradores
 *   AuthMiddleware::roles('admin', 'vendedor')  -> personal de la tienda
 *   AuthMiddleware::autenticado()               -> cualquier usuario con sesión
 *   AuthMiddleware::opcional()                  -> identifica al usuario si envía token, pero no lo exige
 *
 * El usuario se vuelve a leer de la base de datos en cada petición: si el administrador
 * lo desactiva o le cambia el rol, el cambio se aplica de inmediato aunque el token siga vigente.
 */
final class AuthMiddleware
{
    /** @param list<string> $rolesPermitidos */
    private function __construct(
        private readonly array $rolesPermitidos,
        private readonly bool $obligatorio,
    ) {
    }

    public static function roles(string ...$roles): self
    {
        return new self(array_values($roles), true);
    }

    public static function autenticado(): self
    {
        return new self([], true);
    }

    public static function opcional(): self
    {
        return new self([], false);
    }

    public function handle(Request $request): void
    {
        $token = $request->tokenBearer();

        if ($token === null) {
            if ($this->obligatorio) {
                throw HttpException::noAutenticado();
            }
            return;
        }

        try {
            $datos = Jwt::verificar($token);
        } catch (HttpException $error) {
            // En rutas públicas un token vencido no impide ver el contenido.
            if ($this->obligatorio) {
                throw $error;
            }
            return;
        }

        $usuario = (new Usuario())->buscarPorId((int) $datos['sub']);

        if ($usuario === null || !$usuario['activo']) {
            if ($this->obligatorio) {
                throw HttpException::noAutenticado('Tu cuenta no está disponible.');
            }
            return;
        }

        if ($this->rolesPermitidos !== [] && !in_array($usuario['rol'], $this->rolesPermitidos, true)) {
            throw HttpException::prohibido();
        }

        $request->usuario = $usuario;
    }
}
