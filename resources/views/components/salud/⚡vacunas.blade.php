<?php

use App\Models\HealthRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El carnet de vacunas de la historia clínica: cada aplicación con su
 * vacuna, dosis y fecha, agrupadas por vacuna, y la próxima dosis si se
 * conoce. Cualquier persona con acceso a la historia lo opera.
 */
new class extends Component
{
    #[Locked]
    public int $recordId;

    // Alta de una aplicación.
    public bool $addingVaccine = false;
    public string $vaccineName = '';
    public string $vaccineDose = '';
    public string $vaccineAppliedOn = '';
    public string $vaccineNextDueOn = '';
    public string $vaccineNote = '';

    // Edición de una ya guardada.
    public ?int $editingVaccineId = null;
    public string $editVaccineName = '';
    public string $editVaccineDose = '';
    public string $editVaccineAppliedOn = '';
    public string $editVaccineNextDueOn = '';
    public string $editVaccineNote = '';

    public function mount(): void
    {
        $this->vaccineAppliedOn = now()->format('Y-m-d');
    }

    public function addVaccine(): void
    {
        $record = $this->requireRecord();

        $data = $this->validate([
            'vaccineName' => ['required', 'string', 'max:120'],
            'vaccineDose' => ['nullable', 'string', 'max:60'],
            'vaccineAppliedOn' => ['required', 'date'],
            'vaccineNextDueOn' => ['nullable', 'date', 'after:vaccineAppliedOn'],
            'vaccineNote' => ['nullable', 'string', 'max:255'],
        ], [
            'vaccineName.required' => '¿Qué vacuna es?',
            'vaccineAppliedOn.required' => '¿Cuándo se la dieron?',
            'vaccineNextDueOn.after' => 'La próxima dosis tiene que ser después de esta.',
        ]);

        $vaccine = $record->vaccines()->make([
            'name' => trim($data['vaccineName']),
            'dose' => trim($this->vaccineDose) === '' ? null : trim($this->vaccineDose),
            'applied_on' => $data['vaccineAppliedOn'],
            'next_due_on' => $data['vaccineNextDueOn'] ?: null,
            'note' => trim($this->vaccineNote) === '' ? null : trim($this->vaccineNote),
        ]);
        $vaccine->user_id = auth()->id();
        $vaccine->save();

        $this->reset('vaccineName', 'vaccineDose', 'vaccineNextDueOn', 'vaccineNote', 'addingVaccine');
        $this->vaccineAppliedOn = now()->format('Y-m-d');
    }

    public function startEditingVaccine(int $id): void
    {
        $vaccine = $this->requireRecord()->vaccines()->findOrFail($id);

        $this->editingVaccineId = $vaccine->id;
        $this->editVaccineName = $vaccine->name;
        $this->editVaccineDose = (string) $vaccine->dose;
        $this->editVaccineAppliedOn = $vaccine->applied_on->format('Y-m-d');
        $this->editVaccineNextDueOn = $vaccine->next_due_on?->format('Y-m-d') ?? '';
        $this->editVaccineNote = (string) $vaccine->note;
        $this->resetValidation();
    }

    public function saveVaccine(): void
    {
        $vaccine = $this->requireRecord()->vaccines()->findOrFail($this->editingVaccineId);

        $data = $this->validate([
            'editVaccineName' => ['required', 'string', 'max:120'],
            'editVaccineDose' => ['nullable', 'string', 'max:60'],
            'editVaccineAppliedOn' => ['required', 'date'],
            'editVaccineNextDueOn' => ['nullable', 'date', 'after:editVaccineAppliedOn'],
            'editVaccineNote' => ['nullable', 'string', 'max:255'],
        ], [
            'editVaccineName.required' => '¿Qué vacuna es?',
            'editVaccineAppliedOn.required' => '¿Cuándo se la dieron?',
            'editVaccineNextDueOn.after' => 'La próxima dosis tiene que ser después de esta.',
        ]);

        $vaccine->update([
            'name' => trim($data['editVaccineName']),
            'dose' => trim($this->editVaccineDose) === '' ? null : trim($this->editVaccineDose),
            'applied_on' => $data['editVaccineAppliedOn'],
            'next_due_on' => $data['editVaccineNextDueOn'] ?: null,
            'note' => trim($this->editVaccineNote) === '' ? null : trim($this->editVaccineNote),
        ]);

        $this->cancelEditVaccine();
    }

    public function cancelEditVaccine(): void
    {
        $this->reset('editingVaccineId', 'editVaccineName', 'editVaccineDose', 'editVaccineAppliedOn', 'editVaccineNextDueOn', 'editVaccineNote');
        $this->resetValidation();
    }

    public function deleteVaccine(int $id): void
    {
        $this->requireRecord()->vaccines()->findOrFail($id)->delete();

        if ($this->editingVaccineId === $id) {
            $this->cancelEditVaccine();
        }
    }

    private function requireRecord(): HealthRecord
    {
        return auth()->user()->accessibleHealthRecords()->findOrFail($this->recordId);
    }

    /**
     * El carnet: aplicaciones agrupadas por vacuna (en orden alfabético),
     * cada grupo de la primera dosis a la última.
     */
    #[Computed]
    public function carnet(): Collection
    {
        return $this->requireRecord()->vaccines()
            ->orderBy('applied_on')
            ->orderBy('id')
            ->get()
            ->groupBy('name')
            ->sortKeys();
    }
};
?>

