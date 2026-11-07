<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Utilidades de texto.
 */
final class Texto
{
    /**
     * Convierte un nombre en un "slug" apto para URL.
     * Ejemplo: "Nike Air Force 1'07 LX" -> "nike-air-force-1-07-lx"
     */
    public static function slug(string $texto): string
    {
        $texto = mb_strtolower($texto);
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', '&' => ' y ', '™' => '', '´' => '', "'" => '',
        ]);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';

        return trim($texto, '-') ?: 'producto';
    }
}
