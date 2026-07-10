<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TodoImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_muestra_la_consigna_para_la_ia_al_abrir_el_panel(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->assertSee('Consigna para la IA')
            ->assertSee('Armame un plan de tareas');
    }

    public function test_revisa_e_importa_una_lista_con_fechas_repeticiones_y_etiquetas(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', implode("\n", [
                'Cortarme las uñas | repite: semanal | vence: 2026-07-12 | etiquetas: higiene',
                'Sacar turno con la dentista | vence: 15/08/2026 | importante',
                'Comprar alicate | urgente | notas: el bueno',
            ]))
            ->call('previewImport')
            ->assertSee('3 tareas listas para anotar')
            ->call('confirmImport')
            ->assertSee('Listo, anoté las 3 tareas.')
            ->assertSet('importText', '');

        $unas = $this->user->todos()->where('title', 'Cortarme las uñas')->first();
        $this->assertSame('2026-07-12', $unas->due_date->toDateString());
        $this->assertSame('semanal', $unas->repeat_interval);
        $this->assertSame(['higiene'], $unas->tags->pluck('name')->all());

        $dentista = $this->user->todos()->where('title', 'Sacar turno con la dentista')->first();
        $this->assertSame('2026-08-15', $dentista->due_date->toDateString());
        $this->assertTrue($dentista->important);
        $this->assertFalse($dentista->urgent);

        $alicate = $this->user->todos()->where('title', 'Comprar alicate')->first();
        $this->assertTrue($alicate->urgent);
        $this->assertSame('el bueno', $alicate->notes);
    }

    public function test_importa_al_proyecto_elegido(): void
    {
        $project = Project::factory()->for($this->user)->create();

        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', "Una\nDos")
            ->set('importProjectId', (string) $project->id)
            ->call('previewImport')
            ->call('confirmImport');

        $this->assertSame(2, $project->todos()->count());
    }

    public function test_con_un_proyecto_filtrado_el_panel_arranca_en_ese_proyecto(): void
    {
        $project = Project::factory()->for($this->user)->create();

        Livewire::test('todo.todo-list')
            ->call('filterProject', $project->id)
            ->call('startImporting')
            ->assertSet('importProjectId', (string) $project->id);
    }

    public function test_no_se_puede_importar_al_proyecto_de_otra_persona(): void
    {
        $ajeno = Project::factory()->for(User::factory()->create())->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', 'Una tarea')
            ->set('importProjectId', (string) $ajeno->id)
            ->call('previewImport')
            ->call('confirmImport');
    }

    public function test_importa_las_lineas_validas_y_deja_las_fallidas_en_el_campo(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', "Buena\nMala | repite: semanal\nOtra buena")
            ->call('previewImport')
            ->assertSee('2 tareas listas para anotar')
            ->assertSee('1 línea que no entendí')
            ->assertSee('Para repetirse necesita una fecha en «vence:».')
            ->call('confirmImport')
            ->assertSee('Listo, anoté las 2 tareas.')
            ->assertSet('importText', 'Mala | repite: semanal');

        $this->assertSame(2, $this->user->todos()->count());
        $this->assertNull($this->user->todos()->where('title', 'Mala')->first());
    }

    public function test_si_ninguna_linea_se_entiende_no_importa_nada(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', 'Tarea | prioridad: alta')
            ->call('previewImport')
            ->call('confirmImport')
            ->assertHasErrors('importText');

        $this->assertSame(0, $this->user->todos()->count());
    }

    public function test_editar_el_texto_despues_de_revisar_obliga_a_revisar_de_nuevo(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', 'Una tarea')
            ->call('previewImport')
            ->assertSet('importReviewed', true)
            ->set('importText', 'Otra cosa')
            ->assertSet('importReviewed', false)
            ->call('confirmImport');

        $this->assertSame(0, $this->user->todos()->count());
    }

    public function test_rechaza_mas_de_cien_lineas(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', implode("\n", array_map(fn ($i) => "Tarea {$i}", range(1, 101))))
            ->call('previewImport')
            ->assertHasErrors('importText');

        $this->assertSame(0, $this->user->todos()->count());
    }

    public function test_con_el_texto_vacio_pide_pegar_la_lista(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->call('previewImport')
            ->assertHasErrors('importText');
    }

    public function test_una_recurrente_importada_genera_la_proxima_al_completarse(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', 'Afeitarme | repite: semanal | vence: '.today()->toDateString())
            ->call('previewImport')
            ->call('confirmImport');

        $todo = $this->user->todos()->where('title', 'Afeitarme')->firstOrFail();

        Livewire::test('todo.todo-list')->call('toggle', $todo->id);

        $proxima = $this->user->todos()->whereNull('completed_at')->where('title', 'Afeitarme')->first();
        $this->assertSame(today()->addWeek()->toDateString(), $proxima->due_date->toDateString());
        $this->assertSame('semanal', $proxima->repeat_interval);
    }

    public function test_cancelar_limpia_el_panel(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startImporting')
            ->set('importText', 'Algo')
            ->call('previewImport')
            ->call('cancelImporting')
            ->assertSet('importing', false)
            ->assertSet('importText', '')
            ->assertSet('importPreview', []);
    }
}
