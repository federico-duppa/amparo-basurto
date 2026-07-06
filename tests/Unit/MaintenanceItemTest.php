<?php

namespace Tests\Unit;

use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceItemTest extends TestCase
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

    public function test_sin_historial_devuelve_none(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => 10000, 'interval_months' => 12]);

        $status = $item->status(currentKm: 5000);

        $this->assertSame('none', $status['level']);
        $this->assertSame(3, $status['rank']);
        $this->assertSame(PHP_INT_MAX, $status['urgency']);
        $this->assertSame('Sin registrar', $status['headline']);
    }

    public function test_por_km_vencido(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => 10000, 'interval_months' => null]);
        MaintenanceRecord::factory()->for($item, 'item')->create([
            'performed_on' => '2026-01-01',
            'mileage' => 1000,
        ]);

        // Vencía a los 11.000 km; ya lleva 12.000.
        $status = $item->status(currentKm: 12000);

        $this->assertSame('overdue', $status['level']);
        $this->assertSame(0, $status['rank']);
        $this->assertSame('Atrasado', $status['headline']);
        $this->assertStringContainsString('te pasaste por 1.000 km', $status['detail']);
    }

    public function test_por_km_pronto(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => 10000, 'interval_months' => null]);
        MaintenanceRecord::factory()->for($item, 'item')->create([
            'performed_on' => '2026-01-01',
            'mileage' => 0,
        ]);

        // Vence a los 10.000 km; faltan 400: dentro del margen de "pronto" (1.000).
        $status = $item->status(currentKm: 9600);

        $this->assertSame('soon', $status['level']);
        $this->assertSame(1, $status['rank']);
        $this->assertSame('Pronto', $status['headline']);
        $this->assertStringContainsString('faltan 400 km', $status['detail']);
    }

    public function test_por_km_al_dia(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => 10000, 'interval_months' => null]);
        MaintenanceRecord::factory()->for($item, 'item')->create([
            'performed_on' => '2026-01-01',
            'mileage' => 0,
        ]);

        $status = $item->status(currentKm: 5000);

        $this->assertSame('ok', $status['level']);
        $this->assertSame(2, $status['rank']);
        $this->assertSame('Al día', $status['headline']);
    }

    public function test_por_tiempo_vencido(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => null, 'interval_months' => 12]);
        MaintenanceRecord::factory()->for($item, 'item')->create([
            'performed_on' => '2025-01-01',
            'mileage' => 0,
        ]);

        $status = $item->status(currentKm: 0);

        $this->assertSame('overdue', $status['level']);
        $this->assertStringContainsString('vencía el 01/01/2026', $status['detail']);
    }

    public function test_por_tiempo_pronto(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => null, 'interval_months' => 1]);
        MaintenanceRecord::factory()->for($item, 'item')->create([
            'performed_on' => '2026-06-20',
            'mileage' => 0,
        ]);

        // Vence el 20/07, hoy es 05/07: faltan 15 días, dentro del margen de 30.
        $status = $item->status(currentKm: 0);

        $this->assertSame('soon', $status['level']);
    }

    public function test_por_tiempo_al_dia(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => null, 'interval_months' => 3]);
        MaintenanceRecord::factory()->for($item, 'item')->create([
            'performed_on' => '2026-05-10',
            'mileage' => 0,
        ]);

        // Vence el 10/08, hoy es 05/07: faltan 36 días, más allá del margen de 30.
        $status = $item->status(currentKm: 0);

        $this->assertSame('ok', $status['level']);
    }

    public function test_combinado_usa_el_peor_de_los_dos_niveles(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => 10000, 'interval_months' => 1]);
        MaintenanceRecord::factory()->for($item, 'item')->create([
            'performed_on' => '2025-01-01',
            'mileage' => 0,
        ]);

        // Al día por km (falta mucho), pero muy vencido por tiempo: gana lo peor.
        $status = $item->status(currentKm: 1000);

        $this->assertSame('overdue', $status['level']);
    }

    public function test_sin_intervalos_configurados_solo_lleva_el_historial(): void
    {
        $item = MaintenanceItem::factory()->create(['interval_km' => null, 'interval_months' => null]);
        MaintenanceRecord::factory()->for($item, 'item')->create([
            'performed_on' => '2026-01-01',
            'mileage' => 5000,
        ]);

        $status = $item->status(currentKm: 8000);

        $this->assertSame('ok', $status['level']);
        $this->assertSame(2, $status['rank']);
        $this->assertSame(PHP_INT_MAX, $status['urgency']);
        $this->assertStringContainsString('Último: 01/01/2026', $status['detail']);
        $this->assertStringContainsString('5.000 km', $status['detail']);
    }
}
