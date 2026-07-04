<?php

namespace Tests\Feature;

use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TodoListTest extends TestCase
{
    use RefreshDatabase;

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

        $this->assertDatabaseHas('todos', ['title' => 'Comprar yerba', 'completed_at' => null]);
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

    public function test_puede_completar_y_reabrir_una_tarea(): void
    {
        $todo = Todo::factory()->create();

        Livewire::test('todo.todo-list')->call('toggle', $todo->id);
        $this->assertNotNull($todo->fresh()->completed_at);

        Livewire::test('todo.todo-list')->call('toggle', $todo->id);
        $this->assertNull($todo->fresh()->completed_at);
    }

    public function test_puede_eliminar_una_tarea(): void
    {
        $todo = Todo::factory()->create();

        Livewire::test('todo.todo-list')->call('delete', $todo->id);

        $this->assertModelMissing($todo);
    }

    public function test_las_pendientes_se_listan_antes_que_las_completadas(): void
    {
        $completada = Todo::factory()->completed()->create(['title' => 'Tarea completada']);
        $pendiente = Todo::factory()->create(['title' => 'Tarea pendiente']);

        $this->get('/tareas')
            ->assertSeeInOrder(['Tarea pendiente', 'Tarea completada']);
    }
}
