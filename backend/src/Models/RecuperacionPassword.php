<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Tokens para restablecer la contraseña.
 *
 * El token viaja solo en el enlace del correo; en la base de datos se guarda su
 * hash SHA-256. Así, aunque alguien lea la tabla, no puede usar los enlaces.
 */
final class RecuperacionPassword extends Model
{
    public const MINUTOS_VIGENCIA = 60;
    public const MAXIMO_POR_HORA = 3;

    /** @return string Token en texto plano (solo para el enlace del correo) */
    public function crear(int $usuarioId): string
    {
        // Al pedir un enlace nuevo, los anteriores dejan de servir.
        $this->ejecutar(
            'UPDATE recuperaciones_password SET usado_en = NOW() WHERE usuario_id = :usuario AND usado_en IS NULL',
            ['usuario' => $usuarioId]
        );

        $token = bin2hex(random_bytes(32));
        $this->ejecutar(
            'INSERT INTO recuperaciones_password (usuario_id, token_hash, expira_en)
             VALUES (:usuario, :hash, NOW() + INTERVAL :minutos MINUTE)',
            ['usuario' => $usuarioId, 'hash' => hash('sha256', $token), 'minutos' => self::MINUTOS_VIGENCIA]
        );

        return $token;
    }

    /** Cuántos enlaces pidió el usuario en la última hora (para evitar abusos). */
    public function solicitudesRecientes(int $usuarioId): int
    {
        return (int) $this->obtenerUno(
            'SELECT COUNT(*) AS total FROM recuperaciones_password
              WHERE usuario_id = :usuario AND creado_en >= NOW() - INTERVAL 1 HOUR',
            ['usuario' => $usuarioId]
        )['total'];
    }

    /** @return int|null Id del usuario si el token es válido, no se ha usado y no ha caducado */
    public function usuarioDelToken(string $token): ?int
    {
        $fila = $this->obtenerUno(
            'SELECT usuario_id FROM recuperaciones_password
              WHERE token_hash = :hash AND usado_en IS NULL AND expira_en > NOW()',
            ['hash' => hash('sha256', $token)]
        );
        return $fila === null ? null : (int) $fila['usuario_id'];
    }

    /** Marca como usados todos los enlaces pendientes del usuario. */
    public function invalidarTodos(int $usuarioId): void
    {
        $this->ejecutar(
            'UPDATE recuperaciones_password SET usado_en = NOW() WHERE usuario_id = :usuario AND usado_en IS NULL',
            ['usuario' => $usuarioId]
        );
    }
}
