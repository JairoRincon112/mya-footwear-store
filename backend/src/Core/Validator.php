<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validación de datos de entrada.
 *
 * Uso:
 *   $datos = Validator::validar($request->cuerpo(), [
 *       'email'    => 'requerido|email|max:150',
 *       'password' => 'requerido|texto|min:8|max:72',
 *   ]);
 *
 * Devuelve solo los campos validados, ya limpios y convertidos (enteros, decimales, booleanos).
 * Si hay errores lanza HttpException 422 con el mensaje de cada campo.
 *
 * Reglas: requerido, texto, email, min:N, max:N (longitud), entero, numerico,
 *         minimo:N, maximo:N (valor), en:a,b,c, booleano, lista_enteros, telefono, sku,
 *         fecha_hora, codigo. Cada una está implementada en ReglasValidacion.
 */
final class Validator
{
    /**
     * @param array<string, mixed>  $datos
     * @param array<string, string> $reglas
     * @return array<string, mixed>
     */
    public static function validar(array $datos, array $reglas): array
    {
        $errores = [];
        $limpios = [];

        foreach ($reglas as $campo => $definicion) {
            [$error, $valor] = self::validarCampo($datos, $campo, explode('|', $definicion));

            if ($error !== null) {
                $errores[$campo] = $error;
                continue;
            }
            if ($valor !== null || array_key_exists($campo, $datos)) {
                $limpios[$campo] = $valor; // Un campo opcional enviado vacío queda en null.
            }
        }

        if ($errores !== []) {
            throw HttpException::validacion($errores);
        }

        return $limpios;
    }

    /**
     * Aplica las reglas de un campo en orden y se detiene en la primera que falle.
     *
     * @param array<string, mixed> $datos
     * @param list<string>         $listaReglas
     * @return array{0: ?string, 1: mixed} [mensaje de error o null, valor limpio]
     */
    private static function validarCampo(array $datos, string $campo, array $listaReglas): array
    {
        $valor = $datos[$campo] ?? null;
        if (is_string($valor)) {
            $valor = trim($valor);
        }

        if ($valor === null || $valor === '' || $valor === []) {
            $obligatorio = in_array('requerido', $listaReglas, true);
            return [$obligatorio ? 'Este campo es obligatorio.' : null, null];
        }

        foreach ($listaReglas as $regla) {
            [$nombre, $parametro] = array_pad(explode(':', $regla, 2), 2, '');
            [$error, $valor] = ReglasValidacion::aplicar($nombre, $valor, $parametro);
            if ($error !== null) {
                return [$error, $valor];
            }
        }

        return [null, $valor];
    }
}
