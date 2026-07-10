<?php

namespace Tests\Unit;

use App\Support\TaskImport;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class TaskImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_parsea_una_linea_con_solo_titulo(): void
    {
        $rows = TaskImport::parse('Comprar yerba');

        $this->assertCount(1, $rows);
        $this->assertSame('Comprar yerba', $rows[0]['title']);
        $this->assertNull($rows[0]['due']);
        $this->assertNull($rows[0]['repeat']);
        $this->assertFalse($rows[0]['urgent']);
        $this->assertFalse($rows[0]['important']);
        $this->assertNull($rows[0]['notes']);
        $this->assertSame([], $rows[0]['tags']);
        $this->assertNull($rows[0]['error']);
    }

    public function test_parsea_una_linea_con_todos_los_campos(): void
    {
        $rows = TaskImport::parse('Cortarme las uñas | vence: 2026-07-12 | repite: semanal | urgente | importante | notas: con el alicate bueno | etiquetas: higiene, casa');

        $this->assertNull($rows[0]['error']);
        $this->assertSame('Cortarme las uñas', $rows[0]['title']);
        $this->assertSame('2026-07-12', $rows[0]['due']);
        $this->assertSame('semanal', $rows[0]['repeat']);
        $this->assertTrue($rows[0]['urgent']);
        $this->assertTrue($rows[0]['important']);
        $this->assertSame('con el alicate bueno', $rows[0]['notes']);
        $this->assertSame(['higiene', 'casa'], $rows[0]['tags']);
    }

    public function test_acepta_fechas_locales_y_es_estricto_con_las_invalidas(): void
    {
        $rows = TaskImport::parse("Pagar el gas | vence: 15/08/2026\nImposible | vence: 31/02/2026\nRara | fecha: mañana");

        $this->assertSame('2026-08-15', $rows[0]['due']);
        $this->assertNull($rows[0]['error']);
        $this->assertStringContainsString('no me cierra', $rows[1]['error']);
        $this->assertStringContainsString('no me cierra', $rows[2]['error']);
    }

    public function test_normaliza_sinonimos_de_repeticion_y_rechaza_las_desconocidas(): void
    {
        $rows = TaskImport::parse("Afeitarme | repite: Diario | vence: 2026-07-11\nRegar | se repite: cada 3 días | vence: 2026-07-11");

        $this->assertSame('diaria', $rows[0]['repeat']);
        $this->assertNull($rows[0]['error']);
        $this->assertStringContainsString('no la conozco', $rows[1]['error']);
    }

    public function test_la_repeticion_sin_fecha_es_un_error(): void
    {
        $rows = TaskImport::parse('Sacar la basura | repite: semanal');

        $this->assertStringContainsString('necesita una fecha', $rows[0]['error']);
    }

    public function test_tolera_vinetas_numeracion_y_casilleros_de_markdown(): void
    {
        $rows = TaskImport::parse("- Una\n* Dos\n• Tres\n3. Cuatro\n- [ ] Cinco\n- [x] Seis");

        $this->assertSame(['Una', 'Dos', 'Tres', 'Cuatro', 'Cinco', 'Seis'], array_column($rows, 'title'));
        $this->assertSame([null], array_unique(array_column($rows, 'error')));
    }

    public function test_saltea_lineas_vacias_conservando_el_numero_de_linea(): void
    {
        $rows = TaskImport::parse("Una\n\n\nDos");

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['line']);
        $this->assertSame(4, $rows[1]['line']);
    }

    public function test_acepta_etiquetas_sueltas_con_numeral_y_no_las_duplica(): void
    {
        $rows = TaskImport::parse('Ordenar el galpón | #casa #Casa | etiquetas: #galpón');

        $this->assertNull($rows[0]['error']);
        $this->assertSame(['casa', 'galpón'], $rows[0]['tags']);
    }

    public function test_un_segmento_desconocido_es_un_error(): void
    {
        $rows = TaskImport::parse('Llamar al plomero | prioridad: alta');

        $this->assertSame('No entendí «prioridad: alta».', $rows[0]['error']);
    }

    public function test_valida_largos_de_titulo_nota_y_etiqueta(): void
    {
        $rows = TaskImport::parse(implode("\n", [
            str_repeat('a', 256),
            'Tarea | notas: '.str_repeat('b', 2001),
            'Tarea | etiquetas: '.str_repeat('c', 41),
        ]));

        $this->assertStringContainsString('muy largo', $rows[0]['error']);
        $this->assertStringContainsString('muy larga', $rows[1]['error']);
        $this->assertStringContainsString('muy larga', $rows[2]['error']);
    }

    public function test_la_consigna_para_la_ia_explica_el_formato_y_ancla_la_fecha_de_hoy(): void
    {
        $prompt = TaskImport::aiPrompt();

        $this->assertStringContainsString('una por línea', $prompt);
        $this->assertStringContainsString('vence:', $prompt);
        $this->assertStringContainsString('diaria, semanal, mensual o anual', $prompt);
        $this->assertStringContainsString('Hoy es 10/07/2026', $prompt);
    }
}
