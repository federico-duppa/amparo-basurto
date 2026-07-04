<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class InflationRate extends Model
{
    protected $fillable = [
        'period',
        'monthly_pct',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'monthly_pct' => 'decimal:4',
        ];
    }

    /**
     * Factor para re-expresar pesos del mes de $from en pesos del mes de $to,
     * componiendo la inflación mensual de los meses intermedios. Los meses sin
     * dato cuentan como 0% (fallback al último valor conocido).
     */
    public static function factorBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        $from = $from->toImmutable()->startOfMonth();
        $to = $to->toImmutable()->startOfMonth();

        if ($from->equalTo($to)) {
            return 1.0;
        }

        $inverted = $from->greaterThan($to);
        [$start, $end] = $inverted ? [$to, $from] : [$from, $to];

        $factor = static::whereDate('period', '>', $start)
            ->whereDate('period', '<=', $end)
            ->pluck('monthly_pct')
            ->reduce(fn (float $carry, $pct) => $carry * (1 + (float) $pct / 100), 1.0);

        return $inverted ? 1 / $factor : $factor;
    }
}
