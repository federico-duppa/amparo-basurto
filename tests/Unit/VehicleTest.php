<?php

namespace Tests\Unit;

use App\Models\FuelLog;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_sin_lecturas(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->assertNull($vehicle->kmPerDay());
    }

    public function test_null_con_una_sola_lectura(): void
    {
        $vehicle = Vehicle::factory()->create();
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-01', 'mileage' => 1000]);

        $this->assertNull($vehicle->kmPerDay());
    }

    public function test_null_con_menos_del_minimo_de_dias_entre_lecturas(): void
    {
        $vehicle = Vehicle::factory()->create();
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-01', 'mileage' => 1000]);
        // 9 días de diferencia, por debajo del mínimo de 14.
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-10', 'mileage' => 1200]);

        $this->assertNull($vehicle->kmPerDay());
    }

    public function test_null_sin_kilometros_recorridos(): void
    {
        $vehicle = Vehicle::factory()->create();
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-01', 'mileage' => 1000]);
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-02-01', 'mileage' => 1000]);

        $this->assertNull($vehicle->kmPerDay());
    }

    public function test_calcula_el_ritmo_con_cargas_de_combustible(): void
    {
        $vehicle = Vehicle::factory()->create();
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-01', 'mileage' => 1000]);
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-21', 'mileage' => 2000]);

        $this->assertEqualsWithDelta(50.0, $vehicle->kmPerDay(), 0.001);
    }

    public function test_combina_cargas_de_combustible_y_mantenimientos(): void
    {
        $vehicle = Vehicle::factory()->create();
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-01', 'mileage' => 1000]);
        MaintenanceRecord::factory()->for($vehicle)->create(['performed_on' => '2026-01-15', 'mileage' => 1500]);
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-02-01', 'mileage' => 2200]);

        // Primera y última lectura: 01/01 (1.000 km) a 01/02 (2.200 km) = 31 días, 1.200 km.
        $this->assertEqualsWithDelta(1200 / 31, $vehicle->kmPerDay(), 0.001);
    }

    public function test_acepta_exactamente_el_minimo_de_dias(): void
    {
        $vehicle = Vehicle::factory()->create();
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-01', 'mileage' => 1000]);
        FuelLog::factory()->for($vehicle)->create(['filled_on' => '2026-01-15', 'mileage' => 1140]);

        $this->assertEqualsWithDelta(10.0, $vehicle->kmPerDay(), 0.001);
    }
}
