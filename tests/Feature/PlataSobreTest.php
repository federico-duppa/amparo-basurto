<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\EnvelopeMovement;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\InflationRate;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PlataSobreTest extends TestCase
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

    private function sobre(array $attributes = []): Envelope
    {
        return Envelope::factory()->for($this->user)->create($attributes + ['target_amount' => null]);
    }

    public function test_la_pagina_del_sobre_renderiza_el_componente(): void
    {
        $sobre = $this->sobre(['name' => 'Vacaciones']);

        $this->get('/plata/sobres/'.$sobre->id)
            ->assertOk()
            ->assertSeeLivewire('plata.sobre')
            ->assertSee('Vacaciones');
    }

    public function test_un_sobre_ajeno_responde_404(): void
    {
        $ajeno = Envelope::factory()->create();

        $this->get('/plata/sobres/'.$ajeno->id)->assertNotFound();
    }

    public function test_un_sobre_inexistente_responde_404(): void
    {
        $this->get('/plata/sobres/99999')->assertNotFound();
    }

    public function test_puede_aportar_y_el_saldo_emerge_de_los_movimientos(): void
    {
        $sobre = $this->sobre();

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->set('movementAmount', '50000')
            ->set('movementDate', now()->format('Y-m-d'))
            ->call('aporte')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('envelope_movements', [
            'envelope_id' => $sobre->id,
            'user_id' => $this->user->id,
            'type' => EnvelopeMovement::APORTE,
            'amount' => '50000.00',
            'currency' => $sobre->currency,
        ]);

        $this->assertEqualsWithDelta(50000.0, $sobre->fresh()->balance(), 0.001);
    }

    public function test_puede_retirar_si_alcanza_el_saldo(): void
    {
        $sobre = $this->sobre();
        EnvelopeMovement::factory()->for($this->user)->for($sobre)->create(['amount' => 80000]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->set('movementAmount', '30000')
            ->set('movementDate', now()->format('Y-m-d'))
            ->call('retiro')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(50000.0, $sobre->fresh()->balance(), 0.001);
    }

    public function test_no_deja_retirar_mas_de_lo_que_hay(): void
    {
        $sobre = $this->sobre();
        EnvelopeMovement::factory()->for($this->user)->for($sobre)->create(['amount' => 10000]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->set('movementAmount', '20000')
            ->set('movementDate', now()->format('Y-m-d'))
            ->call('retiro')
            ->assertHasErrors(['movementAmount']);

        $this->assertEqualsWithDelta(10000.0, $sobre->fresh()->balance(), 0.001);
    }

    public function test_no_puede_operar_sobre_un_sobre_ajeno(): void
    {
        $ajeno = Envelope::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('plata.sobre', ['envelope' => $ajeno->id]);
    }

    public function test_puede_transferir_entre_sobres_de_la_misma_moneda(): void
    {
        $ahorro = $this->sobre(['name' => 'Aguinaldo']);
        $gasto = Envelope::factory()->gasto()->for($this->user)->create(['name' => 'Vacaciones', 'currency' => 'ARS']);
        EnvelopeMovement::factory()->for($this->user)->for($ahorro)->create(['amount' => 200000]);

        Livewire::test('plata.sobre', ['envelope' => $ahorro->id])
            ->set('transferAmount', '150000')
            ->set('transferTo', (string) $gasto->id)
            ->call('transfer')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(50000.0, $ahorro->fresh()->balance(), 0.001);
        $this->assertEqualsWithDelta(150000.0, $gasto->fresh()->balance(), 0.001);

        $out = $ahorro->movements()->where('type', EnvelopeMovement::TRANSFER_OUT)->first();
        $in = $gasto->movements()->where('type', EnvelopeMovement::TRANSFER_IN)->first();

        $this->assertNotNull($out);
        $this->assertNotNull($in);
        $this->assertSame($out->transfer_group, $in->transfer_group);
        $this->assertNull($in->exchange_rate);
    }

    public function test_la_transferencia_entre_monedas_convierte_a_la_cotizacion_del_dia(): void
    {
        ExchangeRate::create([
            'rate_type' => 'blue',
            'quoted_on' => now()->toDateString(),
            'sell' => 1000,
        ]);

        $ahorro = $this->sobre(['currency' => 'USD']);
        $gasto = Envelope::factory()->gasto()->for($this->user)->create(['currency' => 'ARS']);
        EnvelopeMovement::factory()->for($this->user)->for($ahorro)->create(['amount' => 500, 'currency' => 'USD']);

        Livewire::test('plata.sobre', ['envelope' => $ahorro->id])
            ->set('transferAmount', '100')
            ->set('transferTo', (string) $gasto->id)
            ->call('transfer')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(400.0, $ahorro->fresh()->balance(), 0.001);
        $this->assertEqualsWithDelta(100000.0, $gasto->fresh()->balance(), 0.001);

        $in = $gasto->movements()->where('type', EnvelopeMovement::TRANSFER_IN)->first();
        $this->assertSame('1000.0000', $in->exchange_rate);
    }

    public function test_sin_cotizacion_la_transferencia_entre_monedas_no_se_hace(): void
    {
        // Http::fake() del setUp: no hay serie guardada ni API que responda.
        $ahorro = $this->sobre(['currency' => 'USD']);
        Envelope::factory()->gasto()->for($this->user)->create(['currency' => 'ARS']);
        $destino = $this->user->envelopes()->where('currency', 'ARS')->first();
        EnvelopeMovement::factory()->for($this->user)->for($ahorro)->create(['amount' => 500, 'currency' => 'USD']);

        Livewire::test('plata.sobre', ['envelope' => $ahorro->id])
            ->set('transferAmount', '100')
            ->set('transferTo', (string) $destino->id)
            ->call('transfer')
            ->assertHasErrors(['transferAmount']);

        $this->assertDatabaseMissing('envelope_movements', ['type' => EnvelopeMovement::TRANSFER_OUT]);
    }

    public function test_no_puede_transferir_a_un_sobre_ajeno(): void
    {
        $propio = $this->sobre();
        EnvelopeMovement::factory()->for($this->user)->for($propio)->create(['amount' => 10000]);
        $ajeno = Envelope::factory()->create(['currency' => 'ARS']);

        try {
            Livewire::test('plata.sobre', ['envelope' => $propio->id])
                ->set('transferAmount', '1000')
                ->set('transferTo', (string) $ajeno->id)
                ->call('transfer');
            $this->fail('No debería poder transferirse a un sobre ajeno.');
        } catch (ModelNotFoundException) {
            // esperado: para este usuario ese sobre no existe
        }

        $this->assertDatabaseMissing('envelope_movements', ['type' => EnvelopeMovement::TRANSFER_OUT]);
    }

    public function test_el_objetivo_indexado_se_reexpresa_con_el_ipc_y_muestra_la_brecha(): void
    {
        // Objetivo de $100.000 anclado dos meses atrás; 10% de inflación en
        // cada mes siguiente: la vara de hoy es $121.000.
        InflationRate::create(['period' => now()->subMonth()->startOfMonth()->toDateString(), 'monthly_pct' => 10]);
        InflationRate::create(['period' => now()->startOfMonth()->toDateString(), 'monthly_pct' => 10]);

        $sobre = Envelope::factory()->indexado()->for($this->user)->create([
            'target_amount' => 100000,
            'target_month' => now()->subMonths(2)->startOfMonth()->toDateString(),
        ]);
        EnvelopeMovement::factory()->for($this->user)->for($sobre)->create(['amount' => 100000]);

        $this->assertEqualsWithDelta(121000.0, $sobre->currentTarget(), 0.001);
        $this->assertEqualsWithDelta(21000.0, $sobre->gap(), 0.001);

        $this->get('/plata/sobres/'.$sobre->id)
            ->assertSee('121.000,00')
            ->assertSee('Para mantener el poder de compra te falta aportar')
            ->assertSee('21.000,00');
    }

    public function test_el_saldo_guardado_es_siempre_nominal_aunque_el_sobre_este_indexado(): void
    {
        InflationRate::create(['period' => now()->startOfMonth()->toDateString(), 'monthly_pct' => 25]);

        $sobre = Envelope::factory()->indexado()->for($this->user)->create([
            'target_month' => now()->subMonth()->startOfMonth()->toDateString(),
        ]);
        EnvelopeMovement::factory()->for($this->user)->for($sobre)->create(['amount' => 80000]);

        // El saldo no se "infla" solo: son los pesos que hay, punto.
        $this->assertEqualsWithDelta(80000.0, $sobre->balance(), 0.001);
    }

    public function test_puede_eliminar_un_movimiento_y_una_transferencia_se_borra_entera(): void
    {
        $origen = $this->sobre();
        $destino = Envelope::factory()->gasto()->for($this->user)->create(['currency' => 'ARS']);
        EnvelopeMovement::factory()->for($this->user)->for($origen)->create(['amount' => 100000]);

        Livewire::test('plata.sobre', ['envelope' => $origen->id])
            ->set('transferAmount', '40000')
            ->set('transferTo', (string) $destino->id)
            ->call('transfer')
            ->assertHasNoErrors();

        $out = $origen->movements()->where('type', EnvelopeMovement::TRANSFER_OUT)->first();

        Livewire::test('plata.sobre', ['envelope' => $origen->id])
            ->call('deleteMovement', $out->id);

        $this->assertDatabaseMissing('envelope_movements', ['type' => EnvelopeMovement::TRANSFER_OUT]);
        $this->assertDatabaseMissing('envelope_movements', ['type' => EnvelopeMovement::TRANSFER_IN]);
        $this->assertEqualsWithDelta(100000.0, $origen->fresh()->balance(), 0.001);
        $this->assertEqualsWithDelta(0.0, $destino->fresh()->balance(), 0.001);
    }

    public function test_no_puede_eliminar_movimientos_de_sobres_ajenos(): void
    {
        $propio = $this->sobre();
        $movimientoAjeno = EnvelopeMovement::factory()->create();

        try {
            Livewire::test('plata.sobre', ['envelope' => $propio->id])
                ->call('deleteMovement', $movimientoAjeno->id);
            $this->fail('Un movimiento ajeno no debería poder eliminarse.');
        } catch (ModelNotFoundException) {
            // esperado
        }

        $this->assertModelExists($movimientoAjeno);
    }

    public function test_puede_editar_un_aporte(): void
    {
        $sobre = $this->sobre();
        $mov = EnvelopeMovement::factory()->for($this->user)->for($sobre)->create([
            'amount' => 50000,
            'moved_on' => now()->subMonth()->format('Y-m-d'),
            'note' => 'Sueldo',
        ]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('startEditingMovement', $mov->id)
            ->assertSet('editingMovementId', $mov->id)
            ->assertSet('editMovementNote', 'Sueldo')
            ->set('editMovementAmount', '70000')
            ->set('editMovementNote', 'Aguinaldo')
            ->call('updateMovement')
            ->assertHasNoErrors()
            ->assertSet('editingMovementId', null);

        $mov->refresh();
        $this->assertSame('70000.00', $mov->amount);
        $this->assertSame('Aguinaldo', $mov->note);
        $this->assertEqualsWithDelta(70000.0, $sobre->fresh()->balance(), 0.001);
    }

    public function test_editar_un_retiro_no_puede_dejar_el_sobre_en_rojo(): void
    {
        $sobre = $this->sobre();
        EnvelopeMovement::factory()->for($this->user)->for($sobre)->create(['amount' => 100000]);
        $retiro = EnvelopeMovement::factory()->for($this->user)->for($sobre)->retiro()->create(['amount' => 30000]);

        // Hay $70.000 de saldo; sin este retiro habría $100.000. Sacar $120.000 no entra.
        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('startEditingMovement', $retiro->id)
            ->set('editMovementAmount', '120000')
            ->call('updateMovement')
            ->assertHasErrors(['editMovementAmount']);

        $this->assertSame('30000.00', $retiro->fresh()->amount);
    }

    public function test_no_permite_editar_una_transferencia(): void
    {
        $origen = $this->sobre();
        $destino = Envelope::factory()->gasto()->for($this->user)->create(['currency' => 'ARS']);
        EnvelopeMovement::factory()->for($this->user)->for($origen)->create(['amount' => 100000]);

        Livewire::test('plata.sobre', ['envelope' => $origen->id])
            ->set('transferAmount', '40000')
            ->set('transferTo', (string) $destino->id)
            ->call('transfer');

        $out = $origen->movements()->where('type', EnvelopeMovement::TRANSFER_OUT)->first();

        Livewire::test('plata.sobre', ['envelope' => $origen->id])
            ->call('startEditingMovement', $out->id)
            ->assertSet('editingMovementId', null);
    }

    public function test_no_puede_editar_movimientos_de_sobres_ajenos(): void
    {
        $propio = $this->sobre();
        $movimientoAjeno = EnvelopeMovement::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('plata.sobre', ['envelope' => $propio->id])
            ->call('startEditingMovement', $movimientoAjeno->id);
    }

    public function test_puede_editar_el_objetivo_de_un_sobre(): void
    {
        $sobre = $this->sobre(['target_amount' => 100000]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('startEditingTarget')
            ->assertSet('editingTarget', true)
            ->assertSet('targetAmountInput', '100000')
            ->set('targetAmountInput', '250000')
            ->call('updateTarget')
            ->assertHasNoErrors()
            ->assertSet('editingTarget', false);

        $this->assertSame('250000.00', $sobre->fresh()->target_amount);
    }

    public function test_puede_ponerle_un_objetivo_a_un_sobre_que_no_tenia(): void
    {
        $sobre = $this->sobre(['target_amount' => null]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('startEditingTarget')
            ->assertSet('targetAmountInput', '')
            ->set('targetAmountInput', '80000')
            ->call('updateTarget')
            ->assertHasNoErrors();

        $this->assertSame('80000.00', $sobre->fresh()->target_amount);
    }

    public function test_puede_sacarle_el_objetivo_a_un_sobre_nominal(): void
    {
        $sobre = $this->sobre(['target_amount' => 100000]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('startEditingTarget')
            ->set('targetAmountInput', '')
            ->call('updateTarget')
            ->assertHasNoErrors();

        $this->assertNull($sobre->fresh()->target_amount);
    }

    public function test_no_deja_un_objetivo_invalido(): void
    {
        $sobre = $this->sobre(['target_amount' => 100000]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('startEditingTarget')
            ->set('targetAmountInput', '-5')
            ->call('updateTarget')
            ->assertHasErrors(['targetAmountInput']);

        $this->assertSame('100000.00', $sobre->fresh()->target_amount);
    }

    public function test_no_puede_dejar_sin_objetivo_un_sobre_indexado(): void
    {
        $sobre = Envelope::factory()->indexado()->for($this->user)->create([
            'target_amount' => 100000,
            'target_month' => now()->subMonths(3)->startOfMonth()->toDateString(),
        ]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('startEditingTarget')
            ->set('targetAmountInput', '')
            ->call('updateTarget')
            ->assertHasErrors(['targetAmountInput']);

        $this->assertSame('100000.00', $sobre->fresh()->target_amount);
    }

    public function test_editar_el_objetivo_de_un_sobre_indexado_reancla_el_mes_base(): void
    {
        $sobre = Envelope::factory()->indexado()->for($this->user)->create([
            'target_amount' => 100000,
            'target_month' => now()->subMonths(3)->startOfMonth()->toDateString(),
        ]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('startEditingTarget')
            ->set('targetAmountInput', '150000')
            ->call('updateTarget')
            ->assertHasNoErrors();

        $sobre->refresh();
        $this->assertSame('150000.00', $sobre->target_amount);
        // El monto es "en pesos de hoy": el mes base vuelve a arrancar desde este mes.
        $this->assertSame(now()->startOfMonth()->toDateString(), $sobre->target_month->toDateString());
    }

    public function test_al_eliminar_un_sobre_los_gastos_quedan_sueltos(): void
    {
        $sobre = Envelope::factory()->gasto()->for($this->user)->create();
        EnvelopeMovement::factory()->for($this->user)->for($sobre)->create(['amount' => 50000]);
        $gasto = Expense::factory()->for($this->user)->create(['envelope_id' => $sobre->id]);

        Livewire::test('plata.sobre', ['envelope' => $sobre->id])
            ->call('deleteEnvelope')
            ->assertRedirect(route('plata.sobres'));

        $this->assertModelMissing($sobre);
        $this->assertDatabaseCount('envelope_movements', 0);
        $this->assertModelExists($gasto);
        $this->assertNull($gasto->fresh()->envelope_id);
    }
}
