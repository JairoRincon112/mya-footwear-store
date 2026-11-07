<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de la tabla tallas (numeración europea: 36, 37, 38...).
 */
final class Talla extends Model
{
    /** @return list<array{id: int, valor: string}> */
    public function todas(): array
    {
        return array_map(
            static fn (array $talla): array => ['id' => (int) $talla['id'], 'valor' => self::formatearTalla($talla['valor'])],
            $this->obtenerTodos('SELECT id, valor FROM tallas ORDER BY orden, valor')
        );
    }

    /**
     * @param list<int> $ids
     * @return array<int, string> id => valor, solo de las tallas que existen
     */
    public function buscarVarias(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // Un marcador :t0, :t1, ... por cada id (sigue siendo consulta preparada).
        $marcadores = [];
        $parametros = [];
        foreach (array_values($ids) as $posicion => $id) {
            $marcadores[] = ":t$posicion";
            $parametros["t$posicion"] = $id;
        }

        $tallas = [];
        $filas = $this->obtenerTodos(
            'SELECT id, valor FROM tallas WHERE id IN (' . implode(', ', $marcadores) . ')',
            $parametros
        );
        foreach ($filas as $fila) {
            $tallas[(int) $fila['id']] = self::formatearTalla($fila['valor']);
        }
        return $tallas;
    }
}
