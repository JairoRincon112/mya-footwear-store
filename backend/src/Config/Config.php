<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Configuración de la aplicación.
 *
 * Lee los valores del archivo .env (credenciales de la base de datos, clave JWT, etc.).
 * Ese archivo no se sube al repositorio; en su lugar se versiona .env.example.
 *
 * En el hosting (Kyra Cloud) los valores se definen como variables de entorno del
 * servidor: si una clave no está en el .env, se busca con getenv().
 */
final class Config
{
    /** @var array<string, string> */
    private static array $valores = [];

    public static function cargar(string $rutaArchivo): void
    {
        if (!is_file($rutaArchivo)) {
            // Sin .env solo se puede seguir si el servidor ya trae las variables de entorno.
            if (getenv('DB_NAME') === false || getenv('JWT_SECRET') === false) {
                throw new RuntimeException('Falta el archivo .env del backend (copia .env.example como .env).');
            }
            return;
        }

        foreach (file($rutaArchivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);

            // Se ignoran comentarios y líneas sin "clave=valor".
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $valor = trim($valor);

            // Permite valores entre comillas: CLAVE="valor con espacios"
            if (preg_match('/^(["\'])(.*)\1$/', $valor, $coincidencia)) {
                $valor = $coincidencia[2];
            }

            self::$valores[trim($clave)] = $valor;
        }
    }

    public static function get(string $clave, string $porDefecto = ''): string
    {
        if (isset(self::$valores[$clave])) {
            return self::$valores[$clave];
        }

        $entorno = getenv($clave);
        return $entorno === false ? $porDefecto : $entorno;
    }

    public static function entero(string $clave, int $porDefecto): int
    {
        $valor = filter_var(self::get($clave), FILTER_VALIDATE_INT);
        return $valor === false ? $porDefecto : $valor;
    }

    public static function booleano(string $clave): bool
    {
        return filter_var(self::get($clave, 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return list<string> */
    public static function lista(string $clave): array
    {
        return array_values(array_filter(array_map('trim', explode(',', self::get($clave)))));
    }
}
