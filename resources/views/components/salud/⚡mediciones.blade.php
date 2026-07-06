<?php

use App\Models\HealthMeasurement;
use App\Models\HealthRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Mediciones de la historia clínica (peso, presión, glucemia…) con su
 * evolución en el tiempo. Cualquier persona con acceso a la historia las
 * opera. Una medición mal cargada se elimina y se anota de nuevo.
 */
new class extends Component
{
    /** Mediciones visibles por página; "Ver más" agranda la ventana. */
    private const MEASUREMENTS_PAGE = 10;

    #[Locked]
    public int $recordId;

    // Tipo elegido: define qué evolución se ve y qué se anota.
    public string $selectedType = 'peso';

    // Alta de una medición del tipo elegido.
    public bool $addingMeasurement = false;
    public string $measurementValue = '';
    public string $measurementValue2 = '';
    public string $measurementDate = '';

    // Ventana visible de la evolución.
    public int $measurementsLimit = self::MEASUREMENTS_PAGE;

    public function mount(): void
    {
        $this->measurementDate = now()->format('Y-m-d');

        // Arrancamos en el primer tipo que ya tenga datos, si hay alguno.
        $withData = $this->requireRecord()->measurements()
            ->orderByDesc('measured_on')
            ->orderByDesc('id')
            ->value('type');

        if ($withData !== null && array_key_exists($withData, HealthMeasurement::TYPES)) {
            $this->selectedType = $withData;
        }
    }

    public function selectType(string $type): void
    {
        if (! array_key_exists($type, HealthMeasurement::TYPES)) {
            return;
        }

        $this->selectedType = $type;
        $this->reset('addingMeasurement', 'measurementValue', 'measurementValue2', 'measurementsLimit');
        $this->resetValidation();
    }

    public function addMeasurement(): void
    {
        $record = $this->requireRecord();
        $dual = HealthMeasurement::isDualType($this->selectedType);

        $data = $this->validate([
            'measurementValue' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'measurementValue2' => $dual ? ['required', 'numeric', 'gt:0', 'max:100000'] : ['nullable'],
            'measurementDate' => ['required', 'date'],
        ], [
            'measurementValue.required' => $dual ? '¿Cuánto dio la máxima?' : '¿Cuánto dio?',
            'measurementValue.gt' => 'Ese valor no me cierra.',
            'measurementValue2.required' => '¿Y la mínima?',
            'measurementValue2.gt' => 'Ese valor no me cierra.',
            'measurementDate.required' => '¿De qué día es?',
        ]);

        $measurement = $record->measurements()->make([
            'type' => $this->selectedType,
            'value' => (float) $data['measurementValue'],
            'value2' => $dual ? (float) $data['measurementValue2'] : null,
            'measured_on' => $data['measurementDate'],
        ]);
        $measurement->user_id = auth()->id();
        $measurement->save();

        $this->reset('measurementValue', 'measurementValue2', 'addingMeasurement');
        $this->measurementDate = now()->format('Y-m-d');
    }

    public function deleteMeasurement(int $id): void
    {
        $this->requireRecord()->measurements()->findOrFail($id)->delete();
    }

    public function showMoreMeasurements(): void
    {
        $this->measurementsLimit += self::MEASUREMENTS_PAGE;
    }

    private function requireRecord(): HealthRecord
    {
        return auth()->user()->accessibleHealthRecords()->findOrFail($this->recordId);
    }

    /**
     * La última medición de cada tipo, para los chips.
     *
     * @return Collection<string, HealthMeasurement>
     */
    #[Computed]
    public function latestByType(): Collection
    {
        // Una sola query para todos los tipos: queda la fila que no tiene otra
        // más nueva del mismo tipo (por fecha y, a igual fecha, por id).
        // Compatible con SQLite y Postgres.
        return $this->requireRecord()->measurements()
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('health_measurements as later')
                    ->whereColumn('later.health_record_id', 'health_measurements.health_record_id')
                    ->whereColumn('later.type', 'health_measurements.type')
                    ->where(function ($query) {
                        $query->whereColumn('later.measured_on', '>', 'health_measurements.measured_on')
                            ->orWhere(function ($query) {
                                $query->whereColumn('later.measured_on', 'health_measurements.measured_on')
                                    ->whereColumn('later.id', '>', 'health_measurements.id');
                            });
                    });
            })
            ->get()
            ->keyBy('type');
    }

    /**
     * La evolución del tipo elegido, de la más reciente a la más vieja, con
     * la diferencia contra la medición anterior. Se muestra de a
     * MEASUREMENTS_PAGE; "Ver más" agranda la ventana. La ventana se
     * consulta con limit+1: alcanza para saber si hay más y para calcular
     * la diferencia de la última fila visible.
     */
    #[Computed]
    public function history(): Collection
    {
        $window = $this->historyWindow;

        return $window->take($this->measurementsLimit)->values()->map(function ($measurement, $index) use ($window) {
            $previous = $window->get($index + 1);

            $delta = null;
            if ($previous && ! HealthMeasurement::isDualType($measurement->type)) {
                $delta = round($measurement->value - $previous->value, 2);
            }

            return ['measurement' => $measurement, 'delta' => $delta];
        });
    }

    #[Computed]
    public function hasMoreHistory(): bool
    {
        return $this->historyWindow->count() > $this->measurementsLimit;
    }

    #[Computed]
    public function historyWindow(): Collection
    {
        return $this->requireRecord()->measurements()
            ->where('type', $this->selectedType)
            ->orderByDesc('measured_on')
            ->orderByDesc('id')
            ->limit($this->measurementsLimit + 1)
            ->get();
    }
};
?>

