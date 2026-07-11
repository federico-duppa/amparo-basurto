<?php

namespace Tests\Feature;

use App\Models\FuelLog;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutoPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_la_pagina_de_auto_renderiza_el_componente(): void
    {
        $this->get('/auto')
            ->assertOk()
            ->assertSeeLivewire('auto.panel');
    }

    public function test_muestra_el_estado_vacio_con_la_voz_de_amparo(): void
    {
        $this->get('/auto')
            ->assertSee('Todavía no cargaste ningún vehículo. Contame si es un auto o una moto y empezamos a llevarle la cuenta.');
    }

    public function test_puede_crear_un_auto(): void
    {
        Livewire::test('auto.panel')
            ->set('newMarca', 'Volkswagen')
            ->set('newModelo', 'Gol')
            ->set('newPatente', 'ab123cd')
            ->set('newKilometraje', 85000)
            ->call('createVehicle')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $this->user->id,
            'marca' => 'Volkswagen',
            'modelo' => 'Gol',
            'patente' => 'AB123CD',
            'kilometraje' => 85000,
        ]);
    }

    public function test_al_crear_un_auto_se_siembran_mantenimientos_sugeridos(): void
    {
        Livewire::test('auto.panel')
            ->set('newMarca', 'Renault')
            ->set('newModelo', 'Clio')
            ->set('newKilometraje', 10000)
            ->call('createVehicle');

        $this->assertDatabaseHas('maintenance_items', ['name' => 'Cambio de aceite', 'user_id' => $this->user->id]);
        $this->assertDatabaseHas('maintenance_items', ['name' => 'Cambio de bujías']);
        $this->assertDatabaseHas('maintenance_items', ['name' => 'Correa de distribución']);
    }

    public function test_puede_crear_una_moto_con_sus_mantenimientos(): void
    {
        Livewire::test('auto.panel')
            ->set('newTipo', 'moto')
            ->set('newMarca', 'Honda')
            ->set('newModelo', 'Tornado')
            ->set('newKilometraje', 5000)
            ->call('createVehicle')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $this->user->id,
            'tipo' => 'moto',
            'marca' => 'Honda',
            'modelo' => 'Tornado',
        ]);

        // La moto arranca con sus propios presets, no con los del auto.
        $this->assertDatabaseHas('maintenance_items', ['name' => 'Kit de arrastre', 'user_id' => $this->user->id]);
        $this->assertDatabaseMissing('maintenance_items', ['name' => 'Correa de distribución', 'user_id' => $this->user->id]);
    }

    public function test_el_tipo_de_vehiculo_tiene_que_ser_valido(): void
    {
        Livewire::test('auto.panel')
            ->set('newTipo', 'camion')
            ->set('newMarca', 'Ford')
            ->set('newModelo', 'F100')
            ->set('newKilometraje', 1000)
            ->call('createVehicle')
            ->assertHasErrors('newTipo');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_la_marca_y_el_modelo_son_obligatorios(): void
    {
        Livewire::test('auto.panel')
            ->set('newMarca', '')
            ->set('newModelo', '')
            ->set('newKilometraje', 1000)
            ->call('createVehicle')
            ->assertHasErrors(['newMarca' => 'required', 'newModelo' => 'required']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_el_kilometraje_es_obligatorio_al_crear(): void
    {
        Livewire::test('auto.panel')
            ->set('newMarca', 'Fiat')
            ->set('newModelo', 'Cronos')
            ->set('newKilometraje', null)
            ->call('createVehicle')
            ->assertHasErrors(['newKilometraje' => 'required']);
    }

    public function test_puede_actualizar_el_kilometraje(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 50000]);

        Livewire::test('auto.panel')
            ->call('startEditingKm')
            ->set('kmValue', 55000)
            ->call('saveKm')
            ->assertHasNoErrors();

        $this->assertSame(55000, $vehicle->fresh()->kilometraje);
    }

    public function test_no_ve_los_autos_de_otras_personas(): void
    {
        Vehicle::factory()->create(['marca' => 'Ajeno', 'modelo' => 'Secreto']);
        Vehicle::factory()->for($this->user)->create(['marca' => 'Propio', 'modelo' => 'Auto']);

        $this->get('/auto')
            ->assertSee('Propio')
            ->assertDontSee('Ajeno');
    }

    public function test_no_puede_seleccionar_un_auto_ajeno(): void
    {
        $ajeno = Vehicle::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')->call('selectVehicle', $ajeno->id);
    }

    public function test_no_puede_eliminar_un_auto_ajeno(): void
    {
        $ajeno = Vehicle::factory()->create();

        try {
            Livewire::test('auto.panel')->call('deleteVehicle', $ajeno->id);
            $this->fail('Un auto ajeno no debería poder eliminarse.');
        } catch (ModelNotFoundException) {
            // esperado
        }

        $this->assertModelExists($ajeno);
    }

    public function test_al_eliminar_un_auto_se_borra_su_historial(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();
        $record = MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create();
        $fuel = FuelLog::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')->call('deleteVehicle', $vehicle->id);

        $this->assertModelMissing($vehicle);
        $this->assertModelMissing($item);
        $this->assertModelMissing($record);
        $this->assertModelMissing($fuel);
    }

    public function test_al_eliminar_un_auto_se_borran_sus_documentos(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')->call('deleteVehicle', $vehicle->id);

        $this->assertModelMissing($doc);
    }

    // --- Editar auto ------------------------------------------------------

    public function test_el_dueno_puede_editar_los_datos_del_auto(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['marca' => 'Fiat', 'modelo' => 'Uno']);

        Livewire::test('auto.panel')
            ->call('startEditingVehicle')
            ->set('editMarca', 'Volkswagen')
            ->set('editModelo', 'Gol')
            ->set('editPatente', 'ab123cd')
            ->call('saveVehicle')
            ->assertHasNoErrors();

        $vehicle->refresh();
        $this->assertSame('Volkswagen', $vehicle->marca);
        $this->assertSame('Gol', $vehicle->modelo);
        $this->assertSame('AB123CD', $vehicle->patente);
    }

    public function test_un_usuario_con_quien_se_comparte_no_puede_editar_el_auto(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $vehicle->members()->attach($this->user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')
            ->set('vehicleId', $vehicle->id)
            ->call('startEditingVehicle');
    }

    // --- Compartir --------------------------------------------------------

    public function test_el_dueno_puede_compartir_el_auto_por_usuario(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $otra = User::factory()->create(['username' => 'martina']);

        Livewire::test('auto.panel')
            ->set('shareUsername', 'Martina')
            ->call('share')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_user', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $otra->id,
        ]);
    }

    public function test_compartir_avisa_si_el_usuario_no_existe(): void
    {
        Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('shareUsername', 'fantasma')
            ->call('share')
            ->assertHasErrors('shareUsername');

        $this->assertDatabaseCount('vehicle_user', 0);
    }

    public function test_no_puede_compartir_dos_veces_con_la_misma_persona(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $otra = User::factory()->create(['username' => 'martina']);
        $vehicle->members()->attach($otra);

        Livewire::test('auto.panel')
            ->set('shareUsername', 'martina')
            ->call('share')
            ->assertHasErrors('shareUsername');

        $this->assertDatabaseCount('vehicle_user', 1);
    }

    public function test_el_dueno_puede_dejar_de_compartir(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $otra = User::factory()->create();
        $vehicle->members()->attach($otra);

        Livewire::test('auto.panel')->call('unshare', $otra->id);

        $this->assertDatabaseCount('vehicle_user', 0);
    }

    public function test_quien_recibe_el_auto_compartido_lo_ve_con_sus_datos(): void
    {
        $owner = User::factory()->create(['name' => 'Fede']);
        $vehicle = Vehicle::factory()->for($owner)->create(['marca' => 'Peugeot', 'modelo' => '208']);
        MaintenanceItem::factory()->for($owner)->for($vehicle)->create(['name' => 'Cambio de aceite']);
        FuelLog::factory()->for($owner)->for($vehicle)->create(['cost' => 33000]);
        $vehicle->members()->attach($this->user);

        // El usuario invitado (autenticado en setUp) entra y ve el auto compartido.
        $this->get('/auto')
            ->assertOk()
            ->assertSee('Peugeot')
            ->assertSee('Cambio de aceite')
            ->assertSee('Compartido por Fede');
    }

    public function test_un_usuario_sin_acceso_no_puede_compartir_un_auto_ajeno(): void
    {
        $ajeno = Vehicle::factory()->create();
        User::factory()->create(['username' => 'martina']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')
            ->set('vehicleId', $ajeno->id)
            ->set('shareUsername', 'martina')
            ->call('share');
    }

    // --- Transferir la propiedad --------------------------------------------

    public function test_el_dueno_puede_transferir_el_auto_a_alguien_con_quien_lo_comparte(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $otra = User::factory()->create();
        $vehicle->members()->attach($otra);

        Livewire::test('auto.panel')
            ->call('transferOwnership', $otra->id)
            ->assertHasNoErrors();

        // La otra persona pasa a ser dueña y quien transfirió queda como compartido.
        $this->assertSame($otra->id, $vehicle->fresh()->user_id);
        $this->assertDatabaseHas('vehicle_user', ['vehicle_id' => $vehicle->id, 'user_id' => $this->user->id]);
        $this->assertDatabaseMissing('vehicle_user', ['vehicle_id' => $vehicle->id, 'user_id' => $otra->id]);
    }

    public function test_quien_transfirio_sigue_viendo_el_auto_pero_sin_acciones_de_dueno(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['marca' => 'Peugeot', 'modelo' => '208']);
        $otra = User::factory()->create(['name' => 'Martina']);
        $vehicle->members()->attach($otra);

        Livewire::test('auto.panel')->call('transferOwnership', $otra->id);

        $this->get('/auto')
            ->assertSee('Peugeot')
            ->assertSee('Compartido por Martina');

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')->call('startEditingVehicle');
    }

    public function test_no_puede_transferir_a_alguien_que_no_tiene_el_auto_compartido(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $otra = User::factory()->create();

        try {
            Livewire::test('auto.panel')->call('transferOwnership', $otra->id);
            $this->fail('No debería poder transferirse a alguien sin el auto compartido.');
        } catch (ModelNotFoundException) {
            // esperado
        }

        $this->assertSame($this->user->id, $vehicle->fresh()->user_id);
    }

    public function test_quien_recibe_el_auto_compartido_no_puede_transferirlo(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $vehicle->members()->attach($this->user);

        try {
            Livewire::test('auto.panel')
                ->set('vehicleId', $vehicle->id)
                ->call('transferOwnership', $this->user->id);
            $this->fail('Transferir es una acción reservada al dueño.');
        } catch (ModelNotFoundException) {
            // esperado
        }

        $this->assertSame($owner->id, $vehicle->fresh()->user_id);
    }

    // --- Quién anotó cada registro ------------------------------------------

    public function test_en_un_auto_compartido_se_ve_quien_anoto_cada_registro(): void
    {
        $owner = User::factory()->create(['name' => 'Fede']);
        $vehicle = Vehicle::factory()->for($owner)->create();
        FuelLog::factory()->for($owner)->for($vehicle)->create();
        VehicleDocument::factory()->for($owner)->for($vehicle)->create();
        $vehicle->members()->attach($this->user);

        $this->get('/auto')->assertSee('Anotó Fede');
    }

    public function test_en_un_auto_sin_compartir_no_se_muestra_quien_anoto(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        FuelLog::factory()->for($this->user)->for($vehicle)->create();
        VehicleDocument::factory()->for($this->user)->for($vehicle)->create();

        $this->get('/auto')->assertDontSee('Anotó');
    }

    // --- Otro vehículo -------------------------------------------------------

    public function test_puede_dar_de_alta_otro_auto_teniendo_uno(): void
    {
        Vehicle::factory()->for($this->user)->create(['marca' => 'Fiat', 'modelo' => 'Uno']);

        $component = Livewire::test('auto.panel')
            ->assertSee('+ Otro vehículo')
            ->call('startAddingVehicle')
            ->set('newMarca', 'Peugeot')
            ->set('newModelo', '208')
            ->set('newKilometraje', 15000)
            ->call('createVehicle')
            ->assertHasNoErrors()
            ->assertSet('addingVehicle', false);

        $this->assertSame(2, $this->user->vehicles()->count());

        // El auto recién creado queda seleccionado.
        $nuevo = Vehicle::where('modelo', '208')->firstOrFail();
        $component->assertSet('vehicleId', $nuevo->id);
    }

    public function test_el_alta_de_otro_auto_se_puede_cancelar(): void
    {
        Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.panel')
            ->call('startAddingVehicle')
            ->assertSet('addingVehicle', true)
            ->set('newMarca', 'Peugeot')
            ->call('cancelAddVehicle')
            ->assertSet('addingVehicle', false)
            ->assertSet('newMarca', '');

        $this->assertDatabaseCount('vehicles', 1);
    }
}
