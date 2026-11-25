<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\Database;

/**
 * POST /api/pedidos/cotizar: vista previa del carrito con el cupón opcional.
 * El cupón vacío o ausente no es un error; uno mal escrito sí.
 */
final class CotizacionCuponTest extends ApiTestCase
{
    /** @return array<string, mixed> */
    private static function carrito(array $extra = []): array
    {
        $varianteId = (int) Database::conexion()->query(
            'SELECT v.id FROM variantes v JOIN productos p ON p.id = v.producto_id
             WHERE v.activo = 1 AND p.activo = 1 AND v.stock > 0 ORDER BY v.id LIMIT 1'
        )->fetchColumn();
        self::assertGreaterThan(0, $varianteId, 'Se necesita una variante activa con stock.');

        return ['items' => [['variante_id' => $varianteId, 'cantidad' => 1]]] + $extra;
    }

    public function testSinCuponCotizaSinDescuento(): void
    {
        foreach ([[], ['cupon' => ''], ['cupon' => null]] as $cupon) {
            $respuesta = self::peticion('POST', '/api/pedidos/cotizar', self::tokenDe('cliente'), json: self::carrito($cupon));

            self::assertSame(200, $respuesta['estado'], json_encode($respuesta['cuerpo']));
            self::assertEquals(0, $respuesta['cuerpo']['data']['descuento']);
            self::assertCount(1, $respuesta['cuerpo']['data']['lineas']);
        }
    }

    public function testCuponMalEscritoEsUnErrorDeValidacion(): void
    {
        $respuesta = self::peticion('POST', '/api/pedidos/cotizar', self::tokenDe('cliente'), json: self::carrito(['cupon' => 'NO VALE!']));

        self::assertSame(422, $respuesta['estado']);
        self::assertArrayHasKey('cupon', $respuesta['cuerpo']['errores']);
    }

    public function testCarritoVacioEsUnError(): void
    {
        $respuesta = self::peticion('POST', '/api/pedidos/cotizar', self::tokenDe('cliente'), json: ['items' => []]);

        self::assertSame(422, $respuesta['estado']);
        self::assertSame('Tu carrito está vacío.', $respuesta['cuerpo']['errores']['items']);
    }
}
