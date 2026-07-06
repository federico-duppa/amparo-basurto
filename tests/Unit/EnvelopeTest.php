<?php

namespace Tests\Unit;

use App\Models\Envelope;
use App\Models\EnvelopeMovement;
use App\Models\Expense;
use App\Models\InflationRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_balance_resta_salidas_y_gastos_de_las_entradas(): void
    {
        $envelope = Envelope::factory()->create(['target_amount' => null]);

        EnvelopeMovement::factory()->for($envelope)->create(['amount' => 1000]);
        EnvelopeMovement::factory()->for($envelope)->retiro()->create(['amount' => 200]);
        Expense::factory()->create(['envelope_id' => $envelope->id, 'amount' => 300]);

        $this->assertEqualsWithDelta(500.0, $envelope->balance(), 0.001);
    }

    public function test_current_target_null_sin_objetivo(): void
    {
        $envelope = Envelope::factory()->create(['target_amount' => null]);

        $this->assertNull($envelope->currentTarget());
    }

    public function test_current_target_nominal_no_se_indexa(): void
    {
        $envelope = Envelope::factory()->create(['indexed' => false, 'target_amount' => 1000]);

        InflationRate::create(['period' => '2026-06-01', 'monthly_pct' => 50]);

        $this->assertEqualsWithDelta(1000.0, $envelope->currentTarget(), 0.001);
    }

    public function test_current_target_indexado_ajusta_por_ipc_desde_el_mes_base(): void
    {
        $envelope = Envelope::factory()->indexado()->create([
            'target_amount' => 1000,
            'target_month' => '2026-05-01',
        ]);

        InflationRate::create(['period' => '2026-06-01', 'monthly_pct' => 10]);
        InflationRate::create(['period' => '2026-07-01', 'monthly_pct' => 5]);

        // 1000 * 1.10 * 1.05 = 1155.
        $this->assertEqualsWithDelta(1155.0, $envelope->currentTarget(), 0.01);
    }

    public function test_current_target_se_baja_por_los_pagos_que_lo_cumplen(): void
    {
        $envelope = Envelope::factory()->create(['indexed' => false, 'target_amount' => 1000]);

        Expense::factory()->create(['envelope_id' => $envelope->id, 'amount' => 200, 'reduces_target' => true]);
        Expense::factory()->create(['envelope_id' => $envelope->id, 'amount' => 999, 'reduces_target' => false]);

        $this->assertEqualsWithDelta(800.0, $envelope->currentTarget(), 0.001);
    }

    public function test_gap_null_sin_objetivo(): void
    {
        $envelope = Envelope::factory()->create(['target_amount' => null]);

        $this->assertNull($envelope->gap());
    }

    public function test_gap_calcula_lo_que_falta_para_el_objetivo(): void
    {
        $envelope = Envelope::factory()->create(['indexed' => false, 'target_amount' => 1000]);

        EnvelopeMovement::factory()->for($envelope)->create(['amount' => 300]);

        $this->assertEqualsWithDelta(700.0, $envelope->gap(), 0.001);
    }

    public function test_gap_no_es_negativo_si_ya_se_supero_el_objetivo(): void
    {
        $envelope = Envelope::factory()->create(['indexed' => false, 'target_amount' => 1000]);

        EnvelopeMovement::factory()->for($envelope)->create(['amount' => 1500]);

        $this->assertEqualsWithDelta(0.0, $envelope->gap(), 0.001);
    }

    public function test_progress_null_sin_objetivo(): void
    {
        $envelope = Envelope::factory()->create(['target_amount' => null]);

        $this->assertNull($envelope->progress());
    }

    public function test_progress_null_con_objetivo_en_cero(): void
    {
        $envelope = Envelope::factory()->create(['indexed' => false, 'target_amount' => 0]);

        $this->assertNull($envelope->progress());
    }

    public function test_progress_calcula_el_porcentaje_contra_el_objetivo(): void
    {
        $envelope = Envelope::factory()->create(['indexed' => false, 'target_amount' => 1000]);

        EnvelopeMovement::factory()->for($envelope)->create(['amount' => 250]);

        $this->assertEqualsWithDelta(25.0, $envelope->progress(), 0.001);
    }

    public function test_progress_no_es_negativo_con_saldo_en_rojo(): void
    {
        $envelope = Envelope::factory()->create(['indexed' => false, 'target_amount' => 1000]);

        Expense::factory()->create(['envelope_id' => $envelope->id, 'amount' => 500]);

        $this->assertEqualsWithDelta(0.0, $envelope->progress(), 0.001);
    }
}
