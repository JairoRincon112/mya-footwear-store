<?php

declare(strict_types=1);

/**
 * Optimiza las imágenes que ya están en el catálogo (las subidas nuevas se optimizan solas).
 * Uso (desde la carpeta backend):
 *   php bin/optimizar_imagenes.php
 *
 * Cada imagen se reduce a máximo 1200 px y se recomprime, conservando el nombre.
 * Solo se reemplaza si la versión optimizada pesa al menos un 5 % menos; si no, se deja
 * la original (recomprimir un JPEG que ya venía muy comprimido puede hacerlo crecer).
 */

use App\Core\HttpException;
use App\Core\Imagen;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit("Este script solo se ejecuta desde la consola.\n");
}

$publica = dirname(__DIR__, 2) . '/public/mya-api';
$carpetas = [$publica . '/img/productos', $publica . '/uploads/productos'];
$antes = 0;
$despues = 0;

foreach ($carpetas as $carpeta) {
    foreach (glob("$carpeta/*.{jpg,jpeg,png,webp,JPG,PNG}", GLOB_BRACE) ?: [] as $archivo) {
        $nombre = basename($archivo);
        // El tipo se detecta por el contenido: hay archivos .png que en realidad son JPEG o WEBP.
        $tipo = Imagen::tipoReal($archivo);
        if ($tipo === null) {
            echo "  OMITIDA   $nombre: no es una imagen JPG, PNG o WEBP\n";
            continue;
        }

        $pesoAntes = filesize($archivo);
        $temporal = "$archivo.optimizada";
        try {
            $medidas = Imagen::optimizar($archivo, $temporal, $tipo);
        } catch (HttpException $error) {
            echo "  OMITIDA   $nombre: " . implode(' ', $error->errores()) . "\n";
            continue;
        }

        $pesoNuevo = filesize($temporal);
        $conviene = $pesoNuevo < $pesoAntes * 0.95;

        if ($conviene) {
            rename($temporal, $archivo);
        } else {
            unlink($temporal);
        }

        $pesoFinal = $conviene ? $pesoNuevo : $pesoAntes;
        $antes += $pesoAntes;
        $despues += $pesoFinal;
        printf(
            "  %-9s %-42s %5d KB -> %5d KB  %s\n",
            $conviene ? 'OPTIMIZADA' : 'SIN CAMBIO',
            $nombre,
            $pesoAntes / 1024,
            $pesoFinal / 1024,
            $conviene ? "({$medidas['ancho']}x{$medidas['alto']})" : ''
        );
    }
}

printf("\nTotal: %.1f MB -> %.1f MB\n", $antes / 1048576, $despues / 1048576);
