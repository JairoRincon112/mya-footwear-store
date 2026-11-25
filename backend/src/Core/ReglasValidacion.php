<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use DateTimeImmutable;
use LogicException;

/**
 * Reglas que usa Validator. Cada regla es una función pequeña que recibe el valor (y el
 * parámetro de la regla, por ejemplo el 8 de "min:8") y devuelve
 * [mensaje de error o null, valor convertido].
 *
 * Para agregar una regla se escribe su método y se registra en mapa(): el recorrido de
 * los campos en Validator no cambia (principio abierto/cerrado).
 */
final class ReglasValidacion
{
    /** Formatos de fecha aceptados: input date, input datetime-local y el de MySQL. */
    private const FORMATOS_FECHA = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];

    /**
     * Expresión regular, mensaje de error y si el valor se pasa a mayúsculas.
     *
     * @var array<string, array{0: string, 1: string, 2: bool}>
     */
    private const FORMATOS = [
        'telefono' => ['/^[0-9+()\s-]{7,20}$/', 'Escribe un teléfono válido (solo números, espacios, + y -).', false],
        'codigo' => ['/^[A-Za-z0-9_-]{3,30}$/', 'El código solo admite letras, números, guiones (3 a 30 caracteres).', true],
        'sku' => ['/^[A-Za-z0-9-]{3,60}$/', 'El SKU solo admite letras, números y guiones (3 a 60 caracteres).', true],
    ];

    /** @var array<string, Closure>|null */
    private static ?array $mapa = null;

    /** @return array{0: ?string, 1: mixed} [mensaje de error o null, valor convertido] */
    public static function aplicar(string $regla, mixed $valor, string $parametro): array
    {
        $funcion = self::mapa()[$regla] ?? throw new LogicException("Regla de validación desconocida: $regla");
        return $funcion($valor, $parametro);
    }

    /** @return array<string, Closure> nombre de la regla => función que la aplica */
    private static function mapa(): array
    {
        return self::$mapa ??= [
            // "requerido" ya se revisó en Validator antes de aplicar las reglas.
            'requerido' => static fn (mixed $valor): array => [null, $valor],
            'texto' => self::texto(...),
            'email' => self::email(...),
            'min' => self::longitudMinima(...),
            'max' => self::longitudMaxima(...),
            'entero' => self::entero(...),
            'numerico' => self::numerico(...),
            'minimo' => self::valorMinimo(...),
            'maximo' => self::valorMaximo(...),
            'en' => self::unoDe(...),
            'booleano' => self::booleano(...),
            'lista_enteros' => self::listaEnteros(...),
            'fecha_hora' => self::fechaHora(...),
            'telefono' => static fn (mixed $valor): array => self::formato('telefono', $valor),
            'codigo' => static fn (mixed $valor): array => self::formato('codigo', $valor),
            'sku' => static fn (mixed $valor): array => self::formato('sku', $valor),
        ];
    }

    private static function texto(mixed $valor): array
    {
        return is_string($valor) ? [null, $valor] : ['Debe ser un texto.', $valor];
    }

    private static function email(mixed $valor): array
    {
        if (!is_string($valor) || filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            return ['Escribe un correo electrónico válido.', $valor];
        }
        return [null, mb_strtolower($valor)];
    }

    private static function longitudMinima(mixed $valor, string $minimo): array
    {
        $error = mb_strlen((string) $valor) < (int) $minimo ? "Debe tener al menos $minimo caracteres." : null;
        return [$error, $valor];
    }

    private static function longitudMaxima(mixed $valor, string $maximo): array
    {
        $error = mb_strlen((string) $valor) > (int) $maximo ? "Debe tener como máximo $maximo caracteres." : null;
        return [$error, $valor];
    }

    private static function entero(mixed $valor): array
    {
        $entero = is_bool($valor) ? false : filter_var($valor, FILTER_VALIDATE_INT);
        return $entero === false ? ['Debe ser un número entero.', $valor] : [null, $entero];
    }

    private static function numerico(mixed $valor): array
    {
        return is_numeric($valor) ? [null, (float) $valor] : ['Debe ser un número.', $valor];
    }

    private static function valorMinimo(mixed $valor, string $minimo): array
    {
        return [$valor < (float) $minimo ? "Debe ser mayor o igual a $minimo." : null, $valor];
    }

    private static function valorMaximo(mixed $valor, string $maximo): array
    {
        return [$valor > (float) $maximo ? "Debe ser menor o igual a $maximo." : null, $valor];
    }

    private static function unoDe(mixed $valor, string $opciones): array
    {
        return in_array((string) $valor, explode(',', $opciones), true)
            ? [null, (string) $valor]
            : ['El valor seleccionado no es válido.', $valor];
    }

    private static function booleano(mixed $valor): array
    {
        $booleano = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $booleano === null ? ['Debe ser verdadero o falso.', $valor] : [null, $booleano];
    }

    private static function listaEnteros(mixed $valor): array
    {
        if (!is_array($valor)) {
            return ['Debe ser una lista.', $valor];
        }
        $enteros = array_map(static fn ($item) => filter_var($item, FILTER_VALIDATE_INT), $valor);
        if (in_array(false, $enteros, true)) {
            return ['La lista solo puede contener números enteros.', $valor];
        }
        return [null, array_values(array_unique($enteros))];
    }

    private static function fechaHora(mixed $valor): array
    {
        foreach (self::FORMATOS_FECHA as $formato) {
            $fecha = is_string($valor) ? DateTimeImmutable::createFromFormat('!' . $formato, $valor) : false;
            if ($fecha !== false && $fecha->format($formato) === $valor) {
                return [null, $fecha->format('Y-m-d H:i:s')];
            }
        }
        return ['Escribe una fecha válida.', $valor];
    }

    /** Valida un texto contra su expresión regular de FORMATOS (códigos y SKU quedan en mayúsculas). */
    private static function formato(string $nombre, mixed $valor): array
    {
        [$expresion, $mensaje, $mayusculas] = self::FORMATOS[$nombre];
        if (!is_string($valor) || !preg_match($expresion, $valor)) {
            return [$mensaje, $valor];
        }
        return [null, $mayusculas ? strtoupper($valor) : $valor];
    }
}
