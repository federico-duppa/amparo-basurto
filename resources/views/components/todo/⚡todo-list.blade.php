<?php

use App\Models\Project;
use App\Models\Todo;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tareas')] class extends Component
{
    public string $title = '';

    public string $dueDate = '';

    public string $repeat = '';

    public string $projectId = '';

    public bool $urgent = false;

    public bool $important = false;

    // Detalles del formulario (fecha, proyecto, prioridad) plegados por defecto
    // para que anotar rápido siga siendo un solo campo.
    public bool $showDetails = false;

    // Edición de una tarea existente (null = estamos agregando una nueva).
    public ?int $editingId = null;

    // Vista activa: hoy | proximas | lista.
    public string $view = 'lista';

    // Filtro por proyecto (null = sin filtro).
    public ?int $activeProjectId = null;

    // Alta de proyecto.
    public bool $creatingProject = false;

    public string $projectName = '';

    protected function taskRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'dueDate' => ['nullable', 'date'],
            'repeat' => ['nullable', Rule::in(array_keys(Todo::REPEAT_INTERVALS))],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Contame qué tenés que hacer y la anoto.',
            'title.max' => 'Eso es muy largo para una tarea — probá resumirlo.',
            'dueDate.date' => 'Esa fecha no me cierra.',
            'repeat.in' => 'Esa repetición no la conozco.',
            'projectName.required' => 'Contame cómo se llama el proyecto.',
            'projectName.max' => 'Ese nombre es muy largo para un proyecto.',
        ];
    }

    public function add(): void
    {
        $attributes = $this->validatedTask();

        if ($attributes === null) {
            return;
        }

        auth()->user()->todos()->create($attributes);

        $this->reset('title', 'dueDate', 'repeat', 'urgent', 'important');
    }

    public function toggle(int $id): void
    {
        $todo = auth()->user()->todos()->findOrFail($id);

        if ($todo->isCompleted()) {
            $todo->update(['completed_at' => null]);

            return;
        }

        $todo->update(['completed_at' => now()]);

        // Una tarea que se repite deja lista la próxima ocurrencia al completarse.
        if ($todo->repeat_interval !== null && $todo->due_date !== null) {
            auth()->user()->todos()->create([
                'title' => $todo->title,
                'project_id' => $todo->project_id,
                'due_date' => $todo->nextDueDate(),
                'urgent' => $todo->urgent,
                'important' => $todo->important,
                'repeat_interval' => $todo->repeat_interval,
            ]);
        }
    }

    public function startEditing(int $id): void
    {
        $todo = auth()->user()->todos()->findOrFail($id);

        $this->editingId = $todo->id;
        $this->title = $todo->title;
        $this->dueDate = $todo->due_date?->format('Y-m-d') ?? '';
        $this->repeat = $todo->repeat_interval ?? '';
        $this->projectId = $todo->project_id === null ? '' : (string) $todo->project_id;
        $this->urgent = $todo->urgent;
        $this->important = $todo->important;
        $this->showDetails = true;
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $todo = auth()->user()->todos()->findOrFail($this->editingId);

        $attributes = $this->validatedTask();

        if ($attributes === null) {
            return;
        }

        $todo->update($attributes);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'title', 'dueDate', 'repeat', 'urgent', 'important', 'showDetails');
        $this->projectId = $this->defaultProjectId();
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
        $this->scopedTodos()->whereNotNull('completed_at')->delete();

        if ($this->editingId !== null && ! auth()->user()->todos()->whereKey($this->editingId)->exists()) {
            $this->cancelEdit();
        }
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['hoy', 'proximas', 'lista'], true)) {
            $this->view = $view;
        }
    }

    public function filterProject(?int $id = null): void
    {
        $this->activeProjectId = $id === null
            ? null
            : auth()->user()->projects()->findOrFail($id)->id;

        if ($this->editingId === null) {
            $this->projectId = $this->defaultProjectId();
        }
    }

    public function startCreatingProject(): void
    {
        $this->creatingProject = true;
        $this->resetValidation('projectName');
    }

    public function cancelCreatingProject(): void
    {
        $this->reset('creatingProject', 'projectName');
        $this->resetValidation('projectName');
    }

    public function addProject(): void
    {
        $this->validate(['projectName' => ['required', 'string', 'max:100']]);

        $name = trim($this->projectName);

        if (auth()->user()->projects()->where('name', $name)->exists()) {
            $this->addError('projectName', 'Ya tenés un proyecto con ese nombre.');

            return;
        }

        $project = auth()->user()->projects()->create(['name' => $name]);

        $this->reset('creatingProject', 'projectName');
        $this->activeProjectId = $project->id;

        if ($this->editingId === null) {
            $this->projectId = (string) $project->id;
        }
    }

    public function deleteProject(int $id): void
    {
        auth()->user()->projects()->findOrFail($id)->delete();

        if ($this->activeProjectId === $id) {
            $this->activeProjectId = null;
        }

        if ($this->projectId === (string) $id) {
            $this->projectId = '';
        }

        unset($this->projects);
    }

    /**
     * Valida el formulario de tarea y devuelve los atributos listos para
     * guardar, o null si quedó un error cargado (la repetición sin fecha).
     */
    private function validatedTask(): ?array
    {
        try {
            $this->validate($this->taskRules());
        } catch (ValidationException $e) {
            // Si el problema está en un detalle plegado, lo desplegamos para que se vea.
            if ($e->validator->errors()->hasAny(['dueDate', 'repeat'])) {
                $this->showDetails = true;
            }

            throw $e;
        }

        if ($this->repeat !== '' && $this->dueDate === '') {
            $this->showDetails = true;
            $this->addError('repeat', 'Para repetirla necesito una fecha de vencimiento.');

            return null;
        }

        $project = $this->projectId === ''
            ? null
            : auth()->user()->projects()->findOrFail((int) $this->projectId);

        return [
            'title' => trim($this->title),
            'due_date' => $this->dueDate === '' ? null : $this->dueDate,
            'repeat_interval' => $this->repeat === '' ? null : $this->repeat,
            'project_id' => $project?->id,
            'urgent' => $this->urgent,
            'important' => $this->important,
        ];
    }

    private function defaultProjectId(): string
    {
        return $this->activeProjectId === null ? '' : (string) $this->activeProjectId;
    }

    private function scopedTodos()
    {
        $query = auth()->user()->todos();

        if ($this->activeProjectId !== null) {
            $query->where('project_id', $this->activeProjectId);
        }

        return $query;
    }

    public function quadrantHint(): ?string
    {
        return match (true) {
            $this->urgent && $this->important => 'Urgente e importante: de las primeras a encarar.',
            $this->important => 'Importante sin apuro: reservale un momento.',
            $this->urgent => 'Urgente pero no importante: sacala rápido de encima.',
            default => null,
        };
    }

    #[Computed]
    public function todos(): Collection
    {
        $query = $this->scopedTodos()->with('project');

        if ($this->view === 'hoy') {
            return $query->whereNull('completed_at')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', today())
                ->get()
                ->sortBy([
                    fn (Todo $a, Todo $b) => $a->eisenhowerWeight() <=> $b->eisenhowerWeight(),
                    fn (Todo $a, Todo $b) => $a->due_date <=> $b->due_date,
                    fn (Todo $a, Todo $b) => $b->id <=> $a->id,
                ])->values();
        }

        if ($this->view === 'proximas') {
            return $query->whereNull('completed_at')
                ->whereDate('due_date', '>', today())
                ->get()
                ->sortBy([
                    fn (Todo $a, Todo $b) => $a->due_date <=> $b->due_date,
                    fn (Todo $a, Todo $b) => $a->eisenhowerWeight() <=> $b->eisenhowerWeight(),
                    fn (Todo $a, Todo $b) => $b->id <=> $a->id,
                ])->values();
        }

        return $query->get()->sortBy([
            fn (Todo $a, Todo $b) => (int) $a->isCompleted() <=> (int) $b->isCompleted(),
            fn (Todo $a, Todo $b) => $a->isCompleted() ? 0 : $a->eisenhowerWeight() <=> $b->eisenhowerWeight(),
            fn (Todo $a, Todo $b) => $b->id <=> $a->id,
        ])->values();
    }

    #[Computed]
    public function projects(): Collection
    {
        return auth()->user()->projects()
            ->withCount(['todos as pending_count' => fn ($query) => $query->whereNull('completed_at')])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function activeProject(): ?Project
    {
        return $this->activeProjectId === null
            ? null
            : $this->projects->firstWhere('id', $this->activeProjectId);
    }

    #[Computed]
    public function pending(): int
    {
        return auth()->user()->todos()->whereNull('completed_at')->count();
    }

    #[Computed]
    public function completed(): int
    {
        return $this->scopedTodos()->whereNotNull('completed_at')->count();
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

    <nav aria-label="Vistas de Tareas" class="flex border-b border-cuero/20">
        @foreach (['hoy' => 'Hoy', 'proximas' => 'Próximas', 'lista' => 'Lista'] as $clave => $etiqueta)
            <button
                type="button"
                wire:click="setView('{{ $clave }}')"
                aria-pressed="{{ $view === $clave ? 'true' : 'false' }}"
                class="-mb-px flex min-h-11 items-center border-b-2 px-3 text-sm {{ $view === $clave ? 'border-vino font-semibold text-vino' : 'border-transparent text-cuero/70 hover:text-cuero' }}"
            >{{ $etiqueta }}</button>
        @endforeach
    </nav>

    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtrar por proyecto">
            @if ($this->projects->isNotEmpty())
                <button
                    type="button"
                    wire:click="filterProject"
                    aria-pressed="{{ $activeProjectId === null ? 'true' : 'false' }}"
                    class="min-h-9 rounded-sm px-2.5 text-sm {{ $activeProjectId === null ? 'bg-vino font-medium text-crema' : 'border border-cuero/30 text-cuero/70 hover:text-cuero' }}"
                >Todas</button>
                @foreach ($this->projects as $proyecto)
                    <button
                        type="button"
                        wire:key="proyecto-{{ $proyecto->id }}"
                        wire:click="filterProject({{ $proyecto->id }})"
                        aria-pressed="{{ $activeProjectId === $proyecto->id ? 'true' : 'false' }}"
                        class="min-h-9 rounded-sm px-2.5 text-sm {{ $activeProjectId === $proyecto->id ? 'bg-vino font-medium text-crema' : 'border border-cuero/30 text-cuero/70 hover:text-cuero' }}"
                    >{{ $proyecto->name }}@if ($proyecto->pending_count > 0) ({{ $proyecto->pending_count }})@endif</button>
                @endforeach
            @endif

            @if (! $creatingProject)
                <button
                    type="button"
                    wire:click="startCreatingProject"
                    class="min-h-9 rounded-sm border border-dashed border-cuero/40 px-2.5 text-sm text-cuero/70 hover:border-monte hover:text-monte"
                >+ Proyecto</button>
            @endif
        </div>

        @if ($creatingProject)
            <form wire:submit="addProject" class="flex gap-2">
                <label for="projectName" class="sr-only">Nombre del proyecto</label>
                <input
                    id="projectName"
                    type="text"
                    wire:model="projectName"
                    wire:keydown.escape="cancelCreatingProject"
                    placeholder="¿Cómo se llama el proyecto?"
                    autocomplete="off"
                    class="min-h-11 w-full min-w-0 rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                <button
                    type="submit"
                    class="min-h-11 shrink-0 rounded-sm bg-monte px-4 text-sm font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte"
                >Crear</button>
                <button
                    type="button"
                    wire:click="cancelCreatingProject"
                    class="min-h-11 shrink-0 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-cuero"
                >Cancelar</button>
            </form>
            @error('projectName')
                <p class="text-sm text-teja" role="alert">{{ $message }}</p>
            @enderror
        @endif

        @if ($this->activeProject)
            <div class="flex items-center justify-between gap-2 text-sm text-cuero/70">
                <span>
                    Viendo «{{ $this->activeProject->name }}»
                    ({{ $this->activeProject->pending_count === 1 ? '1 pendiente' : $this->activeProject->pending_count.' pendientes' }}).
                </span>
                <button
                    type="button"
                    wire:click="deleteProject({{ $this->activeProject->id }})"
                    wire:confirm="Vas a eliminar el proyecto «{{ $this->activeProject->name }}». Sus tareas no se pierden: quedan sueltas."
                    class="font-medium text-teja underline hover:no-underline"
                >Eliminar proyecto</button>
            </div>
        @endif
    </div>

    <form wire:submit="{{ $editingId ? 'saveEdit' : 'add' }}" class="space-y-2">
        @if ($editingId)
            <div class="flex items-center gap-2 rounded-sm bg-vino/10 px-3 py-2 text-sm text-vino" role="status">
                <span class="font-medium">Estás editando una tarea.</span>
                <button type="button" wire:click="cancelEdit" class="ml-auto font-medium underline hover:no-underline">Cancelar</button>
            </div>
        @endif

        <div class="flex gap-2">
            <label for="title" class="sr-only">{{ $editingId ? 'Editar tarea' : 'Nueva tarea' }}</label>
            <input
                id="title"
                type="text"
                wire:model="title"
                wire:keydown.escape="cancelEdit"
                placeholder="¿Qué tenés pendiente?"
                autocomplete="off"
                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
            >
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="min-h-11 shrink-0 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
            >
                {{ $editingId ? 'Guardar' : 'Agregar' }}
            </button>
        </div>
        @error('title')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror

        <button
            type="button"
            wire:click="$toggle('showDetails')"
            aria-expanded="{{ $showDetails ? 'true' : 'false' }}"
            class="flex min-h-11 items-center gap-1 text-sm font-medium text-monte hover:text-monte/80 focus-visible:outline-2 focus-visible:outline-monte"
        >
            {{-- Heroicon: chevron-down (mini) --}}
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 transition-transform {{ $showDetails ? 'rotate-180' : '' }}">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
            {{ $showDetails ? 'Ocultar detalles' : 'Fecha, proyecto y prioridad' }}
        </button>

        @if ($showDetails)
            <div class="space-y-3 rounded-sm border border-cuero/20 p-3">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <x-ui.date-field model="dueDate" label="Vence" accent="vino" preset="tarea" />
                    </div>
                    <div>
                        <label for="repeat" class="mb-1 block text-sm text-cuero/70">Se repite</label>
                        <select
                            id="repeat"
                            wire:model="repeat"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                        >
                            <option value="">No se repite</option>
                            @foreach (App\Models\Todo::REPEAT_INTERVALS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @error('dueDate')
                    <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                @enderror
                @error('repeat')
                    <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                @enderror

                @if ($this->projects->isNotEmpty())
                    <div>
                        <label for="projectId" class="mb-1 block text-sm text-cuero/70">Proyecto</label>
                        <select
                            id="projectId"
                            wire:model="projectId"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                        >
                            <option value="">Sin proyecto</option>
                            @foreach ($this->projects as $proyecto)
                                <option value="{{ $proyecto->id }}">{{ $proyecto->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <fieldset>
                    <legend class="mb-1 block text-sm text-cuero/70">Prioridad</legend>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            wire:click="$toggle('urgent')"
                            aria-pressed="{{ $urgent ? 'true' : 'false' }}"
                            class="min-h-11 rounded-sm px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero {{ $urgent ? 'bg-ocre text-negro' : 'border border-cuero/30 text-cuero/70 hover:text-cuero' }}"
                        >Urgente</button>
                        <button
                            type="button"
                            wire:click="$toggle('important')"
                            aria-pressed="{{ $important ? 'true' : 'false' }}"
                            class="min-h-11 rounded-sm px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vino {{ $important ? 'bg-vino text-crema' : 'border border-cuero/30 text-cuero/70 hover:text-cuero' }}"
                        >Importante</button>
                    </div>
                    @if ($this->quadrantHint())
                        <p class="mt-1 text-sm text-cuero/60">{{ $this->quadrantHint() }}</p>
                    @endif
                </fieldset>
            </div>
        @endif
    </form>

    @if ($this->todos->isEmpty())
        <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
            @if ($view === 'hoy')
                Nada para hoy. Si querés adelantar, mirá las próximas.
            @elseif ($view === 'proximas')
                No tenés nada con fecha por venir.
            @elseif ($this->activeProject)
                Este proyecto está vacío. Anotale la primera tarea.
            @else
                Todavía no anotaste nada. Cuando quieras, empezamos.
            @endif
        </p>
    @else
        @if ($view === 'lista' && $this->pending === 0)
            <p class="text-sm text-yerba" role="status">No te queda nada pendiente. Buen trabajo.</p>
        @endif

        <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
            @foreach ($this->todos as $todo)
                <li wire:key="{{ $todo->id }}" class="flex items-start gap-2 py-1">
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

                    <div class="min-w-0 flex-1 py-2">
                        <p class="break-words {{ $todo->isCompleted() ? 'text-cuero/50 line-through' : '' }} {{ $editingId === $todo->id ? 'text-vino' : '' }}">
                            {{ $todo->title }}
                        </p>
                        @unless ($todo->isCompleted())
                            @php
                                $piezas = [];
                                if ($todo->due_date) {
                                    $piezas[] = [
                                        'texto' => $todo->isOverdue()
                                            ? 'venció el '.$todo->due_date->format('d/m/Y')
                                            : ($todo->due_date->isToday() ? 'vence hoy' : 'vence el '.$todo->due_date->format('d/m/Y')),
                                        'clase' => $todo->isOverdue() ? 'font-medium text-teja' : '',
                                    ];
                                }
                                if ($todo->repeat_interval) {
                                    $piezas[] = ['texto' => 'se repite', 'clase' => ''];
                                }
                                if ($todo->project && $activeProjectId === null) {
                                    $piezas[] = ['texto' => 'en '.$todo->project->name, 'clase' => ''];
                                }
                            @endphp
                            @if ($todo->urgent || $todo->important || $piezas !== [])
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-sm text-cuero/60">
                                    @if ($todo->urgent)
                                        <span class="rounded-sm bg-ocre px-1.5 py-0.5 text-xs font-semibold text-negro">Urgente</span>
                                    @endif
                                    @if ($todo->important)
                                        <span class="rounded-sm bg-vino px-1.5 py-0.5 text-xs font-semibold text-crema">Importante</span>
                                    @endif
                                    @foreach ($piezas as $pieza)
                                        @unless ($loop->first && ! $todo->urgent && ! $todo->important)
                                            <span aria-hidden="true">·</span>
                                        @endunless
                                        <span @if ($pieza['clase']) class="{{ $pieza['clase'] }}" @endif>{{ $pieza['texto'] }}</span>
                                    @endforeach
                                </p>
                            @endif
                        @endunless
                    </div>

                    <button
                        type="button"
                        wire:click="startEditing({{ $todo->id }})"
                        aria-label="Editar: {{ $todo->title }}"
                        @if ($editingId === $todo->id) aria-current="true" @endif
                        class="grid size-11 shrink-0 place-items-center focus-visible:outline-2 focus-visible:outline-vino {{ $editingId === $todo->id ? 'text-vino' : 'text-cuero/60 hover:text-vino' }}"
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
                </li>
            @endforeach
        </ul>

        @if ($view === 'lista' && $this->completed > 0)
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
