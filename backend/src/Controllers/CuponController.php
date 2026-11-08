<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Descuentos\EstrategiasDescuento;
use App\Models\Cupon;

/**
 * Administración de cupones de descuento (solo admin).
 * Los cupones no se borran (pueden estar en pedidos): se desactivan.
 */
final class CuponController extends Controller
{
    /** GET /api/cupones */
    public function index(Request $request): void
    {
        Response::ok((new Cupon())->todos());
    }

    /** POST /api/cupones */
    public function crear(Request $request): void
    {
        $cupones = new Cupon();
        $datos = $this->validar($request, $cupones);
        $id = $cupones->crear($datos);
        Response::creado($cupones->buscarPorId($id), "Cupón {$datos['codigo']} creado.");
    }

    /** PUT /api/cupones/{id} */
    public function actualizar(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $cupones = new Cupon();
        if ($cupones->buscarPorId($id) === null) {
            throw HttpException::noEncontrado('El cupón no existe.');
        }

        $cupones->actualizar($id, $this->validar($request, $cupones, $id));
        Response::ok($cupones->buscarPorId($id), 'Cupón actualizado.');
    }

    /** PATCH /api/cupones/{id}/activo { activo } */
    public function cambiarActivo(Request $request): void
    {
        $id = $this->idDeRuta($request);
        $cupones = new Cupon();
        if ($cupones->buscarPorId($id) === null) {
            throw HttpException::noEncontrado('El cupón no existe.');
        }

        $datos = Validator::validar($request->cuerpo(), ['activo' => 'requerido|booleano']);
        $cupones->cambiarActivo($id, $datos['activo']);
        Response::ok($cupones->buscarPorId($id), $datos['activo'] ? 'Cupón activado.' : 'Cupón desactivado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, Cupon $cupones, ?int $exceptoId = null): array
    {
        $datos = Validator::validar($request->cuerpo(), [
            'codigo' => 'requerido|codigo',
            'descripcion' => 'texto|max:255',
            'tipo' => 'requerido|en:' . implode(',', EstrategiasDescuento::tipos()),
            'valor' => 'requerido|numerico|minimo:1|maximo:99999999',
            'minimo_compra' => 'numerico|minimo:0|maximo:99999999',
            'usos_maximos' => 'entero|minimo:1|maximo:1000000',
            'uno_por_cliente' => 'booleano',
            'inicio' => 'fecha_hora',
            'fin' => 'fecha_hora',
            'activo' => 'booleano',
        ]);

        // Cada tipo de cupón valida su propio valor (patrón Strategy).
        $errorValor = EstrategiasDescuento::para($datos['tipo'])->validarValor((float) $datos['valor']);
        if ($errorValor !== null) {
            throw HttpException::validacion(['valor' => $errorValor]);
        }
        if (!empty($datos['inicio']) && !empty($datos['fin']) && $datos['inicio'] >= $datos['fin']) {
            throw HttpException::validacion(['fin' => 'La fecha de fin debe ser posterior a la de inicio.']);
        }
        if (!$cupones->codigoDisponible($datos['codigo'], $exceptoId)) {
            throw HttpException::validacion(['codigo' => 'Ya existe un cupón con ese código.']);
        }

        return [
            'codigo' => $datos['codigo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'tipo' => $datos['tipo'],
            'valor' => round($datos['valor'], 2),
            'minimo_compra' => round($datos['minimo_compra'] ?? 0, 2),
            'usos_maximos' => $datos['usos_maximos'] ?? null,
            'uno_por_cliente' => $datos['uno_por_cliente'] ?? false,
            'inicio' => $datos['inicio'] ?? null,
            'fin' => $datos['fin'] ?? null,
            'activo' => $datos['activo'] ?? true,
        ];
    }
}
