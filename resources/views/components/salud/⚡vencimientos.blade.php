<?php

use App\Models\HealthRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Vencimientos de la historia clínica: próximo control, receta que caduca,
 * estudio anual… Mismo patrón que la documentación de Auto. Cualquier
 * persona con acceso a la historia los opera.
 */
new class extends Component
{
    #[Locked]
    public int $recordId;

    /** Caché de requireRecord() dentro del mismo request. */
    private ?HealthRecord $requiredRecord = null;

    // Alta de un vencimiento.
    public bool $addingReminder = false;
    public string $reminderName = '';
    public string $reminderExpiresOn = '';
    public ?int $reminderIntervalMonths = null;
    public string $reminderNote = '';

    // Edición de uno ya guardado.
    public ?int $editingReminderId = null;
    public string $editReminderName = '';
    public string $editReminderExpiresOn = '';
    public ?int $editReminderIntervalMonths = null;
    public string $editReminderNote = '';

    // "Ya está": pasa el vencimiento a la fecha nueva.
    public ?int $renewingReminderId = null;
    public string $renewReminderExpiresOn = '';

    public function addReminder(): void
    {
        $record = $this->requireRecord();

        $data = $this->validate([
            'reminderName' => ['required', 'string', 'max:120'],
            'reminderExpiresOn' => ['required', 'date'],
            'reminderIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
            'reminderNote' => ['nullable', 'string', 'max:255'],
        ], [
            'reminderName.required' => '¿Qué querés que te recuerde?',
            'reminderExpiresOn.required' => '¿Para cuándo es?',
        ]);

        $reminder = $record->reminders()->make([
            'name' => trim($data['reminderName']),
            'expires_on' => $data['reminderExpiresOn'],
            'interval_months' => $data['reminderIntervalMonths'],
            'note' => trim($this->reminderNote) === '' ? null : trim($this->reminderNote),
        ]);
        $reminder->user_id = auth()->id();
        $reminder->save();

        $this->reset('reminderName', 'reminderExpiresOn', 'reminderIntervalMonths', 'reminderNote', 'addingReminder');
    }

    public function startEditingReminder(int $id): void
    {
        $reminder = $this->requireRecord()->reminders()->findOrFail($id);

        $this->editingReminderId = $reminder->id;
        $this->editReminderName = $reminder->name;
        $this->editReminderExpiresOn = $reminder->expires_on->format('Y-m-d');
        $this->editReminderIntervalMonths = $reminder->interval_months;
        $this->editReminderNote = (string) $reminder->note;
        $this->resetValidation();
    }

    public function saveReminder(): void
    {
        $reminder = $this->requireRecord()->reminders()->findOrFail($this->editingReminderId);

        $data = $this->validate([
            'editReminderName' => ['required', 'string', 'max:120'],
            'editReminderExpiresOn' => ['required', 'date'],
            'editReminderIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:600'],
            'editReminderNote' => ['nullable', 'string', 'max:255'],
        ], [
            'editReminderName.required' => '¿Qué querés que te recuerde?',
            'editReminderExpiresOn.required' => '¿Para cuándo es?',
        ]);

        $reminder->update([
            'name' => trim($data['editReminderName']),
            'expires_on' => $data['editReminderExpiresOn'],
            'interval_months' => $data['editReminderIntervalMonths'],
            'note' => trim($this->editReminderNote) === '' ? null : trim($this->editReminderNote),
        ]);

        $this->cancelEditReminder();
    }

    public function cancelEditReminder(): void
    {
        $this->reset('editingReminderId', 'editReminderName', 'editReminderExpiresOn', 'editReminderIntervalMonths', 'editReminderNote');
        $this->resetValidation();
    }

    /**
     * "Ya está" (fue al control, renovó la receta): el vencimiento pasa a la
     * fecha nueva. Si tiene periodicidad, se sugiere la próxima.
     */
    public function startRenewingReminder(int $id): void
    {
        $reminder = $this->requireRecord()->reminders()->findOrFail($id);

        $this->renewingReminderId = $reminder->id;
        $this->renewReminderExpiresOn = $reminder->suggestedNextExpiry()?->format('Y-m-d') ?? '';
        $this->resetValidation();
    }

    public function saveRenewal(): void
    {
        $reminder = $this->requireRecord()->reminders()->findOrFail($this->renewingReminderId);

        $this->validate([
            'renewReminderExpiresOn' => ['required', 'date'],
        ], [
            'renewReminderExpiresOn.required' => '¿Para cuándo queda el próximo?',
        ]);

        $reminder->update(['expires_on' => $this->renewReminderExpiresOn]);

        $this->reset('renewingReminderId', 'renewReminderExpiresOn');
    }

    public function cancelRenewal(): void
    {
        $this->reset('renewingReminderId', 'renewReminderExpiresOn');
        $this->resetValidation();
    }

    public function deleteReminder(int $id): void
    {
        $this->requireRecord()->reminders()->findOrFail($id)->delete();

        if ($this->editingReminderId === $id) {
            $this->cancelEditReminder();
        }

        if ($this->renewingReminderId === $id) {
            $this->cancelRenewal();
        }
    }

    /** Memoizado por request: se llama varias veces en el mismo render. */
    private function requireRecord(): HealthRecord
    {
        return $this->requiredRecord ??= auth()->user()->accessibleHealthRecords()->findOrFail($this->recordId);
    }

    /**
     * Vencimientos ordenados por urgencia (lo vencido y lo que se viene
     * primero), con su estado calculado contra la fecha de hoy.
     */
    #[Computed]
    public function reminders(): Collection
    {
        return $this->requireRecord()->reminders()
            ->get()
            ->map(fn ($reminder) => [
                'reminder' => $reminder,
                'status' => $reminder->status(),
            ])
            ->sortBy(fn ($row) => [$row['status']['rank'], $row['status']['urgency']])
            ->values();
    }
};
?>

