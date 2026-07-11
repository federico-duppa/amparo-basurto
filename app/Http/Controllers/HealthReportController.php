<?php

namespace App\Http\Controllers;

use App\Models\HealthMeasurement;
use App\Models\HealthRecord;
use Illuminate\Contracts\View\View;

class HealthReportController extends Controller
{
    /**
     * Reporte imprimible de una historia clínica, para llevar al médico.
     * Página propia sin el layout de la app, pensada para imprimir o guardar
     * como PDF desde el navegador. Solo para quien tiene acceso a la historia
     * (dueño o compartida); lo ajeno responde 404.
     */
    public function __invoke(HealthRecord $record): View
    {
        auth()->user()->accessibleHealthRecords()->findOrFail($record->id);

        $record->load(['entries.attachments', 'reminders', 'vaccines', 'contacts', 'measurements']);

        return view('salud.reporte', [
            'record' => $record,

            // Vencimientos por urgencia, como en el panel.
            'reminders' => $record->reminders
                ->map(fn ($reminder) => ['reminder' => $reminder, 'status' => $reminder->status()])
                ->sortBy(fn ($row) => [$row['status']['rank'], $row['status']['urgency']])
                ->values(),

            // El carnet agrupado por vacuna (alfabético) y de la primera a la última dosis.
            'vaccineGroups' => $record->vaccines
                ->sortBy([['applied_on', 'asc'], ['id', 'asc']])
                ->groupBy('name')
                ->sortKeys(),

            // Mediciones por tipo (en el orden fijo de la app), de la más reciente a la más vieja.
            'measurementGroups' => collect(HealthMeasurement::TYPES)
                ->map(fn ($type, $key) => $record->measurements
                    ->where('type', $key)
                    ->sortBy([['measured_on', 'desc'], ['id', 'desc']])
                    ->values())
                ->filter(fn ($group) => $group->isNotEmpty()),

            'contacts' => $record->contacts->sortBy('name')->values(),

            // El timeline completo: acá no hay "Ver más", el papel aguanta todo.
            'entries' => $record->entries->sortBy([['occurred_on', 'desc'], ['id', 'desc']])->values(),

            // Adjuntos sueltos de la historia, como inventario (el archivo no se imprime).
            'documents' => $record->attachments->whereNull('health_entry_id')->sortByDesc('id')->values(),
        ]);
    }
}
