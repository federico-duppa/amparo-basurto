<?php

namespace Tests\Feature;

use App\Models\HealthEntry;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaludPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_la_pagina_de_salud_renderiza_el_componente(): void
    {
        $this->get('/salud')
            ->assertOk()
            ->assertSeeLivewire('salud.panel');
    }

    public function test_muestra_el_estado_vacio_con_la_voz_de_amparo(): void
    {
        $this->get('/salud')
            ->assertSee('Todavía no armaste ninguna historia clínica. Puede ser tuya, de un familiar o de un paciente: contame de quién es y empezamos.');
    }

    public function test_puede_crear_una_historia(): void
    {
        Livewire::test('salud.panel')
            ->set('newTitular', 'Rosa Basurto')
            ->set('newNacimiento', '1954-03-12')
            ->call('createRecord')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_records', [
            'user_id' => $this->user->id,
            'titular' => 'Rosa Basurto',
        ]);
    }

    public function test_el_titular_es_obligatorio(): void
    {
        Livewire::test('salud.panel')
            ->set('newTitular', '')
            ->call('createRecord')
            ->assertHasErrors(['newTitular' => 'required']);

        $this->assertDatabaseCount('health_records', 0);
    }

    public function test_el_nacimiento_no_puede_ser_futuro(): void
    {
        Livewire::test('salud.panel')
            ->set('newTitular', 'Rosa Basurto')
            ->set('newNacimiento', now()->addYear()->format('Y-m-d'))
            ->call('createRecord')
            ->assertHasErrors('newNacimiento');
    }

    public function test_puede_crear_varias_historias(): void
    {
        HealthRecord::factory()->for($this->user)->create(['titular' => 'Titular Uno']);

        Livewire::test('salud.panel')
            ->set('addingRecord', true)
            ->set('newTitular', 'Titular Dos')
            ->call('createRecord')
            ->assertHasNoErrors();

        $this->assertSame(2, $this->user->healthRecords()->count());
    }

    public function test_el_duenio_puede_editar_al_titular(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create(['titular' => 'Nombre Viejo']);

        Livewire::test('salud.panel')
            ->call('startEditingRecord')
            ->set('editTitular', 'Nombre Nuevo')
            ->set('editNacimiento', '1990-01-15')
            ->call('saveRecord')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame('Nombre Nuevo', $record->titular);
        $this->assertSame('1990-01-15', $record->nacimiento->format('Y-m-d'));
    }

    public function test_cualquiera_con_acceso_puede_editar_la_ficha(): void
    {
        $owner = User::factory()->create();
        $record = HealthRecord::factory()->for($owner)->create();
        $record->members()->attach($this->user);

        Livewire::test('salud.panel')
            ->call('startEditingFicha')
            ->set('fichaGrupo', '0+')
            ->set('fichaObraSocial', 'OSDE 210 · 12345')
            ->set('fichaAlergias', 'Penicilina')
            ->set('fichaCondiciones', 'Hipertensión')
            ->set('fichaMedicacion', 'Enalapril 10 mg, una por día')
            ->call('saveFicha')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame('0+', $record->grupo_sanguineo);
        $this->assertSame('Penicilina', $record->alergias);
        $this->assertSame('Enalapril 10 mg, una por día', $record->medicacion);
    }

    public function test_quien_tiene_la_historia_compartida_no_puede_editar_al_titular(): void
    {
        $owner = User::factory()->create();
        $record = HealthRecord::factory()->for($owner)->create();
        $record->members()->attach($this->user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('salud.panel')->call('startEditingRecord');
    }

    public function test_no_puede_ver_ni_operar_una_historia_ajena(): void
    {
        $other = User::factory()->create();
        $record = HealthRecord::factory()->for($other)->create(['titular' => 'Titular Ajeno']);

        $this->get('/salud')->assertDontSee('Titular Ajeno');

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('salud.panel')->call('selectRecord', $record->id);
    }

    public function test_puede_anotar_una_entrada(): void
    {
        HealthRecord::factory()->for($this->user)->create();

        Livewire::test('salud.panel')
            ->set('entryDate', '2026-06-20')
            ->set('entryType', 'estudio')
            ->set('entryTitle', 'Análisis de sangre')
            ->set('entryDetail', 'Colesterol un poco alto.')
            ->call('addEntry')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_entries', [
            'user_id' => $this->user->id,
            'type' => 'estudio',
            'title' => 'Análisis de sangre',
            'detail' => 'Colesterol un poco alto.',
        ]);
    }

    public function test_el_titulo_y_la_fecha_de_la_entrada_son_obligatorios(): void
    {
        HealthRecord::factory()->for($this->user)->create();

        Livewire::test('salud.panel')
            ->set('entryDate', '')
            ->set('entryTitle', '')
            ->call('addEntry')
            ->assertHasErrors(['entryDate' => 'required', 'entryTitle' => 'required']);
    }

    public function test_el_tipo_de_entrada_tiene_que_ser_valido(): void
    {
        HealthRecord::factory()->for($this->user)->create();

        Livewire::test('salud.panel')
            ->set('entryTitle', 'Algo')
            ->set('entryType', 'cirugia-mayor')
            ->call('addEntry')
            ->assertHasErrors('entryType');
    }

    public function test_puede_editar_una_entrada(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $entry = HealthEntry::factory()->for($record, 'record')->for($this->user)->create(['title' => 'Viejo']);

        Livewire::test('salud.panel')
            ->call('startEditingEntry', $entry->id)
            ->set('editEntryTitle', 'Nuevo título')
            ->set('editEntryType', 'nota')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $entry->refresh();
        $this->assertSame('Nuevo título', $entry->title);
        $this->assertSame('nota', $entry->type);
    }

    public function test_puede_eliminar_una_entrada(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $entry = HealthEntry::factory()->for($record, 'record')->for($this->user)->create();

        Livewire::test('salud.panel')->call('deleteEntry', $entry->id);

        $this->assertDatabaseMissing('health_entries', ['id' => $entry->id]);
    }

    public function test_filtra_las_entradas_por_tipo(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        HealthEntry::factory()->for($record, 'record')->for($this->user)->create(['type' => 'consulta', 'title' => 'Turno con la clínica']);
        HealthEntry::factory()->for($record, 'record')->for($this->user)->create(['type' => 'vacuna', 'title' => 'Antigripal']);

        Livewire::test('salud.panel')
            ->call('filterByType', 'vacuna')
            ->assertSee('Antigripal')
            ->assertDontSee('Turno con la clínica');
    }

    public function test_busca_en_titulo_y_detalle(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        HealthEntry::factory()->for($record, 'record')->for($this->user)->create(['title' => 'Control anual', 'detail' => 'Todo bien']);
        HealthEntry::factory()->for($record, 'record')->for($this->user)->create(['title' => 'Radiografía', 'detail' => 'Muñeca izquierda']);

        Livewire::test('salud.panel')
            ->set('search', 'muñeca')
            ->assertSee('Radiografía')
            ->assertDontSee('Control anual');
    }

    public function test_la_busqueda_sin_resultados_habla_con_la_voz_de_amparo(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        HealthEntry::factory()->for($record, 'record')->for($this->user)->create(['title' => 'Control anual']);

        Livewire::test('salud.panel')
            ->set('search', 'zzzz')
            ->assertSee('No encontré nada con eso.');
    }

    public function test_el_duenio_puede_compartir_la_historia(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $friend = User::factory()->create(['username' => 'hermana']);

        Livewire::test('salud.panel')
            ->set('shareUsername', 'Hermana')
            ->call('share')
            ->assertHasNoErrors();

        $this->assertTrue($record->members()->whereKey($friend->id)->exists());
    }

    public function test_compartir_avisa_si_no_encuentra_el_usuario(): void
    {
        HealthRecord::factory()->for($this->user)->create();

        Livewire::test('salud.panel')
            ->set('shareUsername', 'nadie')
            ->call('share')
            ->assertHasErrors('shareUsername');
    }

    public function test_no_puede_compartir_consigo_mismo_ni_repetir(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $this->user->update(['username' => 'yo']);
        $friend = User::factory()->create(['username' => 'amiga']);
        $record->members()->attach($friend);

        Livewire::test('salud.panel')
            ->set('shareUsername', 'yo')
            ->call('share')
            ->assertHasErrors('shareUsername');

        Livewire::test('salud.panel')
            ->set('shareUsername', 'amiga')
            ->call('share')
            ->assertHasErrors('shareUsername');
    }

    public function test_el_duenio_puede_dejar_de_compartir(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $friend = User::factory()->create();
        $record->members()->attach($friend);

        Livewire::test('salud.panel')->call('unshare', $friend->id);

        $this->assertFalse($record->members()->whereKey($friend->id)->exists());
    }

    public function test_quien_tiene_la_historia_compartida_puede_anotar_entradas(): void
    {
        $owner = User::factory()->create();
        $record = HealthRecord::factory()->for($owner)->create(['titular' => 'Titular Compartido']);
        $record->members()->attach($this->user);

        $this->get('/salud')->assertSee('Titular Compartido');

        Livewire::test('salud.panel')
            ->set('entryTitle', 'Consulta de control')
            ->call('addEntry')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('health_entries', [
            'health_record_id' => $record->id,
            'user_id' => $this->user->id,
            'title' => 'Consulta de control',
        ]);
    }

    public function test_quien_tiene_la_historia_compartida_no_puede_compartirla(): void
    {
        $owner = User::factory()->create();
        $record = HealthRecord::factory()->for($owner)->create();
        $record->members()->attach($this->user);
        User::factory()->create(['username' => 'tercero']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('salud.panel')
            ->set('shareUsername', 'tercero')
            ->call('share');
    }

    public function test_quien_tiene_la_historia_compartida_no_puede_eliminarla(): void
    {
        $owner = User::factory()->create();
        $record = HealthRecord::factory()->for($owner)->create();
        $record->members()->attach($this->user);

        try {
            Livewire::test('salud.panel')->call('deleteRecord', $record->id);
            $this->fail('Se esperaba un 404 al intentar eliminar una historia ajena.');
        } catch (ModelNotFoundException) {
            // La historia sigue existiendo.
        }

        $this->assertDatabaseHas('health_records', ['id' => $record->id]);
    }

    public function test_eliminar_la_historia_borra_sus_entradas(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();
        $entry = HealthEntry::factory()->for($record, 'record')->for($this->user)->create();

        Livewire::test('salud.panel')->call('deleteRecord', $record->id);

        $this->assertDatabaseMissing('health_records', ['id' => $record->id]);
        $this->assertDatabaseMissing('health_entries', ['id' => $entry->id]);
    }
}