<div class="space-y-3">
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <h2 class="font-brand text-lg font-bold">Mediciones</h2>
            <p class="text-sm text-cuero/60">Peso, presión, glucemia… anotá cada medición y seguimos cómo viene.</p>
        </div>
        @unless ($this->addingMeasurement)
            <button type="button" wire:click="$set('addingMeasurement', true)"
                class="min-h-11 shrink-0 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-ciruela">
                Anotar
            </button>
        @endunless
    </div>

    {{-- Un chip por tipo, con la última medición si la hay --}}
    <div class="flex flex-wrap gap-2" role="group" aria-label="Elegí qué medición ver">
        @foreach (\App\Models\HealthMeasurement::TYPES as $type => $config)
            @php($latest = $this->latestByType->get($type))
            <button type="button" wire:click="selectType('{{ $type }}')"
                @if ($this->selectedType === $type) aria-pressed="true" @endif
                class="min-h-11 rounded-sm border px-3 text-sm {{ $this->selectedType === $type ? 'border-ciruela bg-ciruela/10 font-semibold text-ciruela' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }}">
                {{ $config['label'] }}@if ($latest)<span class="ml-1 font-normal {{ $this->selectedType === $type ? 'text-ciruela/80' : 'text-cuero/50' }}">· {{ $latest->formattedValue() }}</span>@endif
            </button>
        @endforeach
    </div>

    @if ($this->addingMeasurement)
        @php($config = \App\Models\HealthMeasurement::TYPES[$this->selectedType])
        <form wire:submit="addMeasurement" class="space-y-3 rounded-sm border border-cuero/20 p-3">
            <h3 class="text-sm font-medium">{{ $config['label'] }} de hoy o de cuando haya sido</h3>
            <div class="grid gap-3 sm:grid-cols-3">
                @if ($config['dual'])
                    <div>
                        <label for="measurementValue" class="mb-1 block text-sm font-medium">Máxima <span class="font-normal text-cuero/60">({{ $config['unit'] }})</span></label>
                        <input id="measurementValue" type="number" inputmode="decimal" step="any" min="0" wire:model="measurementValue"
                            placeholder="120"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                        @error('measurementValue') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="measurementValue2" class="mb-1 block text-sm font-medium">Mínima <span class="font-normal text-cuero/60">({{ $config['unit'] }})</span></label>
                        <input id="measurementValue2" type="number" inputmode="decimal" step="any" min="0" wire:model="measurementValue2"
                            placeholder="80"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                        @error('measurementValue2') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label for="measurementValue" class="mb-1 block text-sm font-medium">Valor <span class="font-normal text-cuero/60">({{ $config['unit'] }})</span></label>
                        <input id="measurementValue" type="number" inputmode="decimal" step="any" min="0" wire:model="measurementValue"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                        @error('measurementValue') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <x-ui.date-field model="measurementDate" label="Fecha" accent="ciruela" preset="pasado" />
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                    Guardar
                </button>
                <button type="button" wire:click="$set('addingMeasurement', false)"
                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
            </div>
        </form>
    @endif

    @if ($this->history->isEmpty())
        @unless ($this->addingMeasurement)
            <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70" aria-live="polite">
                @if ($this->latestByType->isEmpty())
                    Todavía no anotaste ninguna medición. Cargá la primera y empezamos a seguirla.
                @else
                    Todavía no hay mediciones de {{ strtolower(\App\Models\HealthMeasurement::TYPES[$this->selectedType]['label']) }}. Anotá la primera cuando la tengas.
                @endif
            </p>
        @endunless
    @else
        <ul class="divide-y divide-cuero/15 rounded-sm border border-cuero/20">
            @foreach ($this->history as $row)
                @php($measurement = $row['measurement'])
                <li wire:key="measurement-{{ $measurement->id }}" class="flex items-center gap-3 px-3 py-2">
                    <span class="w-24 shrink-0 text-sm text-cuero/60">{{ $measurement->measured_on->format('d/m/Y') }}</span>
                    <span class="min-w-0 flex-1 font-medium">{{ $measurement->formattedValue() }}</span>
                    @if ($row['delta'] !== null && $row['delta'] != 0.0)
                        <span class="shrink-0 text-sm text-cuero/60">
                            {{ $row['delta'] > 0 ? '+' : '−' }}{{ \App\Models\HealthMeasurement::formatNumber(abs($row['delta'])) }}
                        </span>
                    @endif
                    <button type="button" wire:click="deleteMeasurement({{ $measurement->id }})"
                        wire:confirm="Vas a eliminar la medición del {{ $measurement->measured_on->format('d/m/Y') }}. Esto no se puede deshacer."
                        aria-label="Eliminar la medición del {{ $measurement->measured_on->format('d/m/Y') }}"
                        class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                        {{-- Heroicon: trash (mini) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </li>
            @endforeach
        </ul>

        @if ($this->hasMoreHistory)
            <button type="button" wire:click="showMoreMeasurements" wire:loading.attr="disabled"
                class="min-h-11 w-full rounded-sm border border-cuero/30 px-4 text-sm font-medium text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ciruela disabled:opacity-60">
                Ver más mediciones
            </button>
        @endif
    @endif
</div>
