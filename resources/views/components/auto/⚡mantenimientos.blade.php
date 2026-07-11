<?php

use App\Models\Concerns\FormatsMoney;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Mantenimientos a seguir del auto: qué le toca y cuándo, con el historial
 * de realizaciones de cada ítem. Cualquier persona con acceso al auto los
 * opera. Registrar o editar una realización puede adelantar el kilometraje
 * del auto: se avisa con el evento `vehiculo-actualizado`.
 */
new class extends Component
{
    use FormatsMoney;

    /** Realizaciones visibles por página en el historial de un ítem. */
    private const HISTORY_PAGE = 10;

    #[Locked]
    public int $vehicleId;

    /** Caché de requireVehicle() dentro del mismo request. */
    private ?Vehicle $requiredVehicle = null;

    // Alta de mantenimiento a seguir.
    public bool $addingItem = false;
    public string $itemName = '';
    public ?int $itemIntervalKm = null;
    public ?int $itemIntervalMonths = null;

    // Edición de un ítem de mantenimiento ya guardado.
    public ?int $editingItemId = null;
    public string $editItemName = '';
    public ?int $editItemIntervalKm = null;
    public ?int $editItemIntervalMonths = null;

    // Registrar que un mantenimiento se hizo.
    public ?int $loggingItemId = null;
    public string $logDate = '';
    public ?int $logMileage = null;
    public ?string $logCost = null;
    public string $logNote = '';

    // Historial de realizaciones desplegado y edición de una realización.
    public ?int $historyItemId = null;
    public int $historyLimit = self::HISTORY_PAGE;
    public ?int $editingRecordId = null;
    public string $editRecordDate = '';
    public ?int $editRecordMileage = null;
    public ?string $editRecordCost = null;
    public string $editRecordNote = '';

    public function mount(): void
    {
        $this->logDate = now()->format('Y-m-d');
    }

    /**
     * Otro componente cambió el auto (una carga adelantó el kilometraje):
     * recibir el evento ya re-renderiza los estados con datos frescos.
     */
    #[On('vehiculo-actualizado')]
    public function refresh(): void {}

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

        $vehicle->addMaintenanceItem(trim($data['itemName']), $data['itemIntervalKm'], $data['itemIntervalMonths'], auth()->id());

        $this->reset('itemName', 'itemIntervalKm', 'itemIntervalMonths', 'addingItem');
    }

    public function startEditingItem(int $id): void
    {
        $item = $this->requireVehicle()->maintenanceItems()->findOrFail($id);

        $this->editingItemId = $item->id;
        $this->editItemName = $item->name;
        $this->editItemIntervalKm = $item->interval_km;
        $this->editItemIntervalMonths = $item->interval_months;
        $this->resetValidation();
    }

    public function saveItem(): void
    {
        $item = $this->requireVehicle()->maintenanceItems()->findOrFail($this->editingItemId);

        $data = $this->validate([
            'editItemName' => ['required', 'string', 'max:80'],
            'editItemIntervalKm' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'editItemIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
        ], [
            'editItemName.required' => '¿Qué mantenimiento querés seguir?',
        ]);

        $item->update([
            'name' => trim($data['editItemName']),
            'interval_km' => $data['editItemIntervalKm'],
            'interval_months' => $data['editItemIntervalMonths'],
        ]);

        $this->cancelEditItem();
    }

    public function cancelEditItem(): void
    {
        $this->reset('editingItemId', 'editItemName', 'editItemIntervalKm', 'editItemIntervalMonths');
        $this->resetValidation();
    }

    public function deleteItem(int $id): void
    {
        $this->requireVehicle()->maintenanceItems()->findOrFail($id)->delete();

        if ($this->loggingItemId === $id) {
            $this->loggingItemId = null;
        }

        if ($this->editingItemId === $id) {
            $this->cancelEditItem();
        }

        if ($this->historyItemId === $id) {
            $this->reset('historyItemId', 'editingRecordId', 'editRecordDate', 'editRecordMileage', 'editRecordCost');
        }

        // Con el ítem se va su historial: los gastos por período cambian.
        $this->dispatch('vehiculo-actualizado');
    }

    public function startLog(int $id): void
    {
        $item = $this->requireVehicle()->maintenanceItems()->findOrFail($id);

        $this->loggingItemId = $item->id;
        $this->logDate = now()->format('Y-m-d');
        $this->logMileage = $this->vehicle?->kilometraje;
        $this->logCost = null;
        $this->logNote = '';
        $this->resetValidation();
    }

    public function cancelLog(): void
    {
        $this->reset('loggingItemId', 'logMileage', 'logCost', 'logNote');
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
            'logNote' => ['nullable', 'string', 'max:255'],
        ], [
            'logDate.required' => '¿Qué día lo hiciste?',
            'logMileage.required' => 'Anotá con cuántos km lo hiciste.',
        ]);

        $record = $item->records()->make([
            'performed_on' => $data['logDate'],
            'mileage' => $data['logMileage'],
            'cost' => $data['logCost'],
            'note' => trim($this->logNote) === '' ? null : trim($this->logNote),
        ]);
        $record->user_id = auth()->id();
        $record->vehicle_id = $vehicle->id;
        $record->save();

        $vehicle->bumpMileage((int) $data['logMileage']);

        $this->reset('loggingItemId', 'logMileage', 'logCost', 'logNote');
        $this->dispatch('vehiculo-actualizado');
    }

    // --- Realizaciones (historial) ----------------------------------------

    public function toggleHistory(int $id): void
    {
        // Acordeón: abrir un historial cierra el que estuviera abierto.
        $this->historyItemId = $this->historyItemId === $id ? null : $id;
        $this->reset('historyLimit');
        $this->cancelEditRecord();
    }

    public function showMoreHistory(): void
    {
        $this->historyLimit += self::HISTORY_PAGE;
    }

    public function startEditingRecord(int $id): void
    {
        $record = $this->requireVehicle()->maintenanceRecords()->findOrFail($id);

        $this->editingRecordId = $record->id;
        $this->editRecordDate = $record->performed_on->format('Y-m-d');
        $this->editRecordMileage = $record->mileage;
        $this->editRecordCost = $record->cost === null ? null : $this->cleanDecimal($record->cost);
        $this->editRecordNote = (string) $record->note;
        $this->resetValidation();
    }

    public function saveRecord(): void
    {
        $vehicle = $this->requireVehicle();
        $record = $vehicle->maintenanceRecords()->findOrFail($this->editingRecordId);

        $this->editRecordCost = $this->editRecordCost === '' ? null : $this->editRecordCost;

        $data = $this->validate([
            'editRecordDate' => ['required', 'date'],
            'editRecordMileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'editRecordCost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'editRecordNote' => ['nullable', 'string', 'max:255'],
        ], [
            'editRecordDate.required' => '¿Qué día lo hiciste?',
            'editRecordMileage.required' => 'Anotá con cuántos km lo hiciste.',
        ]);

        $record->update([
            'performed_on' => $data['editRecordDate'],
            'mileage' => $data['editRecordMileage'],
            'cost' => $data['editRecordCost'],
            'note' => trim($this->editRecordNote) === '' ? null : trim($this->editRecordNote),
        ]);

        $vehicle->bumpMileage((int) $data['editRecordMileage']);

        $this->cancelEditRecord();
        $this->dispatch('vehiculo-actualizado');
    }

    public function cancelEditRecord(): void
    {
        $this->reset('editingRecordId', 'editRecordDate', 'editRecordMileage', 'editRecordCost', 'editRecordNote');
        $this->resetValidation();
    }

    public function deleteRecord(int $id): void
    {
        $this->requireVehicle()->maintenanceRecords()->findOrFail($id)->delete();

        if ($this->editingRecordId === $id) {
            $this->cancelEditRecord();
        }

        $this->dispatch('vehiculo-actualizado');
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

    #[Computed]
    public function items(): Collection
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return collect();
        }

        $km = $vehicle->kilometraje;
        $kmPerDay = $vehicle->kmPerDay();

        return $vehicle->maintenanceItems()
            ->with('latestRecord')
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'item' => $item,
                'status' => $item->status($km, $kmPerDay),
            ])
            ->sortBy(fn ($row) => [$row['status']['rank'], $row['status']['urgency']])
            ->values();
    }

    /**
     * Realizaciones del ítem desplegado, de la más reciente a la más vieja.
     * Se muestran de a HISTORY_PAGE; "Ver más" agranda la ventana.
     */
    #[Computed]
    public function history(): Collection
    {
        return $this->historyWindow->take($this->historyLimit)->values();
    }

    #[Computed]
    public function hasMoreHistory(): bool
    {
        return $this->historyWindow->count() > $this->historyLimit;
    }

    /**
     * Ventana de realizaciones acotada en SQL: con limit+1 alcanza para
     * armar la página y saber si hay más, sin cargar años de historia.
     */
    #[Computed]
    public function historyWindow(): Collection
    {
        if ($this->historyItemId === null) {
            return collect();
        }

        $item = $this->vehicle?->maintenanceItems()->find($this->historyItemId);

        return $item
            ? $item->records()->with('user')->orderByDesc('performed_on')->orderByDesc('mileage')->limit($this->historyLimit + 1)->get()
            : collect();
    }

    #[Computed]
    public function maintenanceTotal(): float
    {
        return (float) ($this->vehicle?->maintenanceRecords()->sum('cost') ?? 0);
    }
};
?>

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
                    @if ($this->editingItemId === $item->id)
                        <form wire:submit="saveItem" class="space-y-3">
                            <div>
                                <label for="editItemName-{{ $item->id }}" class="mb-1 block text-sm font-medium">¿Qué mantenimiento?</label>
                                <input id="editItemName-{{ $item->id }}" type="text" wire:model="editItemName" autocomplete="off"
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @error('editItemName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="editItemIntervalKm-{{ $item->id }}" class="mb-1 block text-sm font-medium">Cada cuántos km <span class="font-normal text-cuero/60">(opcional)</span></label>
                                    <input id="editItemIntervalKm-{{ $item->id }}" type="number" inputmode="numeric" min="1" wire:model="editItemIntervalKm"
                                        placeholder="10000"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editItemIntervalKm') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="editItemIntervalMonths-{{ $item->id }}" class="mb-1 block text-sm font-medium">Cada cuántos meses <span class="font-normal text-cuero/60">(opcional)</span></label>
                                    <input id="editItemIntervalMonths-{{ $item->id }}" type="number" inputmode="numeric" min="1" wire:model="editItemIntervalMonths"
                                        placeholder="12"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editItemIntervalMonths') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <p class="text-xs text-cuero/60">Cambiar los intervalos recalcula cuándo toca el próximo. El historial de realizaciones no se toca.</p>
                            <div class="flex gap-2">
                                <button type="submit"
                                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                    Guardar
                                </button>
                                <button type="button" wire:click="cancelEditItem"
                                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                            </div>
                        </form>
                    @else
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
                                @if ($item->latestRecord->note)
                                    <p class="mt-1 text-xs text-cuero/60">{{ $item->latestRecord->note }}</p>
                                @endif
                            @endif

                            <p class="mt-1 text-xs text-cuero/50">
                                Cada
                                @if ($item->interval_km){{ $this->km($item->interval_km) }}@endif
                                @if ($item->interval_km && $item->interval_months) o @endif
                                @if ($item->interval_months){{ $item->interval_months }} {{ $item->interval_months === 1 ? 'mes' : 'meses' }}@endif
                                @if (! $item->interval_km && ! $item->interval_months)<span class="italic">sin recordatorio automático</span>@endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center">
                            <button type="button" wire:click="startEditingItem({{ $item->id }})"
                                aria-label="Editar {{ $item->name }}"
                                class="grid size-9 place-items-center text-cuero/50 hover:text-grafito focus-visible:outline-2 focus-visible:outline-grafito">
                                {{-- Heroicon: pencil-square (mini) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                    <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                </svg>
                            </button>
                            <button type="button" wire:click="deleteItem({{ $item->id }})"
                                wire:confirm="Vas a eliminar «{{ $item->name }}» y su historial. Esto no se puede deshacer."
                                aria-label="Eliminar {{ $item->name }}"
                                class="grid size-9 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    @if ($this->loggingItemId === $item->id)
                        <form wire:submit="saveLog" class="mt-3 space-y-3 border-t border-cuero/15 pt-3">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <x-ui.date-field model="logDate" id="logDate-{{ $item->id }}" label="Fecha" accent="grafito" preset="pasado" />
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
                            <div>
                                <label for="logNote-{{ $item->id }}" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                                <input id="logNote-{{ $item->id }}" type="text" wire:model="logNote" autocomplete="off"
                                    placeholder="Taller, qué se hizo, repuestos…"
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @error('logNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
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

                    @if ($item->latestRecord)
                        <button type="button" wire:click="toggleHistory({{ $item->id }})"
                            aria-expanded="{{ $this->historyItemId === $item->id ? 'true' : 'false' }}"
                            class="mt-3 flex min-h-11 items-center gap-1 text-sm text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                class="size-4 transition-transform {{ $this->historyItemId === $item->id ? 'rotate-90' : '' }}">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                            </svg>
                            {{ $this->historyItemId === $item->id ? 'Ocultar historial' : 'Ver historial' }}
                        </button>

                        @if ($this->historyItemId === $item->id)
                            <ul class="mt-2 divide-y divide-cuero/15 border-y border-cuero/15">
                                @foreach ($this->history as $record)
                                    <li wire:key="record-{{ $record->id }}" class="py-2">
                                        @if ($this->editingRecordId === $record->id)
                                            <form wire:submit="saveRecord" class="space-y-3">
                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <x-ui.date-field model="editRecordDate" id="editRecordDate-{{ $record->id }}" label="Fecha" accent="grafito" preset="pasado" />
                                                    </div>
                                                    <div>
                                                        <label for="editRecordMileage-{{ $record->id }}" class="mb-1 block text-sm font-medium">Kilometraje</label>
                                                        <input id="editRecordMileage-{{ $record->id }}" type="number" inputmode="numeric" min="0" wire:model="editRecordMileage"
                                                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                        @error('editRecordMileage') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                                    </div>
                                                    <div>
                                                        <label for="editRecordCost-{{ $record->id }}" class="mb-1 block text-sm font-medium">Costo <span class="font-normal text-cuero/60">(opcional)</span></label>
                                                        <input id="editRecordCost-{{ $record->id }}" type="number" inputmode="decimal" min="0" step="0.01" wire:model="editRecordCost"
                                                            placeholder="0,00"
                                                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                        @error('editRecordCost') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                                    </div>
                                                </div>
                                                <div>
                                                    <label for="editRecordNote-{{ $record->id }}" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                                                    <input id="editRecordNote-{{ $record->id }}" type="text" wire:model="editRecordNote" autocomplete="off"
                                                        placeholder="Taller, qué se hizo, repuestos…"
                                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                    @error('editRecordNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                                </div>
                                                <div class="flex gap-2">
                                                    <button type="submit"
                                                        class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                                        Guardar
                                                    </button>
                                                    <button type="button" wire:click="cancelEditRecord"
                                                        class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm">
                                                        <span class="font-medium">{{ $record->performed_on->format('d/m/Y') }}</span>
                                                        · {{ $this->km($record->mileage) }}@if ($record->cost !== null) · {{ $this->pesos($record->cost) }}@endif
                                                    </p>
                                                    @if ($record->note)
                                                        <p class="text-xs text-cuero/60">{{ $record->note }}</p>
                                                    @endif
                                                    @if ($this->isShared)
                                                        <p class="text-xs text-cuero/50">Anotó {{ $record->user->name }}</p>
                                                    @endif
                                                </div>
                                                <button type="button" wire:click="startEditingRecord({{ $record->id }})"
                                                    aria-label="Editar realización del {{ $record->performed_on->format('d/m/Y') }}"
                                                    class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-grafito focus-visible:outline-2 focus-visible:outline-grafito">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                                        <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                                    </svg>
                                                </button>
                                                <button type="button" wire:click="deleteRecord({{ $record->id }})"
                                                    wire:confirm="Vas a eliminar esta realización. Esto no se puede deshacer."
                                                    aria-label="Eliminar realización del {{ $record->performed_on->format('d/m/Y') }}"
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

                            @if ($this->hasMoreHistory)
                                <button type="button" wire:click="showMoreHistory" wire:loading.attr="disabled"
                                    class="mt-2 min-h-11 w-full rounded-sm border border-cuero/30 px-4 text-sm font-medium text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-grafito disabled:opacity-60">
                                    Ver más realizaciones
                                </button>
                            @endif
                        @endif
                    @endif
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
