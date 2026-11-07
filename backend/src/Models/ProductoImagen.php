<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de la tabla producto_imagenes.
 * Una imagen puede asociarse a un color (para mostrar la foto del color elegido).
 */
final class ProductoImagen extends Model
{
    public function crear(int $productoId, string $ruta, ?int $colorId): int
    {
        // La nueva imagen queda al final de la galería del producto.
        $siguienteOrden = (int) $this->obtenerUno(
            'SELECT COALESCE(MAX(orden), -1) + 1 AS orden FROM producto_imagenes WHERE producto_id = :producto',
            ['producto' => $productoId]
        )['orden'];

        $this->ejecutar(
            'INSERT INTO producto_imagenes (producto_id, color_id, ruta, orden)
             VALUES (:producto, :color, :ruta, :orden)',
            ['producto' => $productoId, 'color' => $colorId, 'ruta' => $ruta, 'orden' => $siguienteOrden]
        );
        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $imagen = $this->obtenerUno(
            'SELECT id, producto_id, color_id, ruta, orden FROM producto_imagenes WHERE id = :id',
            ['id' => $id]
        );
        if ($imagen === null) {
            return null;
        }
        return [
            'id' => (int) $imagen['id'],
            'producto_id' => (int) $imagen['producto_id'],
            'color_id' => $imagen['color_id'] === null ? null : (int) $imagen['color_id'],
            'ruta' => $imagen['ruta'],
            'orden' => (int) $imagen['orden'],
        ];
    }

    public function eliminar(int $id): void
    {
        $this->ejecutar('DELETE FROM producto_imagenes WHERE id = :id', ['id' => $id]);
    }
}
