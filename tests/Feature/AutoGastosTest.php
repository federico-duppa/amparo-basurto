<?php

namespace Tests\Feature;

use App\Models\FuelLog;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutoGastosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
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

        $spending = Livewire::test('auto.gastos', ['vehicleId' => $vehicle->id])->instance()->spending;

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

        $component = Livewire::test('auto.gastos', ['vehicleId' => $vehicle->id])->call('setSpendPeriod', 'anio');
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

        $component = Livewire::test('auto.gastos', ['vehicleId' => $vehicle->id]);

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
