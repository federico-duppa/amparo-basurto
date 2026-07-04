<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tareas');

Route::livewire('/tareas', 'todo.todo-list')->name('todos');
