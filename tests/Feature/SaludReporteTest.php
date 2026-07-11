<?php

namespace Tests\Feature;

use App\Models\HealthContact;
use App\Models\HealthEntry;
use App\Models\HealthMeasurement;
use App\Models\HealthRecord;
use App\Models\HealthReminder;
use App\Models\HealthVaccine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaludReporteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_el_reporte_muestra_la_historia_completa(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create([
            'titular' => 'Rosa',
            'alergias' => 'Penicilina',
            'medicacion' => 'Losartán 50 mg',
        ]);
        HealthEntry::factory()->for($record, 'record')->for($this->user)->create([
            'type' => 'consulta',
            'title' => 'Control anual con clínica',
            'detail' => 'Todo en orden.',
        ]);
        HealthReminder::factory()->for($record, 'record')->for($this->user)->create(['name' => 'Receta de losartán']);
        HealthVaccine::factory()->for($record, 'record')->for($this->user)->create(['name' => 'Antigripal']);
        HealthMeasurement::factory()->for($record, 'record')->for($this->user)->create(['type' => 'peso', 'value' => 78.5]);
        HealthContact::factory()->for($record, 'record')->for($this->user)->create(['name' => 'Dra. Paniagua']);

        $this->get(route('salud.reporte', $record))
            ->assertOk()
            ->assertSee('Historia clínica')
            ->assertSee('Rosa')
            ->assertSee('Penicilina')
            ->assertSee('Losartán 50 mg')
            ->assertSee('Control anual con clínica')
            ->assertSee('Receta de losartán')
            ->assertSee('Antigripal')
            ->assertSee('78,5 kg')
            ->assertSee('Dra. Paniagua');
    }

    public function test_el_reporte_incluye_el_timeline_completo_sin_ventana(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();

        // Más entradas que la ventana de 20 del panel: el reporte trae todas.
        foreach (range(1, 25) as $i) {
            HealthEntry::factory()->for($record, 'record')->for($this->user)->create([
                'occurred_on' => now()->subDays($i),
                'title' => 'Entrada número '.$i,
            ]);
        }

        $this->get(route('salud.reporte', $record))
            ->assertSee('Entrada número 1')
            ->assertSee('Entrada número 25');
    }

    public function test_la_ficha_de_mascota_muestra_especie_y_veterinaria(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create([
            'tipo' => 'mascota',
            'titular' => 'Pilca',
            'especie' => 'Perro',
            'obra_social' => 'Veterinaria del Parque',
        ]);

        $this->get(route('salud.reporte', $record))
            ->assertSee('Perro')
            ->assertSee('Veterinaria del Parque')
            ->assertDontSee('Grupo sanguíneo');
    }

    public function test_quien_tiene_la_historia_compartida_puede_ver_el_reporte(): void
    {
        $owner = User::factory()->create();
        $record = HealthRecord::factory()->for($owner)->create(['titular' => 'Abuela Delia']);
        $record->members()->attach($this->user);

        $this->get(route('salud.reporte', $record))
            ->assertOk()
            ->assertSee('Abuela Delia');
    }

    public function test_no_se_puede_ver_el_reporte_de_una_historia_ajena(): void
    {
        $otro = User::factory()->create();
        $record = HealthRecord::factory()->for($otro)->create();

        $this->get(route('salud.reporte', $record))->assertNotFound();
    }

    public function test_sin_iniciar_sesion_no_hay_reporte(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();

        auth()->logout();

        $this->get(route('salud.reporte', $record))->assertRedirect(route('login'));
    }

    public function test_el_panel_ofrece_el_reporte_de_la_historia(): void
    {
        $record = HealthRecord::factory()->for($this->user)->create();

        $this->get('/salud')->assertSee(route('salud.reporte', $record));
    }
}
