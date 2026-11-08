<?php

declare(strict_types=1);

namespace App\Descuentos;

use InvalidArgumentException;

/**
 * Registro de estrategias de descuento: relaciona cada tipo de cupón (columna cupones.tipo)
 * con la clase que lo calcula. Para agregar un tipo nuevo se crea su clase, se registra aquí
 * y se agrega el valor al ENUM de la tabla cupones.
 */
final class EstrategiasDescuento
{
    /** @var array<string, class-string<EstrategiaDescuento>> */
    private const CLASES = [
        'porcentaje' => DescuentoPorcentaje::class,
        'valor' => DescuentoValorFijo::class,
    ];

    public static function para(string $tipo): EstrategiaDescuento
    {
        $clase = self::CLASES[$tipo] ?? throw new InvalidArgumentException("Tipo de cupón desconocido: $tipo");
        return new $clase();
    }

    /** @return list<string> */
    public static function tipos(): array
    {
        return array_keys(self::CLASES);
    }
}
