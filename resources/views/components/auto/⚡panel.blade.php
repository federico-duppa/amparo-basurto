<?php

use App\Livewire\Concerns\SharesWithMembers;
use App\Models\Concerns\FormatsMoney;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Página del módulo Auto: el vehículo (alta, selector, ficha, kilometraje,
 * compartir) y un hijo Livewire por sección — mantenimientos, documentación,
 * combustible y gastos — para que cada interacción viaje con el estado justo.
 */
new #[Title('Auto')] class extends Component
{
    use FormatsMoney;
    use SharesWithMembers;

    // Auto seleccionado (null hasta que exista alguno).
    public ?int $vehicleId = null;

    /** Caché de requireVehicle() dentro del mismo request. */
    private ?Vehicle $requiredVehicle = null;

    // Alta de auto (con autos ya cargados el formulario se abre a pedido).
    public bool $addingVehicle = false;
    public string $newTipo = 'auto';
    public string $newMarca = '';
    public string $newModelo = '';
    public string $newPatente = '';
    public ?int $newKilometraje = null;

    // Edición de los datos del auto (solo el dueño).
    public bool $editingVehicle = false;
    public string $editTipo = 'auto';
    public string $editMarca = '';
    public string $editModelo = '';
    public string $editPatente = '';

    // Compartir el auto con otra persona (solo el dueño).
    public string $shareUsername = '';

    // Edición del kilometraje actual.
    public bool $editingKm = false;
    public ?int $kmValue = null;

    public function mount(): void
    {
        $this->vehicleId = auth()->user()->accessibleVehicles()->min('vehicles.id');
    }

    /**
     * Un hijo cambió el auto (un mantenimiento o una carga adelantaron el
     * kilometraje): recibir el evento ya re-renderiza la ficha con datos
     * frescos.
     */
    #[On('vehiculo-actualizado')]
    public function refresh(): void {}

    // --- Auto -------------------------------------------------------------

    public function startAddingVehicle(): void
    {
        $this->reset('newTipo', 'newMarca', 'newModelo', 'newPatente', 'newKilometraje');
        $this->addingVehicle = true;
        $this->resetValidation();
    }

    public function cancelAddVehicle(): void
    {
        $this->reset('addingVehicle', 'newTipo', 'newMarca', 'newModelo', 'newPatente', 'newKilometraje');
        $this->resetValidation();
    }

    public function createVehicle(): void
    {
        $data = $this->validate([
            'newTipo' => ['required', Rule::in(array_keys(Vehicle::TIPOS))],
            'newMarca' => ['required', 'string', 'max:60'],
            'newModelo' => ['required', 'string', 'max:60'],
            'newPatente' => ['nullable', 'string', 'max:12'],
            'newKilometraje' => ['required', 'integer', 'min:0', 'max:9999999'],
        ], [
            'newMarca.required' => 'Contame la marca.',
            'newModelo.required' => '¿Qué modelo es?',
            'newKilometraje.required' => 'Necesito el kilometraje para llevarte la cuenta.',
            'newKilometraje.integer' => 'El kilometraje va en números, sin puntos ni letras.',
        ]);

        $vehicle = auth()->user()->vehicles()->create([
            'tipo' => $data['newTipo'],
            'marca' => trim($data['newMarca']),
            'modelo' => trim($data['newModelo']),
            'patente' => $data['newPatente'] ? strtoupper(trim($data['newPatente'])) : null,
            'kilometraje' => $data['newKilometraje'],
        ]);

        // Le dejo cargados los mantenimientos más comunes del tipo para que
        // arranque con algo. Son sugerencias: los podés editar o borrar.
        foreach ($vehicle->presets() as $preset) {
            $vehicle->addMaintenanceItem($preset['name'], $preset['interval_km'], $preset['interval_months'], auth()->id());
        }

        $this->reset('addingVehicle', 'newTipo', 'newMarca', 'newModelo', 'newPatente', 'newKilometraje', 'editingKm', 'editingVehicle');
        $this->vehicleId = $vehicle->id;
    }

    public function selectVehicle(int $id): void
    {
        auth()->user()->accessibleVehicles()->findOrFail($id);

        // Los hijos van rekeyeados por auto, así que se remontan solos con
        // su estado limpio; acá alcanza con resetear lo propio de la ficha.
        $this->vehicleId = $id;
        $this->reset('addingVehicle', 'editingKm', 'editingVehicle');
    }

    public function startEditingVehicle(): void
    {
        $vehicle = $this->requireOwnedVehicle();

        $this->editTipo = $vehicle->tipo;
        $this->editMarca = $vehicle->marca;
        $this->editModelo = $vehicle->modelo;
        $this->editPatente = (string) $vehicle->patente;
        $this->editingVehicle = true;
        $this->resetValidation();
    }

    public function saveVehicle(): void
    {
        $vehicle = $this->requireOwnedVehicle();

        $data = $this->validate([
            'editTipo' => ['required', Rule::in(array_keys(Vehicle::TIPOS))],
            'editMarca' => ['required', 'string', 'max:60'],
            'editModelo' => ['required', 'string', 'max:60'],
            'editPatente' => ['nullable', 'string', 'max:12'],
        ], [
            'editMarca.required' => 'Contame la marca.',
            'editModelo.required' => '¿Qué modelo es?',
        ]);

        $vehicle->update([
            'tipo' => $data['editTipo'],
            'marca' => trim($data['editMarca']),
            'modelo' => trim($data['editModelo']),
            'patente' => $data['editPatente'] ? strtoupper(trim($data['editPatente'])) : null,
        ]);

        $this->editingVehicle = false;
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

        // El km es la referencia de los vencimientos: los hijos lo recalculan.
        $this->dispatch('vehiculo-actualizado');
    }

    public function deleteVehicle(int $id): void
    {
        // Solo el dueño puede eliminar el auto (borra también su historial).
        $vehicle = auth()->user()->vehicles()->findOrFail($id);
        $vehicle->delete();

        $this->vehicleId = auth()->user()->accessibleVehicles()->min('vehicles.id');
        $this->reset('addingVehicle', 'editingKm', 'editingVehicle');
    }

    // --- Compartir --------------------------------------------------------

    protected function shareableOwned(): Vehicle
    {
        return $this->requireOwnedVehicle();
    }

    protected function shareableNoun(): array
    {
        return ['noun' => 'vehículo', 'genero' => 'm'];
    }

    /**
     * Pasarle el auto a una persona con quien ya se comparte: ella queda como
     * dueña y quien lo transfiere pasa a verlo como compartido. Así el auto
     * no queda huérfano si el dueño deja de usar la app.
     */
    public function transferOwnership(int $userId): void
    {
        $vehicle = $this->requireOwnedVehicle();
        $newOwner = $vehicle->members()->findOrFail($userId);

        DB::transaction(function () use ($vehicle, $newOwner) {
            $vehicle->members()->detach($newOwner->id);
            $vehicle->members()->attach($vehicle->user_id);
            $vehicle->user()->associate($newOwner)->save();
        });

        $this->reset('editingVehicle', 'shareUsername');
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * Auto al que el usuario tiene acceso (propio o compartido). Cualquier
     * persona con acceso puede corregir el kilometraje.
     * Memoizado por request: varias acciones lo resuelven en el mismo render.
     */
    private function requireVehicle(): Vehicle
    {
        return $this->requiredVehicle ??= auth()->user()->accessibleVehicles()->findOrFail($this->vehicleId);
    }

    /**
     * Auto del que el usuario es dueño. Editar, eliminar y compartir son
     * acciones reservadas al dueño.
     */
    private function requireOwnedVehicle(): Vehicle
    {
        return auth()->user()->vehicles()->findOrFail($this->vehicleId);
    }

    #[Computed]
    public function vehicles(): Collection
    {
        return auth()->user()->accessibleVehicles()->with('user')->orderBy('vehicles.id')->get();
    }

    #[Computed]
    public function vehicle(): ?Vehicle
    {
        return $this->vehicles->firstWhere('id', $this->vehicleId) ?? $this->vehicles->first();
    }

    #[Computed]
    public function isOwner(): bool
    {
        return $this->vehicle !== null && $this->vehicle->user_id === auth()->id();
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->vehicle?->members()->orderBy('name')->get() ?? collect();
    }
};
?>

<section class="space-y-6">
    <header class="flex items-center gap-3">
        <span class="h-8 w-1.5 rounded-sm bg-grafito" aria-hidden="true"></span>
        <h1 class="font-brand text-3xl font-bold">Auto</h1>
    </header>

    @if (! $this->vehicle)
        {{-- Sin vehículo todavía --}}
        <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
            Todavía no cargaste ningún vehículo. Contame si es un auto o una moto y empezamos a llevarle la cuenta.
        </p>
    @endif

    @if (! $this->vehicle || $this->addingVehicle)
        {{-- Alta: el primer vehículo, o uno más --}}
        @php($esNuevaMoto = $newTipo === 'moto')
        <form wire:submit="createVehicle" class="space-y-3 rounded-sm border border-cuero/20 p-4">
            <h2 class="font-brand text-lg font-bold">
                {{ $this->vehicle ? ($esNuevaMoto ? 'Otra moto' : 'Otro auto') : ($esNuevaMoto ? 'Tu moto' : 'Tu auto') }}
            </h2>

            <fieldset>
                <legend class="mb-1 block text-sm font-medium">¿Qué es?</legend>
                <div class="flex gap-2" role="radiogroup">
                    @foreach (\App\Models\Vehicle::TIPOS as $value => $label)
                        <label class="flex-1">
                            <input type="radio" wire:model.live="newTipo" value="{{ $value }}" class="peer sr-only">
                            <span class="grid min-h-11 cursor-pointer place-items-center rounded-sm border border-cuero/30 text-sm text-cuero/70 hover:text-cuero peer-checked:border-grafito peer-checked:bg-grafito/10 peer-checked:font-semibold peer-checked:text-grafito peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-grafito">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="newMarca" class="mb-1 block text-sm font-medium">Marca</label>
                    <input id="newMarca" type="text" wire:model="newMarca" autocomplete="off"
                        placeholder="{{ $esNuevaMoto ? 'Honda' : 'Volkswagen' }}"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('newMarca') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="newModelo" class="mb-1 block text-sm font-medium">Modelo</label>
                    <input id="newModelo" type="text" wire:model="newModelo" autocomplete="off"
                        placeholder="{{ $esNuevaMoto ? 'Tornado' : 'Gol Trend' }}"
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

            <div class="flex flex-wrap gap-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 w-full rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60 sm:w-auto">
                    {{ $esNuevaMoto ? 'Guardar moto' : 'Guardar auto' }}
                </button>
                @if ($this->vehicle)
                    <button type="button" wire:click="cancelAddVehicle"
                        class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                @endif
            </div>
        </form>
    @endif

    @if ($this->vehicle)
        @php($vehicle = $this->vehicle)

        {{-- Selector cuando hay más de un auto, con el alta de otro a mano --}}
        @if ($this->vehicles->count() > 1 || ! $this->addingVehicle)
            <div class="flex flex-wrap gap-2" role="group" aria-label="Tus vehículos">
                @if ($this->vehicles->count() > 1)
                    @foreach ($this->vehicles as $v)
                        <button type="button" wire:click="selectVehicle({{ $v->id }})"
                            @if ($v->id === $vehicle->id) aria-current="true" @endif
                            class="min-h-11 rounded-sm border px-3 text-sm {{ $v->id === $vehicle->id ? 'border-grafito bg-grafito/10 font-semibold text-grafito' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }}">
                            {{ $v->nombre() }}@unless ($v->user_id === auth()->id())<span class="ml-1 text-xs font-normal text-cuero/50">· compartido</span>@endunless
                        </button>
                    @endforeach
                @endif
                @unless ($this->addingVehicle)
                    <button type="button" wire:click="startAddingVehicle"
                        class="min-h-11 rounded-sm border border-dashed border-cuero/40 px-3 text-sm font-medium text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                        + Otro vehículo
                    </button>
                @endunless
            </div>
        @endif

        {{-- Ficha del auto --}}
        <div class="rounded-sm border border-cuero/20 p-4">
            @if ($this->editingVehicle)
                <form wire:submit="saveVehicle" class="space-y-3">
                    <h2 class="font-brand text-lg font-bold">{{ $editTipo === 'moto' ? 'Editar moto' : 'Editar auto' }}</h2>
                    <fieldset>
                        <legend class="mb-1 block text-sm font-medium">¿Qué es?</legend>
                        <div class="flex gap-2" role="radiogroup">
                            @foreach (\App\Models\Vehicle::TIPOS as $value => $label)
                                <label class="flex-1">
                                    <input type="radio" wire:model.live="editTipo" value="{{ $value }}" class="peer sr-only">
                                    <span class="grid min-h-11 cursor-pointer place-items-center rounded-sm border border-cuero/30 text-sm text-cuero/70 hover:text-cuero peer-checked:border-grafito peer-checked:bg-grafito/10 peer-checked:font-semibold peer-checked:text-grafito peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-grafito">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="editMarca" class="mb-1 block text-sm font-medium">Marca</label>
                            <input id="editMarca" type="text" wire:model="editMarca" autocomplete="off"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('editMarca') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="editModelo" class="mb-1 block text-sm font-medium">Modelo</label>
                            <input id="editModelo" type="text" wire:model="editModelo" autocomplete="off"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('editModelo') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="editPatente" class="mb-1 block text-sm font-medium">Patente <span class="font-normal text-cuero/60">(opcional)</span></label>
                            <input id="editPatente" type="text" wire:model="editPatente" autocomplete="off"
                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base uppercase focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                            @error('editPatente') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('editingVehicle', false)"
                            class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                    </div>
                </form>
            @else
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-brand text-2xl font-bold leading-tight">{{ $vehicle->nombre() }}</h2>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="inline-block rounded-sm border border-cuero/30 px-2 py-0.5 text-xs font-medium text-cuero/70">
                                {{ \App\Models\Vehicle::TIPOS[$vehicle->tipo] ?? 'Auto' }}
                            </span>
                            @if ($vehicle->patente)
                                <span class="inline-block rounded-sm bg-ocre px-2 py-0.5 text-xs font-semibold tracking-wide text-negro">
                                    {{ $vehicle->patente }}
                                </span>
                            @endif
                        </div>
                        @unless ($this->isOwner)
                            <p class="mt-1 text-xs text-cuero/60">Compartido por {{ $vehicle->user->name }}</p>
                        @endunless
                    </div>
                    @if ($this->isOwner)
                        <div class="flex shrink-0 items-center">
                            <button type="button" wire:click="startEditingVehicle" aria-label="Editar {{ $vehicle->sustantivo() }}"
                                class="grid size-11 place-items-center text-cuero/60 hover:text-cuero focus-visible:outline-2 focus-visible:outline-grafito">
                                {{-- Heroicon: pencil-square (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                            </button>
                            <button type="button" wire:click="deleteVehicle({{ $vehicle->id }})"
                                wire:confirm="Vas a eliminar {{ $vehicle->esMoto() ? 'esta moto' : 'este auto' }} y todo su historial de mantenimientos y cargas. Esto no se puede deshacer."
                                aria-label="Eliminar {{ $vehicle->sustantivo() }}"
                                class="grid size-11 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>
            @endif

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

        {{-- Compartir (solo el dueño) --}}
        @if ($this->isOwner)
            <div class="space-y-3 rounded-sm border border-cuero/20 p-4">
                <div>
                    <h2 class="font-brand text-lg font-bold">Compartir</h2>
                    <p class="text-sm text-cuero/60">Sumá a otra persona con su usuario y verá este vehículo con todos sus gastos y mantenimientos.</p>
                </div>

                @if ($this->members->isNotEmpty())
                    <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                        @foreach ($this->members as $member)
                            <li wire:key="member-{{ $member->id }}" class="flex items-center gap-2 py-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $member->name }}</p>
                                    <p class="text-xs text-cuero/50">{{ '@'.$member->username }}</p>
                                </div>
                                <button type="button" wire:click="transferOwnership({{ $member->id }})"
                                    wire:confirm="Vas a pasarle este vehículo a {{ $member->name }}: va a ser quien pueda editarlo, eliminarlo y compartirlo. Vos lo vas a seguir viendo como compartido."
                                    aria-label="Hacer que {{ $member->name }} sea quien tenga el vehículo a su nombre"
                                    class="min-h-11 shrink-0 rounded-sm px-3 text-sm text-cuero/70 hover:text-grafito focus-visible:outline-2 focus-visible:outline-grafito">
                                    Hacer dueño
                                </button>
                                <button type="button" wire:click="unshare({{ $member->id }})"
                                    wire:confirm="Vas a dejar de compartir el vehículo con {{ $member->name }}. Ya no va a poder verlo."
                                    aria-label="Dejar de compartir con {{ $member->name }}"
                                    class="min-h-11 shrink-0 rounded-sm px-3 text-sm text-cuero/70 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                    Quitar
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form wire:submit="share" class="flex flex-wrap items-end gap-2">
                    <div class="min-w-0 flex-1">
                        <label for="shareUsername" class="mb-1 block text-sm font-medium">Usuario</label>
                        <input id="shareUsername" type="text" wire:model="shareUsername" autocomplete="off" autocapitalize="none"
                            placeholder="usuario"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    </div>
                    <button type="submit" wire:loading.attr="disabled"
                        class="min-h-11 shrink-0 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                        Compartir
                    </button>
                    @error('shareUsername') <p class="w-full text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </form>
            </div>
        @endif

        {{-- Cada sección es un hijo Livewire: la interacción de una no arrastra el estado de las demás --}}
        <livewire:auto.mantenimientos :vehicle-id="$vehicle->id" :key="'mantenimientos-'.$vehicle->id" />

        <livewire:auto.documentos :vehicle-id="$vehicle->id" :key="'documentos-'.$vehicle->id" />

        <livewire:auto.combustible :vehicle-id="$vehicle->id" :key="'combustible-'.$vehicle->id" />

        <livewire:auto.gastos :vehicle-id="$vehicle->id" :key="'gastos-'.$vehicle->id" />
    @endif
</section>
