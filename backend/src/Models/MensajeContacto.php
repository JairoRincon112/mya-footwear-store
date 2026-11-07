<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de la tabla mensajes_contacto (formulario de la página Contacto).
 */
final class MensajeContacto extends Model
{
    /** @param array{nombre: string, email: string, telefono: ?string, mensaje: string} $datos */
    public function crear(array $datos): int
    {
        $this->ejecutar(
            'INSERT INTO mensajes_contacto (nombre, email, telefono, mensaje)
             VALUES (:nombre, :email, :telefono, :mensaje)',
            $datos
        );
        return (int) $this->db->lastInsertId();
    }

    /** @return array{items: list<array<string, mixed>>, total: int, no_leidos: int} */
    public function listar(?bool $leido, int $pagina, int $porPagina): array
    {
        $where = $leido === null ? '' : 'WHERE leido = :leido';
        $parametros = $leido === null ? [] : ['leido' => $leido ? 1 : 0];

        $total = (int) $this->obtenerUno("SELECT COUNT(*) AS total FROM mensajes_contacto $where", $parametros)['total'];
        $filas = $this->obtenerTodos(
            "SELECT id, nombre, email, telefono, mensaje, leido, creado_en
               FROM mensajes_contacto $where
              ORDER BY creado_en DESC, id DESC
              LIMIT :limite OFFSET :desplazamiento",
            $parametros + ['limite' => $porPagina, 'desplazamiento' => ($pagina - 1) * $porPagina]
        );

        return [
            'items' => array_map(static fn (array $fila): array => ['id' => (int) $fila['id'], 'leido' => (bool) $fila['leido']] + $fila, $filas),
            'total' => $total,
            'no_leidos' => $this->contarNoLeidos(),
        ];
    }

    public function contarNoLeidos(): int
    {
        return (int) $this->obtenerUno('SELECT COUNT(*) AS total FROM mensajes_contacto WHERE leido = 0')['total'];
    }

    public function existe(int $id): bool
    {
        return $this->obtenerUno('SELECT 1 FROM mensajes_contacto WHERE id = :id', ['id' => $id]) !== null;
    }

    public function marcarLeido(int $id, bool $leido): void
    {
        $this->ejecutar('UPDATE mensajes_contacto SET leido = :leido WHERE id = :id', ['leido' => $leido ? 1 : 0, 'id' => $id]);
    }
}
