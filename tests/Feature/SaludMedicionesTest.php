<?php

namespace Tests\Feature;

use App\Models\HealthMeasurement;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaludMedicionesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private HealthRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->record = HealthRecord::factory()->for($this->user)->create();
    }

    public function test_muestra_el_estado_vacio_con_la_voz_de_amparo(): void
    {
        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->assertSee('Todavía no anotaste ninguna medición.');
    }

    public function test_puede_anotar_un_peso(): void
    {
        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->call('selectType', 'peso')
            ->set('measurementValue', '78.5')
            ->set('measurementDate', '2026-07-01')
            ->call('addMeasurement')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_measurements', [
            'health_record_id' => $this->record->id,
            'user_id' => $this->user->id,
            'type' => 'peso',
            'value' => 78.5,
        ]);
    }

    public function test_la_presion_pide_maxima_y_minima(): void
    {
        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->call('selectType', 'presion')
            ->set('measurementValue', '120')
            ->set('measurementValue2', '')
            ->call('addMeasurement')
            ->assertHasErrors(['measurementValue2' => 'required']);

        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->call('selectType', 'presion')
            ->set('measurementValue', '120')
            ->set('measurementValue2', '80')
            ->call('addMeasurement')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_measurements', [
            'type' => 'presion',
            'value' => 120,
            'value2' => 80,
        ]);
    }

    public function test_el_valor_tiene_que_ser_positivo(): void
    {
        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->set('measurementValue', '0')
            ->call('addMeasurement')
            ->assertHasErrors('measurementValue');
    }

    public function test_arranca_en_el_tipo_que_ya_tiene_datos(): void
    {
        HealthMeasurement::factory()->for($this->record, 'record')->for($this->user)->create([
            'type' => 'glucemia', 'value' => 95,
        ]);

        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->assertSet('selectedType', 'glucemia');
    }

    public function test_muestra_la_ultima_medicion_en_formato_argentino(): void
    {
        HealthMeasurement::factory()->for($this->record, 'record')->for($this->user)->create([
            'type' => 'peso', 'value' => 78.5, 'measured_on' => '2026-07-01',
        ]);
        HealthMeasurement::factory()->for($this->record, 'record')->for($this->user)->presion()->create([
            'value' => 120, 'value2' => 80, 'measured_on' => '2026-07-01',
        ]);

        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->assertSee('78,5 kg')
            ->assertSee('120/80 mmHg');
    }

    public function test_la_evolucion_muestra_la_diferencia_con_la_anterior(): void
    {
        HealthMeasurement::factory()->for($this->record, 'record')->for($this->user)->create([
            'type' => 'peso', 'value' => 80, 'measured_on' => '2026-06-01',
        ]);
        HealthMeasurement::factory()->for($this->record, 'record')->for($this->user)->create([
            'type' => 'peso', 'value' => 78.5, 'measured_on' => '2026-07-01',
        ]);

        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->assertSee('−1,5');
    }

    public function test_la_evolucion_se_pagina_con_ver_mas(): void
    {
        foreach (range(1, 11) as $i) {
            HealthMeasurement::factory()->for($this->record, 'record')->for($this->user)->create([
                'type' => 'peso',
                'value' => 70 + $i,
                'measured_on' => now()->subDays($i),
            ]);
        }

        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->assertSee('Ver más mediciones')
            ->call('showMoreMeasurements')
            ->assertDontSee('Ver más mediciones');
    }

    public function test_puede_eliminar_una_medicion(): void
    {
        $measurement = HealthMeasurement::factory()->for($this->record, 'record')->for($this->user)->create();

        Livewire::test('salud.mediciones', ['recordId' => $this->record->id])
            ->call('deleteMeasurement', $measurement->id);

        $this->assertDatabaseMissing('health_measurements', ['id' => $measurement->id]);
    }

    public function test_no_puede_operar_las_mediciones_de_una_historia_ajena(): void
    {
        $other = User::factory()->create();
        $foreign = HealthRecord::factory()->for($other)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('salud.mediciones', ['recordId' => $foreign->id]);
    }

    public function test_quien_tiene_la_historia_compartida_puede_anotar_mediciones(): void
    {
        $owner = User::factory()->create();
        $shared = HealthRecord::factory()->for($owner)->create();
        $shared->members()->attach($this->user);

        Livewire::test('salud.mediciones', ['recordId' => $shared->id])
            ->set('measurementValue', '78')
            ->set('measurementDate', '2026-07-01')
            ->call('addMeasurement')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_measurements', [
            'health_record_id' => $shared->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_eliminar_la_historia_borra_sus_mediciones(): void
    {
        $measurement = HealthMeasurement::factory()->for($this->record, 'record')->for($this->user)->create();

        $this->record->delete();

        $this->assertDatabaseMissing('health_measurements', ['id' => $measurement->id]);
    }
}
