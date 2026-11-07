<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Variante;

/**
 * Inventario: stock por variante y su historial de movimientos (personal de la tienda).
 */
final class InventarioController extends Controller
{
    /** Desde cuántas unidades se considera "bajo stock". */
    public const UMBRAL_BAJO_STOCK = 3;

    /** GET /api/inventario?q=&marca=&bajo_stock=1&pagina= */
    public function index(Request $request): void
    {
        $pagina = max(1, (int) ($request->query('pagina') ?? 1));
        $porPagina = min(100, max(1, (int) ($request->query('por_pagina') ?? 25)));

        $resultado = (new Variante())->listarInventario([
            'q' => mb_substr($request->query('q') ?? '', 0, 100) ?: null,
            'marca' => $request->query('marca') ?: null,
            'bajo_stock' => $request->query('bajo_stock') === '1',
        ], $pagina, $porPagina, self::UMBRAL_BAJO_STOCK);

        Response::ok($resultado + [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'paginas' => (int) ceil($resultado['total'] / $porPagina),
            'umbral' => self::UMBRAL_BAJO_STOCK,
        ]);
    }

    /** GET /api/variantes/{id}/movimientos */
    public function movimientos(Request $request): void
    {
        $variantes = new Variante();
        $variante = $variantes->buscarPorId($this->idDeRuta($request));
        if ($variante === null) {
            throw HttpException::noEncontrado('La variante no existe.');
        }

        Response::ok(['variante' => $variante, 'movimientos' => $variantes->movimientos($variante['id'])]);
    }
}
