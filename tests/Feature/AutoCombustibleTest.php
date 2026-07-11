<?php

namespace Tests\Feature;

use App\Models\FuelLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutoCombustibleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_puede_anotar_una_carga_de_combustible(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 40000]);

        Livewire::test('auto.combustible', ['vehicleId' => $vehicle->id])
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
        $vehicle = Vehicle::factory()->for($this->user)->create();

        Livewire::test('auto.combustible', ['vehicleId' => $vehicle->id])
            ->set('fuelMileage', null)
            ->call('addFuel')
            ->assertHasErrors(['fuelMileage' => 'required']);
    }

    public function test_una_carga_vieja_no_hace_retroceder_el_kilometraje(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 80000]);

        Livewire::test('auto.combustible', ['vehicleId' => $vehicle->id])
            ->set('fuelDate', '2026-01-01')
            ->set('fuelMileage', 60000)
            ->call('addFuel')
            ->assertHasNoErrors();

        $this->assertSame(80000, $vehicle->fresh()->kilometraje);
    }

    public function test_no_puede_cargar_combustible_a_un_auto_ajeno(): void
    {
        $ajeno = Vehicle::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('auto.combustible', ['vehicleId' => $ajeno->id])
            ->set('fuelMileage', 1000)
            ->call('addFuel');
    }

    public function test_puede_editar_una_carga_de_combustible(): void
    {
        $vehicle = Vehicle::factory()->for($this->user)->create(['kilometraje' => 40000]);
        $log = FuelLog::factory()->for($this->user)->for($vehicle)->create([
            'filled_on' => '2026-01-01',
            'mileage' => 40000,
            'cost' => 28000,
        ]);

        Livewire::test('auto.combustible', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.combustible', ['vehicleId' => $vehicle->id])
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

        Livewire::test('auto.combustible', ['vehicleId' => $vehicle->id])
            ->call('startEditingFuel', $log->id)
            ->set('editFuelMileage', 20800)
            ->call('saveFuelEdit')
            ->assertHasNoErrors();

        $this->assertSame(20800, $log->fresh()->mileage);
    }

    public function test_quien_recibe_el_auto_puede_cargar_combustible(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create(['kilometraje' => 20000]);
        $vehicle->members()->attach($this->user);

        Livewire::test('auto.combustible', ['vehicleId' => $vehicle->id])
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

        $component = Livewire::test('auto.combustible', ['vehicleId' => $vehicle->id]);

        // Se ven las últimas 20; la carga extra de la ventana da los km de la última visible.
        $this->assertCount(20, $component->instance()->fuelLogs);
        $this->assertSame(100, $component->instance()->fuelLogs->last()['since']);
        $component->assertSee('Ver más cargas')
            ->assertDontSee('01/01/2026');

        $component->call('showMoreFuel')
            ->assertSee('01/01/2026')
            ->assertDontSee('Ver más cargas');
    }
}
