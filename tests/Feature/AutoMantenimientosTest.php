<?php

namespace Tests\Feature;

use App\Models\FuelLog;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutoMantenimientosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_puede_agregar_un_mantenimiento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

    public function test_puede_editar_un_item_de_mantenimiento(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create();
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create([
            'name' => 'Cambio de aceite',
            'interval_km' => 10000,
            'interval_months' => 12,
        ]);

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])->call('deleteRecord', $record->id);

        $this->assertModelMissing($record);
    }

    public function test_no_puede_editar_realizaciones_de_autos_ajenos(): void
    {
        $ajeno = Vehicle::factory()->create();
        $item = MaintenanceItem::factory()->for($ajeno->user)->for($ajeno)->create();
        $record = MaintenanceRecord::factory()->for($ajeno->user)->for($ajeno)->for($item, 'item')->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.mantenimientos', ['vehicleId' => $ajeno->id])
            ->call('startEditingRecord', $record->id);
    }

    public function test_puede_registrar_un_mantenimiento_con_nota(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 40000]);
        $item = MaintenanceItem::factory()->for($this->user)->for($vehicle)->create();

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])
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

        $component = Livewire::test('auto.mantenimientos', ['vehicleId' => $vehicle->id])->call('toggleHistory', $item->id);

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
}
