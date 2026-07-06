<?php

use App\Models\HealthRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Contactos médicos de la historia clínica: médico de cabecera,
 * especialistas y sus teléfonos. Cualquier persona con acceso a la
 * historia los opera.
 */
new class extends Component
{
    #[Locked]
    public int $recordId;

    /** Caché de requireRecord() dentro del mismo request. */
    private ?HealthRecord $requiredRecord = null;

    // Alta de un contacto.
    public bool $addingContact = false;
    public string $contactName = '';
    public string $contactSpecialty = '';
    public string $contactPhone = '';
    public string $contactNote = '';

    // Edición de uno ya guardado.
    public ?int $editingContactId = null;
    public string $editContactName = '';
    public string $editContactSpecialty = '';
    public string $editContactPhone = '';
    public string $editContactNote = '';

    public function addContact(): void
    {
        $record = $this->requireRecord();

        $data = $this->validate([
            'contactName' => ['required', 'string', 'max:120'],
            'contactSpecialty' => ['nullable', 'string', 'max:120'],
            'contactPhone' => ['nullable', 'string', 'max:40'],
            'contactNote' => ['nullable', 'string', 'max:255'],
        ], [
            'contactName.required' => '¿Cómo se llama?',
        ]);

        $contact = $record->contacts()->make([
            'name' => trim($data['contactName']),
            'specialty' => trim($this->contactSpecialty) === '' ? null : trim($this->contactSpecialty),
            'phone' => trim($this->contactPhone) === '' ? null : trim($this->contactPhone),
            'note' => trim($this->contactNote) === '' ? null : trim($this->contactNote),
        ]);
        $contact->user_id = auth()->id();
        $contact->save();

        $this->reset('contactName', 'contactSpecialty', 'contactPhone', 'contactNote', 'addingContact');
    }

    public function startEditingContact(int $id): void
    {
        $contact = $this->requireRecord()->contacts()->findOrFail($id);

        $this->editingContactId = $contact->id;
        $this->editContactName = $contact->name;
        $this->editContactSpecialty = (string) $contact->specialty;
        $this->editContactPhone = (string) $contact->phone;
        $this->editContactNote = (string) $contact->note;
        $this->resetValidation();
    }

    public function saveContact(): void
    {
        $contact = $this->requireRecord()->contacts()->findOrFail($this->editingContactId);

        $data = $this->validate([
            'editContactName' => ['required', 'string', 'max:120'],
            'editContactSpecialty' => ['nullable', 'string', 'max:120'],
            'editContactPhone' => ['nullable', 'string', 'max:40'],
            'editContactNote' => ['nullable', 'string', 'max:255'],
        ], [
            'editContactName.required' => '¿Cómo se llama?',
        ]);

        $contact->update([
            'name' => trim($data['editContactName']),
            'specialty' => trim($this->editContactSpecialty) === '' ? null : trim($this->editContactSpecialty),
            'phone' => trim($this->editContactPhone) === '' ? null : trim($this->editContactPhone),
            'note' => trim($this->editContactNote) === '' ? null : trim($this->editContactNote),
        ]);

        $this->cancelEditContact();
    }

    public function cancelEditContact(): void
    {
        $this->reset('editingContactId', 'editContactName', 'editContactSpecialty', 'editContactPhone', 'editContactNote');
        $this->resetValidation();
    }

    public function deleteContact(int $id): void
    {
        $this->requireRecord()->contacts()->findOrFail($id)->delete();

        if ($this->editingContactId === $id) {
            $this->cancelEditContact();
        }
    }

    /** Memoizado por request: se llama varias veces en el mismo render. */
    private function requireRecord(): HealthRecord
    {
        return $this->requiredRecord ??= auth()->user()->accessibleHealthRecords()->findOrFail($this->recordId);
    }

    #[Computed]
    public function contacts(): Collection
    {
        return $this->requireRecord()->contacts()->orderBy('name')->get();
    }
};
?>

