<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\EnvelopeMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlataSobresTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_la_pagina_de_sobres_renderiza_el_componente(): void
    {
        $this->get('/plata/sobres')
            ->assertOk()
            ->assertSeeLivewire('plata.sobres');
    }

    public function test_muestra_el_estado_vacio_con_la_voz_de_amparo(): void
    {
        $this->get('/plata/sobres')
            ->assertSee('Todavía no armaste ningún sobre.');
    }

    public function test_puede_crear_un_sobre_de_ahorro_nominal(): void
    {
        Livewire::test('plata.sobres')
            ->set('creating', true)
            ->set('name', 'Auto nuevo')
            ->set('kind', Envelope::KIND_AHORRO)
            ->set('currency', 'ARS')
            ->set('targetAmount', '500000')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('envelopes', [
            'user_id' => $this->user->id,
            'name' => 'Auto nuevo',
            'kind' => Envelope::KIND_AHORRO,
            'currency' => 'ARS',
            'indexed' => false,
            'target_amount' => '500000.00',
            'target_month' => null,
        ]);
    }

    public function test_puede_crear_un_sobre_de_ahorro_en_dolares(): void
    {
        Livewire::test('plata.sobres')
            ->set('name', 'Viaje grande')
            ->set('kind', Envelope::KIND_AHORRO)
            ->set('currency', 'USD')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('envelopes', [
            'name' => 'Viaje grande',
            'currency' => 'USD',
            'indexed' => false,
        ]);
    }

    public function test_puede_crear_un_sobre_de_gasto_previsto(): void
    {
        Livewire::test('plata.sobres')
            ->set('name', 'Seguro de marzo')
            ->set('kind', Envelope::KIND_GASTO)
            ->set('currency', 'ARS')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('envelopes', [
            'name' => 'Seguro de marzo',
            'kind' => Envelope::KIND_GASTO,
            'indexed' => false,
        ]);
    }

    public function test_el_sobre_indexado_queda_siempre_en_pesos_y_ancla_su_mes_base(): void
    {
        Livewire::test('plata.sobres')
            ->set('name', 'Fondo')
            ->set('kind', Envelope::KIND_AHORRO)
            ->set('currency', 'USD') // se ignora: el poder de compra se ancla al IPC argentino
            ->set('indexed', true)
            ->set('targetAmount', '200000')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('envelopes', [
            'name' => 'Fondo',
            'currency' => 'ARS',
            'indexed' => true,
            'target_amount' => '200000.00',
            'target_month' => now()->startOfMonth()->toDateString().' 00:00:00',
        ]);
    }

    public function test_el_sobre_indexado_necesita_un_objetivo(): void
    {
        Livewire::test('plata.sobres')
            ->set('name', 'Fondo')
            ->set('kind', Envelope::KIND_AHORRO)
            ->set('indexed', true)
            ->set('targetAmount', '')
            ->call('create')
            ->assertHasErrors(['targetAmount']);

        $this->assertDatabaseCount('envelopes', 0);
    }

    public function test_un_sobre_de_gasto_nunca_queda_indexado(): void
    {
        Livewire::test('plata.sobres')
            ->set('name', 'Vacaciones')
            ->set('kind', Envelope::KIND_GASTO)
            ->set('indexed', true)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('envelopes', [
            'name' => 'Vacaciones',
            'kind' => Envelope::KIND_GASTO,
            'indexed' => false,
        ]);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        Livewire::test('plata.sobres')
            ->set('name', '')
            ->call('create')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_muestra_el_saldo_emergente_de_los_movimientos(): void
    {
        $sobre = Envelope::factory()->for($this->user)->create(['name' => 'Vacaciones', 'target_amount' => null]);
        EnvelopeMovement::factory()->for($this->user)->for($sobre)->create(['amount' => 300000]);
        EnvelopeMovement::factory()->retiro()->for($this->user)->for($sobre)->create(['amount' => 50000]);

        $this->get('/plata/sobres')
            ->assertSee('Vacaciones')
            ->assertSee('$250.000,00');
    }

    public function test_no_ve_los_sobres_de_otros_usuarios(): void
    {
        Envelope::factory()->create(['name' => 'Sobre de otra persona']);
        Envelope::factory()->for($this->user)->create(['name' => 'Sobre propio']);

        $this->get('/plata/sobres')
            ->assertSee('Sobre propio')
            ->assertDontSee('Sobre de otra persona');
    }
}
