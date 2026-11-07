<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Talla;

/**
 * Datos de apoyo del catálogo: marcas, tallas y colores (públicos).
 */
final class CatalogoController extends Controller
{
    /** GET /api/marcas */
    public function marcas(Request $request): void
    {
        Response::ok((new Marca())->todas());
    }

    /** GET /api/tallas */
    public function tallas(Request $request): void
    {
        Response::ok((new Talla())->todas());
    }

    /** GET /api/colores */
    public function colores(Request $request): void
    {
        Response::ok((new Color())->todos());
    }

    /** GET /api/salud — comprueba que la API y la base de datos responden. */
    public function salud(Request $request): void
    {
        Database::conexion()->query('SELECT 1');
        Response::ok(['api' => 'ok', 'base_de_datos' => 'ok']);
    }
}
