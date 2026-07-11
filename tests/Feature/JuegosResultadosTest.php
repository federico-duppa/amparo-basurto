<?php

namespace Tests\Feature;

use App\Livewire\Concerns\RecordsGameResults;
use App\Models\GameResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class JuegosResultadosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** "Hoy" del puzzle del día, en la zona horaria de la casa. */
    private function hoy(): string
    {
        return CarbonImmutable::now(config('amparo.zona_horaria'))->toDateString();
    }

    public function test_ganar_el_puzzle_del_dia_guarda_el_resultado(): void
    {
        Livewire::test('juegos.queens')
            ->call('recordWin', 'daily', 125, $this->hoy());

        $this->assertDatabaseHas('game_results', [
            'user_id' => $this->user->id,
            'game' => 'queens',
            'daily' => true,
            'seconds' => 125,
        ]);

        $this->assertSame(1, GameResult::streak($this->user, 'queens', $this->hoy()));
        $this->assertSame(125, GameResult::bestTime($this->user, 'queens'));
    }

    public function test_repetir_el_puzzle_del_dia_no_duplica_ni_pisa_el_primer_tiempo(): void
    {
        $component = Livewire::test('juegos.queens');
        $component->call('recordWin', 'daily', 200, $this->hoy());
        $component->call('recordWin', 'daily', 90, $this->hoy());

        $this->assertSame(1, $this->user->gameResults()->where('daily', true)->count());
        $this->assertSame(200, GameResult::dailyResult($this->user, 'queens', $this->hoy())->seconds);
    }

    public function test_las_partidas_libres_se_acumulan_y_cuentan_para_el_mejor_tiempo(): void
    {
        $component = Livewire::test('juegos.solyluna');
        $component->call('recordWin', 'free', 300, $this->hoy());
        $component->call('recordWin', 'free', 45, $this->hoy());

        $this->assertSame(2, $this->user->gameResults()->count());
        $this->assertSame(45, GameResult::bestTime($this->user, 'solyluna'));
        // Las libres no sostienen la racha.
        $this->assertSame(0, GameResult::streak($this->user, 'solyluna', $this->hoy()));
    }

    public function test_una_fecha_vieja_vale_como_partida_libre(): void
    {
        // Una pestaña abierta de otro día: el tablero resuelto no era el del
        // día, así que la victoria vale pero la racha no.
        $vieja = CarbonImmutable::parse($this->hoy())->subDays(5)->toDateString();

        Livewire::test('juegos.queens')
            ->call('recordWin', 'daily', 100, $vieja);

        $this->assertSame(0, $this->user->gameResults()->where('daily', true)->count());
        $this->assertSame(1, $this->user->gameResults()->where('daily', false)->count());
    }

    public function test_la_racha_cuenta_dias_consecutivos_y_un_hueco_la_corta(): void
    {
        $hoy = CarbonImmutable::parse($this->hoy());

        foreach ([1, 2] as $atras) {
            GameResult::factory()->for($this->user)->create([
                'game' => 'queens',
                'played_on' => $hoy->subDays($atras)->toDateString(),
            ]);
        }

        // Ayer y anteayer: la racha sigue viva aunque hoy todavía no jugó.
        $this->assertSame(2, GameResult::streak($this->user, 'queens', $hoy->toDateString()));

        // Con la de hoy se estira a 3.
        GameResult::factory()->for($this->user)->create([
            'game' => 'queens',
            'played_on' => $hoy->toDateString(),
        ]);
        $this->assertSame(3, GameResult::streak($this->user, 'queens', $hoy->toDateString()));

        // Un hueco de más de un día la corta de cuajo.
        $otro = User::factory()->create();
        GameResult::factory()->for($otro)->create([
            'game' => 'queens',
            'played_on' => $hoy->subDays(3)->toDateString(),
        ]);
        GameResult::factory()->for($otro)->create([
            'game' => 'queens',
            'played_on' => $hoy->subDays(4)->toDateString(),
        ]);
        $this->assertSame(0, GameResult::streak($otro, 'queens', $hoy->toDateString()));
    }

    public function test_cada_usuario_ve_solo_sus_numeros(): void
    {
        $otro = User::factory()->create();
        GameResult::factory()->for($otro)->create([
            'game' => 'queens',
            'played_on' => $this->hoy(),
            'seconds' => 10,
        ]);

        $this->assertNull(GameResult::bestTime($this->user, 'queens'));
        $this->assertSame(0, GameResult::streak($this->user, 'queens', $this->hoy()));
        $this->assertNull(GameResult::dailyResult($this->user, 'queens', $this->hoy()));
    }

    public function test_la_racha_y_el_diario_no_se_mezclan_entre_juegos(): void
    {
        Livewire::test('juegos.queens')
            ->call('recordWin', 'daily', 100, $this->hoy());

        $this->assertSame(0, GameResult::streak($this->user, 'solyluna', $this->hoy()));
        $this->assertNull(GameResult::dailyResult($this->user, 'solyluna', $this->hoy()));
    }

    public function test_los_segundos_se_acotan_a_un_rango_sano(): void
    {
        Livewire::test('juegos.queens')
            ->call('recordWin', 'free', 999_999_999, $this->hoy());

        $this->assertSame(86_400, $this->user->gameResults()->first()->seconds);
    }

    public function test_un_modo_desconocido_se_rechaza(): void
    {
        Livewire::test('juegos.queens')
            ->call('recordWin', 'cualquiera', 100, $this->hoy())
            ->assertStatus(422);

        $this->assertSame(0, $this->user->gameResults()->count());
    }

    public function test_el_panel_muestra_los_numeros_del_usuario(): void
    {
        GameResult::factory()->for($this->user)->create([
            'game' => 'queens',
            'played_on' => $this->hoy(),
            'seconds' => 135,
        ]);

        $this->get('/juegos')
            ->assertOk()
            ->assertSee('El del día, listo')
            ->assertSee('Racha: 1 día')
            ->assertSee('02:15');
    }

    public function test_las_paginas_de_juego_sirven_el_estado_inicial(): void
    {
        Livewire::test('juegos.queens')
            ->call('recordWin', 'daily', 80, $this->hoy());

        // El estado que se inyecta al tablero Alpine refleja lo guardado.
        $component = new class extends Component
        {
            use RecordsGameResults;

            protected function gameKey(): string
            {
                return 'queens';
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        $state = $component->gameState();

        $this->assertSame($this->hoy(), $state['date']);
        $this->assertTrue($state['dailySolved']);
        $this->assertSame(80, $state['dailySeconds']);
        $this->assertSame(1, $state['streak']);
        $this->assertSame(80, $state['best']);
    }
}
