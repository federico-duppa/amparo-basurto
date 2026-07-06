<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\QueensPuzzle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_el_componente_arranca_con_un_tablero_de_8_regiones(): void
    {
        $component = Livewire::test('juegos.queens');

        $regions = $component->get('regions');

        $this->assertBoardIsWellFormed($regions);
    }

    public function test_tablero_nuevo_regenera_el_puzzle(): void
    {
        $component = Livewire::test('juegos.queens');
        $firstGameId = $component->get('gameId');

        $component->call('nuevo');

        $this->assertGreaterThan($firstGameId, $component->get('gameId'));
        $this->assertBoardIsWellFormed($component->get('regions'));
    }

    public function test_el_generador_produce_tableros_validos_y_de_solucion_unica(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $puzzle = QueensPuzzle::generate();

            $this->assertBoardIsWellFormed($puzzle['regions']);
            $this->assertSolutionIsValid($puzzle['regions'], $puzzle['solution']);
            $this->assertRegionsAreContiguous($puzzle['regions']);
            $this->assertSame(
                1,
                $this->countSolutions($puzzle['regions']),
                'El tablero debería tener exactamente una solución.'
            );
        }
    }

    /** El tablero es 8x8 y sus celdas se reparten en exactamente 8 regiones. */
    private function assertBoardIsWellFormed(array $regions): void
    {
        $this->assertCount(8, $regions);

        $seen = [];
        foreach ($regions as $row) {
            $this->assertCount(8, $row);
            foreach ($row as $value) {
                $this->assertIsInt($value);
                $this->assertGreaterThanOrEqual(0, $value);
                $this->assertLessThanOrEqual(7, $value);
                $seen[$value] = true;
            }
        }

        $this->assertCount(8, $seen, 'El tablero debería tener 8 regiones distintas.');
    }

    /** La solución de origen cumple las reglas del juego. */
    private function assertSolutionIsValid(array $regions, array $solution): void
    {
        $this->assertCount(8, $solution);

        $cols = [];
        $regs = [];
        foreach ($solution as $row => $col) {
            $this->assertArrayNotHasKey($col, $cols, 'Dos reinas en la misma columna.');
            $cols[$col] = true;

            $reg = $regions[$row][$col];
            $this->assertArrayNotHasKey($reg, $regs, 'Dos reinas en la misma región.');
            $regs[$reg] = true;

            if ($row > 0) {
                $this->assertGreaterThan(1, abs($col - $solution[$row - 1]), 'Reinas contiguas se tocan.');
            }
        }
    }

    /** Cada región es una sola mancha conexa (adyacencia ortogonal). */
    private function assertRegionsAreContiguous(array $regions): void
    {
        $cellsByRegion = [];
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                $cellsByRegion[$regions[$r][$c]][$r * 8 + $c] = true;
            }
        }

        foreach ($cellsByRegion as $cells) {
            $start = array_key_first($cells);
            $seen = [$start => true];
            $stack = [$start];

            while ($stack !== []) {
                $key = array_pop($stack);
                $r = intdiv($key, 8);
                $c = $key % 8;
                foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dr, $dc]) {
                    $nr = $r + $dr;
                    $nc = $c + $dc;
                    if ($nr < 0 || $nr >= 8 || $nc < 0 || $nc >= 8) {
                        continue;
                    }
                    $nk = $nr * 8 + $nc;
                    if (isset($cells[$nk]) && ! isset($seen[$nk])) {
                        $seen[$nk] = true;
                        $stack[] = $nk;
                    }
                }
            }

            $this->assertCount(count($cells), $seen, 'Una región quedó partida en dos.');
        }
    }

    /**
     * Contador de soluciones independiente del generador, para chequear la
     * unicidad sin confiar en la lógica interna de QueensPuzzle.
     */
    private function countSolutions(array $regions): int
    {
        $count = 0;
        $usedCols = [];
        $usedRegions = [];

        $solve = function (int $row, ?int $prevCol) use (&$solve, &$count, $regions, &$usedCols, &$usedRegions): void {
            if ($row === 8) {
                $count++;

                return;
            }
            for ($col = 0; $col < 8; $col++) {
                if (isset($usedCols[$col])) {
                    continue;
                }
                if ($prevCol !== null && abs($col - $prevCol) < 2) {
                    continue;
                }
                $reg = $regions[$row][$col];
                if (isset($usedRegions[$reg])) {
                    continue;
                }
                $usedCols[$col] = true;
                $usedRegions[$reg] = true;
                $solve($row + 1, $col);
                unset($usedCols[$col], $usedRegions[$reg]);
            }
        };

        $solve(0, null);

        return $count;
    }
}
