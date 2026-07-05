<?php

namespace Tests\Unit;

use App\Support\NaturalDate;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class NaturalDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 2026-07-05 es un domingo: base fija para razonar los días relativos.
        Carbon::setTestNow('2026-07-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reconoce_hoy_manana_y_pasado_manana(): void
    {
        $this->assertSame('2026-07-05', NaturalDate::parse('llamar hoy')['date']);
        $this->assertSame('2026-07-06', NaturalDate::parse('comprar pan mañana')['date']);
        $this->assertSame('2026-07-07', NaturalDate::parse('pagar pasado mañana')['date']);
    }

    public function test_reconoce_dias_de_la_semana_hacia_adelante(): void
    {
        // Domingo → el próximo viernes es el 10.
        $this->assertSame('2026-07-10', NaturalDate::parse('turno el viernes')['date']);
        // El mismo día de la semana salta a la semana siguiente.
        $this->assertSame('2026-07-12', NaturalDate::parse('misa el domingo')['date']);
    }

    public function test_reconoce_plazos_relativos(): void
    {
        $this->assertSame('2026-07-08', NaturalDate::parse('en 3 días')['date']);
        $this->assertSame('2026-07-19', NaturalDate::parse('en 2 semanas')['date']);
        $this->assertSame('2026-08-05', NaturalDate::parse('en 1 mes')['date']);
        $this->assertSame('2026-07-06', NaturalDate::parse('la semana que viene')['date']);
    }

    public function test_reconoce_fecha_explicita_y_asume_el_ano_que_viene_si_ya_paso(): void
    {
        $this->assertSame('2026-08-15', NaturalDate::parse('sacar turno 15/8')['date']);
        $this->assertSame('2026-12-31', NaturalDate::parse('cierre 31/12/2026')['date']);
        // 20/06 ya pasó respecto del 5/7 → se entiende el año que viene.
        $this->assertSame('2027-06-20', NaturalDate::parse('renovar 20/06')['date']);
    }

    public function test_no_inventa_fecha_donde_no_la_hay(): void
    {
        $this->assertNull(NaturalDate::parse('comprar 2 kg de asado'));
        $this->assertNull(NaturalDate::parse('barrer el patio'));
        // Fecha inválida: no existe el 32.
        $this->assertNull(NaturalDate::parse('algo 32/13'));
    }

    public function test_extract_limpia_el_fragmento_del_titulo(): void
    {
        $r = NaturalDate::extract('Comprar regalo mañana');
        $this->assertSame('2026-07-06', $r['date']);
        $this->assertSame('Comprar regalo', $r['title']);

        $r = NaturalDate::extract('Llamar al dentista el viernes');
        $this->assertSame('Llamar al dentista', $r['title']);
    }

    public function test_extract_no_deja_el_titulo_vacio(): void
    {
        // Si el texto es sólo la fecha, no hay tarea que anotar.
        $this->assertNull(NaturalDate::extract('mañana'));
    }
}
