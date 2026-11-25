<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\Database;

/**
 * Creación de cuentas: registro público de clientes (POST /api/auth/registro) y
 * creación de usuarios desde el panel (POST /api/usuarios). Ambas comparten reglas.
 */
final class RegistroUsuariosTest extends ApiTestCase
{
    private const DOMINIO_PRUEBA = '@pruebas.mya.test';

    protected function tearDown(): void
    {
        // Se borran las cuentas creadas por las pruebas (nunca tienen pedidos).
        Database::conexion()->exec("DELETE FROM usuarios WHERE email LIKE '%" . self::DOMINIO_PRUEBA . "'");
    }

    private static function correoNuevo(): string
    {
        return 'cuenta' . bin2hex(random_bytes(4)) . self::DOMINIO_PRUEBA;
    }

    public function testElRegistroPublicoCreaUnClienteYEntregaSuToken(): void
    {
        $correo = self::correoNuevo();
        $respuesta = self::peticion('POST', '/api/auth/registro', json: [
            'nombre' => 'Cliente de prueba', 'email' => strtoupper($correo), 'password' => 'ClaveSegura1', 'rol' => 'admin',
        ]);

        self::assertSame(201, $respuesta['estado'], json_encode($respuesta['cuerpo']));
        self::assertSame('cliente', $respuesta['cuerpo']['data']['usuario']['rol'], 'El registro público nunca crea personal.');
        self::assertSame($correo, $respuesta['cuerpo']['data']['usuario']['email']);
        self::assertNotEmpty($respuesta['cuerpo']['data']['token']);
    }

    public function testNoPermiteDosCuentasConElMismoCorreo(): void
    {
        $datos = ['nombre' => 'Cliente de prueba', 'email' => self::correoNuevo(), 'password' => 'ClaveSegura1'];
        self::peticion('POST', '/api/auth/registro', json: $datos);

        $repetida = self::peticion('POST', '/api/auth/registro', json: $datos);
        $desdePanel = self::peticion('POST', '/api/usuarios', self::tokenDe('admin'), json: $datos + ['rol' => 'vendedor']);

        foreach ([$repetida, $desdePanel] as $respuesta) {
            self::assertSame(422, $respuesta['estado']);
            self::assertSame('Ya existe una cuenta con este correo.', $respuesta['cuerpo']['errores']['email']);
        }
    }

    public function testValidaLosDatosDeLaCuentaIgualEnElRegistroYEnElPanel(): void
    {
        $datos = ['nombre' => 'A', 'email' => 'no-es-correo', 'password' => 'corta', 'telefono' => 'abc'];

        $registro = self::peticion('POST', '/api/auth/registro', json: $datos);
        $panel = self::peticion('POST', '/api/usuarios', self::tokenDe('admin'), json: $datos + ['rol' => 'vendedor']);

        foreach ([$registro, $panel] as $respuesta) {
            self::assertSame(422, $respuesta['estado']);
            self::assertSame(['nombre', 'email', 'password', 'telefono'], array_keys($respuesta['cuerpo']['errores']));
        }
    }

    public function testElAdministradorCreaUnVendedor(): void
    {
        $correo = self::correoNuevo();
        $respuesta = self::peticion('POST', '/api/usuarios', self::tokenDe('admin'), json: [
            'nombre' => 'Vendedor de prueba', 'email' => $correo, 'password' => 'ClaveSegura1', 'rol' => 'vendedor', 'telefono' => '3001234567',
        ]);

        self::assertSame(201, $respuesta['estado'], json_encode($respuesta['cuerpo']));
        self::assertSame('vendedor', $respuesta['cuerpo']['data']['rol']);
        self::assertSame('3001234567', $respuesta['cuerpo']['data']['telefono']);
    }

    public function testElRolDebeSerUnoDeLosPermitidos(): void
    {
        $respuesta = self::peticion('POST', '/api/usuarios', self::tokenDe('admin'), json: [
            'nombre' => 'Rol raro', 'email' => self::correoNuevo(), 'password' => 'ClaveSegura1', 'rol' => 'superusuario',
        ]);

        self::assertSame(422, $respuesta['estado']);
        self::assertArrayHasKey('rol', $respuesta['cuerpo']['errores']);
    }
}
