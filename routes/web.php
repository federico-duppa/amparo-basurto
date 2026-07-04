<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tareas');

Route::middleware('guest')->group(function () {
    Route::livewire('/entrar', 'auth.login')->name('login');
    Route::livewire('/registro', 'auth.register')->name('register');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/tareas', 'todo.todo-list')->name('todos');
    Route::livewire('/auto', 'auto.panel')->name('auto');

    Route::post('/salir', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
