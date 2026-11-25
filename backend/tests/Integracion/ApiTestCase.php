<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\Database;
use App\Core\Jwt;
use CURLFile;
use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas de integración: hacen peticiones HTTP reales a la API local
 * (Apache + PHP + MariaDB de XAMPP) y revisan el código de estado y el JSON.
 *
 * Para no escribir contraseñas en el repositorio, el token se firma aquí mismo con el
 * JWT_SECRET del backend/.env local, igual que lo hace la API al iniciar sesión.
 */
abstract class ApiTestCase extends TestCase
{
    protected static function urlApi(): string
    {
        return rtrim(getenv('MYA_API_URL') ?: 'http://localhost/mya-api', '/');
    }

    protected function setUp(): void
    {
        $respuesta = @file_get_contents(self::urlApi() . '/api/marcas');
        if ($respuesta === false) {
            self::markTestSkipped('La API local no responde en ' . self::urlApi() . ' (¿XAMPP encendido?).');
        }
    }

    /** Token de la primera cuenta activa con ese rol. */
    protected static function tokenDe(string $rol): string
    {
        $id = Database::conexion()
            ->query("SELECT id FROM usuarios WHERE rol = " . Database::conexion()->quote($rol) . ' AND activo = 1 ORDER BY id LIMIT 1')
            ->fetchColumn();
        self::assertNotFalse($id, "No hay una cuenta activa de $rol en la base de datos local.");

        return Jwt::generar(['sub' => (int) $id, 'rol' => $rol]);
    }

    /**
     * @param array<string, mixed>|null $json   Cuerpo JSON
     * @param array<string, mixed>|null $multipart Campos de formulario (admite CURLFile)
     * @return array{estado: int, cuerpo: array<string, mixed>}
     */
    protected static function peticion(string $metodo, string $ruta, ?string $token = null, ?array $json = null, ?array $multipart = null): array
    {
        $curl = curl_init(self::urlApi() . $ruta);
        $cabeceras = ['Accept: application/json'];
        if ($token !== null) {
            $cabeceras[] = "Authorization: Bearer $token";
        }
        if ($json !== null) {
            $cabeceras[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json, JSON_THROW_ON_ERROR));
        }
        if ($multipart !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart);
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabeceras,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);

        $texto = (string) curl_exec($curl);
        $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return ['estado' => $estado, 'cuerpo' => json_decode($texto, true) ?? ['crudo' => $texto]];
    }

    protected static function archivo(string $nombre, string $tipo): CURLFile
    {
        return new CURLFile(dirname(__DIR__) . '/datos/' . $nombre, $tipo, $nombre);
    }
}
