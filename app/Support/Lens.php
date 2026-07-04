<?php

namespace App\Support;

use App\Models\InflationRate;
use Carbon\CarbonInterface;

/**
 * Un lente compone dos ejes independientes sobre el mismo dataset:
 * en qué moneda/cotización se muestra (eje FX) y si se ajusta por
 * inflación a una fecha de referencia (eje temporal). Nada de lo que
 * calcula se guarda: es siempre una vista derivada.
 */
final class Lens
{
    public const FX = ['ars', 'blue', 'oficial', 'mep'];

    public const TIEMPOS = ['nominal', 'real'];

    public function __construct(
        public readonly string $fx,
        public readonly string $tiempo,
        public readonly CarbonInterface $reference,
    ) {}

    /**
     * Proyecta un movimiento (monto, moneda, fecha) bajo este lente:
     *
     * 1. A ARS con la cotización del día de la transacción (el snapshot
     *    congelado si existe — lo que realmente salió).
     * 2. Si el eje temporal es real, de ARS-de-esa-fecha a ARS-de-la-referencia
     *    con el IPC. La inflación vive en espacio ARS.
     * 3. Recién ahí, si el lente pide dólares, a la cotización de la fecha
     *    de referencia.
     *
     * Devuelve null si falta una cotización imprescindible.
     */
    public function value(float $amount, string $currency, CarbonInterface $date, ?float $frozenRate = null): ?float
    {
        if ($currency === 'ARS') {
            $ars = $amount;
        } else {
            $rate = $frozenRate ?? MarketData::rate('blue', $date);

            if ($rate === null) {
                return null;
            }

            $ars = $amount * $rate;
        }

        if ($this->tiempo === 'real') {
            $ars *= InflationRate::factorBetween($date, $this->reference);
        }

        if ($this->fx === 'ars') {
            return $ars;
        }

        $referenceRate = MarketData::rate($this->fx, $this->reference);

        return $referenceRate === null || $referenceRate <= 0 ? null : $ars / $referenceRate;
    }

    public function currency(): string
    {
        return $this->fx === 'ars' ? 'ARS' : 'USD';
    }
}
