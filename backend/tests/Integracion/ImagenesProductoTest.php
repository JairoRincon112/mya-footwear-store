<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\Database;

/**
 * POST /api/productos/{id}/imagenes: el administrador sube fotos de un producto.
 * Se revisan las validaciones del archivo y que una imagen real quede guardada.
 */
final class ImagenesProductoTest extends ApiTestCase
{
    private static function productoId(): int
    {
        return (int) Database::conexion()->query('SELECT id FROM productos ORDER BY id LIMIT 1')->fetchColumn();
    }

    public function testSinArchivoPideSeleccionarUnaImagen(): void
    {
        $respuesta = self::peticion('POST', '/api/productos/' . self::productoId() . '/imagenes', self::tokenDe('admin'), multipart: ['color_id' => '']);

        self::assertSame(422, $respuesta['estado']);
        self::assertSame('Selecciona una imagen.', $respuesta['cuerpo']['errores']['imagen']);
    }

    public function testRechazaUnArchivoQueNoEsImagenAunqueTengaExtensionJpg(): void
    {
        $respuesta = self::peticion('POST', '/api/productos/' . self::productoId() . '/imagenes', self::tokenDe('admin'), multipart: [
            'imagen' => self::archivo('texto-disfrazado.jpg', 'image/jpeg'),
        ]);

        self::assertSame(422, $respuesta['estado']);
        self::assertSame('Solo se permiten imágenes JPG, PNG o WEBP.', $respuesta['cuerpo']['errores']['imagen']);
    }

    public function testRechazaUnColorQueNoExiste(): void
    {
        $respuesta = self::peticion('POST', '/api/productos/' . self::productoId() . '/imagenes', self::tokenDe('admin'), multipart: [
            'color_id' => '999999',
            'imagen' => self::archivo('zapato.png', 'image/png'),
        ]);

        self::assertSame(422, $respuesta['estado']);
        self::assertSame('El color no existe.', $respuesta['cuerpo']['errores']['color_id']);
    }

    public function testProductoInexistenteResponde404(): void
    {
        $respuesta = self::peticion('POST', '/api/productos/999999/imagenes', self::tokenDe('admin'), multipart: [
            'imagen' => self::archivo('zapato.png', 'image/png'),
        ]);

        self::assertSame(404, $respuesta['estado']);
    }

    public function testUnVendedorNoPuedeSubirImagenes(): void
    {
        $respuesta = self::peticion('POST', '/api/productos/' . self::productoId() . '/imagenes', self::tokenDe('vendedor'), multipart: [
            'imagen' => self::archivo('zapato.png', 'image/png'),
        ]);

        self::assertSame(403, $respuesta['estado']);
    }

    public function testGuardaUnaImagenRealYLuegoSePuedeEliminar(): void
    {
        $token = self::tokenDe('admin');
        $subida = self::peticion('POST', '/api/productos/' . self::productoId() . '/imagenes', $token, multipart: [
            'imagen' => self::archivo('zapato.png', 'image/png'),
        ]);

        self::assertSame(201, $subida['estado'], json_encode($subida['cuerpo']));
        $imagen = $subida['cuerpo']['data'];
        self::assertMatchesRegularExpression('#^uploads/productos/[0-9a-f]{32}\.png$#', $imagen['ruta']);
        $rutaDisco = dirname(__DIR__, 3) . '/public/mya-api/' . $imagen['ruta'];
        self::assertFileExists($rutaDisco);

        $borrado = self::peticion('DELETE', '/api/imagenes/' . $imagen['id'], $token);

        self::assertSame(200, $borrado['estado']);
        self::assertFileDoesNotExist($rutaDisco);
    }
}
