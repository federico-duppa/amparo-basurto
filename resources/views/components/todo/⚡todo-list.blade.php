<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tareas')] class extends Component
{
    public string $title = '';

    // Edición del título de una tarea existente.
    public ?int $editingId = null;

    public string $editTitle = '';

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'editTitle' => ['required', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Contame qué tenés que hacer y la anoto.',
            'title.max' => 'Eso es muy largo para una tarea — probá resumirlo.',
            'editTitle.required' => 'La tarea no puede quedar sin nombre.',
            'editTitle.max' => 'Eso es muy largo para una tarea — probá resumirlo.',
        ];
    }

    public function add(): void
    {
        $this->validateOnly('title');

        auth()->user()->todos()->create(['title' => trim($this->title)]);

        $this->reset('title');
    }

    public function toggle(int $id): void
    {
        $todo = auth()->user()->todos()->findOrFail($id);

        $todo->update(['completed_at' => $todo->isCompleted() ? null : now()]);
    }

    public function startEditing(int $id): void
    {
        $todo = auth()->user()->todos()->findOrFail($id);

        $this->editingId = $todo->id;
        $this->editTitle = $todo->title;
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $todo = auth()->user()->todos()->findOrFail($this->editingId);

        $this->validateOnly('editTitle');

        $todo->update(['title' => trim($this->editTitle)]);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editTitle');
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        auth()->user()->todos()->findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }

    public function clearCompleted(): void
    {
        auth()->user()->todos()->whereNotNull('completed_at')->delete();

        if ($this->editingId !== null && ! auth()->user()->todos()->whereKey($this->editingId)->exists()) {
            $this->cancelEdit();
        }
    }

    #[Computed]
    public function todos(): Collection
    {
        return auth()->user()->todos()
            ->orderByRaw('(completed_at is not null) asc')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function pending(): int
    {
        return $this->todos->whereNull('completed_at')->count();
    }

    #[Computed]
    public function completed(): int
    {
        return $this->todos->whereNotNull('completed_at')->count();
    }
};
?>

<section class="space-y-6">
    <header class="flex items-center gap-3">
        <span class="h-8 w-1.5 rounded-sm bg-vino" aria-hidden="true"></span>
        <h1 class="font-brand text-3xl font-bold">Tareas</h1>
        @if ($this->pending > 0)
            <span class="ml-auto rounded-sm bg-ocre px-2.5 py-1 text-xs font-semibold text-negro">
                {{ $this->pending === 1 ? '1 pendiente' : $this->pending.' pendientes' }}
            </span>
        @endif
    </header>

    <form wire:submit="add" class="space-y-2">
        <div class="flex gap-2">
            <label for="title" class="sr-only">Nueva tarea</label>
            <input
                id="title"
                type="text"
                wire:model="title"
                placeholder="¿Qué tenés pendiente?"
                autocomplete="off"
                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
            >
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="min-h-11 shrink-0 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
            >
                Agregar
            </button>
        </div>
        @error('title')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror
    </form>

    @if ($this->todos->isEmpty())
        <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
            Todavía no anotaste nada. Cuando quieras, empezamos.
        </p>
    @else
        @if ($this->pending === 0)
            <p class="text-sm text-yerba" role="status">No te queda nada pendiente. Buen trabajo.</p>
        @endif

        <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
            @foreach ($this->todos as $todo)
                <li wire:key="{{ $todo->id }}" class="flex items-center gap-2 py-1">
                    <button
                        type="button"
                        wire:click="toggle({{ $todo->id }})"
                        aria-pressed="{{ $todo->isCompleted() ? 'true' : 'false' }}"
                        aria-label="{{ $todo->isCompleted() ? 'Marcar como pendiente' : 'Marcar como completada' }}: {{ $todo->title }}"
                        class="grid size-11 shrink-0 place-items-center focus-visible:outline-2 focus-visible:outline-vino"
                    >
                        <span class="grid size-5 place-items-center rounded-sm border-2 {{ $todo->isCompleted() ? 'border-vino bg-vino' : 'border-cuero/50' }}">
                            @if ($todo->isCompleted())
                                {{-- Heroicon: check (mini) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 text-crema">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                            @endif
                        </span>
                    </button>

                    @if ($this->editingId === $todo->id)
                        <form wire:submit="saveEdit" class="flex min-w-0 flex-1 items-center gap-2 py-1">
                            <label for="editTitle" class="sr-only">Editar tarea</label>
                            <input
                                id="editTitle"
                                type="text"
                                wire:model="editTitle"
                                wire:keydown.escape="cancelEdit"
                                autocomplete="off"
                                autofocus
                                class="min-h-11 w-full min-w-0 rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                            >
                            <button
                                type="submit"
                                aria-label="Guardar cambios"
                                class="grid size-11 shrink-0 place-items-center text-monte hover:text-monte/80 focus-visible:outline-2 focus-visible:outline-monte"
                            >
                                {{-- Heroicon: check (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                wire:click="cancelEdit"
                                aria-label="Cancelar edición"
                                class="grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-cuero focus-visible:outline-2 focus-visible:outline-cuero"
                            >
                                {{-- Heroicon: x-mark (outline) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </form>
                    @else
                        <span class="min-w-0 flex-1 break-words py-2.5 {{ $todo->isCompleted() ? 'text-cuero/50 line-through' : '' }}">
                            {{ $todo->title }}
                        </span>

                        <button
                            type="button"
                            wire:click="startEditing({{ $todo->id }})"
                            aria-label="Editar: {{ $todo->title }}"
                            class="grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-vino focus-visible:outline-2 focus-visible:outline-vino"
                        >
                            {{-- Heroicon: pencil-square (outline) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            wire:click="delete({{ $todo->id }})"
                            wire:confirm="Vas a eliminar esta tarea. Esto no se puede deshacer."
                            aria-label="Eliminar: {{ $todo->title }}"
                            class="grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja"
                        >
                            {{-- Heroicon: trash (outline) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($this->completed > 0)
            <button
                type="button"
                wire:click="clearCompleted"
                wire:confirm="Vas a eliminar {{ $this->completed === 1 ? 'la tarea completada' : 'las '.$this->completed.' tareas completadas' }}. Esto no se puede deshacer."
                class="min-h-11 w-full rounded-sm border border-cuero/30 px-4 text-sm font-medium text-cuero/70 hover:border-teja hover:text-teja focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teja"
            >
                {{ $this->completed === 1 ? 'Limpiar la tarea completada' : 'Limpiar las '.$this->completed.' completadas' }}
            </button>
        @endif
    @endif
</section>
