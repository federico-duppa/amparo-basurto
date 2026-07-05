<?php

namespace Tests\Feature;

use App\Models\HealthRecord;
use App\Models\HealthVaccine;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaludVacunasTest extends TestCase
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
        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            ->assertSee('Todavía no cargaste ninguna vacuna.');
    }

    public function test_puede_anotar_una_vacuna(): void
    {
        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            ->set('vaccineName', 'Antigripal')
            ->set('vaccineDose', 'Refuerzo')
            ->set('vaccineAppliedOn', '2026-05-10')
            ->set('vaccineNextDueOn', '2027-05-10')
            ->set('vaccineNote', 'En la farmacia del barrio')
            ->call('addVaccine')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_vaccines', [
            'health_record_id' => $this->record->id,
            'user_id' => $this->user->id,
            'name' => 'Antigripal',
            'dose' => 'Refuerzo',
            'note' => 'En la farmacia del barrio',
        ]);
    }

    public function test_el_nombre_y_la_fecha_son_obligatorios(): void
    {
        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            ->set('vaccineName', '')
            ->set('vaccineAppliedOn', '')
            ->call('addVaccine')
            ->assertHasErrors(['vaccineName' => 'required', 'vaccineAppliedOn' => 'required']);
    }

    public function test_la_proxima_dosis_tiene_que_ser_posterior_a_la_aplicacion(): void
    {
        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            ->set('vaccineName', 'Hepatitis B')
            ->set('vaccineAppliedOn', '2026-05-10')
            ->set('vaccineNextDueOn', '2026-05-01')
            ->call('addVaccine')
            ->assertHasErrors('vaccineNextDueOn');
    }

    public function test_agrupa_las_aplicaciones_por_vacuna(): void
    {
        HealthVaccine::factory()->for($this->record, 'record')->for($this->user)->create([
            'name' => 'Hepatitis B', 'dose' => '1ª dosis', 'applied_on' => '2026-01-15', 'next_due_on' => null,
        ]);
        HealthVaccine::factory()->for($this->record, 'record')->for($this->user)->create([
            'name' => 'Hepatitis B', 'dose' => '2ª dosis', 'applied_on' => '2026-02-15', 'next_due_on' => null,
        ]);
        HealthVaccine::factory()->for($this->record, 'record')->for($this->user)->create([
            'name' => 'Antigripal', 'dose' => null, 'applied_on' => '2026-04-01', 'next_due_on' => null,
        ]);

        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            // Grupos en orden alfabético; dentro del grupo, de la primera a la última dosis.
            ->assertSeeInOrder(['Antigripal', 'Hepatitis B', '1ª dosis', '2ª dosis']);
    }

    public function test_avisa_cuando_una_proxima_dosis_quedo_pendiente(): void
    {
        HealthVaccine::factory()->for($this->record, 'record')->for($this->user)->create([
            'applied_on' => now()->subYear(),
            'next_due_on' => now()->subDays(15),
        ]);

        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            ->assertSee('Dosis pendiente desde el '.now()->subDays(15)->format('d/m/Y'));
    }

    public function test_muestra_la_proxima_dosis_cuando_esta_anotada(): void
    {
        HealthVaccine::factory()->for($this->record, 'record')->for($this->user)->create([
            'applied_on' => now()->subMonths(2),
            'next_due_on' => now()->addMonths(10),
        ]);

        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            ->assertSee('Próxima dosis el '.now()->addMonths(10)->format('d/m/Y'));
    }

    public function test_puede_editar_una_aplicacion(): void
    {
        $vaccine = HealthVaccine::factory()->for($this->record, 'record')->for($this->user)->create([
            'name' => 'Antigripal', 'dose' => null,
        ]);

        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            ->call('startEditingVaccine', $vaccine->id)
            ->set('editVaccineDose', 'Única')
            ->set('editVaccineNextDueOn', '')
            ->call('saveVaccine')
            ->assertHasNoErrors();

        $this->assertSame('Única', $vaccine->refresh()->dose);
    }

    public function test_puede_eliminar_una_aplicacion(): void
    {
        $vaccine = HealthVaccine::factory()->for($this->record, 'record')->for($this->user)->create();

        Livewire::test('salud.vacunas', ['recordId' => $this->record->id])
            ->call('deleteVaccine', $vaccine->id);

        $this->assertDatabaseMissing('health_vaccines', ['id' => $vaccine->id]);
    }

    public function test_no_puede_operar_el_carnet_de_una_historia_ajena(): void
    {
        $other = User::factory()->create();
        $foreign = HealthRecord::factory()->for($other)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('salud.vacunas', ['recordId' => $foreign->id])
            ->set('vaccineName', 'Antigripal')
            ->set('vaccineAppliedOn', '2026-05-10')
            ->call('addVaccine');
    }

    public function test_quien_tiene_la_historia_compartida_puede_anotar_vacunas(): void
    {
        $owner = User::factory()->create();
        $shared = HealthRecord::factory()->for($owner)->create();
        $shared->members()->attach($this->user);

        Livewire::test('salud.vacunas', ['recordId' => $shared->id])
            ->set('vaccineName', 'Antitetánica')
            ->set('vaccineAppliedOn', '2026-05-10')
            ->call('addVaccine')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_vaccines', [
            'health_record_id' => $shared->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_eliminar_la_historia_borra_su_carnet(): void
    {
        $vaccine = HealthVaccine::factory()->for($this->record, 'record')->for($this->user)->create();

        $this->record->delete();

        $this->assertDatabaseMissing('health_vaccines', ['id' => $vaccine->id]);
    }
}
