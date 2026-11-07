<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Error controlado que se convierte en una respuesta JSON con su código HTTP.
 * El router frontal (public/index.php) la captura y responde al cliente.
 */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $errores Errores por campo (validación) */
    public function __construct(int $estado, string $mensaje, private readonly array $errores = [])
    {
        parent::__construct($mensaje, $estado);
    }

    public function estado(): int
    {
        return $this->getCode();
    }

    /** @return array<string, string> */
    public function errores(): array
    {
        return $this->errores;
    }

    public static function solicitudInvalida(string $mensaje): self
    {
        return new self(400, $mensaje);
    }

    public static function noAutenticado(string $mensaje = 'Debes iniciar sesión.'): self
    {
        return new self(401, $mensaje);
    }

    public static function prohibido(string $mensaje = 'No tienes permiso para realizar esta acción.'): self
    {
        return new self(403, $mensaje);
    }

    public static function noEncontrado(string $mensaje = 'El recurso solicitado no existe.'): self
    {
        return new self(404, $mensaje);
    }

    public static function conflicto(string $mensaje): self
    {
        return new self(409, $mensaje);
    }

    /** @param array<string, string> $errores */
    public static function validacion(array $errores, string $mensaje = 'Revisa los datos enviados.'): self
    {
        return new self(422, $mensaje, $errores);
    }
}
