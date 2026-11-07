<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Config;
use RuntimeException;

/**
 * Tokens JWT firmados con HMAC-SHA256 (HS256), implementados sin librerías.
 *
 * Un JWT tiene tres partes separadas por puntos: cabecera.datos.firma
 * La firma se calcula con la clave secreta del servidor (JWT_SECRET); si alguien
 * modifica los datos del token (por ejemplo su rol), la firma deja de coincidir.
 */
final class Jwt
{
    /** @param array<string, mixed> $datos */
    public static function generar(array $datos): string
    {
        $ahora = time();
        $cabecera = ['alg' => 'HS256', 'typ' => 'JWT'];
        $contenido = $datos + ['iat' => $ahora, 'exp' => $ahora + self::duracion()];

        $partes = [
            self::base64Url(json_encode($cabecera, JSON_THROW_ON_ERROR)),
            self::base64Url(json_encode($contenido, JSON_THROW_ON_ERROR)),
        ];
        $partes[] = self::firmar(implode('.', $partes));

        return implode('.', $partes);
    }

    /** @return array<string, mixed> Datos del token si es válido y no ha expirado */
    public static function verificar(string $token): array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            throw HttpException::noAutenticado('Token inválido.');
        }

        [$cabecera, $contenido, $firma] = $partes;

        // Solo se acepta HS256 (evita el ataque de tokens con "alg": "none").
        $datosCabecera = json_decode(self::desdeBase64Url($cabecera), true);
        if (($datosCabecera['alg'] ?? null) !== 'HS256') {
            throw HttpException::noAutenticado('Token inválido.');
        }

        // hash_equals compara en tiempo constante para no filtrar información.
        if (!hash_equals(self::firmar("$cabecera.$contenido"), $firma)) {
            throw HttpException::noAutenticado('Token inválido.');
        }

        $datos = json_decode(self::desdeBase64Url($contenido), true);
        if (!is_array($datos) || !isset($datos['sub'], $datos['exp'])) {
            throw HttpException::noAutenticado('Token inválido.');
        }
        if ($datos['exp'] < time()) {
            throw HttpException::noAutenticado('Tu sesión expiró. Inicia sesión de nuevo.');
        }

        return $datos;
    }

    public static function duracion(): int
    {
        return Config::entero('JWT_TTL', 28800);
    }

    private static function firmar(string $texto): string
    {
        $clave = Config::get('JWT_SECRET');
        if (strlen($clave) < 32) {
            throw new RuntimeException('JWT_SECRET debe tener al menos 32 caracteres.');
        }
        return self::base64Url(hash_hmac('sha256', $texto, $clave, true));
    }

    private static function base64Url(string $texto): string
    {
        return rtrim(strtr(base64_encode($texto), '+/', '-_'), '=');
    }

    private static function desdeBase64Url(string $texto): string
    {
        return (string) base64_decode(strtr($texto, '-_', '+/'), true);
    }
}
