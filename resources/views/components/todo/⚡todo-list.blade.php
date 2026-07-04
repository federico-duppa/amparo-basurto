<?php

use App\Models\Todo;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tareas')] class extends Component
{
    public string $title = '';

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Contame qué tenés que hacer y la anoto.',
            'title.max' => 'Eso es muy largo para una tarea — probá resumirlo.',
        ];
    }

    public function add(): void
    {
        $this->validate();

        Todo::create(['title' => trim($this->title)]);

        $this->reset('title');
    }

    public function toggle(Todo $todo): void
    {
        $todo->update(['completed_at' => $todo->isCompleted() ? null : now()]);
    }

    public function delete(Todo $todo): void
    {
        $todo->delete();
    }

    #[Computed]
    public function todos(): Collection
    {
        return Todo::query()
            ->orderByRaw('(completed_at is not null) asc')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function pending(): int
    {
        return $this->todos->whereNull('completed_at')->count();
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

                    <span class="min-w-0 flex-1 break-words py-2.5 {{ $todo->isCompleted() ? 'text-cuero/50 line-through' : '' }}">
                        {{ $todo->title }}
                    </span>

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
                </li>
            @endforeach
        </ul>
    @endif
</section>
