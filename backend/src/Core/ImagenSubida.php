<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Imagen recibida en un formulario multipart (por ejemplo la foto de un producto).
 *
 * Revisa que el archivo haya llegado bien, que no pese más de lo permitido y que su
 * contenido real sea JPG, PNG o WEBP (no se confía en la extensión del nombre), y lo
 * guarda optimizado con un nombre aleatorio.
 */
final class ImagenSubida
{
    /** Errores de PHP al recibir el archivo que tienen un mensaje propio para el usuario. */
    private const MENSAJES_ERROR = [
        UPLOAD_ERR_NO_FILE => 'Selecciona una imagen.',
        UPLOAD_ERR_INI_SIZE => 'La imagen es demasiado grande.',
        UPLOAD_ERR_FORM_SIZE => 'La imagen es demasiado grande.',
    ];

    private const BYTES_POR_MB = 1024 * 1024;

    /**
     * Valida el archivo y lo guarda optimizado en $carpeta.
     *
     * @param array{error: int, tmp_name: string, size: int}|null $archivo Entrada de $_FILES
     * @return string Nombre con el que quedó guardado (aleatorio, con la extensión real)
     */
    public static function guardar(?array $archivo, string $carpeta, int $maximoMb): string
    {
        $tipo = self::validar($archivo, $maximoMb);
        self::asegurarCarpeta($carpeta);

        $nombre = bin2hex(random_bytes(16)) . '.' . Imagen::EXTENSIONES[$tipo];
        Imagen::optimizar($archivo['tmp_name'], "$carpeta/$nombre", $tipo);

        return $nombre;
    }

    /**
     * @param array{error: int, tmp_name: string, size: int}|null $archivo
     * @return string Tipo MIME real de la imagen
     */
    private static function validar(?array $archivo, int $maximoMb): string
    {
        $codigo = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if (isset(self::MENSAJES_ERROR[$codigo])) {
            throw self::error(self::MENSAJES_ERROR[$codigo]);
        }
        if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            throw self::error('No se pudo recibir la imagen. Intenta de nuevo.');
        }
        if ($archivo['size'] > $maximoMb * self::BYTES_POR_MB) {
            throw self::error("La imagen no puede pesar más de $maximoMb MB.");
        }

        // Se revisa el contenido real del archivo para evitar subir scripts disfrazados de imagen.
        return Imagen::tipoReal($archivo['tmp_name'])
            ?? throw self::error('Solo se permiten imágenes JPG, PNG o WEBP.');
    }

    private static function asegurarCarpeta(string $carpeta): void
    {
        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
        }
    }

    private static function error(string $mensaje): HttpException
    {
        return HttpException::validacion(['imagen' => $mensaje]);
    }
}
