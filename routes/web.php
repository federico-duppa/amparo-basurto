<?php

use App\Http\Controllers\HealthAttachmentController;
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
    Route::livewire('/salud', 'salud.panel')->name('salud');
    Route::get('/salud/adjuntos/{attachment}', HealthAttachmentController::class)->name('salud.adjunto')->whereNumber('attachment');
    Route::livewire('/compras', 'compras.lista')->name('compras');
    Route::livewire('/plata', 'plata.gastos')->name('plata.gastos');
    Route::livewire('/plata/sobres', 'plata.sobres')->name('plata.sobres');
    Route::livewire('/plata/sobres/{envelope}', 'plata.sobre')->name('plata.sobre')->whereNumber('envelope');
    Route::livewire('/plata/reportes', 'plata.reportes')->name('plata.reportes');

    Route::livewire('/juegos', 'juegos.panel')->name('juegos');
    Route::livewire('/juegos/queens', 'juegos.queens')->name('juegos.queens');

    // Destino del share de Android (Web Share Target del manifest).
    Route::livewire('/compartir', 'compartir.recibir')->name('compartir');

    Route::post('/salir', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
