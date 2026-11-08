<?php

declare(strict_types=1);

namespace App\Descuentos;

/**
 * Patrón Strategy: cada tipo de cupón implementa esta interfaz con sus propias reglas.
 * El modelo Cupon (contexto) delega en ella el cálculo, sin condicionales sobre el tipo.
 */
interface EstrategiaDescuento
{
    /** Descuento en pesos para un subtotal, antes del redondeo y del tope que aplica Cupon. */
    public function calcular(float $subtotal, float $valor): float;

    /** Mensaje de error si el valor del cupón no es válido para este tipo, o null si lo es. */
    public function validarValor(float $valor): ?string;

    /** Cómo se muestra el valor al usuario, por ejemplo "10 %" o "$20.000". */
    public function describir(float $valor): string;
}