<div class="space-y-3">
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <h2 class="font-brand text-lg font-bold">Vacunas</h2>
            <p class="text-sm text-cuero/60">El carnet: cada dosis con su fecha, y la próxima si ya la sabés.</p>
        </div>
        @unless ($this->addingVaccine)
            <button type="button" wire:click="$set('addingVaccine', true)"
                class="min-h-11 shrink-0 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-ciruela">
                Anotar
            </button>
        @endunless
    </div>

    @if ($this->addingVaccine)
        <form wire:submit="addVaccine" class="space-y-3 rounded-sm border border-cuero/20 p-3">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="vaccineName" class="mb-1 block text-sm font-medium">Vacuna</label>
                    <input id="vaccineName" type="text" wire:model="vaccineName" autocomplete="off" list="vacunas-comunes"
                        placeholder="Antigripal, antitetánica…"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    <datalist id="vacunas-comunes">
                        <option value="Antigripal"></option>
                        <option value="Antitetánica"></option>
                        <option value="Hepatitis A"></option>
                        <option value="Hepatitis B"></option>
                        <option value="Fiebre amarilla"></option>
                        <option value="Neumococo"></option>
                    </datalist>
                    @error('vaccineName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="vaccineDose" class="mb-1 block text-sm font-medium">Dosis <span class="font-normal text-cuero/60">(opcional)</span></label>
                    <input id="vaccineDose" type="text" wire:model="vaccineDose" autocomplete="off" list="dosis-comunes"
                        placeholder="1ª dosis, refuerzo…"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    <datalist id="dosis-comunes">
                        <option value="1ª dosis"></option>
                        <option value="2ª dosis"></option>
                        <option value="3ª dosis"></option>
                        <option value="Refuerzo"></option>
                        <option value="Única"></option>
                    </datalist>
                    @error('vaccineDose') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <x-ui.date-field model="vaccineAppliedOn" label="Se la dieron" accent="ciruela" preset="pasado" />
                </div>
                <div>
                    <x-ui.date-field model="vaccineNextDueOn" label="Próxima dosis" :optional="true" accent="ciruela" preset="vencimiento" />
                    @error('vaccineNextDueOn') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="vaccineNote" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                <input id="vaccineNote" type="text" wire:model="vaccineNote" autocomplete="off"
                    placeholder="Marca, lote, dónde se la dieron…"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                @error('vaccineNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                    Guardar
                </button>
                <button type="button" wire:click="$set('addingVaccine', false)"
                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
            </div>
        </form>
    @endif

    @if ($this->carnet->isEmpty())
        @unless ($this->addingVaccine)
            <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
                Todavía no cargaste ninguna vacuna. Anotá la última que se dio y armamos el carnet.
            </p>
        @endunless
    @else
        <div class="space-y-3">
            @foreach ($this->carnet as $name => $applications)
                <div wire:key="vaccine-group-{{ md5($name) }}" class="rounded-sm border border-cuero/20 p-3">
                    <h3 class="font-medium">{{ $name }}</h3>
                    <ul class="mt-2 divide-y divide-cuero/15">
                        @foreach ($applications as $vaccine)
                            <li wire:key="vaccine-{{ $vaccine->id }}" class="py-2">
                                @if ($this->editingVaccineId === $vaccine->id)
                                    <form wire:submit="saveVaccine" class="space-y-3">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label for="editVaccineName-{{ $vaccine->id }}" class="mb-1 block text-sm font-medium">Vacuna</label>
                                                <input id="editVaccineName-{{ $vaccine->id }}" type="text" wire:model="editVaccineName" autocomplete="off"
                                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                @error('editVaccineName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label for="editVaccineDose-{{ $vaccine->id }}" class="mb-1 block text-sm font-medium">Dosis <span class="font-normal text-cuero/60">(opcional)</span></label>
                                                <input id="editVaccineDose-{{ $vaccine->id }}" type="text" wire:model="editVaccineDose" autocomplete="off"
                                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                                @error('editVaccineDose') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                            </div>
                                        </div>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <x-ui.date-field model="editVaccineAppliedOn" id="editVaccineAppliedOn-{{ $vaccine->id }}" label="Se la dieron" accent="ciruela" preset="pasado" />
                                            </div>
                                            <div>
                                                <x-ui.date-field model="editVaccineNextDueOn" id="editVaccineNextDueOn-{{ $vaccine->id }}" label="Próxima dosis" :optional="true" accent="ciruela" preset="vencimiento" />
                                                @error('editVaccineNextDueOn') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                            </div>
                                        </div>
                                        <div>
                                            <label for="editVaccineNote-{{ $vaccine->id }}" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                                            <input id="editVaccineNote-{{ $vaccine->id }}" type="text" wire:model="editVaccineNote" autocomplete="off"
                                                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                            @error('editVaccineNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit"
                                                class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                                Guardar
                                            </button>
                                            <button type="button" wire:click="cancelEditVaccine"
                                                class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex items-start gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                                <span class="font-medium">{{ $vaccine->dose ?? 'Dosis' }}</span>
                                                <span class="text-cuero/70">{{ $vaccine->applied_on->format('d/m/Y') }}</span>
                                            </p>
                                            @php($nextStatus = $vaccine->nextDoseStatus())
                                            @if ($nextStatus)
                                                <p class="mt-1">
                                                    <span @class([
                                                        'inline-block rounded-sm px-2 py-0.5 text-xs font-semibold',
                                                        'bg-teja text-crema' => $nextStatus['level'] === 'overdue',
                                                        'bg-ocre text-negro' => $nextStatus['level'] === 'soon',
                                                        'border border-cuero/30 text-cuero/70' => $nextStatus['level'] === 'ok',
                                                    ])>
                                                        @if ($nextStatus['level'] === 'overdue')
                                                            Dosis pendiente desde el {{ $vaccine->next_due_on->format('d/m/Y') }}
                                                        @else
                                                            Próxima dosis el {{ $vaccine->next_due_on->format('d/m/Y') }}
                                                        @endif
                                                    </span>
                                                </p>
                                            @endif
                                            @if ($vaccine->note)
                                                <p class="mt-1 text-xs text-cuero/60">{{ $vaccine->note }}</p>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 items-center">
                                            <button type="button" wire:click="startEditingVaccine({{ $vaccine->id }})"
                                                aria-label="Editar {{ $name }} del {{ $vaccine->applied_on->format('d/m/Y') }}"
                                                class="grid size-9 place-items-center text-cuero/50 hover:text-ciruela focus-visible:outline-2 focus-visible:outline-ciruela">
                                                {{-- Heroicon: pencil (mini) --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                                    <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                                </svg>
                                            </button>
                                            <button type="button" wire:click="deleteVaccine({{ $vaccine->id }})"
                                                wire:confirm="Vas a eliminar esta aplicación de «{{ $name }}». Esto no se puede deshacer."
                                                aria-label="Eliminar {{ $name }} del {{ $vaccine->applied_on->format('d/m/Y') }}"
                                                class="grid size-9 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                                {{-- Heroicon: trash (mini) --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
