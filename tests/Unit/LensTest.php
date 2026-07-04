<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use App\Models\InflationRate;
use App\Support\Lens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_ars_nominal_devuelve_el_monto_tal_cual(): void
    {
        $lens = new Lens('ars', 'nominal', now());

        $this->assertEqualsWithDelta(1500.0, $lens->value(1500, 'ARS', now()->subMonth()), 0.001);
    }

    public function test_usd_a_ars_usa_la_cotizacion_congelada_de_la_transaccion(): void
    {
        $lens = new Lens('ars', 'nominal', now());

        $this->assertEqualsWithDelta(60000.0, $lens->value(60, 'USD', now()->subMonth(), frozenRate: 1000), 0.001);
    }

    public function test_el_orden_importa_primero_ars_despues_ipc_despues_fx(): void
    {
        // 10 USD gastados a cotización congelada 500; un mes con 100% de
        // inflación; blue de hoy a 1000. ARS: 5.000 → real: 10.000 → USD de
        // hoy: 10. El poder de compra en dólares se mantuvo.
        InflationRate::create(['period' => now()->startOfMonth()->toDateString(), 'monthly_pct' => 100]);
        ExchangeRate::create(['rate_type' => 'blue', 'quoted_on' => now()->toDateString(), 'sell' => 1000]);

        $lens = new Lens('blue', 'real', now());

        $this->assertEqualsWithDelta(10.0, $lens->value(10, 'USD', now()->subMonth(), frozenRate: 500), 0.001);
    }

    public function test_usd_nominal_revalua_sin_tocar_el_ipc(): void
    {
        // El mismo gasto de 10 USD, lente USD-blue nominal: 5.000 ARS de aquel
        // día valen 5 USD al blue de hoy. La inflación no participa.
        InflationRate::create(['period' => now()->startOfMonth()->toDateString(), 'monthly_pct' => 100]);
        ExchangeRate::create(['rate_type' => 'blue', 'quoted_on' => now()->toDateString(), 'sell' => 1000]);

        $lens = new Lens('blue', 'nominal', now());

        $this->assertEqualsWithDelta(5.0, $lens->value(10, 'USD', now()->subMonth(), frozenRate: 500), 0.001);
    }

    public function test_sin_cotizacion_imprescindible_devuelve_null(): void
    {
        $lens = new Lens('ars', 'nominal', now());

        $this->assertNull($lens->value(10, 'USD', now()->subMonth()));

        $lensUsd = new Lens('blue', 'nominal', now());

        $this->assertNull($lensUsd->value(1000, 'ARS', now()->subMonth()));
    }

    public function test_usa_el_tipo_de_cotizacion_del_lente_en_la_referencia(): void
    {
        ExchangeRate::create(['rate_type' => 'blue', 'quoted_on' => now()->toDateString(), 'sell' => 1200]);
        ExchangeRate::create(['rate_type' => 'oficial', 'quoted_on' => now()->toDateString(), 'sell' => 1000]);

        $blue = new Lens('blue', 'nominal', now());
        $oficial = new Lens('oficial', 'nominal', now());

        $this->assertEqualsWithDelta(100.0, $blue->value(120000, 'ARS', now()), 0.001);
        $this->assertEqualsWithDelta(120.0, $oficial->value(120000, 'ARS', now()), 0.001);
    }
}
