<?php

declare(strict_types=1);

namespace App\Descuentos;

/** Cupón tipo "porcentaje": descuenta un % del subtotal (de 1 a 90). */
final class DescuentoPorcentaje implements EstrategiaDescuento
{
    public const MAXIMO = 90;

    public function calcular(float $subtotal, float $valor): float
    {
        return $subtotal * $valor / 100;
    }

    public function validarValor(float $valor): ?string
    {
        return $valor > self::MAXIMO ? 'El porcentaje máximo es ' . self::MAXIMO . ' %.' : null;
    }

    public function describir(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',') . ' %';
    }
}
