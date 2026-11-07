<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Config;
use PDO;
use Throwable;

/**
 * Conexión única (singleton) a MySQL/MariaDB mediante PDO.
 *
 * - ERRMODE_EXCEPTION: cualquier error de SQL lanza una excepción.
 * - EMULATE_PREPARES = false: las consultas preparadas las resuelve el servidor,
 *   así los datos nunca se mezclan con el SQL (protección contra inyección SQL).
 */
final class Database
{
    private static ?PDO $conexion = null;

    public static function conexion(): PDO
    {
        if (self::$conexion === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', '127.0.0.1'),
                Config::entero('DB_PORT', 3306),
                Config::get('DB_NAME', 'mya_footwear')
            );

            self::$conexion = new PDO($dsn, Config::get('DB_USER', 'root'), Config::get('DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // MySQL usa la misma zona horaria que PHP (fechas de pedidos y reportes coherentes).
            self::$conexion->exec("SET time_zone = '" . date('P') . "'");
        }

        return self::$conexion;
    }

    /**
     * Ejecuta varias operaciones como una sola transacción:
     * si alguna falla, se deshacen todas (rollback).
     *
     * @template T
     * @param callable(): T $operaciones
     * @return T
     */
    public static function transaccion(callable $operaciones): mixed
    {
        $db = self::conexion();

        // Si ya hay una transacción abierta, la operación se suma a ella.
        if ($db->inTransaction()) {
            return $operaciones();
        }

        $db->beginTransaction();
        try {
            $resultado = $operaciones();
            $db->commit();
            return $resultado;
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
