<?php

declare(strict_types=1);

namespace App\Descuentos;

/** Cupón tipo "valor": descuenta una cantidad fija de pesos. */
final class DescuentoValorFijo implements EstrategiaDescuento
{
    public function calcular(float $subtotal, float $valor): float
    {
        return $valor;
    }

    public function validarValor(float $valor): ?string
    {
        return null; // El mínimo (mayor que 0) ya lo valida el controlador para todos los tipos.
    }

    public function describir(float $valor): string
    {
        return '$' . number_format($valor, 0, ',', '.');
    }
}
