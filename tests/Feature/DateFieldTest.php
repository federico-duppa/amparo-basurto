<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DateFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fuera de Livewire, Blade::render no comparte el bag de errores que usa @error
        View::share('errors', new ViewErrorBag);
    }

    public function test_renderiza_el_disparador_con_la_voz_de_amparo(): void
    {
        $html = Blade::render('<x-ui.date-field model="dueDate" label="Vence" accent="vino" preset="tarea" />');

        // Ya no es un input nativo del sistema
        $this->assertStringNotContainsString('type="date"', $html);

        // Es nuestro disparador propio, atado a la propiedad Livewire
        $this->assertStringContainsString('wire:model="dueDate"', $html);
        $this->assertStringContainsString('x-data="dateField(', $html);
        $this->assertStringContainsString('Elegí una fecha', $html);
    }

    public function test_el_label_puede_ser_solo_para_lectores_de_pantalla(): void
    {
        $html = Blade::render('<x-ui.date-field model="spentOn" label="Fecha" :srLabel="true" accent="oliva" />');

        $this->assertStringContainsString('sr-only', $html);
        $this->assertStringContainsString('for="spentOn"', $html);
    }

    public function test_nacimiento_abre_en_anios_y_no_permite_el_futuro(): void
    {
        $html = Blade::render('<x-ui.date-field model="newNacimiento" label="Nacimiento" accent="ciruela" preset="nacimiento" />');

        // startView years y max = hoy quedan embebidos en la config de Alpine
        $this->assertStringContainsString("startView:'years'", str_replace(' ', '', $html));
        $this->assertStringContainsString(today()->toDateString(), $html);
    }

    public function test_un_id_propio_permite_usarlo_en_listas(): void
    {
        $html = Blade::render('<x-ui.date-field model="editFuelDate" id="editFuelDate-7" label="Fecha" accent="grafito" />');

        $this->assertStringContainsString('id="editFuelDate-7"', $html);
        $this->assertStringContainsString('wire:model="editFuelDate"', $html);
    }
}
