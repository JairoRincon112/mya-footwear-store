<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Reporte;

/**
 * Resumen de ventas, pedidos e inventario para el panel.
 */
final class ReporteController extends Controller
{
    /** GET /api/reportes/resumen */
    public function resumen(Request $request): void
    {
        Response::ok((new Reporte())->resumen(InventarioController::UMBRAL_BAJO_STOCK));
    }
}
