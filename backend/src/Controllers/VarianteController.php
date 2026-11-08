<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Texto;
use App\Core\Validator;
use App\Models\Color;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\Variante;

/**
 * Variantes (talla + color) de un producto y su stock.
 */
final class VarianteController extends Controller
{
    /**
     * POST /api/productos/{id}/variantes
     * Crea de una vez las variantes de un color en varias tallas:
     *   { "color_id": 1, "talla_ids": [3, 4, 5], "stock": 10 }
     * Las combinaciones que ya existen se omiten.
     */
    public function crear(Request $request): void
    {
        $producto = (new Producto())->buscarBasico($this->idDeRuta($request));
        if ($producto === null) {
            throw HttpException::noEncontrado('El producto no existe.');
        }

        $datos = Validator::validar($request->cuerpo(), [
            'color_id' => 'requerido|entero',
            'talla_ids' => 'requerido|lista_enteros',
            'stock' => 'requerido|entero|minimo:0|maximo:100000',
        ]);

        $color = (new Color())->buscarPorId($datos['color_id']);
        if ($color === null) {
            throw HttpException::validacion(['color_id' => 'El color no existe.']);
        }

        $tallas = (new Talla())->buscarVarias($datos['talla_ids']);
        if (count($tallas) !== count($datos['talla_ids'])) {
            throw HttpException::validacion(['talla_ids' => 'Alguna de las tallas seleccionadas no existe.']);
        }

        $variantes = new Variante();
        $usuarioId = $this->usuarioId($request);

        $resultado = Database::transaccion(function () use ($variantes, $producto, $color, $tallas, $datos, $usuarioId): array {
            $creadas = [];
            $omitidas = [];

            foreach ($tallas as $tallaId => $valorTalla) {
                if ($variantes->existeCombinacion($producto['id'], $tallaId, $color['id'])) {
                    $omitidas[] = $valorTalla;
                    continue;
                }
                $sku = $this->generarSku($variantes, $producto, $color['nombre'], $valorTalla);
                $creadas[] = $variantes->crear($producto['id'], $tallaId, $color['id'], $sku, $datos['stock'], $usuarioId);
            }

            return ['creadas' => array_map([$variantes, 'buscarPorId'], $creadas), 'omitidas' => $omitidas];
        });

        $mensaje = count($resultado['creadas']) . ' variante(s) creada(s).';
        if ($resultado['omitidas'] !== []) {
            $mensaje .= ' Ya existían las tallas: ' . implode(', ', $resultado['omitidas']) . '.';
        }

        Response::creado($resultado, $mensaje);
    }

    /** PUT /api/variantes/{id} — cambia el SKU o activa/desactiva la variante. */
    public function actualizar(Request $request): void
    {
        $variantes = new Variante();
        $variante = $variantes->buscarPorId($this->idDeRuta($request));
        if ($variante === null) {
            throw HttpException::noEncontrado('La variante no existe.');
        }

        $datos = Validator::validar($request->cuerpo(), [
            'sku' => 'requerido|sku',
            'activo' => 'requerido|booleano',
        ]);

        if (!$variantes->skuDisponible($datos['sku'], $variante['id'])) {
            throw HttpException::validacion(['sku' => 'Ese SKU ya está en uso.']);
        }

        $variantes->actualizar($variante['id'], $datos['sku'], $datos['activo']);
        Response::ok($variantes->buscarPorId($variante['id']), 'Variante actualizada.');
    }

    /**
     * PATCH /api/variantes/{id}/stock
     *   { "tipo": "entrada", "cantidad": 5, "motivo": "Llegó pedido del proveedor" }
     * El controlador solo valida el formato; las reglas del inventario las aplica Variante.
     */
    public function ajustarStock(Request $request): void
    {
        $variantes = new Variante();
        $variante = $variantes->buscarPorId($this->idDeRuta($request));
        if ($variante === null) {
            throw HttpException::noEncontrado('La variante no existe.');
        }

        $datos = Validator::validar($request->cuerpo(), [
            'tipo' => 'requerido|en:' . implode(',', Variante::TIPOS_MANUALES),
            'cantidad' => 'requerido|entero|minimo:-100000|maximo:100000',
            'motivo' => 'texto|max:255',
        ]);

        $variantes->ajustarStock(
            $variante['id'],
            $datos['cantidad'],
            $datos['tipo'],
            $datos['motivo'] ?? null,
            $this->usuarioId($request)
        );

        Response::ok($variantes->buscarPorId($variante['id']), 'Stock actualizado.');
    }

    /** SKU legible y único, por ejemplo: SUPERSTAR-NEGRO-38 */
    private function generarSku(Variante $variantes, array $producto, string $color, string $talla): string
    {
        $base = strtoupper(substr($producto['slug'], 0, 30) . '-' . substr(Texto::slug($color), 0, 10) . '-' . str_replace('.', '', $talla));
        $sku = $base;
        for ($numero = 2; !$variantes->skuDisponible($sku); $numero++) {
            $sku = "$base-$numero";
        }
        return $sku;
    }
}
