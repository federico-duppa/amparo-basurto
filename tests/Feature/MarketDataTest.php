<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Support\MarketData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_comando_sincroniza_cotizaciones_e_inflacion(): void
    {
        Http::fake([
            '*/cotizaciones/dolares/blue' => Http::response([
                ['casa' => 'blue', 'fecha' => '2026-07-01', 'compra' => 1180, 'venta' => 1200],
                ['casa' => 'blue', 'fecha' => '2026-07-02', 'compra' => 1190, 'venta' => 1210],
            ]),
            '*/cotizaciones/dolares/oficial' => Http::response([
                ['casa' => 'oficial', 'fecha' => '2026-07-01', 'compra' => 980, 'venta' => 1000],
            ]),
            '*/cotizaciones/dolares/bolsa' => Http::response([
                ['casa' => 'bolsa', 'fecha' => '2026-07-01', 'compra' => 1080, 'venta' => 1100],
            ]),
            '*/finanzas/indices/inflacion' => Http::response([
                ['fecha' => '2026-05-31', 'valor' => 4.2],
                ['fecha' => '2026-06-30', 'valor' => 3.9],
            ]),
        ]);

        $this->artisan('plata:mercado')->assertSuccessful();

        $this->assertDatabaseHas('exchange_rates', ['rate_type' => 'blue', 'quoted_on' => '2026-07-02', 'sell' => '1210.0000']);
        $this->assertDatabaseHas('exchange_rates', ['rate_type' => 'oficial', 'quoted_on' => '2026-07-01', 'sell' => '1000.0000']);
        $this->assertDatabaseHas('exchange_rates', ['rate_type' => 'mep', 'quoted_on' => '2026-07-01', 'sell' => '1100.0000']);
        $this->assertDatabaseHas('inflation_rates', ['period' => '2026-05-01', 'monthly_pct' => '4.2000']);
        $this->assertDatabaseHas('inflation_rates', ['period' => '2026-06-01', 'monthly_pct' => '3.9000']);
    }

    public function test_sin_conexion_cae_al_ultimo_valor_conocido(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        ExchangeRate::create([
            'rate_type' => 'blue',
            'quoted_on' => now()->subDays(10)->toDateString(),
            'sell' => 950,
        ]);

        $this->assertEqualsWithDelta(950.0, MarketData::rate('blue', now()), 0.001);
    }

    public function test_si_falta_el_dato_del_dia_lo_trae_de_la_api_y_lo_guarda(): void
    {
        Http::fake([
            '*/cotizaciones/dolares/blue/*' => Http::response(['casa' => 'blue', 'fecha' => now()->toDateString(), 'compra' => 1280, 'venta' => 1300]),
        ]);

        $this->assertEqualsWithDelta(1300.0, MarketData::rate('blue', now()), 0.001);

        $this->assertTrue(
            ExchangeRate::where('rate_type', 'blue')
                ->whereDate('quoted_on', now()->toDateString())
                ->where('sell', 1300)
                ->exists()
        );
    }

    public function test_sin_dato_ni_conexion_devuelve_null(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        $this->assertNull(MarketData::rate('blue', now()));
    }
}
