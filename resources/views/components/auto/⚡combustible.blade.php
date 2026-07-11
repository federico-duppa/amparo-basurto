<?php

use App\Models\Concerns\FormatsMoney;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Cargas de combustible del auto, con los km recorridos entre cargas.
 * Cualquier persona con acceso al auto las opera. Anotar o editar una carga
 * puede adelantar el kilometraje del auto: se avisa con el evento
 * `vehiculo-actualizado`.
 */
new class extends Component
{
    use FormatsMoney;

    /** Cargas de combustible visibles por página; "Ver más" agranda la ventana. */
    private const FUEL_PAGE = 20;

    #[Locked]
    public int $vehicleId;

    /** Caché de requireVehicle() dentro del mismo request. */
    private ?Vehicle $requiredVehicle = null;

    // Carga de combustible.
    public int $fuelLimit = self::FUEL_PAGE;
    public string $fuelDate = '';
    public ?int $fuelMileage = null;
    public ?string $fuelCost = null;

    // Edición de una carga de combustible ya guardada.
    public ?int $editingFuelId = null;
    public string $editFuelDate = '';
    public ?int $editFuelMileage = null;
    public ?string $editFuelCost = null;

    public function mount(): void
    {
        $this->fuelDate = now()->format('Y-m-d');
    }

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
            'fuelMileage.required' => 'Anotá cuánto marcaba el tablero al cargar.',
        ]);

        $log = $vehicle->fuelLogs()->make([
            'filled_on' => $data['fuelDate'],
            'mileage' => $data['fuelMileage'],
            'cost' => $data['fuelCost'],
        ]);
        $log->user_id = auth()->id();
        $log->save();

        $vehicle->bumpMileage((int) $data['fuelMileage']);

        $this->reset('fuelMileage', 'fuelCost');
        $this->fuelDate = now()->format('Y-m-d');
        $this->dispatch('vehiculo-actualizado');
    }

    public function startEditingFuel(int $id): void
    {
        $log = $this->requireVehicle()->fuelLogs()->findOrFail($id);

        $this->editingFuelId = $log->id;
        $this->editFuelDate = $log->filled_on->format('Y-m-d');
        $this->editFuelMileage = $log->mileage;
        $this->editFuelCost = $log->cost === null ? null : $this->cleanDecimal($log->cost);
        $this->resetValidation();
    }

    public function saveFuelEdit(): void
    {
        $vehicle = $this->requireVehicle();
        $log = $vehicle->fuelLogs()->findOrFail($this->editingFuelId);

        $this->editFuelCost = $this->editFuelCost === '' ? null : $this->editFuelCost;

        $data = $this->validate([
            'editFuelDate' => ['required', 'date'],
            'editFuelMileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'editFuelCost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'editFuelDate.required' => '¿Qué día cargaste?',
            'editFuelMileage.required' => 'Anotá cuánto marcaba el tablero al cargar.',
        ]);

        $log->update([
            'filled_on' => $data['editFuelDate'],
            'mileage' => $data['editFuelMileage'],
            'cost' => $data['editFuelCost'],
        ]);

        $vehicle->bumpMileage((int) $data['editFuelMileage']);

        $this->cancelEditFuel();
        $this->dispatch('vehiculo-actualizado');
    }

    public function cancelEditFuel(): void
    {
        $this->reset('editingFuelId', 'editFuelDate', 'editFuelMileage', 'editFuelCost');
        $this->resetValidation();
    }

    public function deleteFuel(int $id): void
    {
        $this->requireVehicle()->fuelLogs()->findOrFail($id)->delete();

        if ($this->editingFuelId === $id) {
            $this->cancelEditFuel();
        }

        $this->dispatch('vehiculo-actualizado');
    }

    public function showMoreFuel(): void
    {
        $this->fuelLimit += self::FUEL_PAGE;
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * Auto al que el usuario tiene acceso (propio o compartido). Memoizado
     * por request: varias acciones lo resuelven en el mismo render.
     */
    private function requireVehicle(): Vehicle
    {
        return $this->requiredVehicle ??= auth()->user()->accessibleVehicles()->findOrFail($this->vehicleId);
    }

    #[Computed]
    public function vehicle(): ?Vehicle
    {
        return auth()->user()->accessibleVehicles()->find($this->vehicleId);
    }

    /**
     * Con el auto compartido (dueño + al menos una persona más) mostramos
     * quién anotó cada registro; en un auto de una sola persona es ruido.
     */
    #[Computed]
    public function isShared(): bool
    {
        return $this->vehicle !== null && $this->vehicle->members()->exists();
    }

    /**
     * Cargas visibles, de a FUEL_PAGE, cada una con los km recorridos desde
     * la anterior; "Ver más" agranda la ventana.
     */
    #[Computed]
    public function fuelLogs(): Collection
    {
        $window = $this->fuelWindow;

        return $window->take($this->fuelLimit)->map(function ($log, $index) use ($window) {
            $previous = $window->get($index + 1); // la carga inmediatamente anterior
            $since = $previous ? $log->mileage - $previous->mileage : null;

            return ['log' => $log, 'since' => $since !== null && $since >= 0 ? $since : null];
        });
    }

    #[Computed]
    public function hasMoreFuel(): bool
    {
        return $this->fuelWindow->count() > $this->fuelLimit;
    }

    /**
     * Ventana de cargas acotada en SQL: la carga extra del limit+1 da los
     * "km desde la anterior" de la última visible y avisa si hay más.
     */
    #[Computed]
    public function fuelWindow(): Collection
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return collect();
        }

        return $vehicle->fuelLogs()->with('user')
            ->orderByDesc('filled_on')->orderByDesc('mileage')
            ->limit($this->fuelLimit + 1)->get();
    }

    #[Computed]
    public function fuelTotal(): float
    {
        return (float) ($this->vehicle?->fuelLogs()->sum('cost') ?? 0);
    }
};
?>

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
            <x-ui.date-field model="fuelDate" label="Fecha" accent="grafito" preset="pasado" />
        </div>
        <div>
            <label for="fuelMileage" class="mb-1 block text-sm font-medium">Kilometraje</label>
            <input id="fuelMileage" type="number" inputmode="numeric" min="0" wire:model="fuelMileage"
                placeholder="{{ $this->vehicle?->kilometraje }}"
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
                <li wire:key="fuel-{{ $log->id }}" class="py-2">
                    @if ($this->editingFuelId === $log->id)
                        <form wire:submit="saveFuelEdit" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <x-ui.date-field model="editFuelDate" id="editFuelDate-{{ $log->id }}" label="Fecha" accent="grafito" preset="pasado" />
                                </div>
                                <div>
                                    <label for="editFuelMileage-{{ $log->id }}" class="mb-1 block text-sm font-medium">Kilometraje</label>
                                    <input id="editFuelMileage-{{ $log->id }}" type="number" inputmode="numeric" min="0" wire:model="editFuelMileage"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editFuelMileage') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="editFuelCost-{{ $log->id }}" class="mb-1 block text-sm font-medium">Costo <span class="font-normal text-cuero/60">(opcional)</span></label>
                                    <input id="editFuelCost-{{ $log->id }}" type="number" inputmode="decimal" min="0" step="0.01" wire:model="editFuelCost"
                                        placeholder="0,00"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editFuelCost') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                    Guardar
                                </button>
                                <button type="button" wire:click="cancelEditFuel"
                                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-center gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm">
                                    <span class="font-medium">{{ $log->filled_on->format('d/m/Y') }}</span>
                                    · {{ $this->km($log->mileage) }}
                                    @if ($log->cost !== null) · {{ $this->pesos($log->cost) }} @endif
                                </p>
                                @if ($row['since'] !== null)
                                    <p class="text-xs text-cuero/50">Recorriste {{ $this->km($row['since']) }} desde la carga anterior.</p>
                                @endif
                                @if ($this->isShared)
                                    <p class="text-xs text-cuero/50">Anotó {{ $log->user->name }}</p>
                                @endif
                            </div>
                            <button type="button" wire:click="startEditingFuel({{ $log->id }})"
                                aria-label="Editar carga del {{ $log->filled_on->format('d/m/Y') }}"
                                class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-grafito focus-visible:outline-2 focus-visible:outline-grafito">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                    <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                </svg>
                            </button>
                            <button type="button" wire:click="deleteFuel({{ $log->id }})"
                                wire:confirm="Vas a eliminar esta carga. Esto no se puede deshacer."
                                aria-label="Eliminar carga del {{ $log->filled_on->format('d/m/Y') }}"
                                class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($this->hasMoreFuel)
            <button type="button" wire:click="showMoreFuel" wire:loading.attr="disabled"
                class="min-h-11 w-full rounded-sm border border-cuero/30 px-4 text-sm font-medium text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-grafito disabled:opacity-60">
                Ver más cargas
            </button>
        @endif
    @endif
</div>
