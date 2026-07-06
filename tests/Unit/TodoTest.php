<?php

namespace Tests\Unit;

use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_eisenhower_weight_urgente_e_importante_pesa_cero(): void
    {
        $todo = new Todo(['urgent' => true, 'important' => true]);

        $this->assertSame(0, $todo->eisenhowerWeight());
    }

    public function test_eisenhower_weight_solo_importante_pesa_uno(): void
    {
        $todo = new Todo(['urgent' => false, 'important' => true]);

        $this->assertSame(1, $todo->eisenhowerWeight());
    }

    public function test_eisenhower_weight_solo_urgente_pesa_dos(): void
    {
        $todo = new Todo(['urgent' => true, 'important' => false]);

        $this->assertSame(2, $todo->eisenhowerWeight());
    }

    public function test_eisenhower_weight_ninguno_pesa_tres(): void
    {
        $todo = new Todo(['urgent' => false, 'important' => false]);

        $this->assertSame(3, $todo->eisenhowerWeight());
    }

    public function test_next_due_date_diaria_avanza_hasta_hoy(): void
    {
        $todo = new Todo(['due_date' => '2026-07-01', 'repeat_interval' => 'diaria']);

        $this->assertSame('2026-07-05', $todo->nextDueDate()->toDateString());
    }

    public function test_next_due_date_semanal_no_deja_una_recurrente_atrasada_en_el_pasado(): void
    {
        $todo = new Todo(['due_date' => '2026-06-20', 'repeat_interval' => 'semanal']);

        $this->assertSame('2026-07-11', $todo->nextDueDate()->toDateString());
    }

    public function test_next_due_date_mensual_sin_desbordar_el_mes(): void
    {
        $todo = new Todo(['due_date' => '2026-05-31', 'repeat_interval' => 'mensual']);

        // 31/05 + 1 mes sin desborde = 30/06 (todavía pasado) + 1 mes = 30/07.
        $this->assertSame('2026-07-30', $todo->nextDueDate()->toDateString());
    }

    public function test_next_due_date_anual(): void
    {
        $todo = new Todo(['due_date' => '2025-07-01', 'repeat_interval' => 'anual']);

        $this->assertSame('2027-07-01', $todo->nextDueDate()->toDateString());
    }

    public function test_next_due_date_avanza_al_menos_una_vez_aunque_no_este_atrasada(): void
    {
        $todo = new Todo(['due_date' => '2026-07-05', 'repeat_interval' => 'diaria']);

        $this->assertSame('2026-07-06', $todo->nextDueDate()->toDateString());
    }
}
