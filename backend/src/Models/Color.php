<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de la tabla colores.
 */
final class Color extends Model
{
    /** @return list<array<string, mixed>> */
    public function todos(): array
    {
        return array_map(
            static fn (array $color): array => ['id' => (int) $color['id']] + $color,
            $this->obtenerTodos('SELECT id, nombre, hex FROM colores ORDER BY nombre')
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->obtenerUno('SELECT id, nombre, hex FROM colores WHERE id = :id', ['id' => $id]);
    }
}
