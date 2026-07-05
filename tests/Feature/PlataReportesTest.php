<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\InflationRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PlataReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba sale a la red: la API de mercado responde vacío.
        Http::fake();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_la_pagina_de_reportes_renderiza_el_componente(): void
    {
        $this->get('/plata/reportes')
            ->assertOk()
            ->assertSeeLivewire('plata.reportes');
    }

    public function test_muestra_el_estado_vacio_con_la_voz_de_amparo(): void
    {
        $this->get('/plata/reportes')
            ->assertSee('Todavía no hay gastos para mirar.');
    }

    public function test_agrupa_por_categoria_en_pesos_nominales(): void
    {
        Expense::factory()->for($this->user)->create(['category' => 'Comida', 'amount' => 1000, 'spent_on' => now()]);
        Expense::factory()->for($this->user)->create(['category' => 'Comida', 'amount' => 2000, 'spent_on' => now()]);
        Expense::factory()->for($this->user)->create(['category' => 'Transporte', 'amount' => 500, 'spent_on' => now()]);

        Livewire::test('plata.reportes')
            ->assertSee('Comida')
            ->assertSee('$3.000,00')
            ->assertSee('Transporte')
            ->assertSee('$3.500,00'); // total
    }

    public function test_un_gasto_en_dolares_entra_al_reporte_con_su_cotizacion_congelada(): void
    {
        Expense::factory()->for($this->user)->create([
            'category' => 'Vacaciones',
            'amount' => 60,
            'currency' => 'USD',
            'rate_ars' => 1000,
            'rate_source' => 'blue',
            'spent_on' => now(),
        ]);

        Livewire::test('plata.reportes')
            ->assertSee('$60.000,00');
    }

    public function test_el_lente_en_dolares_convierte_a_la_cotizacion_de_hoy(): void
    {
        ExchangeRate::create(['rate_type' => 'blue', 'quoted_on' => now()->toDateString(), 'sell' => 1200]);

        Expense::factory()->for($this->user)->create(['amount' => 120000, 'currency' => 'ARS', 'spent_on' => now()]);

        Livewire::test('plata.reportes')
            ->set('fx', 'blue')
            ->assertSee('US$100,00');
    }

    public function test_el_lente_real_ajusta_por_ipc_en_espacio_ars(): void
    {
        // Gasto de hace dos meses; 10% de inflación en cada mes siguiente.
        InflationRate::create(['period' => now()->subMonth()->startOfMonth()->toDateString(), 'monthly_pct' => 10]);
        InflationRate::create(['period' => now()->startOfMonth()->toDateString(), 'monthly_pct' => 10]);

        Expense::factory()->for($this->user)->create([
            'amount' => 100000,
            'currency' => 'ARS',
            'spent_on' => now()->subMonths(2),
        ]);

        Livewire::test('plata.reportes')
            ->set('tiempo', 'real')
            ->assertSee('$121.000,00');
    }

    public function test_fx_e_inflacion_son_ejes_separados(): void
    {
        // Mostrar en USD no ajusta por inflación: mismo gasto, lente USD-blue
        // nominal usa solo cotizaciones, sin tocar el IPC.
        InflationRate::create(['period' => now()->startOfMonth()->toDateString(), 'monthly_pct' => 50]);
        ExchangeRate::create(['rate_type' => 'blue', 'quoted_on' => now()->toDateString(), 'sell' => 1000]);

        Expense::factory()->for($this->user)->create(['amount' => 50000, 'currency' => 'ARS', 'spent_on' => now()->subMonth()]);

        Livewire::test('plata.reportes')
            ->set('fx', 'blue')
            ->set('tiempo', 'nominal')
            ->assertSee('US$50,00');
    }

    public function test_avisa_cuando_no_puede_convertir_gastos(): void
    {
        // Gasto en USD sin snapshot y sin serie de cotizaciones.
        Expense::factory()->for($this->user)->create([
            'amount' => 60,
            'currency' => 'USD',
            'rate_ars' => null,
            'spent_on' => now(),
        ]);

        Livewire::test('plata.reportes')
            ->assertSee('Dejé afuera un gasto porque me faltan cotizaciones');
    }

    public function test_un_lente_invalido_cae_al_lente_por_defecto(): void
    {
        Expense::factory()->for($this->user)->create(['amount' => 1000, 'currency' => 'ARS', 'spent_on' => now()]);

        Livewire::test('plata.reportes')
            ->set('fx', 'euro-oficial')
            ->set('tiempo', 'cuantico')
            ->assertSee('$1.000,00');
    }

    public function test_el_reporte_no_consulta_cotizaciones_ni_ipc_por_cada_gasto(): void
    {
        InflationRate::create(['period' => now()->subMonth()->startOfMonth()->toDateString(), 'monthly_pct' => 10]);
        InflationRate::create(['period' => now()->startOfMonth()->toDateString(), 'monthly_pct' => 10]);
        ExchangeRate::create(['rate_type' => 'blue', 'quoted_on' => now()->toDateString(), 'sell' => 1000]);

        // El lente resuelve la referencia una vez, memoiza el IPC por mes y
        // precarga la serie blue: más gastos (en los mismos meses) no pueden
        // significar más consultas.
        $consultas = function (int $gastos): int {
            Expense::query()->delete();

            foreach (range(1, $gastos) as $i) {
                Expense::factory()->for($this->user)->create([
                    'amount' => 1000,
                    'currency' => $i % 2 === 0 ? 'ARS' : 'USD',
                    'rate_ars' => $i % 2 === 0 ? null : 1000,
                    'spent_on' => now()->subMonths($i % 3)->format('Y-m-d'),
                ]);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test('plata.reportes')
                ->set('fx', 'blue')
                ->set('tiempo', 'real');
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->assertSame($consultas(3), $consultas(30));
    }

    public function test_no_ve_los_gastos_de_otros_usuarios(): void
    {
        Expense::factory()->create(['category' => 'Categoría ajena', 'spent_on' => now()]);
        Expense::factory()->for($this->user)->create(['category' => 'Categoría propia', 'spent_on' => now()]);

        Livewire::test('plata.reportes')
            ->assertSee('Categoría propia')
            ->assertDontSee('Categoría ajena');
    }
}