<div class="space-y-3">
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <h2 class="font-brand text-lg font-bold">Vencimientos</h2>
            <p class="text-sm text-cuero/60">El próximo control, la receta que caduca, el estudio anual. Te aviso cuando se acerca.</p>
        </div>
        @unless ($this->addingReminder)
            <button type="button" wire:click="$set('addingReminder', true)"
                class="min-h-11 shrink-0 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/80 hover:text-cuero focus-visible:outline-2 focus-visible:outline-ciruela">
                Anotar
            </button>
        @endunless
    </div>

    @if ($this->addingReminder)
        <form wire:submit="addReminder" class="space-y-3 rounded-sm border border-cuero/20 p-3">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="reminderName" class="mb-1 block text-sm font-medium">¿Qué vence?</label>
                    <input id="reminderName" type="text" wire:model="reminderName" autocomplete="off" list="vencimientos-comunes"
                        placeholder="Control clínico, receta…"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    <datalist id="vencimientos-comunes">
                        <option value="Control clínico"></option>
                        <option value="Receta de la medicación"></option>
                        <option value="Estudio anual"></option>
                        <option value="Apto físico"></option>
                    </datalist>
                    @error('reminderName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-ui.date-field model="reminderExpiresOn" label="Vence" accent="ciruela" preset="vencimiento" />
                </div>
                <div>
                    <label for="reminderIntervalMonths" class="mb-1 block text-sm font-medium">Se repite cada <span class="font-normal text-cuero/60">(meses, opcional)</span></label>
                    <input id="reminderIntervalMonths" type="number" inputmode="numeric" min="1" wire:model="reminderIntervalMonths"
                        placeholder="12"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                    @error('reminderIntervalMonths') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="reminderNote" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                <input id="reminderNote" type="text" wire:model="reminderNote" autocomplete="off"
                    placeholder="Con qué médico, qué estudio…"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                @error('reminderNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60">
                    Guardar
                </button>
                <button type="button" wire:click="$set('addingReminder', false)"
                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
            </div>
        </form>
    @endif

    @if ($this->reminders->isEmpty())
        @unless ($this->addingReminder)
            <p class="rounded-sm border border-cuero/20 px-4 py-6 text-center text-cuero/70">
                Todavía no seguís ningún vencimiento. Anotá el próximo control o la receta que caduca y no se te pasa.
            </p>
        @endunless
    @else
        <ul class="space-y-2">
            @foreach ($this->reminders as $row)
                @php($reminder = $row['reminder'])
                @php($status = $row['status'])
                <li wire:key="reminder-{{ $reminder->id }}" class="rounded-sm border border-cuero/20 p-3">
                    @if ($this->editingReminderId === $reminder->id)
                        <form wire:submit="saveReminder" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label for="editReminderName-{{ $reminder->id }}" class="mb-1 block text-sm font-medium">¿Qué vence?</label>
                                    <input id="editReminderName-{{ $reminder->id }}" type="text" wire:model="editReminderName" autocomplete="off"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editReminderName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-ui.date-field model="editReminderExpiresOn" id="editReminderExpiresOn-{{ $reminder->id }}" label="Vence" accent="ciruela" preset="vencimiento" />
                                </div>
                                <div>
                                    <label for="editReminderIntervalMonths-{{ $reminder->id }}" class="mb-1 block text-sm font-medium">Se repite cada <span class="font-normal text-cuero/60">(meses, opcional)</span></label>
                                    <input id="editReminderIntervalMonths-{{ $reminder->id }}" type="number" inputmode="numeric" min="1" wire:model="editReminderIntervalMonths"
                                        placeholder="12"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                    @error('editReminderIntervalMonths') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div>
                                <label for="editReminderNote-{{ $reminder->id }}" class="mb-1 block text-sm font-medium">Nota <span class="font-normal text-cuero/60">(opcional)</span></label>
                                <input id="editReminderNote-{{ $reminder->id }}" type="text" wire:model="editReminderNote" autocomplete="off"
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                @error('editReminderNote') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                    class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                    Guardar
                                </button>
                                <button type="button" wire:click="cancelEditReminder"
                                    class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ $reminder->name }}</span>
                                    <span @class([
                                        'rounded-sm px-2 py-0.5 text-xs font-semibold',
                                        'bg-teja text-crema' => $status['level'] === 'overdue',
                                        'bg-ocre text-negro' => $status['level'] === 'soon',
                                        'bg-yerba text-crema' => $status['level'] === 'ok',
                                    ])>
                                        {{ $status['headline'] }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-cuero/70">{{ $status['detail'] }}</p>
                                @if ($reminder->note)
                                    <p class="mt-1 text-xs text-cuero/60">{{ $reminder->note }}</p>
                                @endif
                                @if ($reminder->interval_months)
                                    <p class="mt-1 text-xs text-cuero/50">Se repite cada {{ $reminder->interval_months }} {{ $reminder->interval_months === 1 ? 'mes' : 'meses' }}.</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center">
                                <button type="button" wire:click="startEditingReminder({{ $reminder->id }})"
                                    aria-label="Editar {{ $reminder->name }}"
                                    class="grid size-9 place-items-center text-cuero/50 hover:text-ciruela focus-visible:outline-2 focus-visible:outline-ciruela">
                                    {{-- Heroicon: pencil (mini) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                        <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                    </svg>
                                </button>
                                <button type="button" wire:click="deleteReminder({{ $reminder->id }})"
                                    wire:confirm="Vas a eliminar «{{ $reminder->name }}». Esto no se puede deshacer."
                                    aria-label="Eliminar {{ $reminder->name }}"
                                    class="grid size-9 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                    {{-- Heroicon: trash (mini) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        @if ($this->renewingReminderId === $reminder->id)
                            <form wire:submit="saveRenewal" class="mt-3 space-y-3 border-t border-cuero/15 pt-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-ui.date-field model="renewReminderExpiresOn" id="renewReminderExpiresOn-{{ $reminder->id }}" label="Próximo vencimiento" accent="ciruela" preset="vencimiento" />
                                        @error('renewReminderExpiresOn') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                @if ($reminder->interval_months)
                                    <p class="text-xs text-cuero/60">Te sugerí la fecha según su periodicidad de {{ $reminder->interval_months }} {{ $reminder->interval_months === 1 ? 'mes' : 'meses' }}. Cambiala si no coincide.</p>
                                @endif
                                <div class="flex gap-2">
                                    <button type="submit"
                                        class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                        Guardar
                                    </button>
                                    <button type="button" wire:click="cancelRenewal"
                                        class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                                </div>
                            </form>
                        @else
                            <button type="button" wire:click="startRenewingReminder({{ $reminder->id }})"
                                class="mt-3 min-h-11 w-full rounded-sm border border-ciruela/40 px-3 text-sm font-medium text-ciruela hover:bg-ciruela/5 focus-visible:outline-2 focus-visible:outline-ciruela sm:w-auto">
                                Ya está
                            </button>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
