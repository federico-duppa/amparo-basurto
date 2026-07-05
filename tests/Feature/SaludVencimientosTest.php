<?php

namespace Tests\Feature;

use App\Models\HealthRecord;
use App\Models\HealthReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaludVencimientosTest extends TestCase
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
        Livewire::test('salud.vencimientos', ['recordId' => $this->record->id])
            ->assertSee('Todavía no seguís ningún vencimiento.');
    }

    public function test_puede_anotar_un_vencimiento(): void
    {
        Livewire::test('salud.vencimientos', ['recordId' => $this->record->id])
            ->set('reminderName', 'Control clínico')
            ->set('reminderExpiresOn', now()->addMonths(3)->format('Y-m-d'))
            ->set('reminderIntervalMonths', 12)
            ->set('reminderNote', 'Con la Dra. García')
            ->call('addReminder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_reminders', [
            'health_record_id' => $this->record->id,
            'user_id' => $this->user->id,
            'name' => 'Control clínico',
            'interval_months' => 12,
            'note' => 'Con la Dra. García',
        ]);
    }

    public function test_el_nombre_y_la_fecha_son_obligatorios(): void
    {
        Livewire::test('salud.vencimientos', ['recordId' => $this->record->id])
            ->set('reminderName', '')
            ->set('reminderExpiresOn', '')
            ->call('addReminder')
            ->assertHasErrors(['reminderName' => 'required', 'reminderExpiresOn' => 'required']);

        $this->assertDatabaseCount('health_reminders', 0);
    }

    public function test_muestra_el_estado_de_cada_vencimiento(): void
    {
        HealthReminder::factory()->for($this->record, 'record')->for($this->user)->create([
            'name' => 'Receta vencida',
            'expires_on' => now()->subDays(10),
            'interval_months' => null,
        ]);
        HealthReminder::factory()->for($this->record, 'record')->for($this->user)->create([
            'name' => 'Control cerca',
            'expires_on' => now()->addDays(10),
            'interval_months' => null,
        ]);
        HealthReminder::factory()->for($this->record, 'record')->for($this->user)->create([
            'name' => 'Estudio lejano',
            'expires_on' => now()->addMonths(6),
            'interval_months' => null,
        ]);

        Livewire::test('salud.vencimientos', ['recordId' => $this->record->id])
            ->assertSee('Vencido')
            ->assertSee('Por vencer')
            ->assertSee('Al día')
            // Ordenado por urgencia: lo vencido primero, lo lejano al final.
            ->assertSeeInOrder(['Receta vencida', 'Control cerca', 'Estudio lejano']);
    }

    public function test_puede_editar_un_vencimiento(): void
    {
        $reminder = HealthReminder::factory()->for($this->record, 'record')->for($this->user)->create(['name' => 'Viejo']);

        Livewire::test('salud.vencimientos', ['recordId' => $this->record->id])
            ->call('startEditingReminder', $reminder->id)
            ->set('editReminderName', 'Control con cardiología')
            ->set('editReminderExpiresOn', '2027-03-01')
            ->call('saveReminder')
            ->assertHasNoErrors();

        $reminder->refresh();
        $this->assertSame('Control con cardiología', $reminder->name);
        $this->assertSame('2027-03-01', $reminder->expires_on->format('Y-m-d'));
    }

    public function test_ya_esta_sugiere_la_proxima_fecha_segun_la_periodicidad(): void
    {
        $reminder = HealthReminder::factory()->for($this->record, 'record')->for($this->user)->create([
            'expires_on' => '2026-07-10',
            'interval_months' => 6,
        ]);

        Livewire::test('salud.vencimientos', ['recordId' => $this->record->id])
            ->call('startRenewingReminder', $reminder->id)
            ->assertSet('renewReminderExpiresOn', '2027-01-10')
            ->call('saveRenewal')
            ->assertHasNoErrors();

        $this->assertSame('2027-01-10', $reminder->refresh()->expires_on->format('Y-m-d'));
    }

    public function test_ya_esta_sin_periodicidad_pide_la_fecha(): void
    {
        $reminder = HealthReminder::factory()->for($this->record, 'record')->for($this->user)->create([
            'interval_months' => null,
        ]);

        Livewire::test('salud.vencimientos', ['recordId' => $this->record->id])
            ->call('startRenewingReminder', $reminder->id)
            ->assertSet('renewReminderExpiresOn', '')
            ->call('saveRenewal')
            ->assertHasErrors(['renewReminderExpiresOn' => 'required']);
    }

    public function test_puede_eliminar_un_vencimiento(): void
    {
        $reminder = HealthReminder::factory()->for($this->record, 'record')->for($this->user)->create();

        Livewire::test('salud.vencimientos', ['recordId' => $this->record->id])
            ->call('deleteReminder', $reminder->id);

        $this->assertDatabaseMissing('health_reminders', ['id' => $reminder->id]);
    }

    public function test_no_puede_operar_los_vencimientos_de_una_historia_ajena(): void
    {
        $other = User::factory()->create();
        $foreign = HealthRecord::factory()->for($other)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('salud.vencimientos', ['recordId' => $foreign->id])
            ->set('reminderName', 'Intruso')
            ->set('reminderExpiresOn', '2027-01-01')
            ->call('addReminder');
    }

    public function test_quien_tiene_la_historia_compartida_puede_anotar_vencimientos(): void
    {
        $owner = User::factory()->create();
        $shared = HealthRecord::factory()->for($owner)->create();
        $shared->members()->attach($this->user);

        Livewire::test('salud.vencimientos', ['recordId' => $shared->id])
            ->set('reminderName', 'Control clínico')
            ->set('reminderExpiresOn', '2027-01-01')
            ->call('addReminder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_reminders', [
            'health_record_id' => $shared->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_eliminar_la_historia_borra_sus_vencimientos(): void
    {
        $reminder = HealthReminder::factory()->for($this->record, 'record')->for($this->user)->create();

        $this->record->delete();

        $this->assertDatabaseMissing('health_reminders', ['id' => $reminder->id]);
    }
}
