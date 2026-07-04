<?php

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Auto')] class extends Component
{
    // Auto seleccionado (null hasta que exista alguno).
    public ?int $vehicleId = null;

    // Alta de auto.
    public string $newMarca = '';
    public string $newModelo = '';
    public string $newPatente = '';
    public ?int $newKilometraje = null;

    // Edición del kilometraje actual.
    public bool $editingKm = false;
    public ?int $kmValue = null;

    // Alta de mantenimiento a seguir.
    public bool $addingItem = false;
    public string $itemName = '';
    public ?int $itemIntervalKm = null;
    public ?int $itemIntervalMonths = null;

    // Registrar que un mantenimiento se hizo.
    public ?int $loggingItemId = null;
    public string $logDate = '';
    public ?int $logMileage = null;
    public ?string $logCost = null;

    // Carga de combustible.
    public string $fuelDate = '';
    public ?int $fuelMileage = null;
    public ?string $fuelCost = null;

    public function mount(): void
    {
        $this->logDate = now()->format('Y-m-d');
        $this->fuelDate = now()->format('Y-m-d');
        $this->vehicleId = auth()->user()->vehicles()->min('id');
    }

    // --- Auto -------------------------------------------------------------

    public function createVehicle(): void
    {
        $data = $this->validate([
            'newMarca' => ['required', 'string', 'max:60'],
            'newModelo' => ['required', 'string', 'max:60'],
            'newPatente' => ['nullable', 'string', 'max:12'],
            'newKilometraje' => ['required', 'integer', 'min:0', 'max:9999999'],
        ], [
            'newMarca.required' => 'Contame la marca del auto.',
            'newModelo.required' => '¿Qué modelo es?',
            'newKilometraje.required' => 'Necesito el kilometraje para llevarte la cuenta.',
            'newKilometraje.integer' => 'El kilometraje va en números, sin puntos ni letras.',
        ]);

        $vehicle = auth()->user()->vehicles()->create([
            'marca' => trim($data['newMarca']),
            'modelo' => trim($data['newModelo']),
            'patente' => $data['newPatente'] ? strtoupper(trim($data['newPatente'])) : null,
            'kilometraje' => $data['newKilometraje'],
        ]);

        // Le dejo cargados los mantenimientos más comunes para que arranque con algo.
        // Son sugerencias: los podés editar o borrar.
        foreach ([
            ['name' => 'Cambio de aceite', 'interval_km' => 10000, 'interval_months' => 12],
            ['name' => 'Cambio de bujías', 'interval_km' => 40000, 'interval_months' => null],
            ['name' => 'Correa de distribución', 'interval_km' => 60000, 'interval_months' => 60],
        ] as $preset) {
            $this->makeItem($vehicle, $preset['name'], $preset['interval_km'], $preset['interval_months']);
        }

        $this->reset('newMarca', 'newModelo', 'newPatente', 'newKilometraje');
        $this->vehicleId = $vehicle->id;
    }

    public function selectVehicle(int $id): void
    {
        auth()->user()->vehicles()->findOrFail($id);

        $this->vehicleId = $id;
        $this->reset('editingKm', 'addingItem', 'loggingItemId');
    }

    public function startEditingKm(): void
    {
        $this->kmValue = $this->vehicle?->kilometraje;
        $this->editingKm = true;
    }

    public function saveKm(): void
    {
        $vehicle = $this->requireVehicle();

        $this->validate([
            'kmValue' => ['required', 'integer', 'min:0', 'max:9999999'],
        ], [
            'kmValue.required' => 'Decime cuánto marca el tablero.',
            'kmValue.integer' => 'El kilometraje va en números.',
        ]);

        $vehicle->update(['kilometraje' => $this->kmValue]);
        $this->editingKm = false;
    }

    public function deleteVehicle(int $id): void
    {
        $vehicle = auth()->user()->vehicles()->findOrFail($id);
        $vehicle->delete();

        $this->vehicleId = auth()->user()->vehicles()->min('id');
        $this->reset('editingKm', 'addingItem', 'loggingItemId');
    }

    // --- Mantenimientos ---------------------------------------------------

    public function addItem(): void
    {
        $vehicle = $this->requireVehicle();

        $data = $this->validate([
            'itemName' => ['required', 'string', 'max:80'],
            'itemIntervalKm' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'itemIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
        ], [
            'itemName.required' => '¿Qué mantenimiento querés seguir?',
        ]);

        $this->makeItem($vehicle, trim($data['itemName']), $data['itemIntervalKm'], $data['itemIntervalMonths']);

        $this->reset('itemName', 'itemIntervalKm', 'itemIntervalMonths', 'addingItem');
    }

    public function deleteItem(int $id): void
    {
        $this->requireVehicle()->maintenanceItems()->findOrFail($id)->delete();

        if ($this->loggingItemId === $id) {
            $this->loggingItemId = null;
        }
    }

    public function startLog(int $id): void
    {
        $item = $this->requireVehicle()->maintenanceItems()->findOrFail($id);

        $this->loggingItemId = $item->id;
        $this->logDate = now()->format('Y-m-d');
        $this->logMileage = $this->vehicle?->kilometraje;
        $this->logCost = null;
        $this->resetValidation();
    }

    public function cancelLog(): void
    {
        $this->reset('loggingItemId', 'logMileage', 'logCost');
        $this->resetValidation();
    }

    public function saveLog(): void
    {
        $vehicle = $this->requireVehicle();
        $item = $vehicle->maintenanceItems()->findOrFail($this->loggingItemId);

        $this->logCost = $this->logCost === '' ? null : $this->logCost;

        $data = $this->validate([
            'logDate' => ['required', 'date'],
            'logMileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'logCost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'logDate.required' => '¿Qué día lo hiciste?',
            'logMileage.required' => 'Anotá con cuántos km lo hiciste.',
        ]);

        $record = $item->records()->make([
            'performed_on' => $data['logDate'],
            'mileage' => $data['logMileage'],
            'cost' => $data['logCost'],
        ]);
        $record->user_id = $vehicle->user_id;
        $record->vehicle_id = $vehicle->id;
        $record->save();

        $vehicle->bumpMileage((int) $data['logMileage']);

        $this->reset('loggingItemId', 'logMileage', 'logCost');
    }

    // --- Combustible ------------------------------------------------------

    public function addFuel(): void
    {
        $vehicle = $this->requireVehicle();

        $this->fuelCost = $this->fuelCost === '' ? null : $this->fuelCost;

        $data = $this->validate([
            'fuelDate' => ['required', 'date'],
            'fuelMileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'fuelCost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'fuelDate.required' => '¿Qué día cargaste?',
            'fuelMileage.required' => 'Anotá cuánto marcaba el auto al cargar.',
        ]);

        $log = $vehicle->fuelLogs()->make([
            'filled_on' => $data['fuelDate'],
            'mileage' => $data['fuelMileage'],
            'cost' => $data['fuelCost'],
        ]);
        $log->user_id = $vehicle->user_id;
        $log->save();

        $vehicle->bumpMileage((int) $data['fuelMileage']);

        $this->reset('fuelMileage', 'fuelCost');
        $this->fuelDate = now()->format('Y-m-d');
    }

    public function deleteFuel(int $id): void
    {
        $this->requireVehicle()->fuelLogs()->findOrFail($id)->delete();
    }

    // --- Helpers ----------------------------------------------------------

    private function makeItem(Vehicle $vehicle, string $name, ?int $km, ?int $months): void
    {
        $item = $vehicle->maintenanceItems()->make([
            'name' => $name,
            'interval_km' => $km,
            'interval_months' => $months,
        ]);
        $item->user_id = $vehicle->user_id;
        $item->save();
    }

    private function requireVehicle(): Vehicle
    {
        return auth()->user()->vehicles()->findOrFail($this->vehicleId);
    }

    public function pesos(int|string|null $value): string
    {
        return '$'.number_format((float) $value, 2, ',', '.');
    }

    public function km(int|string|null $value): string
    {
        return number_format((int) $value, 0, ',', '.').' km';
    }

    #[Computed]
    public function vehicles(): Collection
    {
        return auth()->user()->vehicles()->orderBy('id')->get();
    }

    #[Computed]
    public function vehicle(): ?Vehicle
    {
        return $this->vehicles->firstWhere('id', $this->vehicleId) ?? $this->vehicles->first();
    }

    #[Computed]
    public function items(): Collection
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return collect();
        }

        $km = $vehicle->kilometraje;

        return $vehicle->maintenanceItems()
            ->with('latestRecord')
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'item' => $item,
                'status' => $item->status($km),
            ])
            ->sortBy(fn ($row) => [$row['status']['rank'], $row['status']['urgency']])
            ->values();
    }

    #[Computed]
    public function fuelLogs(): Collection
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return collect();
        }

        $logs = $vehicle->fuelLogs()->orderByDesc('filled_on')->orderByDesc('mileage')->get();

        return $logs->map(function ($log, $index) use ($logs) {
            $previous = $logs->get($index + 1); // la carga inmediatamente anterior
            $since = $previous ? $log->mileage - $previous->mileage : null;

            return ['log' => $log, 'since' => $since !== null && $since >= 0 ? $since : null];
        });
    }

    #[Computed]
    public function maintenanceTotal(): float
    {
        return (float) ($this->vehicle?->maintenanceRecords()->sum('cost') ?? 0);
    }

    #[Computed]
    public function fuelTotal(): float
    {
        return (float) ($this->vehicle?->fuelLogs()->sum('cost') ?? 0);
    }
};
?>

