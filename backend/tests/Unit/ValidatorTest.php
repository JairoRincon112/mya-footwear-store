<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Validator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validación de datos de entrada (App\Core\Validator).
 * Cubre cada regla: el valor que se limpia o convierte y el mensaje de error.
 */
final class ValidatorTest extends TestCase
{
    /** @return array<string, array{string, mixed, mixed}> regla, valor recibido, valor esperado */
    public static function valoresValidos(): array
    {
        return [
            'texto se recorta' => ['texto', '  Tenis  ', 'Tenis'],
            'email en minúsculas' => ['email', 'Cliente@Example.COM', 'cliente@example.com'],
            'entero desde texto' => ['entero', '42', 42],
            'numérico a decimal' => ['numerico', '159900.50', 159900.5],
            'booleano desde "1"' => ['booleano', '1', true],
            'booleano desde "false"' => ['booleano', 'false', false],
            'lista de enteros sin repetidos' => ['lista_enteros', ['3', 3, '7'], [3, 7]],
            'opción de una lista' => ['en:entrada,salida', 'salida', 'salida'],
            'código en mayúsculas' => ['codigo', 'bienvenida10', 'BIENVENIDA10'],
            'sku en mayúsculas' => ['sku', 'nk-air-40', 'NK-AIR-40'],
            'teléfono' => ['telefono', '+57 300 123-4567', '+57 300 123-4567'],
            'fecha del formulario' => ['fecha_hora', '2026-12-01T08:30', '2026-12-01 08:30:00'],
            'fecha sola' => ['fecha_hora', '2026-12-01', '2026-12-01 00:00:00'],
            'longitud mínima' => ['min:3', 'abc', 'abc'],
            'valor dentro del rango' => ['numerico|minimo:1|maximo:10', '10', 10.0],
        ];
    }

    #[DataProvider('valoresValidos')]
    public function testConvierteYLimpiaLosValoresValidos(string $reglas, mixed $valor, mixed $esperado): void
    {
        $datos = Validator::validar(['campo' => $valor], ['campo' => $reglas]);

        self::assertSame($esperado, $datos['campo']);
    }

    /** @return array<string, array{string, mixed, string}> regla, valor recibido, mensaje esperado */
    public static function valoresInvalidos(): array
    {
        return [
            'texto que no es texto' => ['texto', 123, 'Debe ser un texto.'],
            'correo inválido' => ['email', 'no-es-correo', 'Escribe un correo electrónico válido.'],
            'muy corto' => ['min:8', 'abc', 'Debe tener al menos 8 caracteres.'],
            'muy largo' => ['max:3', 'abcd', 'Debe tener como máximo 3 caracteres.'],
            'entero con decimales' => ['entero', '4.5', 'Debe ser un número entero.'],
            'booleano no es entero' => ['entero', true, 'Debe ser un número entero.'],
            'no numérico' => ['numerico', 'diez', 'Debe ser un número.'],
            'menor que el mínimo' => ['numerico|minimo:1', '0', 'Debe ser mayor o igual a 1.'],
            'mayor que el máximo' => ['entero|maximo:50', '51', 'Debe ser menor o igual a 50.'],
            'opción que no existe' => ['en:entrada,salida', 'regalo', 'El valor seleccionado no es válido.'],
            'booleano inválido' => ['booleano', 'quizás', 'Debe ser verdadero o falso.'],
            'lista que no es lista' => ['lista_enteros', '3', 'Debe ser una lista.'],
            'lista con texto' => ['lista_enteros', [1, 'a'], 'La lista solo puede contener números enteros.'],
            'teléfono con letras' => ['telefono', '300-ABC', 'Escribe un teléfono válido (solo números, espacios, + y -).'],
            'fecha imposible' => ['fecha_hora', '2026-02-30', 'Escribe una fecha válida.'],
            'código con espacios' => ['codigo', 'MI CUPON', 'El código solo admite letras, números, guiones (3 a 30 caracteres).'],
            'sku corto' => ['sku', 'A1', 'El SKU solo admite letras, números y guiones (3 a 60 caracteres).'],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function testRechazaLosValoresInvalidosConSuMensaje(string $reglas, mixed $valor, string $mensaje): void
    {
        try {
            Validator::validar(['campo' => $valor], ['campo' => $reglas]);
            self::fail('Se esperaba un error de validación.');
        } catch (HttpException $error) {
            self::assertSame(422, $error->getCode());
            self::assertSame(['campo' => $mensaje], $error->errores());
        }
    }

    public function testCampoRequeridoVacioEsUnError(): void
    {
        $this->expectException(HttpException::class);

        Validator::validar(['nombre' => '   '], ['nombre' => 'requerido|texto']);
    }

    public function testCampoOpcionalVacioQuedaEnNullYElAusenteNoAparece(): void
    {
        $datos = Validator::validar(['telefono' => ''], ['telefono' => 'telefono', 'descripcion' => 'texto']);

        self::assertSame(['telefono' => null], $datos);
    }

    public function testSoloDevuelveLosCamposValidados(): void
    {
        $datos = Validator::validar(['nombre' => 'Ana', 'rol' => 'admin'], ['nombre' => 'requerido|texto']);

        self::assertSame(['nombre' => 'Ana'], $datos);
    }

    public function testReuneLosErroresDeTodosLosCampos(): void
    {
        try {
            Validator::validar(['email' => 'malo'], ['nombre' => 'requerido', 'email' => 'email']);
            self::fail('Se esperaba un error de validación.');
        } catch (HttpException $error) {
            self::assertSame(['nombre', 'email'], array_keys($error->errores()));
        }
    }

    public function testUnaReglaDesconocidaEsUnErrorDeProgramacion(): void
    {
        $this->expectException(LogicException::class);

        Validator::validar(['campo' => 'x'], ['campo' => 'inventada']);
    }
}