<div class="space-y-3">
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <h2 class="font-brand text-lg font-bold">Contactos</h2>
            <p class="text-sm text-cuero/60">El médico de cabecera, los especialistas y los teléfonos que conviene tener a mano.</p>
        </div>
        @unless ($this->addingContact)
            <button type="button" wire:click="$set('addingContact', true)"
                class="min-h-11 shrink-0 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-ciruela">
                Anotar
            </button>
        @endunless
    </div>

    @if ($this->addingContact)
        <form wire:submit="addContact" class="space-y-3 rounded-sm border border-cuero/20 p-3">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="contactName" class="mb-1 block text-sm font-medium">Nombre</label>
                    <input id="contactName" type="text" wire:model="contactName" autocomplete="off"
                        placeholder="Dra. García"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('contactName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contactSpecialty" class="mb-1 block text-sm font-medium">Especialidad <span class="font-normal text-cuero/60">(opcional)</span></label>
                    <input id="contactSpecialty" type="text" wire:model="contactSpecialty" autocomplete="off" list="especialidades-comunes"
                        placeholder="Clínica médica, cardiología…"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    <datalist id="especialidades-comunes">
                        <option value="Médico de cabecera"></option>
                        <option value="Clínica médica"></option>
                        <option value="Cardiología"></option>
                        <option value="Pediatría"></option>
                        <option value="Odontología"></option>
                    </datalist>
                    @error('contactSpecialty') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="contactPhone" class="mb-1 block text-sm font-medium">Teléfono <span class="font-normal text-cuero/60">(opcional)</span></label>
                    <input id="contactPhone" type="tel" wire:model="contactPhone" autocomplete="off"
                        placeholder="11 5555-5555"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('contactPhone') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contactNote" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                    <input id="contactNote" type="text" wire:model="contactNote" autocomplete="off"
                        placeholder="Consultorio, horarios…"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('contactNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                    Guardar
                </button>
                <button type="button" wire:click="$set('addingContact', false)"
                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
            </div>
        </form>
    @endif

    @if ($this->contacts->isEmpty())
        @unless ($this->addingContact)
            <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
                Todavía no anotaste ningún contacto. Sumá al médico de cabecera y lo tenés siempre a mano.
            </p>
        @endunless
    @else
        <ul class="space-y-2">
            @foreach ($this->contacts as $contact)
                <li wire:key="contact-{{ $contact->id }}" class="rounded-sm border border-cuero/20 p-3">
                    @if ($this->editingContactId === $contact->id)
                        <form wire:submit="saveContact" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="editContactName-{{ $contact->id }}" class="mb-1 block text-sm font-medium">Nombre</label>
                                    <input id="editContactName-{{ $contact->id }}" type="text" wire:model="editContactName" autocomplete="off"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editContactName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="editContactSpecialty-{{ $contact->id }}" class="mb-1 block text-sm font-medium">Especialidad <span class="font-normal text-cuero/60">(opcional)</span></label>
                                    <input id="editContactSpecialty-{{ $contact->id }}" type="text" wire:model="editContactSpecialty" autocomplete="off"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editContactSpecialty') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="editContactPhone-{{ $contact->id }}" class="mb-1 block text-sm font-medium">Teléfono <span class="font-normal text-cuero/60">(opcional)</span></label>
                                    <input id="editContactPhone-{{ $contact->id }}" type="tel" wire:model="editContactPhone" autocomplete="off"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editContactPhone') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="editContactNote-{{ $contact->id }}" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                                    <input id="editContactNote-{{ $contact->id }}" type="text" wire:model="editContactNote" autocomplete="off"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editContactNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                    Guardar
                                </button>
                                <button type="button" wire:click="cancelEditContact"
                                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $contact->name }}</p>
                                @if ($contact->specialty)
                                    <p class="text-sm text-cuero/70">{{ $contact->specialty }}</p>
                                @endif
                                @if ($contact->phone)
                                    <p class="mt-1 text-sm">
                                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $contact->phone) }}"
                                            class="font-medium text-monte underline underline-offset-2 hover:text-monte/80 focus-visible:outline-2 focus-visible:outline-monte">
                                            {{ $contact->phone }}
                                        </a>
                                    </p>
                                @endif
                                @if ($contact->note)
                                    <p class="mt-1 text-xs text-cuero/60">{{ $contact->note }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center">
                                <button type="button" wire:click="startEditingContact({{ $contact->id }})"
                                    aria-label="Editar {{ $contact->name }}"
                                    class="grid size-9 place-items-center text-cuero/50 hover:text-ciruela focus-visible:outline-2 focus-visible:outline-ciruela">
                                    {{-- Heroicon: pencil (mini) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                        <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                    </svg>
                                </button>
                                <button type="button" wire:click="deleteContact({{ $contact->id }})"
                                    wire:confirm="Vas a eliminar el contacto de {{ $contact->name }}. Esto no se puede deshacer."
                                    aria-label="Eliminar {{ $contact->name }}"
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
    @endif
</div>
