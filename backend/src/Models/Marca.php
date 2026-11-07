<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de la tabla marcas (Adidas, Nike, Puma...).
 */
final class Marca extends Model
{
    /** @return list<array<string, mixed>> Marcas con la cantidad de productos activos */
    public function todas(): array
    {
        $marcas = $this->obtenerTodos(
            'SELECT m.id, m.nombre, m.slug, m.descripcion,
                    COUNT(p.id) AS total_productos
               FROM marcas m
               LEFT JOIN productos p ON p.marca_id = m.id AND p.activo = 1
              GROUP BY m.id, m.nombre, m.slug, m.descripcion
              ORDER BY m.nombre'
        );

        return array_map(static function (array $marca): array {
            $marca['id'] = (int) $marca['id'];
            $marca['total_productos'] = (int) $marca['total_productos'];
            return $marca;
        }, $marcas);
    }

    public function existe(int $id): bool
    {
        return $this->obtenerUno('SELECT 1 FROM marcas WHERE id = :id', ['id' => $id]) !== null;
    }
}
