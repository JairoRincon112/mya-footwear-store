<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;

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
 *         fecha_hora, codigo
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
            $listaReglas = explode('|', $definicion);
            $valor = $datos[$campo] ?? null;

            if (is_string($valor)) {
                $valor = trim($valor);
            }

            if ($valor === null || $valor === '' || $valor === []) {
                if (in_array('requerido', $listaReglas, true)) {
                    $errores[$campo] = 'Este campo es obligatorio.';
                } elseif (array_key_exists($campo, $datos)) {
                    $limpios[$campo] = null; // Campo opcional enviado vacío.
                }
                continue;
            }

            $error = null;
            foreach ($listaReglas as $regla) {
                [$nombre, $parametro] = array_pad(explode(':', $regla, 2), 2, '');
                [$error, $valor] = self::aplicarRegla($nombre, $parametro, $valor);
                if ($error !== null) {
                    break;
                }
            }

            if ($error !== null) {
                $errores[$campo] = $error;
            } else {
                $limpios[$campo] = $valor;
            }
        }

        if ($errores !== []) {
            throw HttpException::validacion($errores);
        }

        return $limpios;
    }

    /** @return array{0: ?string, 1: mixed} [mensaje de error o null, valor convertido] */
    private static function aplicarRegla(string $regla, string $parametro, mixed $valor): array
    {
        switch ($regla) {
            case 'requerido':
                return [null, $valor];

            case 'texto':
                return is_string($valor) ? [null, $valor] : ['Debe ser un texto.', $valor];

            case 'email':
                if (!is_string($valor) || filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
                    return ['Escribe un correo electrónico válido.', $valor];
                }
                return [null, mb_strtolower($valor)];

            case 'min':
                return mb_strlen((string) $valor) < (int) $parametro
                    ? ["Debe tener al menos $parametro caracteres.", $valor]
                    : [null, $valor];

            case 'max':
                return mb_strlen((string) $valor) > (int) $parametro
                    ? ["Debe tener como máximo $parametro caracteres.", $valor]
                    : [null, $valor];

            case 'entero':
                $entero = is_bool($valor) ? false : filter_var($valor, FILTER_VALIDATE_INT);
                return $entero === false ? ['Debe ser un número entero.', $valor] : [null, $entero];

            case 'numerico':
                return is_numeric($valor) ? [null, (float) $valor] : ['Debe ser un número.', $valor];

            case 'minimo':
                return $valor < (float) $parametro ? ["Debe ser mayor o igual a $parametro.", $valor] : [null, $valor];

            case 'maximo':
                return $valor > (float) $parametro ? ["Debe ser menor o igual a $parametro.", $valor] : [null, $valor];

            case 'en':
                return in_array((string) $valor, explode(',', $parametro), true)
                    ? [null, (string) $valor]
                    : ['El valor seleccionado no es válido.', $valor];

            case 'booleano':
                $booleano = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                return $booleano === null ? ['Debe ser verdadero o falso.', $valor] : [null, $booleano];

            case 'lista_enteros':
                if (!is_array($valor)) {
                    return ['Debe ser una lista.', $valor];
                }
                $enteros = array_map(static fn ($item) => filter_var($item, FILTER_VALIDATE_INT), $valor);
                if (in_array(false, $enteros, true)) {
                    return ['La lista solo puede contener números enteros.', $valor];
                }
                return [null, array_values(array_unique($enteros))];

            case 'telefono':
                return is_string($valor) && preg_match('/^[0-9+()\s-]{7,20}$/', $valor)
                    ? [null, $valor]
                    : ['Escribe un teléfono válido (solo números, espacios, + y -).', $valor];

            case 'fecha_hora':
                // Acepta "AAAA-MM-DD", "AAAA-MM-DDTHH:MM" (input datetime-local) o "AAAA-MM-DD HH:MM:SS".
                if (is_string($valor)) {
                    foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $formato) {
                        $fecha = \DateTimeImmutable::createFromFormat('!' . $formato, $valor);
                        if ($fecha !== false && $fecha->format($formato) === $valor) {
                            return [null, $fecha->format('Y-m-d H:i:s')];
                        }
                    }
                }
                return ['Escribe una fecha válida.', $valor];

            case 'codigo':
                return is_string($valor) && preg_match('/^[A-Za-z0-9_-]{3,30}$/', $valor)
                    ? [null, strtoupper($valor)]
                    : ['El código solo admite letras, números, guiones (3 a 30 caracteres).', $valor];

            case 'sku':
                return is_string($valor) && preg_match('/^[A-Za-z0-9-]{3,60}$/', $valor)
                    ? [null, strtoupper($valor)]
                    : ['El SKU solo admite letras, números y guiones (3 a 60 caracteres).', $valor];

            default:
                throw new LogicException("Regla de validación desconocida: $regla");
        }
    }
}
