<?php

namespace Tests\Feature;

use App\Models\HealthContact;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaludContactosTest extends TestCase
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
        Livewire::test('salud.contactos', ['recordId' => $this->record->id])
            ->assertSee('Todavía no anotaste ningún contacto.');
    }

    public function test_puede_anotar_un_contacto(): void
    {
        Livewire::test('salud.contactos', ['recordId' => $this->record->id])
            ->set('contactName', 'Dra. García')
            ->set('contactSpecialty', 'Clínica médica')
            ->set('contactPhone', '11 5555-5555')
            ->set('contactNote', 'Atiende martes y jueves')
            ->call('addContact')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_contacts', [
            'health_record_id' => $this->record->id,
            'user_id' => $this->user->id,
            'name' => 'Dra. García',
            'specialty' => 'Clínica médica',
            'phone' => '11 5555-5555',
            'note' => 'Atiende martes y jueves',
        ]);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        Livewire::test('salud.contactos', ['recordId' => $this->record->id])
            ->set('contactName', '')
            ->call('addContact')
            ->assertHasErrors(['contactName' => 'required']);

        $this->assertDatabaseCount('health_contacts', 0);
    }

    public function test_el_telefono_se_muestra_como_link_para_llamar(): void
    {
        HealthContact::factory()->for($this->record, 'record')->for($this->user)->create([
            'phone' => '11 5555-5555',
        ]);

        Livewire::test('salud.contactos', ['recordId' => $this->record->id])
            ->assertSeeHtml('href="tel:1155555555"');
    }

    public function test_lista_los_contactos_por_nombre(): void
    {
        HealthContact::factory()->for($this->record, 'record')->for($this->user)->create(['name' => 'Zulema Pérez']);
        HealthContact::factory()->for($this->record, 'record')->for($this->user)->create(['name' => 'Ana López']);

        Livewire::test('salud.contactos', ['recordId' => $this->record->id])
            ->assertSeeInOrder(['Ana López', 'Zulema Pérez']);
    }

    public function test_puede_editar_un_contacto(): void
    {
        $contact = HealthContact::factory()->for($this->record, 'record')->for($this->user)->create(['name' => 'Viejo']);

        Livewire::test('salud.contactos', ['recordId' => $this->record->id])
            ->call('startEditingContact', $contact->id)
            ->set('editContactName', 'Dr. Nuevo')
            ->set('editContactPhone', '11 4444-4444')
            ->call('saveContact')
            ->assertHasNoErrors();

        $contact->refresh();
        $this->assertSame('Dr. Nuevo', $contact->name);
        $this->assertSame('11 4444-4444', $contact->phone);
    }

    public function test_puede_eliminar_un_contacto(): void
    {
        $contact = HealthContact::factory()->for($this->record, 'record')->for($this->user)->create();

        Livewire::test('salud.contactos', ['recordId' => $this->record->id])
            ->call('deleteContact', $contact->id);

        $this->assertDatabaseMissing('health_contacts', ['id' => $contact->id]);
    }

    public function test_no_puede_operar_los_contactos_de_una_historia_ajena(): void
    {
        $other = User::factory()->create();
        $foreign = HealthRecord::factory()->for($other)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('salud.contactos', ['recordId' => $foreign->id])
            ->set('contactName', 'Intruso')
            ->call('addContact');
    }

    public function test_quien_tiene_la_historia_compartida_puede_anotar_contactos(): void
    {
        $owner = User::factory()->create();
        $shared = HealthRecord::factory()->for($owner)->create();
        $shared->members()->attach($this->user);

        Livewire::test('salud.contactos', ['recordId' => $shared->id])
            ->set('contactName', 'Dra. García')
            ->call('addContact')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_contacts', [
            'health_record_id' => $shared->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_eliminar_la_historia_borra_sus_contactos(): void
    {
        $contact = HealthContact::factory()->for($this->record, 'record')->for($this->user)->create();

        $this->record->delete();

        $this->assertDatabaseMissing('health_contacts', ['id' => $contact->id]);
    }
}
