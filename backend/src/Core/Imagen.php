<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Optimiza imágenes de productos con GD (incluido en XAMPP):
 *  - Las reduce para que su lado más largo no pase de MAX_LADO píxeles.
 *  - Las vuelve a comprimir (JPEG/WEBP con calidad 82, PNG con compresión máxima).
 *  - Corrige la orientación de las fotos tomadas con celular (EXIF).
 *  - Elimina los metadatos (ubicación GPS, modelo de cámara, etc.).
 * Conserva el formato original y la transparencia de los PNG.
 */
final class Imagen
{
    public const MAX_LADO = 1200;
    private const CALIDAD = 82;
    /** Límite de píxeles para no agotar la memoria con imágenes gigantes (~40 megapíxeles). */
    private const MAX_PIXELES = 40_000_000;

    /**
     * @return array{ancho: int, alto: int, reducida: bool} Medidas finales y si se achicó
     */
    public static function optimizar(string $origen, string $destino, string $tipoMime, int $maxLado = self::MAX_LADO): array
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('La extensión GD de PHP no está activa (php.ini: extension=gd).');
        }

        $info = @getimagesize($origen);
        if ($info === false) {
            throw HttpException::validacion(['imagen' => 'El archivo no es una imagen válida.']);
        }
        [$ancho, $alto] = $info;
        if ($ancho * $alto > self::MAX_PIXELES) {
            throw HttpException::validacion(['imagen' => 'La imagen tiene demasiada resolución (máximo 40 megapíxeles).']);
        }

        $imagen = match ($tipoMime) {
            'image/jpeg' => @imagecreatefromjpeg($origen),
            'image/png' => @imagecreatefrompng($origen),
            'image/webp' => @imagecreatefromwebp($origen),
            default => false,
        };
        if ($imagen === false) {
            throw HttpException::validacion(['imagen' => 'No se pudo leer la imagen; puede estar dañada.']);
        }

        if ($tipoMime === 'image/jpeg') {
            $imagen = self::corregirOrientacion($imagen, $origen);
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1, $maxLado / max($ancho, $alto));

        if ($escala < 1) {
            $nuevoAncho = max(1, (int) round($ancho * $escala));
            $nuevoAlto = max(1, (int) round($alto * $escala));
            $reducida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
            // Transparencia para PNG y WEBP.
            imagealphablending($reducida, false);
            imagesavealpha($reducida, true);
            imagefill($reducida, 0, 0, imagecolorallocatealpha($reducida, 0, 0, 0, 127));
            imagecopyresampled($reducida, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
            $imagen = $reducida; // Desde PHP 8 la imagen anterior se libera sola.
            [$ancho, $alto] = [$nuevoAncho, $nuevoAlto];
        } else {
            imagealphablending($imagen, false);
            imagesavealpha($imagen, true);
        }

        // Se escribe en un archivo temporal y luego se reemplaza: así nunca queda una imagen a medias.
        $temporal = $destino . '.tmp';
        $guardado = match ($tipoMime) {
            'image/jpeg' => imagejpeg($imagen, $temporal, self::CALIDAD),
            'image/png' => imagepng($imagen, $temporal, 9),
            'image/webp' => imagewebp($imagen, $temporal, self::CALIDAD),
        };
        unset($imagen);

        if (!$guardado || !rename($temporal, $destino)) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo guardar la imagen optimizada.');
        }

        return ['ancho' => $ancho, 'alto' => $alto, 'reducida' => $escala < 1];
    }

    /** Tipo real de la imagen según su contenido (no según la extensión del nombre). */
    public static function tipoReal(string $archivo): ?string
    {
        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->file($archivo);
        return in_array($tipo, ['image/jpeg', 'image/png', 'image/webp'], true) ? $tipo : null;
    }

    /** Las fotos de celular guardan la rotación en EXIF; aquí se aplica de verdad. */
    private static function corregirOrientacion(\GdImage $imagen, string $archivo): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $imagen;
        }
        $exif = @exif_read_data($archivo);
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($grados === 0) {
            return $imagen;
        }
        $rotada = imagerotate($imagen, $grados, 0);
        return $rotada === false ? $imagen : $rotada;
    }
}
