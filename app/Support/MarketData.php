<?php

namespace App\Support;

use App\Models\ExchangeRate;
use App\Models\InflationRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cotizaciones (blue / oficial / MEP) e inflación (IPC) desde argentinadatos.com,
 * guardadas en la base como cache. Si la API no responde, se cae al último
 * valor conocido — nunca se bloquea al usuario por estar offline.
 */
final class MarketData
{
    private const BASE = 'https://api.argentinadatos.com/v1';

    /** casa de argentinadatos.com para cada tipo de cotización */
    private const CASAS = ['blue' => 'blue', 'oficial' => 'oficial', 'mep' => 'bolsa'];

    /** Cuando una consulta a la API falla, se deja de insistir por un rato. */
    private const OFFLINE_CACHE_KEY = 'plata.mercado.offline';

    /**
     * Cotización de venta para una fecha. Usa la serie guardada; si el dato
     * más cercano quedó lejos, intenta traer el del día y, si no puede,
     * devuelve el último conocido.
     */
    public static function rate(string $type, CarbonInterface $date): ?float
    {
        $quote = ExchangeRate::quoteOn($type, $date);

        if ($quote !== null && abs($quote->quoted_on->diffInDays($date)) <= 7) {
            return (float) $quote->sell;
        }

        self::fetchDay($type, $date);

        $quote = ExchangeRate::quoteOn($type, $date);

        return $quote === null ? null : (float) $quote->sell;
    }

    /**
     * Trae la serie completa de cotizaciones y de inflación. Pensado para el
     * comando programado; las consultas del día a día usan rate().
     */
    public static function sync(): void
    {
        foreach (self::CASAS as $type => $casa) {
            $quotes = Http::timeout(30)->get(self::BASE."/cotizaciones/dolares/{$casa}")->throw()->json();

            collect($quotes)
                ->filter(fn (array $quote) => ! empty($quote['venta']) && ! empty($quote['fecha']))
                ->map(fn (array $quote) => [
                    'rate_type' => $type,
                    'quoted_on' => $quote['fecha'],
                    'buy' => $quote['compra'] ?? null,
                    'sell' => $quote['venta'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->chunk(500)
                ->each(fn ($chunk) => ExchangeRate::upsert(
                    $chunk->values()->all(),
                    ['rate_type', 'quoted_on'],
                    ['buy', 'sell', 'updated_at'],
                ));
        }

        self::syncInflation();
    }

    public static function syncInflation(): void
    {
        $series = Http::timeout(30)->get(self::BASE.'/finanzas/indices/inflacion')->throw()->json();

        collect($series)
            ->filter(fn (array $row) => isset($row['valor'], $row['fecha']))
            ->map(fn (array $row) => [
                'period' => Carbon::parse($row['fecha'])->startOfMonth()->toDateString(),
                'monthly_pct' => $row['valor'],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->unique('period')
            ->chunk(500)
            ->each(fn ($chunk) => InflationRate::upsert(
                $chunk->values()->all(),
                ['period'],
                ['monthly_pct', 'updated_at'],
            ));
    }

    private static function fetchDay(string $type, CarbonInterface $date): void
    {
        if (Cache::get(self::OFFLINE_CACHE_KEY, false)) {
            return;
        }

        try {
            $quote = Http::timeout(5)
                ->get(self::BASE.'/cotizaciones/dolares/'.self::CASAS[$type].'/'.$date->format('Y/m/d'))
                ->throw()
                ->json();

            if (! empty($quote['venta'])) {
                ExchangeRate::updateOrCreate(
                    ['rate_type' => $type, 'quoted_on' => $date->toDateString()],
                    ['buy' => $quote['compra'] ?? null, 'sell' => $quote['venta']],
                );
            }
        } catch (\Throwable) {
            Cache::put(self::OFFLINE_CACHE_KEY, true, now()->addMinutes(5));
        }
    }
}
