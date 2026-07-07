<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JuegosQueensTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_la_pagina_de_juegos_renderiza_el_panel(): void
    {
        $this->get('/juegos')
            ->assertOk()
            ->assertSeeLivewire('juegos.panel')
            ->assertSee('Queens');
    }

    public function test_la_pagina_de_queens_renderiza_el_juego(): void
    {
        // El tablero se arma y se juega entero en el navegador; el componente
        // solo entrega el marco de la página, sin estado en el servidor.
        $this->get('/juegos/queens')
            ->assertOk()
            ->assertSeeLivewire('juegos.queens');
    }

    public function test_juegos_exige_sesion(): void
    {
        auth()->logout();

        $this->get('/juegos')->assertRedirect('/entrar');
        $this->get('/juegos/queens')->assertRedirect('/entrar');
    }
}
