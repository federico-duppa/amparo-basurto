<?php

namespace App\Support;

use App\Models\ExchangeRate;
use App\Models\InflationRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Un lente compone dos ejes independientes sobre el mismo dataset:
 * en qué moneda/cotización se muestra (eje FX) y si se ajusta por
 * inflación a una fecha de referencia (eje temporal). Nada de lo que
 * calcula se guarda: es siempre una vista derivada.
 *
 * Como value() corre por cada movimiento del reporte, el lente cachea
 * dentro de la instancia todo lo que no varía entre movimientos: la
 * cotización de referencia (constante en todo el reporte), los factores
 * de inflación (varían solo por mes) y la serie blue completa (se carga
 * una vez y se consulta en memoria por fecha).
 */
final class Lens
{
    public const FX = ['ars', 'blue', 'oficial', 'mep'];

    public const TIEMPOS = ['nominal', 'real'];

    /** @var array<string, float> factores de inflación por mes (Y-m) de origen */
    private array $inflationFactors = [];

    /** @var array<string, float|null> cotización blue por fecha (Y-m-d) de transacción */
    private array $blueRates = [];

    /** @var list<array{string, float}>|null serie blue completa [fecha, venta], ascendente */
    private ?array $blueSeries = null;

    private ?float $referenceRate = null;

    private bool $referenceRateResolved = false;

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
            $rate = $frozenRate ?? $this->blueRateOn($date);

            if ($rate === null) {
                return null;
            }

            $ars = $amount * $rate;
        }

        if ($this->tiempo === 'real') {
            $ars *= $this->inflationFactor($date);
        }

        if ($this->fx === 'ars') {
            return $ars;
        }

        $referenceRate = $this->referenceRate();

        return $referenceRate === null || $referenceRate <= 0 ? null : $ars / $referenceRate;
    }

    public function currency(): string
    {
        return $this->fx === 'ars' ? 'ARS' : 'USD';
    }

    /**
     * La cotización de referencia es una sola para todo el reporte: se
     * resuelve la primera vez (con la maquinaria de siempre, que puede
     * traer el dato del día) y se reusa.
     */
    private function referenceRate(): ?float
    {
        if (! $this->referenceRateResolved) {
            $this->referenceRate = MarketData::rate($this->fx, $this->reference);
            $this->referenceRateResolved = true;
        }

        return $this->referenceRate;
    }

    /**
     * El factor de IPC solo depende del mes de origen (la referencia es fija).
     */
    private function inflationFactor(CarbonInterface $date): float
    {
        return $this->inflationFactors[$date->format('Y-m')]
            ??= InflationRate::factorBetween($date, $this->reference);
    }

    /**
     * Cotización blue del día de la transacción, contra la serie precargada:
     * la última conocida hasta ese día o, si la serie empieza después, la
     * primera que haya — el mismo criterio de ExchangeRate::quoteOn(). Solo
     * si el dato más cercano quedó a más de una semana (o no hay serie) se
     * delega en MarketData::rate(), que puede traer el día puntual de la API.
     */
    private function blueRateOn(CarbonInterface $date): ?float
    {
        $key = $date->toDateString();

        if (array_key_exists($key, $this->blueRates)) {
            return $this->blueRates[$key];
        }

        $nearest = $this->nearestBlueQuote($key);

        if ($nearest !== null && abs(Carbon::parse($nearest[0])->diffInDays($date)) <= 7) {
            return $this->blueRates[$key] = $nearest[1];
        }

        return $this->blueRates[$key] = MarketData::rate('blue', $date);
    }

    /**
     * @return array{string, float}|null
     */
    private function nearestBlueQuote(string $date): ?array
    {
        $series = $this->blueSeries ??= ExchangeRate::query()
            ->where('rate_type', 'blue')
            ->orderBy('quoted_on')
            ->get(['quoted_on', 'sell'])
            ->map(fn (ExchangeRate $quote) => [$quote->quoted_on->toDateString(), (float) $quote->sell])
            ->all();

        if ($series === []) {
            return null;
        }

        // Última cotización con fecha <= $date, por búsqueda binaria (las
        // fechas ISO comparan bien como strings). Si no hay, la primera.
        $lo = 0;
        $hi = count($series) - 1;
        $best = null;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);

            if ($series[$mid][0] <= $date) {
                $best = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $series[$best ?? 0];
    }
}
