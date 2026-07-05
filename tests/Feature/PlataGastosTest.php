<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\EnvelopeMovement;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PlataGastosTest extends TestCase
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

    public function test_la_pagina_de_gastos_renderiza_el_componente(): void
    {
        $this->get('/plata')
            ->assertOk()
            ->assertSeeLivewire('plata.gastos');
    }

    public function test_muestra_el_estado_vacio_con_la_voz_de_amparo(): void
    {
        $this->get('/plata')
            ->assertSee('Todavía no anotaste ningún gasto. El día a día se anota acá, sin vueltas.');
    }

    public function test_puede_anotar_un_gasto_suelto_en_pesos(): void
    {
        Livewire::test('plata.gastos')
            ->set('description', 'Verdulería')
            ->set('category', 'Comida')
            ->set('amount', '12500.50')
            ->set('currency', 'ARS')
            ->set('spentOn', now()->format('Y-m-d'))
            ->call('add')
            ->assertSet('description', '')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'user_id' => $this->user->id,
            'envelope_id' => null,
            'description' => 'Verdulería',
            'category' => 'Comida',
            'amount' => '12500.50',
            'currency' => 'ARS',
            'rate_ars' => null,
        ]);
    }

    public function test_el_monto_y_la_descripcion_son_obligatorios(): void
    {
        Livewire::test('plata.gastos')
            ->set('description', '')
            ->set('amount', '')
            ->call('add')
            ->assertHasErrors(['description' => 'required', 'amount' => 'required', 'category' => 'required']);

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_el_monto_tiene_que_ser_mayor_a_cero(): void
    {
        Livewire::test('plata.gastos')
            ->set('description', 'Café')
            ->set('category', 'Salidas')
            ->set('amount', '-10')
            ->call('add')
            ->assertHasErrors(['amount' => 'gt']);
    }

    public function test_no_acepta_gastos_con_fecha_futura(): void
    {
        Livewire::test('plata.gastos')
            ->set('description', 'Café')
            ->set('category', 'Salidas')
            ->set('amount', '100')
            ->set('spentOn', now()->addDay()->format('Y-m-d'))
            ->call('add')
            ->assertHasErrors(['spentOn' => 'before_or_equal']);
    }

    public function test_un_gasto_en_dolares_congela_la_cotizacion_del_dia(): void
    {
        ExchangeRate::create([
            'rate_type' => 'blue',
            'quoted_on' => now()->toDateString(),
            'sell' => 1200,
        ]);

        Livewire::test('plata.gastos')
            ->set('description', 'Alquiler cabaña')
            ->set('category', 'Vacaciones')
            ->set('amount', '60')
            ->set('currency', 'USD')
            ->set('spentOn', now()->format('Y-m-d'))
            ->call('add')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'description' => 'Alquiler cabaña',
            'currency' => 'USD',
            'rate_ars' => '1200.0000',
            'rate_source' => 'blue',
        ]);
    }

    public function test_un_gasto_en_dolares_sin_cotizacion_disponible_se_guarda_igual(): void
    {
        // Http::fake() del setUp: la API no devuelve nada útil y no hay serie guardada.
        Livewire::test('plata.gastos')
            ->set('description', 'Streaming')
            ->set('category', 'Casa')
            ->set('amount', '15')
            ->set('currency', 'USD')
            ->set('spentOn', now()->format('Y-m-d'))
            ->call('add')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'description' => 'Streaming',
            'currency' => 'USD',
            'rate_ars' => null,
            'rate_source' => null,
        ]);
    }

    public function test_puede_imputar_un_gasto_a_un_sobre_de_gasto_previsto_y_descuenta_su_saldo(): void
    {
        $sobre = Envelope::factory()->gasto()->for($this->user)->create(['currency' => 'ARS']);
        EnvelopeMovement::factory()->for($this->user)->for($sobre)->create(['amount' => 100000]);

        Livewire::test('plata.gastos')
            ->set('description', 'Hotel')
            ->set('category', 'Vacaciones')
            ->set('amount', '40000')
            ->set('currency', 'ARS')
            ->set('envelopeId', (string) $sobre->id)
            ->call('add')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'envelope_id' => $sobre->id,
            'description' => 'Hotel',
        ]);

        $this->assertEqualsWithDelta(60000.0, $sobre->fresh()->balance(), 0.001);
    }

    public function test_no_deja_imputar_un_gasto_en_otra_moneda_que_la_del_sobre(): void
    {
        $sobre = Envelope::factory()->gasto()->for($this->user)->create(['currency' => 'ARS']);

        Livewire::test('plata.gastos')
            ->set('description', 'Hotel')
            ->set('category', 'Vacaciones')
            ->set('amount', '100')
            ->set('currency', 'USD')
            ->set('envelopeId', (string) $sobre->id)
            ->call('add')
            ->assertHasErrors(['envelopeId']);

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_no_puede_imputar_a_un_sobre_de_ahorro(): void
    {
        $sobre = Envelope::factory()->for($this->user)->create(['kind' => Envelope::KIND_AHORRO]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('plata.gastos')
            ->set('description', 'Hotel')
            ->set('category', 'Vacaciones')
            ->set('amount', '100')
            ->set('envelopeId', (string) $sobre->id)
            ->call('add');
    }

    public function test_no_puede_imputar_a_un_sobre_ajeno(): void
    {
        $ajeno = Envelope::factory()->gasto()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('plata.gastos')
            ->set('description', 'Hotel')
            ->set('category', 'Vacaciones')
            ->set('amount', '100')
            ->set('envelopeId', (string) $ajeno->id)
            ->call('add');
    }

    public function test_puede_eliminar_un_gasto_propio(): void
    {
        $gasto = Expense::factory()->for($this->user)->create();

        Livewire::test('plata.gastos')->call('delete', $gasto->id);

        $this->assertModelMissing($gasto);
    }

    public function test_no_puede_eliminar_gastos_ajenos(): void
    {
        $ajeno = Expense::factory()->create();

        try {
            Livewire::test('plata.gastos')->call('delete', $ajeno->id);
            $this->fail('Un gasto ajeno no debería poder eliminarse.');
        } catch (ModelNotFoundException) {
            // esperado: para este usuario ese gasto no existe
        }

        $this->assertModelExists($ajeno);
    }

    public function test_no_ve_los_gastos_de_otros_usuarios(): void
    {
        Expense::factory()->create(['description' => 'Gasto de otra persona', 'spent_on' => now()]);
        Expense::factory()->for($this->user)->create(['description' => 'Gasto propio', 'spent_on' => now()]);

        $this->get('/plata')
            ->assertSee('Gasto propio')
            ->assertDontSee('Gasto de otra persona');
    }

    public function test_puede_editar_un_gasto(): void
    {
        $gasto = Expense::factory()->for($this->user)->create([
            'description' => 'Verdulería',
            'category' => 'Comida',
            'amount' => '5000.00',
            'currency' => 'ARS',
            'spent_on' => now()->subDay(),
        ]);

        Livewire::test('plata.gastos')
            ->call('startEditing', $gasto->id)
            ->assertSet('editingId', $gasto->id)
            ->assertSet('description', 'Verdulería')
            ->assertSet('category', 'Comida')
            ->set('description', 'Carnicería')
            ->set('amount', '8200')
            ->call('update')
            ->assertHasNoErrors()
            ->assertSet('editingId', null);

        $gasto->refresh();
        $this->assertSame('Carnicería', $gasto->description);
        $this->assertSame('8200.00', $gasto->amount);
    }

    public function test_al_editar_un_gasto_a_dolares_recalcula_la_cotizacion(): void
    {
        ExchangeRate::create([
            'rate_type' => 'blue',
            'quoted_on' => now()->toDateString(),
            'sell' => 1000,
        ]);

        $gasto = Expense::factory()->for($this->user)->create([
            'currency' => 'ARS',
            'rate_ars' => null,
            'spent_on' => now(),
        ]);

        Livewire::test('plata.gastos')
            ->call('startEditing', $gasto->id)
            ->set('amount', '50')
            ->set('currency', 'USD')
            ->set('spentOn', now()->format('Y-m-d'))
            ->call('update')
            ->assertHasNoErrors();

        $gasto->refresh();
        $this->assertSame('USD', $gasto->currency);
        $this->assertSame('1000.0000', $gasto->rate_ars);
        $this->assertSame('blue', $gasto->rate_source);
    }

    public function test_editar_valida_los_campos(): void
    {
        $gasto = Expense::factory()->for($this->user)->create();

        Livewire::test('plata.gastos')
            ->call('startEditing', $gasto->id)
            ->set('description', '')
            ->set('amount', '')
            ->call('update')
            ->assertHasErrors(['description' => 'required', 'amount' => 'required']);
    }

    public function test_no_puede_editar_gastos_ajenos(): void
    {
        $ajeno = Expense::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('plata.gastos')->call('startEditing', $ajeno->id);
    }
}
