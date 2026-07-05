<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TodoListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_la_pagina_de_tareas_renderiza_el_componente(): void
    {
        $this->get('/tareas')
            ->assertOk()
            ->assertSeeLivewire('todo.todo-list');
    }

    public function test_la_raiz_redirige_a_tareas(): void
    {
        $this->get('/')->assertRedirect('/tareas');
    }

    public function test_muestra_el_estado_vacio_con_la_voz_de_amparo(): void
    {
        $this->get('/tareas')
            ->assertSee('Todavía no anotaste nada. Cuando quieras, empezamos.');
    }

    public function test_puede_agregar_una_tarea(): void
    {
        Livewire::test('todo.todo-list')
            ->set('title', 'Comprar yerba')
            ->call('add')
            ->assertSet('title', '')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('todos', [
            'title' => 'Comprar yerba',
            'user_id' => $this->user->id,
            'completed_at' => null,
            'due_date' => null,
            'project_id' => null,
            'urgent' => false,
            'important' => false,
            'repeat_interval' => null,
        ]);
    }

    public function test_puede_agregar_una_tarea_con_todos_los_detalles(): void
    {
        $project = Project::factory()->for($this->user)->create();

        Livewire::test('todo.todo-list')
            ->set('title', 'Pagar expensas')
            ->set('dueDate', today()->addDays(3)->toDateString())
            ->set('repeat', 'mensual')
            ->set('projectId', (string) $project->id)
            ->set('urgent', true)
            ->set('important', true)
            ->call('add')
            ->assertHasNoErrors();

        $todo = $this->user->todos()->sole();

        $this->assertSame('Pagar expensas', $todo->title);
        $this->assertSame(today()->addDays(3)->toDateString(), $todo->due_date->toDateString());
        $this->assertSame('mensual', $todo->repeat_interval);
        $this->assertSame($project->id, $todo->project_id);
        $this->assertTrue($todo->urgent);
        $this->assertTrue($todo->important);
    }

    public function test_el_panel_de_detalles_muestra_los_campos_y_lee_el_cuadrante(): void
    {
        Project::factory()->for($this->user)->create(['name' => 'Mudanza']);

        Livewire::test('todo.todo-list')
            ->set('showDetails', true)
            ->assertSee('Vence')
            ->assertSee('Se repite')
            ->assertSee('Mudanza')
            ->assertSee('Prioridad')
            ->set('urgent', true)
            ->set('important', true)
            ->assertSee('Urgente e importante: de las primeras a encarar.');
    }

    public function test_el_titulo_es_obligatorio(): void
    {
        Livewire::test('todo.todo-list')
            ->set('title', '')
            ->call('add')
            ->assertHasErrors(['title' => 'required']);

        $this->assertDatabaseCount('todos', 0);
    }

    public function test_el_titulo_no_puede_superar_255_caracteres(): void
    {
        Livewire::test('todo.todo-list')
            ->set('title', str_repeat('a', 256))
            ->call('add')
            ->assertHasErrors(['title' => 'max']);
    }

    public function test_la_repeticion_necesita_fecha_de_vencimiento(): void
    {
        Livewire::test('todo.todo-list')
            ->set('title', 'Regar las plantas')
            ->set('repeat', 'semanal')
            ->call('add')
            ->assertHasErrors('repeat')
            ->assertSet('showDetails', true);

        $this->assertDatabaseCount('todos', 0);
    }

    public function test_no_puede_asignar_una_tarea_a_un_proyecto_ajeno(): void
    {
        $ajeno = Project::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('todo.todo-list')
            ->set('title', 'Colarme en otro proyecto')
            ->set('projectId', (string) $ajeno->id)
            ->call('add');
    }

    public function test_puede_completar_y_reabrir_una_tarea(): void
    {
        $todo = Todo::factory()->for($this->user)->create();

        Livewire::test('todo.todo-list')->call('toggle', $todo->id);
        $this->assertNotNull($todo->fresh()->completed_at);

        Livewire::test('todo.todo-list')->call('toggle', $todo->id);
        $this->assertNull($todo->fresh()->completed_at);
    }

    public function test_completar_una_recurrente_crea_la_proxima_ocurrencia(): void
    {
        $todo = Todo::factory()->for($this->user)
            ->dueOn(today()->toDateString())
            ->repeats('semanal')
            ->urgent()
            ->create();

        Livewire::test('todo.todo-list')->call('toggle', $todo->id);

        $this->assertNotNull($todo->fresh()->completed_at);

        $proxima = $this->user->todos()->whereNull('completed_at')->sole();

        $this->assertSame($todo->title, $proxima->title);
        $this->assertSame(today()->addWeek()->toDateString(), $proxima->due_date->toDateString());
        $this->assertSame('semanal', $proxima->repeat_interval);
        $this->assertTrue($proxima->urgent);
    }

    public function test_completar_una_recurrente_atrasada_no_genera_otra_atrasada(): void
    {
        $todo = Todo::factory()->for($this->user)
            ->dueOn(today()->subDays(10)->toDateString())
            ->repeats('semanal')
            ->create();

        Livewire::test('todo.todo-list')->call('toggle', $todo->id);

        $proxima = $this->user->todos()->whereNull('completed_at')->sole();

        // -10 días → -3 → +4: la primera fecha del ciclo que no queda en el pasado.
        $this->assertSame(today()->addDays(4)->toDateString(), $proxima->due_date->toDateString());
    }

    public function test_reabrir_una_recurrente_no_crea_otra_ocurrencia(): void
    {
        $todo = Todo::factory()->for($this->user)
            ->dueOn(today()->toDateString())
            ->repeats('diaria')
            ->create();

        Livewire::test('todo.todo-list')->call('toggle', $todo->id);
        Livewire::test('todo.todo-list')->call('toggle', $todo->id);

        // La ocurrencia generada al completar queda; reabrir no suma otra.
        $this->assertSame(2, $this->user->todos()->count());
    }

    public function test_puede_eliminar_una_tarea(): void
    {
        $todo = Todo::factory()->for($this->user)->create();

        Livewire::test('todo.todo-list')->call('delete', $todo->id);

        $this->assertModelMissing($todo);
    }

    public function test_puede_editar_una_tarea(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $todo = Todo::factory()->for($this->user)->create(['title' => 'Comprar yerba']);

        Livewire::test('todo.todo-list')
            ->call('startEditing', $todo->id)
            ->assertSet('title', 'Comprar yerba')
            ->assertSet('showDetails', true)
            ->set('title', '  Comprar café  ')
            ->set('dueDate', today()->toDateString())
            ->set('projectId', (string) $project->id)
            ->set('important', true)
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSet('editingId', null);

        $todo->refresh();

        $this->assertSame('Comprar café', $todo->title);
        $this->assertSame(today()->toDateString(), $todo->due_date->toDateString());
        $this->assertSame($project->id, $todo->project_id);
        $this->assertTrue($todo->important);
    }

    public function test_el_titulo_editado_es_obligatorio(): void
    {
        $todo = Todo::factory()->for($this->user)->create(['title' => 'Algo']);

        Livewire::test('todo.todo-list')
            ->call('startEditing', $todo->id)
            ->set('title', '')
            ->call('saveEdit')
            ->assertHasErrors(['title' => 'required']);

        $this->assertSame('Algo', $todo->fresh()->title);
    }

    public function test_no_puede_editar_tareas_ajenas(): void
    {
        $ajena = Todo::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('todo.todo-list')->call('startEditing', $ajena->id);
    }

    public function test_puede_limpiar_las_completadas(): void
    {
        $completa = Todo::factory()->for($this->user)->completed()->create();
        $pendiente = Todo::factory()->for($this->user)->create();

        Livewire::test('todo.todo-list')->call('clearCompleted');

        $this->assertModelMissing($completa);
        $this->assertModelExists($pendiente);
    }

    public function test_limpiar_completadas_no_toca_las_de_otros(): void
    {
        $ajenaCompleta = Todo::factory()->completed()->create();
        $propiaCompleta = Todo::factory()->for($this->user)->completed()->create();

        Livewire::test('todo.todo-list')->call('clearCompleted');

        $this->assertModelExists($ajenaCompleta);
        $this->assertModelMissing($propiaCompleta);
    }

    public function test_limpiar_completadas_respeta_el_filtro_de_proyecto(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $delProyecto = Todo::factory()->for($this->user)->for($project)->completed()->create();
        $suelta = Todo::factory()->for($this->user)->completed()->create();

        Livewire::test('todo.todo-list')
            ->call('filterProject', $project->id)
            ->call('clearCompleted');

        $this->assertModelMissing($delProyecto);
        $this->assertModelExists($suelta);
    }

    public function test_las_pendientes_se_listan_antes_que_las_completadas(): void
    {
        Todo::factory()->for($this->user)->completed()->create(['title' => 'Tarea completada']);
        Todo::factory()->for($this->user)->create(['title' => 'Tarea pendiente']);

        $this->get('/tareas')
            ->assertSeeInOrder(['Tarea pendiente', 'Tarea completada']);
    }

    public function test_las_pendientes_se_ordenan_por_cuadrante_de_eisenhower(): void
    {
        Todo::factory()->for($this->user)->create(['title' => 'Ni urgente ni importante']);
        Todo::factory()->for($this->user)->urgent()->create(['title' => 'Solo urgente']);
        Todo::factory()->for($this->user)->important()->create(['title' => 'Solo importante']);
        Todo::factory()->for($this->user)->urgent()->important()->create(['title' => 'Urgente e importante']);

        $this->get('/tareas')->assertSeeInOrder([
            'Urgente e importante',
            'Solo importante',
            'Solo urgente',
            'Ni urgente ni importante',
        ]);
    }

    public function test_la_vista_hoy_muestra_lo_vencido_y_lo_de_hoy(): void
    {
        Todo::factory()->for($this->user)->dueOn(today()->subDay()->toDateString())->create(['title' => 'Tarea vencida']);
        Todo::factory()->for($this->user)->dueOn(today()->toDateString())->create(['title' => 'Tarea de hoy']);
        Todo::factory()->for($this->user)->dueOn(today()->addDay()->toDateString())->create(['title' => 'Tarea de mañana']);
        Todo::factory()->for($this->user)->create(['title' => 'Tarea sin fecha']);
        Todo::factory()->for($this->user)->dueOn(today()->toDateString())->completed()->create(['title' => 'Tarea ya completada']);

        Livewire::test('todo.todo-list')
            ->call('setView', 'hoy')
            ->assertSee('Tarea vencida')
            ->assertSee('Tarea de hoy')
            ->assertDontSee('Tarea de mañana')
            ->assertDontSee('Tarea sin fecha')
            ->assertDontSee('Tarea ya completada');
    }

    public function test_la_vista_proximas_muestra_solo_lo_que_viene(): void
    {
        Todo::factory()->for($this->user)->dueOn(today()->toDateString())->create(['title' => 'Tarea de hoy']);
        Todo::factory()->for($this->user)->dueOn(today()->addDays(2)->toDateString())->create(['title' => 'Tarea que viene']);
        Todo::factory()->for($this->user)->create(['title' => 'Tarea sin fecha']);

        Livewire::test('todo.todo-list')
            ->call('setView', 'proximas')
            ->assertSee('Tarea que viene')
            ->assertDontSee('Tarea de hoy')
            ->assertDontSee('Tarea sin fecha');
    }

    public function test_no_ve_las_tareas_de_otros_usuarios(): void
    {
        Todo::factory()->create(['title' => 'Tarea de otra persona']);
        Todo::factory()->for($this->user)->create(['title' => 'Tarea propia']);

        $this->get('/tareas')
            ->assertSee('Tarea propia')
            ->assertDontSee('Tarea de otra persona');
    }

    public function test_no_puede_completar_tareas_ajenas(): void
    {
        $ajena = Todo::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('todo.todo-list')->call('toggle', $ajena->id);
    }

    public function test_no_puede_eliminar_tareas_ajenas(): void
    {
        $ajena = Todo::factory()->create();

        try {
            Livewire::test('todo.todo-list')->call('delete', $ajena->id);
            $this->fail('Una tarea ajena no debería poder eliminarse.');
        } catch (ModelNotFoundException) {
            // esperado: para este usuario esa tarea no existe
        }

        $this->assertModelExists($ajena);
    }

    public function test_puede_crear_un_proyecto(): void
    {
        Livewire::test('todo.todo-list')
            ->call('startCreatingProject')
            ->set('projectName', '  Mudanza  ')
            ->call('addProject')
            ->assertHasNoErrors()
            ->assertSet('creatingProject', false);

        $this->assertDatabaseHas('projects', [
            'name' => 'Mudanza',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_el_nombre_del_proyecto_no_puede_repetirse(): void
    {
        Project::factory()->for($this->user)->create(['name' => 'Mudanza']);

        Livewire::test('todo.todo-list')
            ->call('startCreatingProject')
            ->set('projectName', 'Mudanza')
            ->call('addProject')
            ->assertHasErrors('projectName');

        $this->assertSame(1, $this->user->projects()->count());
    }

    public function test_eliminar_un_proyecto_deja_sus_tareas_sueltas(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $todo = Todo::factory()->for($this->user)->for($project)->create();

        Livewire::test('todo.todo-list')
            ->call('deleteProject', $project->id)
            ->assertSet('activeProjectId', null);

        $this->assertModelMissing($project);
        $this->assertNull($todo->fresh()->project_id);
    }

    public function test_no_puede_eliminar_proyectos_ajenos(): void
    {
        $ajeno = Project::factory()->create();

        try {
            Livewire::test('todo.todo-list')->call('deleteProject', $ajeno->id);
            $this->fail('Un proyecto ajeno no debería poder eliminarse.');
        } catch (ModelNotFoundException) {
            // esperado: para este usuario ese proyecto no existe
        }

        $this->assertModelExists($ajeno);
    }

    public function test_no_ve_los_proyectos_de_otros_usuarios(): void
    {
        Project::factory()->create(['name' => 'Proyecto ajeno secreto']);
        Project::factory()->for($this->user)->create(['name' => 'Proyecto propio']);

        $this->get('/tareas')
            ->assertSee('Proyecto propio')
            ->assertDontSee('Proyecto ajeno secreto');
    }

    public function test_puede_filtrar_por_proyecto(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Todo::factory()->for($this->user)->for($project)->create(['title' => 'Tarea del proyecto']);
        Todo::factory()->for($this->user)->create(['title' => 'Tarea suelta']);

        Livewire::test('todo.todo-list')
            ->call('filterProject', $project->id)
            ->assertSee('Tarea del proyecto')
            ->assertDontSee('Tarea suelta');
    }

    public function test_no_puede_filtrar_por_un_proyecto_ajeno(): void
    {
        $ajeno = Project::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test('todo.todo-list')->call('filterProject', $ajeno->id);
    }
}
