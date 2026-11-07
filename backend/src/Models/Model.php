<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOStatement;

/**
 * Clase base de los modelos. Cada modelo representa una tabla (o un grupo de tablas
 * relacionadas) y es la ÚNICA capa que escribe SQL.
 */
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /**
     * Prepara y ejecuta una consulta con parámetros (consulta preparada).
     * Los enteros se envían como enteros para que LIMIT/OFFSET funcionen.
     *
     * @param array<string, mixed> $parametros
     */
    protected function ejecutar(string $sql, array $parametros = []): PDOStatement
    {
        $consulta = $this->db->prepare($sql);
        foreach ($parametros as $nombre => $valor) {
            $tipo = match (true) {
                is_int($valor) => PDO::PARAM_INT,
                is_bool($valor) => PDO::PARAM_BOOL,
                $valor === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $consulta->bindValue(':' . ltrim($nombre, ':'), $valor, $tipo);
        }
        $consulta->execute();
        return $consulta;
    }

    /** @return array<string, mixed>|null */
    protected function obtenerUno(string $sql, array $parametros = []): ?array
    {
        $fila = $this->ejecutar($sql, $parametros)->fetch();
        return $fila === false ? null : $fila;
    }

    /** @return list<array<string, mixed>> */
    protected function obtenerTodos(string $sql, array $parametros = []): array
    {
        return $this->ejecutar($sql, $parametros)->fetchAll();
    }

    /** Talla guardada como DECIMAL ("38.0") -> texto legible ("38"). */
    protected static function formatearTalla(mixed $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 1, '.', ''), '0'), '.');
    }
}
