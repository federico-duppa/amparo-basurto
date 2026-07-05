<?php

namespace Tests\Feature;

use App\Models\FuelLog;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentRenewal;
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
            ->assertSee('Todavía no cargaste ningún auto. Contame cuál es y empezamos a llevarle la cuenta.');
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

    public function test_puede_agregar_un_mantenimiento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('itemName', 'Filtro de aire')
            ->set('itemIntervalKm', 20000)
            ->set('itemIntervalMonths', 24)
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('maintenance_items', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->user->id,
            'name' => 'Filtro de aire',
            'interval_km' => 20000,
            'interval_months' => 24,
        ]);
    }

    public function test_el_nombre_del_mantenimiento_es_obligatorio(): void
    {
        Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('itemName', '')
            ->call('addItem')
            ->assertHasErrors(['itemName' => 'required']);
    }

    public function test_puede_registrar_un_mantenimiento_y_avanza_el_kilometraje(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 40000]);
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create([
            'name' => 'Cambio de aceite',
            'interval_km' => 10000,
            'interval_months' => 12,
        ]);

        Livewire::test('auto.panel')
            ->call('startLog', $item->id)
            ->set('logDate', '2026-07-01')
            ->set('logMileage', 42000)
            ->set('logCost', '35000.50')
            ->call('saveLog')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('maintenance_records', [
            'maintenance_item_id' => $item->id,
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->user->id,
            'mileage' => 42000,
            'cost' => 35000.50,
        ]);

        // La carga con mayor km adelanta el kilometraje del auto.
        $this->assertSame(42000, $vehicle->fresh()->kilometraje);
    }

    public function test_el_costo_del_mantenimiento_es_opcional(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')
            ->call('startLog', $item->id)
            ->set('logDate', '2026-07-01')
            ->set('logMileage', 10000)
            ->set('logCost', '')
            ->call('saveLog')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('maintenance_records', [
            'maintenance_item_id' => $item->id,
            'cost' => null,
        ]);
    }

    public function test_puede_anotar_una_carga_de_combustible(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 40000]);

        Livewire::test('auto.panel')
            ->set('fuelDate', '2026-07-02')
            ->set('fuelMileage', 40500)
            ->set('fuelCost', '28000')
            ->call('addFuel')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('fuel_logs', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->user->id,
            'mileage' => 40500,
            'cost' => 28000,
        ]);

        $this->assertSame(40500, $vehicle->fresh()->kilometraje);
    }

    public function test_el_kilometraje_de_la_carga_es_obligatorio(): void
    {
        Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('fuelMileage', null)
            ->call('addFuel')
            ->assertHasErrors(['fuelMileage' => 'required']);
    }

    public function test_una_carga_vieja_no_hace_retroceder_el_kilometraje(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 80000]);

        Livewire::test('auto.panel')
            ->set('fuelDate', '2026-01-01')
            ->set('fuelMileage', 60000)
            ->call('addFuel')
            ->assertHasNoErrors();

        $this->assertSame(80000, $vehicle->fresh()->kilometraje);
    }

    public function test_calcula_el_proximo_mantenimiento_por_km(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 48000]);
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create([
            'name' => 'Cambio de aceite',
            'interval_km' => 10000,
            'interval_months' => null,
        ]);
        MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
            'performed_on' => '2026-01-01',
            'mileage' => 40000,
        ]);

        // Próximo a los 50.000 km; faltan 2.000.
        $status = $item->fresh()->status(48000);
        $this->assertSame('ok', $status['level']);

        // Ya pasado: a 51.000 km está atrasado.
        $this->assertSame('overdue', $item->fresh()->status(51000)['level']);
    }

    public function test_marca_sin_registrar_cuando_no_hay_historial(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create(['interval_km' => 10000]);

        $this->assertSame('none', $item->status($vehicle->kilometraje)['level']);
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

    public function test_no_puede_cargar_combustible_a_un_auto_ajeno(): void
    {
        $ajeno = Vehicle::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')
            ->set('vehicleId', $ajeno->id)
            ->set('fuelMileage', 1000)
            ->call('addFuel');
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

    // --- Editar mantenimientos, realizaciones y cargas --------------------

    public function test_puede_editar_un_item_de_mantenimiento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create([
            'name' => 'Cambio de aceite',
            'interval_km' => 10000,
            'interval_months' => 12,
        ]);

        Livewire::test('auto.panel')
            ->call('startEditingItem', $item->id)
            ->assertSet('editItemName', 'Cambio de aceite')
            ->set('editItemName', 'Aceite y filtro')
            ->set('editItemIntervalKm', 15000)
            ->set('editItemIntervalMonths', null)
            ->call('saveItem')
            ->assertHasNoErrors()
            ->assertSet('editingItemId', null);

        $item->refresh();
        $this->assertSame('Aceite y filtro', $item->name);
        $this->assertSame(15000, $item->interval_km);
        $this->assertNull($item->interval_months);
    }

    public function test_el_nombre_del_item_editado_es_obligatorio(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')
            ->call('startEditingItem', $item->id)
            ->set('editItemName', '')
            ->call('saveItem')
            ->assertHasErrors(['editItemName' => 'required']);
    }

    public function test_puede_editar_una_realizacion(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 40000]);
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();
        $record = MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
            'performed_on' => '2026-01-01',
            'mileage' => 40000,
            'cost' => 20000,
        ]);

        Livewire::test('auto.panel')
            ->call('toggleHistory', $item->id)
            ->call('startEditingRecord', $record->id)
            ->assertSet('editingRecordId', $record->id)
            ->set('editRecordDate', '2026-02-15')
            ->set('editRecordMileage', 43000)
            ->set('editRecordCost', '25500')
            ->call('saveRecord')
            ->assertHasNoErrors()
            ->assertSet('editingRecordId', null);

        $record->refresh();
        $this->assertSame('2026-02-15', $record->performed_on->format('Y-m-d'));
        $this->assertSame(43000, $record->mileage);
        $this->assertSame('25500.00', $record->cost);

        // Editar hacia un km mayor adelanta el kilometraje del auto.
        $this->assertSame(43000, $vehicle->fresh()->kilometraje);
    }

    public function test_puede_eliminar_una_realizacion_suelta(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();
        $record = MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create();

        Livewire::test('auto.panel')->call('deleteRecord', $record->id);

        $this->assertModelMissing($record);
    }

    public function test_no_puede_editar_realizaciones_de_autos_ajenos(): void
    {
        $ajeno = Vehicle::factory()->create();
        $item = MaintenanceItem::factory()->for($ajeno->user)->for($ajeno)->create();
        $record = MaintenanceRecord::factory()->for($ajeno->user)->for($ajeno)->for($item, 'item')->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')
            ->set('vehicleId', $ajeno->id)
            ->call('startEditingRecord', $record->id);
    }

    public function test_puede_editar_una_carga_de_combustible(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 40000]);
        $log = FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-01-01',
            'mileage' => 40000,
            'cost' => 28000,
        ]);

        Livewire::test('auto.panel')
            ->call('startEditingFuel', $log->id)
            ->assertSet('editingFuelId', $log->id)
            ->set('editFuelDate', '2026-02-01')
            ->set('editFuelMileage', 41200)
            ->set('editFuelCost', '31000')
            ->call('saveFuelEdit')
            ->assertHasNoErrors()
            ->assertSet('editingFuelId', null);

        $log->refresh();
        $this->assertSame('2026-02-01', $log->filled_on->format('Y-m-d'));
        $this->assertSame(41200, $log->mileage);
        $this->assertSame('31000.00', $log->cost);
        $this->assertSame(41200, $vehicle->fresh()->kilometraje);
    }

    public function test_el_kilometraje_de_la_carga_editada_es_obligatorio(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $log = FuelLog::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')
            ->call('startEditingFuel', $log->id)
            ->set('editFuelMileage', null)
            ->call('saveFuelEdit')
            ->assertHasErrors(['editFuelMileage' => 'required']);
    }

    public function test_quien_recibe_el_auto_compartido_puede_editar_una_carga(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create(['kilometraje' => 20000]);
        $log = FuelLog::factory()->for($owner)->for($vehicle)->create(['mileage' => 20000]);
        $vehicle->members()->attach($this->user);

        Livewire::test('auto.panel')
            ->set('vehicleId', $vehicle->id)
            ->call('startEditingFuel', $log->id)
            ->set('editFuelMileage', 20800)
            ->call('saveFuelEdit')
            ->assertHasNoErrors();

        $this->assertSame(20800, $log->fresh()->mileage);
    }

    // --- Documentación ----------------------------------------------------

    public function test_puede_cargar_un_documento_con_vencimiento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('addingDocument', true)
            ->set('docName', 'Seguro')
            ->set('docExpiresOn', '2026-12-31')
            ->set('docNote', 'La Segunda')
            ->call('addDocument')
            ->assertHasNoErrors()
            ->assertSet('addingDocument', false);

        $this->assertDatabaseHas('vehicle_documents', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->user->id,
            'name' => 'Seguro',
            'note' => 'La Segunda',
        ]);
    }

    public function test_el_nombre_y_el_vencimiento_del_documento_son_obligatorios(): void
    {
        Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('addingDocument', true)
            ->set('docName', '')
            ->set('docExpiresOn', '')
            ->call('addDocument')
            ->assertHasErrors(['docName' => 'required', 'docExpiresOn' => 'required']);
    }

    public function test_calcula_el_estado_del_documento(): void
    {
        $vencido = VehicleDocument::factory()->create(['expires_on' => now()->subDay()->toDateString()]);
        $porVencer = VehicleDocument::factory()->create(['expires_on' => now()->addDays(10)->toDateString()]);
        $alDia = VehicleDocument::factory()->create(['expires_on' => now()->addMonths(6)->toDateString()]);

        $this->assertSame('overdue', $vencido->status()['level']);
        $this->assertSame('soon', $porVencer->status()['level']);
        $this->assertSame('ok', $alDia->status()['level']);
    }

    public function test_puede_editar_un_documento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create([
            'name' => 'VTV',
            'expires_on' => '2026-06-01',
        ]);

        Livewire::test('auto.panel')
            ->call('startEditingDocument', $doc->id)
            ->assertSet('editDocName', 'VTV')
            ->set('editDocName', 'VTV nueva')
            ->set('editDocExpiresOn', '2027-06-01')
            ->call('saveDocument')
            ->assertHasNoErrors()
            ->assertSet('editingDocumentId', null);

        $doc->refresh();
        $this->assertSame('VTV nueva', $doc->name);
        $this->assertSame('2027-06-01', $doc->expires_on->format('Y-m-d'));
    }

    public function test_puede_eliminar_un_documento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')->call('deleteDocument', $doc->id);

        $this->assertModelMissing($doc);
    }

    public function test_no_puede_cargar_documentos_en_autos_ajenos(): void
    {
        $ajeno = Vehicle::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')
            ->set('vehicleId', $ajeno->id)
            ->set('docName', 'Seguro')
            ->set('docExpiresOn', '2026-12-31')
            ->call('addDocument');
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

    public function test_quien_recibe_el_auto_puede_cargar_combustible(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create(['kilometraje' => 20000]);
        $vehicle->members()->attach($this->user);

        Livewire::test('auto.panel')
            ->set('vehicleId', $vehicle->id)
            ->set('fuelDate', '2026-07-02')
            ->set('fuelMileage', 20500)
            ->set('fuelCost', '30000')
            ->call('addFuel')
            ->assertHasNoErrors();

        // La carga queda a nombre de quien la hizo (el invitado), no del dueño.
        $this->assertDatabaseHas('fuel_logs', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->user->id,
            'mileage' => 20500,
        ]);
        $this->assertSame(20500, $vehicle->fresh()->kilometraje);
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

    // --- Nota en las realizaciones -----------------------------------------

    public function test_puede_registrar_un_mantenimiento_con_nota(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 40000]);
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')
            ->call('startLog', $item->id)
            ->set('logDate', '2026-07-01')
            ->set('logMileage', 42000)
            ->set('logNote', 'Taller de Raúl, aceite y filtro')
            ->call('saveLog')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('maintenance_records', [
            'maintenance_item_id' => $item->id,
            'note' => 'Taller de Raúl, aceite y filtro',
        ]);
    }

    public function test_la_nota_de_la_realizacion_es_opcional(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')
            ->call('startLog', $item->id)
            ->set('logDate', '2026-07-01')
            ->set('logMileage', 10000)
            ->set('logNote', '  ')
            ->call('saveLog')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('maintenance_records', [
            'maintenance_item_id' => $item->id,
            'note' => null,
        ]);
    }

    public function test_puede_editar_la_nota_de_una_realizacion(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();
        $record = MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
            'mileage' => 10000,
            'note' => 'Taller de Raúl',
        ]);

        Livewire::test('auto.panel')
            ->call('toggleHistory', $item->id)
            ->call('startEditingRecord', $record->id)
            ->assertSet('editRecordNote', 'Taller de Raúl')
            ->set('editRecordNote', 'Taller de Raúl, cambiaron también las bujías')
            ->call('saveRecord')
            ->assertHasNoErrors();

        $this->assertSame('Taller de Raúl, cambiaron también las bujías', $record->fresh()->note);
    }

    // --- Ritmo de uso y estimación de fecha ---------------------------------

    public function test_deduce_el_ritmo_de_uso_de_cargas_y_registros(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-06-01',
            'mileage' => 50000,
        ]);
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();
        MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
            'performed_on' => '2026-07-01',
            'mileage' => 53000,
        ]);

        // 3.000 km en 30 días: 100 km por día.
        $this->assertEqualsWithDelta(100.0, $vehicle->kmPerDay(), 0.001);
    }

    public function test_sin_lecturas_suficientes_no_hay_ritmo_de_uso(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        // Sin lecturas.
        $this->assertNull($vehicle->kmPerDay());

        // Una sola lectura.
        FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-07-01',
            'mileage' => 50000,
        ]);
        $this->assertNull($vehicle->kmPerDay());

        // Dos lecturas pero demasiado cercanas en el tiempo.
        FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-07-04',
            'mileage' => 50400,
        ]);
        $this->assertNull($vehicle->kmPerDay());
    }

    public function test_el_vencimiento_por_km_estima_fecha_con_el_ritmo_real(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 47000]);
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create([
            'interval_km' => 10000,
            'interval_months' => null,
        ]);
        MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
            'performed_on' => '2026-01-01',
            'mileage' => 40000,
        ]);

        // Faltan 3.000 km a 100 km/día: unos 30 días.
        $status = $item->fresh()->status(47000, 100.0);
        $this->assertSame(30, $status['urgency']);
        $this->assertStringContainsString(
            '(aprox. el '.now()->addDays(30)->format('d/m/Y').')',
            $status['detail'],
        );
    }

    public function test_sin_ritmo_real_no_estima_fecha_y_usa_la_escala_supuesta(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 47000]);
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create([
            'interval_km' => 10000,
            'interval_months' => null,
        ]);
        MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
            'performed_on' => '2026-01-01',
            'mileage' => 40000,
        ]);

        // Sin ritmo real se supone 40 km/día: 3.000 km ≈ 75 días, sin fecha estimada.
        $status = $item->fresh()->status(47000);
        $this->assertSame(75, $status['urgency']);
        $this->assertStringNotContainsString('aprox.', $status['detail']);
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

    // --- Renovación y periodicidad de documentos ----------------------------

    public function test_puede_cargar_un_documento_con_periodicidad(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.panel')
            ->set('addingDocument', true)
            ->set('docName', 'Seguro')
            ->set('docExpiresOn', '2026-12-31')
            ->set('docIntervalMonths', 6)
            ->call('addDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_documents', [
            'vehicle_id' => $vehicle->id,
            'name' => 'Seguro',
            'interval_months' => 6,
        ]);
    }

    public function test_puede_editar_la_periodicidad_de_un_documento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create(['interval_months' => 6]);

        Livewire::test('auto.panel')
            ->call('startEditingDocument', $doc->id)
            ->assertSet('editDocIntervalMonths', 6)
            ->set('editDocIntervalMonths', 12)
            ->call('saveDocument')
            ->assertHasNoErrors();

        $this->assertSame(12, $doc->fresh()->interval_months);
    }

    public function test_renovar_un_documento_guarda_la_vigencia_anterior(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create([
            'expires_on' => '2026-08-01',
        ]);

        Livewire::test('auto.panel')
            ->call('startRenewingDocument', $doc->id)
            ->set('renewDocExpiresOn', '2027-02-01')
            ->call('saveRenewal')
            ->assertHasNoErrors()
            ->assertSet('renewingDocumentId', null);

        $doc->refresh();
        $this->assertSame('2027-02-01', $doc->expires_on->format('Y-m-d'));

        $renewal = $doc->renewals()->sole();
        $this->assertSame('2026-08-01', $renewal->expires_on->format('Y-m-d'));
        $this->assertSame($this->user->id, $renewal->user_id);
    }

    public function test_renovar_sugiere_la_proxima_fecha_segun_la_periodicidad(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create([
            'expires_on' => '2026-08-01',
            'interval_months' => 6,
        ]);

        Livewire::test('auto.panel')
            ->call('startRenewingDocument', $doc->id)
            ->assertSet('renewDocExpiresOn', '2027-02-01');
    }

    public function test_sin_periodicidad_no_sugiere_fecha_al_renovar(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create([
            'interval_months' => null,
        ]);

        Livewire::test('auto.panel')
            ->call('startRenewingDocument', $doc->id)
            ->assertSet('renewDocExpiresOn', '');
    }

    public function test_el_nuevo_vencimiento_es_obligatorio_al_renovar(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.panel')
            ->call('startRenewingDocument', $doc->id)
            ->set('renewDocExpiresOn', '')
            ->call('saveRenewal')
            ->assertHasErrors(['renewDocExpiresOn' => 'required']);

        $this->assertDatabaseCount('vehicle_document_renewals', 0);
    }

    public function test_quien_recibe_el_auto_compartido_puede_renovar_un_documento(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $doc = VehicleDocument::factory()->for($owner)->for($vehicle)->create(['expires_on' => '2026-08-01']);
        $vehicle->members()->attach($this->user);

        Livewire::test('auto.panel')
            ->set('vehicleId', $vehicle->id)
            ->call('startRenewingDocument', $doc->id)
            ->set('renewDocExpiresOn', '2027-08-01')
            ->call('saveRenewal')
            ->assertHasNoErrors();

        // La renovación queda a nombre de quien la hizo (el invitado).
        $this->assertSame($this->user->id, $doc->renewals()->sole()->user_id);
    }

    public function test_no_puede_renovar_documentos_de_autos_ajenos(): void
    {
        $ajeno = Vehicle::factory()->create();
        $doc = VehicleDocument::factory()->for($ajeno->user)->for($ajeno)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.panel')
            ->set('vehicleId', $ajeno->id)
            ->call('startRenewingDocument', $doc->id);
    }

    public function test_al_eliminar_un_documento_se_borran_sus_vigencias_anteriores(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $doc = VehicleDocument::factory()->for($this->user)->for($vehicle)->create();
        VehicleDocumentRenewal::factory()->for($this->user)->for($doc, 'document')->create();

        Livewire::test('auto.panel')->call('deleteDocument', $doc->id);

        $this->assertModelMissing($doc);
        $this->assertDatabaseCount('vehicle_document_renewals', 0);
    }

    // --- Listas acotadas con "Ver más" ---------------------------------------

    public function test_la_lista_de_cargas_muestra_las_ultimas_y_ofrece_ver_mas(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        // 25 cargas: un día y 100 km entre cada una; la más vieja es la del 01/01.
        foreach (range(0, 24) as $i) {
            FuelLog::factory()->for($this->user)->for($vehicle)->create([
                'filled_on' => now()->parse('2026-01-01')->addDays($i),
                'mileage' => 10000 + $i * 100,
            ]);
        }

        $component = Livewire::test('auto.panel');

        // Se ven las últimas 20; la carga extra de la ventana da los km de la última visible.
        $this->assertCount(20, $component->instance()->fuelLogs);
        $this->assertSame(100, $component->instance()->fuelLogs->last()['since']);
        $component->assertSee('Ver más cargas')
            ->assertDontSee('01/01/2026');

        $component->call('showMoreFuel')
            ->assertSee('01/01/2026')
            ->assertDontSee('Ver más cargas');
    }

    public function test_el_historial_de_realizaciones_muestra_las_ultimas_y_ofrece_ver_mas(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();

        foreach (range(0, 11) as $i) {
            MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
                'performed_on' => now()->parse('2026-01-01')->addMonths($i),
                'mileage' => 10000 + $i * 1000,
            ]);
        }

        $component = Livewire::test('auto.panel')->call('toggleHistory', $item->id);

        $this->assertCount(10, $component->instance()->history);
        $component->assertSee('Ver más realizaciones')
            ->assertDontSee('01/01/2026');

        $component->call('showMoreHistory')
            ->assertSee('01/01/2026')
            ->assertDontSee('Ver más realizaciones');

        // Cerrar y reabrir el historial vuelve a la ventana inicial.
        $component->call('toggleHistory', $item->id)
            ->call('toggleHistory', $item->id)
            ->assertSee('Ver más realizaciones');
    }

    public function test_puede_dar_de_alta_otro_auto_teniendo_uno(): void
    {
        Vehicle::factory()->for($this->user)->create(['marca' => 'Fiat', 'modelo' => 'Uno']);

        $component = Livewire::test('auto.panel')
            ->assertSee('+ Otro auto')
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

    public function test_desglosa_los_gastos_por_mes(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();

        MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
            'performed_on' => '2026-06-10', 'mileage' => 1000, 'cost' => 30000,
        ]);
        FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-06-20', 'mileage' => 1500, 'cost' => 20000,
        ]);
        FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-07-01', 'mileage' => 2000, 'cost' => 25000,
        ]);
        // Sin costo no suma ni crea un período propio.
        FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-05-05', 'mileage' => 500, 'cost' => null,
        ]);

        $spending = Livewire::test('auto.panel')->instance()->spending;

        $this->assertCount(2, $spending);

        // Del más reciente al más viejo.
        $this->assertSame('07/2026', $spending[0]['label']);
        $this->assertSame(0.0, $spending[0]['mantenimiento']);
        $this->assertSame(25000.0, $spending[0]['combustible']);
        $this->assertSame(25000.0, $spending[0]['total']);

        $this->assertSame('06/2026', $spending[1]['label']);
        $this->assertSame(30000.0, $spending[1]['mantenimiento']);
        $this->assertSame(20000.0, $spending[1]['combustible']);
        $this->assertSame(50000.0, $spending[1]['total']);
    }

    public function test_desglosa_los_gastos_por_anio(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();

        MaintenanceRecord::factory()->for($this->user)->for($vehicle)->for($item, 'item')->create([
            'performed_on' => '2025-03-10', 'mileage' => 1000, 'cost' => 10000,
        ]);
        FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2025-11-20', 'mileage' => 1500, 'cost' => 15000,
        ]);
        FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-02-01', 'mileage' => 2000, 'cost' => 22000,
        ]);

        $component = Livewire::test('auto.panel')->call('setSpendPeriod', 'anio');
        $spending = $component->instance()->spending;

        $this->assertCount(2, $spending);
        $this->assertSame('2026', $spending[0]['label']);
        $this->assertSame(22000.0, $spending[0]['total']);
        $this->assertSame('2025', $spending[1]['label']);
        $this->assertSame(10000.0, $spending[1]['mantenimiento']);
        $this->assertSame(15000.0, $spending[1]['combustible']);
        $this->assertSame(25000.0, $spending[1]['total']);
    }

    public function test_el_desglose_de_gastos_muestra_los_ultimos_periodos_y_ofrece_ver_mas(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        // 13 meses con una carga cada uno; el más viejo es 01/2025.
        foreach (range(0, 12) as $i) {
            FuelLog::factory()->for($this->user)->for($vehicle)->create([
                'filled_on' => now()->parse('2025-01-15')->addMonths($i),
                'mileage' => 10000 + $i * 500,
                'cost' => 10000,
            ]);
        }

        $component = Livewire::test('auto.panel');

        // Se ven los últimos 12 períodos; el más viejo queda afuera.
        $this->assertCount(12, $component->instance()->spending);
        $this->assertSame('02/2025', $component->instance()->spending->last()['label']);
        $component->assertSee('Ver más períodos');

        $component->call('showMoreSpending')
            ->assertDontSee('Ver más períodos');
        $this->assertCount(13, $component->instance()->spending);
        $this->assertSame('01/2025', $component->instance()->spending->last()['label']);

        // Cambiar la agrupación vuelve a la ventana inicial.
        $component->call('setSpendPeriod', 'anio')
            ->assertSet('spendLimit', 12);
    }
}
