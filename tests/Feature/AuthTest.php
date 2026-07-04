<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_es_redirigido_al_login(): void
    {
        $this->get('/tareas')->assertRedirect('/entrar');
    }

    public function test_un_usuario_permitido_puede_registrarse(): void
    {
        config(['amparo.allowed_usernames' => ['federico']]);

        Livewire::test('auth.register')
            ->set('name', 'Federico')
            ->set('username', '  Federico ') // se normaliza a minúsculas y sin espacios
            ->set('password', 'una-clave-segura')
            ->set('password_confirmation', 'una-clave-segura')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect('/tareas');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'federico', 'name' => 'Federico']);
    }

    public function test_un_usuario_fuera_de_la_lista_no_puede_registrarse(): void
    {
        config(['amparo.allowed_usernames' => ['federico', 'amparo']]);

        Livewire::test('auth.register')
            ->set('name', 'Intruso')
            ->set('username', 'intruso')
            ->set('password', 'una-clave-segura')
            ->set('password_confirmation', 'una-clave-segura')
            ->call('register')
            ->assertHasErrors(['username' => 'in']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_con_la_lista_vacia_el_registro_esta_cerrado(): void
    {
        config(['amparo.allowed_usernames' => []]);

        $this->get('/registro')
            ->assertOk()
            ->assertSee('El registro está cerrado por ahora.')
            ->assertDontSee('Crear mi cuenta');
    }

    public function test_puede_entrar_con_usuario_y_contrasena(): void
    {
        $user = User::factory()->create(['username' => 'federico']);

        Livewire::test('auth.login')
            ->set('username', 'Federico') // mayúsculas no importan
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/tareas');

        $this->assertAuthenticatedAs($user);
    }

    public function test_no_puede_entrar_con_contrasena_incorrecta(): void
    {
        User::factory()->create(['username' => 'federico']);

        Livewire::test('auth.login')
            ->set('username', 'federico')
            ->set('password', 'otra-cosa')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_puede_cerrar_sesion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/salir')
            ->assertRedirect('/entrar');

        $this->assertGuest();
    }

    public function test_un_usuario_autenticado_no_ve_el_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/entrar')
            ->assertRedirect();
    }
}
