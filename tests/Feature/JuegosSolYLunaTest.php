<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JuegosSolYLunaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_el_catalogo_ofrece_sol_y_luna(): void
    {
        $this->get('/juegos')
            ->assertOk()
            ->assertSee('Sol y luna')
            ->assertSee(route('juegos.solyluna'));
    }

    public function test_la_pagina_de_sol_y_luna_renderiza_el_juego(): void
    {
        // El tablero se arma y se juega entero en el navegador; el componente
        // solo entrega el marco de la página, sin estado en el servidor.
        $this->get('/juegos/solyluna')
            ->assertOk()
            ->assertSeeLivewire('juegos.solyluna');
    }

    public function test_sol_y_luna_exige_sesion(): void
    {
        auth()->logout();

        $this->get('/juegos/solyluna')->assertRedirect('/entrar');
    }
}