<section class="space-y-6">
    <header class="flex items-center gap-3">
        <span class="h-8 w-1.5 rounded-sm bg-grafito" aria-hidden="true"></span>
        <h1 class="font-brand text-3xl font-bold">Auto</h1>
    </header>

    @if (! $this->vehicle)
        {{-- Sin auto todavía: alta --}}
        <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
            Todavía no cargaste ningún auto. Contame cuál es y empezamos a llevarle la cuenta.
        </p>

        <form wire:submit="createVehicle" class="space-y-3 rounded-sm border border-cuero/20 p-4">
            <h2 class="font-brand text-lg font-bold">Tu auto</h2>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="newMarca" class="mb-1 block text-sm font-medium">Marca</label>
                    <input id="newMarca" type="text" wire:model="newMarca" autocomplete="off"
                        placeholder="Volkswagen"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newMarca') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="newModelo" class="mb-1 block text-sm font-medium">Modelo</label>
                    <input id="newModelo" type="text" wire:model="newModelo" autocomplete="off"
                        placeholder="Gol Trend"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newModelo') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="newPatente" class="mb-1 block text-sm font-medium">Patente <span class="font-normal text-cuero/60">(opcional)</span></label>
                    <input id="newPatente" type="text" wire:model="newPatente" autocomplete="off"
                        placeholder="AB123CD"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base uppercase placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newPatente') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="newKilometraje" class="mb-1 block text-sm font-medium">Kilometraje actual</label>
                    <input id="newKilometraje" type="number" inputmode="numeric" min="0" wire:model="newKilometraje"
                        placeholder="85000"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newKilometraje') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="min-h-11 w-full rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60 sm:w-auto">
                Guardar auto
            </button>
        </form>
    @else
        @php($vehicle = $this->vehicle)

        {{-- Selector cuando hay más de un auto --}}
        @if ($this->vehicles->count() > 1)
            <div class="flex flex-wrap gap-2" role="group" aria-label="Elegí un auto">
                @foreach ($this->vehicles as $v)
                    <button type="button" wire:click="selectVehicle({{ $v->id }})"
                        @if ($v->id === $vehicle->id) aria-current="true" @endif
                        class="min-h-11 rounded-sm border px-3 text-sm {{ $v->id === $vehicle->id ? 'border-grafito bg-grafito/10 font-semibold text-grafito' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }}">
                        {{ $v->nombre() }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Ficha del auto --}}
        <div class="rounded-sm border border-cuero/20 p-4">
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="font-brand text-2xl font-bold leading-tight">{{ $vehicle->nombre() }}</h2>
                    @if ($vehicle->patente)
                        <span class="mt-1 inline-block rounded-sm bg-ocre px-2 py-0.5 text-xs font-semibold tracking-wide text-negro">
                            {{ $vehicle->patente }}
                        </span>
                    @endif
                </div>
                <button type="button" wire:click="deleteVehicle({{ $vehicle->id }})"
                    wire:confirm="Vas a eliminar este auto y todo su historial de mantenimientos y cargas. Esto no se puede deshacer."
                    aria-label="Eliminar auto"
                    class="grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 border-t border-cuero/15 pt-3">
                @if ($this->editingKm)
                    <form wire:submit="saveKm" class="flex flex-wrap items-end gap-2">
                        <div>
                            <label for="kmValue" class="mb-1 block text-sm font-medium">Kilometraje actual</label>
                            <input id="kmValue" type="number" inputmode="numeric" min="0" wire:model="kmValue"
                                class="min-h-11 w-40 rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                        </div>
                        <button type="submit"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('editingKm', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                        @error('kmValue') <p class="w-full text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </form>
                @else
                    <div class="flex items-center gap-3">
                        <div>
                            <p class="text-sm text-cuero/60">Kilometraje actual</p>
                            <p class="font-brand text-xl font-bold">{{ $this->km($vehicle->kilometraje) }}</p>
                        </div>
                        <button type="button" wire:click="startEditingKm"
                            class="ml-auto min-h-11 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                            Actualizar
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Mantenimientos --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <h2 class="font-brand text-lg font-bold">Mantenimientos</h2>
                @if ($this->maintenanceTotal > 0)
                    <span class="ml-auto text-sm text-cuero/60">Gastado: {{ $this->pesos($this->maintenanceTotal) }}</span>
                @endif
            </div>

            @if ($this->items->isEmpty())
                <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
                    Todavía no seguís ningún mantenimiento. Agregá el primero y te aviso cuándo toca.
                </p>
            @else
                <ul class="space-y-2">
                    @foreach ($this->items as $row)
                        @php($item = $row['item'])
                        @php($status = $row['status'])
                        <li wire:key="item-{{ $item->id }}" class="rounded-sm border border-cuero/20 p-3">
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium">{{ $item->name }}</span>
                                        <span @class([
                                            'rounded-sm px-2 py-0.5 text-xs font-semibold',
                                            'bg-teja text-crema' => $status['level'] === 'overdue',
                                            'bg-ocre text-negro' => $status['level'] === 'soon',
                                            'bg-yerba text-crema' => $status['level'] === 'ok',
                                            'border border-cuero/30 text-cuero/70' => $status['level'] === 'none',
                                        ])>
                                            {{ $status['headline'] }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-cuero/70">{{ $status['detail'] }}</p>

                                    @if ($item->latestRecord)
                                        <p class="mt-1 text-xs text-cuero/60">
                                            Último: {{ $item->latestRecord->performed_on->format('d/m/Y') }}
                                            · {{ $this->km($item->latestRecord->mileage) }}@if ($item->latestRecord->cost !== null) · {{ $this->pesos($item->latestRecord->cost) }}@endif
                                        </p>
                                    @endif

                                    <p class="mt-1 text-xs text-cuero/50">
                                        Cada
                                        @if ($item->interval_km){{ $this->km($item->interval_km) }}@endif
                                        @if ($item->interval_km && $item->interval_months) o @endif
                                        @if ($item->interval_months){{ $item->interval_months }} {{ $item->interval_months === 1 ? 'mes' : 'meses' }}@endif
                                        @if (! $item->interval_km && ! $item->interval_months)<span class="italic">sin recordatorio automático</span>@endif
                                    </p>
                                </div>

                                <button type="button" wire:click="deleteItem({{ $item->id }})"
                                    wire:confirm="Vas a eliminar «{{ $item->name }}» y su historial. Esto no se puede deshacer."
                                    aria-label="Eliminar {{ $item->name }}"
                                    class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>

                            @if ($this->loggingItemId === $item->id)
                                <form wire:submit="saveLog" class="mt-3 space-y-3 border-t border-cuero/15 pt-3">
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <label for="logDate-{{ $item->id }}" class="mb-1 block text-sm font-medium">Fecha</label>
                                            <input id="logDate-{{ $item->id }}" type="date" wire:model="logDate"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('logDate') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="logMileage-{{ $item->id }}" class="mb-1 block text-sm font-medium">Kilometraje</label>
                                            <input id="logMileage-{{ $item->id }}" type="number" inputmode="numeric" min="0" wire:model="logMileage"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('logMileage') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="logCost-{{ $item->id }}" class="mb-1 block text-sm font-medium">Costo <span class="font-normal text-cuero/60">(opcional)</span></label>
                                            <input id="logCost-{{ $item->id }}" type="number" inputmode="decimal" min="0" step="0.01" wire:model="logCost"
                                                placeholder="0,00"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('logCost') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit"
                                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                            Guardar
                                        </button>
                                        <button type="button" wire:click="cancelLog"
                                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                    </div>
                                </form>
                            @else
                                <button type="button" wire:click="startLog({{ $item->id }})"
                                    class="mt-3 min-h-11 w-full rounded-sm border border-grafito/40 px-3 text-sm font-medium text-grafito hover:bg-grafito/5 focus-visible:outline-2 focus-visible:outline-grafito sm:w-auto">
                                    Registrar que lo hice
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Agregar mantenimiento --}}
            @if ($this->addingItem)
                <form wire:submit="addItem" class="space-y-3 rounded-sm border border-cuero/20 p-3">
                    <div>
                        <label for="itemName" class="mb-1 block text-sm font-medium">¿Qué mantenimiento?</label>
                        <input id="itemName" type="text" wire:model="itemName" autocomplete="off"
                            placeholder="Filtro de aire, líquido de frenos…"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                        @error('itemName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="itemIntervalKm" class="mb-1 block text-sm font-medium">Cada cuántos km <span class="font-normal text-cuero/60">(opcional)</span></label>
                            <input id="itemIntervalKm" type="number" inputmode="numeric" min="1" wire:model="itemIntervalKm"
                                placeholder="10000"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('itemIntervalKm') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="itemIntervalMonths" class="mb-1 block text-sm font-medium">Cada cuántos meses <span class="font-normal text-cuero/60">(opcional)</span></label>
                            <input id="itemIntervalMonths" type="number" inputmode="numeric" min="1" wire:model="itemIntervalMonths"
                                placeholder="12"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('itemIntervalMonths') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-cuero/60">Si dejás los dos vacíos, lo llevo en el historial pero no te aviso del próximo.</p>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                            Agregar
                        </button>
                        <button type="button" wire:click="$set('addingItem', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                    </div>
                </form>
            @else
                <button type="button" wire:click="$set('addingItem', true)"
                    class="min-h-11 w-full rounded-sm border border-dashed border-cuero/40 px-3 text-sm font-medium text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                    + Agregar mantenimiento
                </button>
            @endif
        </div>

        {{-- Combustible --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <h2 class="font-brand text-lg font-bold">Combustible</h2>
                @if ($this->fuelTotal > 0)
                    <span class="ml-auto text-sm text-cuero/60">Gastado: {{ $this->pesos($this->fuelTotal) }}</span>
                @endif
            </div>

            <form wire:submit="addFuel" class="grid gap-3 rounded-sm border border-cuero/20 p-3 sm:grid-cols-4 sm:items-end">
                <div>
                    <label for="fuelDate" class="mb-1 block text-sm font-medium">Fecha</label>
                    <input id="fuelDate" type="date" wire:model="fuelDate"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('fuelDate') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="fuelMileage" class="mb-1 block text-sm font-medium">Kilometraje</label>
                    <input id="fuelMileage" type="number" inputmode="numeric" min="0" wire:model="fuelMileage"
                        placeholder="{{ $vehicle->kilometraje }}"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('fuelMileage') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="fuelCost" class="mb-1 block text-sm font-medium">Costo <span class="font-normal text-cuero/60">(opcional)</span></label>
                    <input id="fuelCost" type="number" inputmode="decimal" min="0" step="0.01" wire:model="fuelCost"
                        placeholder="0,00"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('fuelCost') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                    Anotar carga
                </button>
            </form>

            @if ($this->fuelLogs->isEmpty())
                <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
                    Todavía no anotaste ninguna carga. Cuando cargues, guardá el kilometraje y lo que gastaste.
                </p>
            @else
                <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                    @foreach ($this->fuelLogs as $row)
                        @php($log = $row['log'])
                        <li wire:key="fuel-{{ $log->id }}" class="flex items-center gap-2 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm">
                                    <span class="font-medium">{{ $log->filled_on->format('d/m/Y') }}</span>
                                    · {{ $this->km($log->mileage) }}
                                    @if ($log->cost !== null) · {{ $this->pesos($log->cost) }} @endif
                                </p>
                                @if ($row['since'] !== null)
                                    <p class="text-xs text-cuero/50">Recorriste {{ $this->km($row['since']) }} desde la carga anterior.</p>
                                @endif
                            </div>
                            <button type="button" wire:click="deleteFuel({{ $log->id }})"
                                wire:confirm="Vas a eliminar esta carga. Esto no se puede deshacer."
                                aria-label="Eliminar carga del {{ $log->filled_on->format('d/m/Y') }}"
                                class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>
