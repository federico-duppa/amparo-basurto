<?php

namespace App\Models\Concerns;

/**
 * Formateo de moneda y números en es-AR (punto de miles, coma decimal) que
 * se repetía suelto en los paneles de Auto y Plata, más el idiom para
 * limpiar los ceros de relleno al precargar un input editable.
 */
trait FormatsMoney
{
    public function plata(int|float|string|null $value, string $currency = 'ARS'): string
    {
        return ($currency === 'ARS' ? '$' : 'US$').number_format((float) $value, 2, ',', '.');
    }

    public function pesos(int|float|string|null $value): string
    {
        return $this->plata($value, 'ARS');
    }

    public function km(int|float|string|null $value): string
    {
        return number_format((int) $value, 0, ',', '.').' km';
    }

    /** Limpia los ceros de relleno de un decimal para precargarlo en un input editable. */
    public function cleanDecimal(int|float|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return rtrim(rtrim((string) $value, '0'), '.');
    }
}
