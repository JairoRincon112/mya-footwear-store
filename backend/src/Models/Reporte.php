<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Consultas de resumen para el panel (ventas, pedidos, inventario).
 * Los pedidos cancelados no cuentan como venta.
 */
final class Reporte extends Model
{
    public function resumen(int $umbralStock, int $dias = 30): array
    {
        $ventas = $this->obtenerUno(
            "SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS total
               FROM pedidos WHERE estado <> 'cancelado'"
        );

        $ventasMes = $this->obtenerUno(
            "SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS total
               FROM pedidos
              WHERE estado <> 'cancelado' AND creado_en >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );

        $porEstado = array_fill_keys(Pedido::ESTADOS, 0);
        foreach ($this->obtenerTodos('SELECT estado, COUNT(*) AS total FROM pedidos GROUP BY estado') as $fila) {
            $porEstado[$fila['estado']] = (int) $fila['total'];
        }

        // Ventas por día: se rellenan con 0 los días sin ventas para que la gráfica sea continua.
        $porDia = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $porDia[date('Y-m-d', strtotime("-$i days"))] = ['pedidos' => 0, 'total' => 0.0];
        }
        $filas = $this->obtenerTodos(
            "SELECT DATE(creado_en) AS dia, COUNT(*) AS pedidos, SUM(total) AS total
               FROM pedidos
              WHERE estado <> 'cancelado' AND creado_en >= CURDATE() - INTERVAL :dias DAY
              GROUP BY DATE(creado_en)",
            ['dias' => $dias - 1]
        );
        foreach ($filas as $fila) {
            if (isset($porDia[$fila['dia']])) {
                $porDia[$fila['dia']] = ['pedidos' => (int) $fila['pedidos'], 'total' => (float) $fila['total']];
            }
        }

        $masVendidos = array_map(static fn (array $fila): array => [
            'nombre' => $fila['nombre'],
            'slug' => $fila['slug'],
            'marca' => $fila['marca'],
            'unidades' => (int) $fila['unidades'],
            'ingresos' => (float) $fila['ingresos'],
        ], $this->obtenerTodos(
            "SELECT p.nombre, p.slug, m.nombre AS marca, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS ingresos
               FROM pedido_detalle d
               JOIN pedidos pe ON pe.id = d.pedido_id AND pe.estado <> 'cancelado'
               JOIN variantes v ON v.id = d.variante_id
               JOIN productos p ON p.id = v.producto_id
               JOIN marcas m ON m.id = p.marca_id
              GROUP BY p.id, p.nombre, p.slug, m.nombre
              ORDER BY unidades DESC, ingresos DESC
              LIMIT 5"
        ));

        $inventario = $this->obtenerUno(
            'SELECT SUM(v.stock <= :umbral AND v.stock > 0) AS bajo_stock,
                    SUM(v.stock = 0) AS agotadas,
                    COALESCE(SUM(v.stock), 0) AS unidades
               FROM variantes v
               JOIN productos p ON p.id = v.producto_id
              WHERE v.activo = 1 AND p.activo = 1',
            ['umbral' => $umbralStock]
        );

        $conteos = $this->obtenerUno(
            "SELECT (SELECT COUNT(*) FROM productos WHERE activo = 1) AS productos,
                    (SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente') AS clientes,
                    (SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0) AS mensajes_no_leidos"
        );

        return [
            'ventas' => ['pedidos' => (int) $ventas['pedidos'], 'total' => (float) $ventas['total']],
            'ventas_mes' => ['pedidos' => (int) $ventasMes['pedidos'], 'total' => (float) $ventasMes['total']],
            'pedidos_por_estado' => $porEstado,
            'ventas_por_dia' => array_map(
                static fn (string $dia, array $valor): array => ['dia' => $dia] + $valor,
                array_keys($porDia),
                $porDia
            ),
            'mas_vendidos' => $masVendidos,
            'inventario' => [
                'bajo_stock' => (int) $inventario['bajo_stock'],
                'agotadas' => (int) $inventario['agotadas'],
                'unidades' => (int) $inventario['unidades'],
                'umbral' => $umbralStock,
            ],
            'productos_activos' => (int) $conteos['productos'],
            'clientes' => (int) $conteos['clientes'],
            'mensajes_no_leidos' => (int) $conteos['mensajes_no_leidos'],
        ];
    }
}
