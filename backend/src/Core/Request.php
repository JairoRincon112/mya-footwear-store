<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Representa la petición HTTP que llega a la API:
 * método, ruta, parámetros de la URL, cuerpo JSON, archivos y cabeceras.
 */
final class Request
{
    /** @var array<string, string> Parámetros de la ruta, por ejemplo {id} */
    public array $parametros = [];

    /** @var array<string, mixed>|null Usuario autenticado (lo asigna AuthMiddleware) */
    public ?array $usuario = null;

    /** @var array<string, mixed>|null */
    private ?array $cuerpo = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $archivos
     * @param array<string, string> $cabeceras
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        private readonly array $query,
        private readonly array $archivos,
        private readonly array $cabeceras,
    ) {
    }

    public static function desdeGlobales(): self
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Se quita la carpeta donde está publicada la API (por ejemplo /mya-api).
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && str_starts_with($ruta, $base)) {
            $ruta = substr($ruta, strlen($base));
        }
        $ruta = '/' . trim($ruta, '/');

        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $nombre = strtolower(str_replace('_', '-', substr($clave, 5)));
                $cabeceras[$nombre] = (string) $valor;
            }
        }
        // Apache a veces entrega la cabecera Authorization con otro nombre.
        $cabeceras['authorization'] ??= $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $cabeceras['content-type'] = $_SERVER['CONTENT_TYPE'] ?? '';

        return new self($metodo, $ruta, $_GET, $_FILES, $cabeceras);
    }

    /** Parámetro de la ruta, por ejemplo el {id} de /api/productos/{id}. */
    public function parametro(string $nombre): string
    {
        return $this->parametros[$nombre] ?? '';
    }

    /** Parámetro de la URL (?clave=valor). Solo acepta texto. */
    public function query(string $clave): ?string
    {
        $valor = $this->query[$clave] ?? null;
        return is_string($valor) ? trim($valor) : null;
    }

    /**
     * Cuerpo de la petición: JSON o formulario.
     *
     * @return array<string, mixed>
     */
    public function cuerpo(): array
    {
        if ($this->cuerpo !== null) {
            return $this->cuerpo;
        }

        if (str_contains($this->cabecera('content-type'), 'application/json')) {
            $contenido = file_get_contents('php://input') ?: '';
            if (trim($contenido) === '') {
                return $this->cuerpo = [];
            }
            $datos = json_decode($contenido, true);
            if (!is_array($datos)) {
                throw HttpException::solicitudInvalida('El cuerpo de la petición no es un JSON válido.');
            }
            return $this->cuerpo = $datos;
        }

        return $this->cuerpo = $_POST;
    }

    /** @return array<string, mixed>|null Archivo subido (campo de formulario multipart) */
    public function archivo(string $campo): ?array
    {
        $archivo = $this->archivos[$campo] ?? null;
        return is_array($archivo) && is_string($archivo['tmp_name'] ?? null) ? $archivo : null;
    }

    public function cabecera(string $nombre): string
    {
        return $this->cabeceras[strtolower($nombre)] ?? '';
    }

    /** Token enviado como "Authorization: Bearer <token>". */
    public function tokenBearer(): ?string
    {
        if (preg_match('/^Bearer\s+(\S+)$/i', $this->cabecera('authorization'), $coincidencia)) {
            return $coincidencia[1];
        }
        return null;
    }

    public function esPersonal(): bool
    {
        return in_array($this->usuario['rol'] ?? null, ['admin', 'vendedor'], true);
    }
}
