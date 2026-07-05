<?php

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Todo;
use App\Support\NaturalDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tareas')] class extends Component
{
    use WithPagination;

    private const PER_PAGE = 25;

    private const PER_PAGE_DONE = 15;

    // --- Formulario -------------------------------------------------------

    public string $title = '';

    public string $notes = '';

    public string $dueDate = '';

    public string $deferredUntil = '';

    public string $repeat = '';

    public string $projectId = '';

    public bool $urgent = false;

    public bool $important = false;

    public bool $waiting = false;

    public string $waitingFor = '';

    public bool $someday = false;

    /** @var array<int, string> Etiquetas elegidas para la tarea en edición. */
    public array $selectedTags = [];

    public string $tagDraft = '';

    public string $newSubtask = '';

    // Detalles del formulario plegados por defecto: anotar rápido sigue siendo
    // un solo campo.
    public bool $showDetails = false;

    // Amparo avisa cuando dedujo la fecha del texto ("mañana", "el viernes"…).
    public string $naturalDateNotice = '';

    // Edición de una tarea existente (null = estamos agregando una nueva).
    public ?int $editingId = null;

    // --- Vistas y filtros -------------------------------------------------

    // Vista activa: hoy | proximas | lista | algun_dia.
    public string $view = 'lista';

    public ?int $activeProjectId = null;

    public ?int $activeTagId = null;

    public string $search = '';

    // Mostrar en Lista las pospuestas (por defecto quedan ocultas).
    public bool $showDeferred = false;

    // --- Alta / edición de proyecto ---------------------------------------

    public bool $creatingProject = false;

    public string $projectName = '';

    public ?int $renamingProjectId = null;

    public string $renameProjectName = '';

    public string $shareUsername = '';

    protected function taskRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'dueDate' => ['nullable', 'date'],
            'deferredUntil' => ['nullable', 'date'],
            'waitingFor' => ['nullable', 'string', 'max:120'],
            'repeat' => ['nullable', Rule::in(array_keys(Todo::REPEAT_INTERVALS))],
            'selectedTags.*' => ['string', 'max:40'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Contame qué tenés que hacer y la anoto.',
            'title.max' => 'Eso es muy largo para una tarea — probá resumirlo.',
            'notes.max' => 'Esa nota es muy larga. Guardá lo esencial.',
            'dueDate.date' => 'Esa fecha no me cierra.',
            'deferredUntil.date' => 'Esa fecha no me cierra.',
            'repeat.in' => 'Esa repetición no la conozco.',
            'projectName.required' => 'Contame cómo se llama el proyecto.',
            'projectName.max' => 'Ese nombre es muy largo para un proyecto.',
            'renameProjectName.required' => 'El proyecto necesita un nombre.',
            'renameProjectName.max' => 'Ese nombre es muy largo para un proyecto.',
        ];
    }

    // --- Alta y edición de tareas -----------------------------------------

    public function add(): void
    {
        $this->naturalDateNotice = '';

        // Fecha en lenguaje natural: sólo al anotar y sólo si no la puso a mano.
        if ($this->dueDate === '' && trim($this->title) !== '') {
            $guess = NaturalDate::extract(trim($this->title));

            if ($guess !== null) {
                $this->title = $guess['title'];
                $this->dueDate = $guess['date'];
                $this->naturalDateNotice = 'Le puse fecha para el '.Carbon::parse($guess['date'])->format('d/m/Y').'. Si no era eso, cambiala.';
            }
        }

        $attributes = $this->validatedTask();

        if ($attributes === null) {
            return;
        }

        $todo = auth()->user()->todos()->create($attributes);
        $this->syncTags($todo);

        $this->resetForm();
    }

    public function startEditing(int $id): void
    {
        $todo = auth()->user()->todos()->with('tags')->findOrFail($id);

        $this->editingId = $todo->id;
        $this->title = $todo->title;
        $this->notes = $todo->notes ?? '';
        $this->dueDate = $todo->due_date?->format('Y-m-d') ?? '';
        $this->deferredUntil = $todo->deferred_until?->format('Y-m-d') ?? '';
        $this->repeat = $todo->repeat_interval ?? '';
        $this->projectId = $todo->project_id === null ? '' : (string) $todo->project_id;
        $this->urgent = $todo->urgent;
        $this->important = $todo->important;
        $this->waiting = $todo->waiting;
        $this->waitingFor = $todo->waiting_for ?? '';
        $this->someday = $todo->isSomeday();
        $this->selectedTags = $todo->tags->pluck('name')->all();
        $this->tagDraft = '';
        $this->newSubtask = '';
        $this->naturalDateNotice = '';
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
        $this->syncTags($todo);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showDetails = false;
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->reset(
            'title', 'notes', 'dueDate', 'deferredUntil', 'repeat',
            'urgent', 'important', 'waiting', 'waitingFor', 'someday',
            'selectedTags', 'tagDraft', 'newSubtask', 'editingId',
        );
        $this->projectId = $this->defaultProjectId();
    }

    public function toggle(int $id): void
    {
        // Cualquier miembro de un proyecto compartido puede tachar sus tareas.
        $todo = auth()->user()->accessibleTodos()->findOrFail($id);

        if ($todo->isCompleted()) {
            $todo->update(['completed_at' => null]);

            return;
        }

        $todo->update(['completed_at' => now()]);

        // Una tarea que se repite deja lista la próxima ocurrencia al completarse.
        // La nueva ocurrencia sigue siendo del dueño original, no de quien la tachó.
        if ($todo->repeat_interval !== null && $todo->due_date !== null) {
            $next = $todo->user->todos()->create([
                'title' => $todo->title,
                'notes' => $todo->notes,
                'project_id' => $todo->project_id,
                'due_date' => $todo->nextDueDate(),
                'urgent' => $todo->urgent,
                'important' => $todo->important,
                'repeat_interval' => $todo->repeat_interval,
            ]);

            $next->tags()->sync($todo->tags()->pluck('tags.id'));
        }
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
        // Sólo las mías: en un proyecto compartido no borro lo de otra persona.
        $query = auth()->user()->todos()->whereNotNull('completed_at');

        if ($this->activeProjectId !== null) {
            $query->where('project_id', $this->activeProjectId);
        }

        $query->get()->each->delete();

        $this->resetPage('completadas');

        if ($this->editingId !== null && ! auth()->user()->todos()->whereKey($this->editingId)->exists()) {
            $this->cancelEdit();
        }
    }

    // --- Estados: algún día, en espera, posponer --------------------------

    public function toSomeday(int $id): void
    {
        auth()->user()->todos()->findOrFail($id)->update(['status' => Todo::STATUS_SOMEDAY]);
    }

    public function toActive(int $id): void
    {
        auth()->user()->todos()->findOrFail($id)->update(['status' => Todo::STATUS_ACTIVE]);
    }

    public function undefer(int $id): void
    {
        auth()->user()->todos()->findOrFail($id)->update(['deferred_until' => null]);
    }

    // --- Orden manual (dentro de cada cuadrante de Eisenhower) -------------

    public function moveUp(int $id): void
    {
        $this->reorder($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->reorder($id, 1);
    }

    private function reorder(int $id, int $direction): void
    {
        if (! auth()->user()->todos()->whereKey($id)->exists()) {
            return;
        }

        $list = auth()->user()->todos()
            ->whereNull('completed_at')
            ->where('status', Todo::STATUS_ACTIVE)
            ->visibleToday()
            ->get()
            ->sortBy([
                fn (Todo $a, Todo $b) => $a->eisenhowerWeight() <=> $b->eisenhowerWeight(),
                fn (Todo $a, Todo $b) => $a->position <=> $b->position,
                fn (Todo $a, Todo $b) => $b->id <=> $a->id,
            ])
            ->values();

        $i = $list->search(fn (Todo $t) => $t->id === $id);
        $j = $i + $direction;

        if ($i === false || $j < 0 || $j >= $list->count()) {
            return;
        }

        // No se cruza de cuadrante: el cuadrante manda sobre el orden manual.
        if ($list[$i]->eisenhowerWeight() !== $list[$j]->eisenhowerWeight()) {
            return;
        }

        $swap = $list[$i];
        $list[$i] = $list[$j];
        $list[$j] = $swap;

        foreach ($list as $index => $todo) {
            if ($todo->position !== $index) {
                $todo->update(['position' => $index]);
            }
        }
    }

    // --- Etiquetas --------------------------------------------------------

    public function addTagFromDraft(): void
    {
        $this->pushTag($this->tagDraft);
        $this->tagDraft = '';
    }

    public function toggleTag(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $key = mb_strtolower($name);
        $current = collect($this->selectedTags);

        if ($current->contains(fn ($t) => mb_strtolower($t) === $key)) {
            $this->selectedTags = $current->reject(fn ($t) => mb_strtolower($t) === $key)->values()->all();

            return;
        }

        $this->pushTag($name);
    }

    private function pushTag(string $name): void
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 40) {
            return;
        }

        $key = mb_strtolower($name);

        if (collect($this->selectedTags)->contains(fn ($t) => mb_strtolower($t) === $key)) {
            return;
        }

        $this->selectedTags[] = $name;
    }

    private function syncTags(Todo $todo): void
    {
        $ids = collect($this->selectedTags)
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->map(fn ($name) => auth()->user()->tags()->firstOrCreate(['name' => $name])->id)
            ->all();

        $todo->tags()->sync($ids);

        // Una etiqueta que se quedó sin tareas no tiene por qué seguir en la lista.
        auth()->user()->tags()->doesntHave('todos')->delete();

        unset($this->allTags);

        if ($this->activeTagId !== null && ! auth()->user()->tags()->whereKey($this->activeTagId)->exists()) {
            $this->activeTagId = null;
        }
    }

    // --- Subtareas (checklist) --------------------------------------------

    public function addSubtask(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $this->validate(['newSubtask' => ['required', 'string', 'max:255']], [
            'newSubtask.required' => 'Escribí el paso y lo sumo.',
            'newSubtask.max' => 'Ese paso es muy largo.',
        ]);

        $todo = auth()->user()->todos()->findOrFail($this->editingId);

        $todo->subtasks()->create([
            'title' => trim($this->newSubtask),
            'position' => (int) $todo->subtasks()->max('position') + 1,
        ]);

        $this->newSubtask = '';
    }

    public function toggleSubtask(int $subtaskId): void
    {
        $subtask = $this->editingSubtask($subtaskId);

        $subtask->update([
            'completed_at' => $subtask->isCompleted() ? null : now(),
        ]);
    }

    public function deleteSubtask(int $subtaskId): void
    {
        $this->editingSubtask($subtaskId)->delete();
    }

    private function editingSubtask(int $subtaskId): Subtask
    {
        $todo = auth()->user()->todos()->findOrFail($this->editingId);

        return $todo->subtasks()->findOrFail($subtaskId);
    }

    // --- Vistas y filtros -------------------------------------------------

    public function setView(string $view): void
    {
        if (in_array($view, ['hoy', 'proximas', 'lista', 'algun_dia'], true)) {
            $this->view = $view;
            $this->resetPage();
            $this->resetPage('completadas');
        }
    }

    public function filterProject(?int $id = null): void
    {
        $this->activeProjectId = $id === null
            ? null
            : auth()->user()->accessibleProjects()->findOrFail($id)->id;

        $this->resetPage();
        $this->resetPage('completadas');

        if ($this->editingId === null) {
            $this->projectId = $this->defaultProjectId();
        }
    }

    public function filterTag(?int $id = null): void
    {
        $this->activeTagId = $id === null
            ? null
            : auth()->user()->tags()->findOrFail($id)->id;

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleDeferred(): void
    {
        $this->showDeferred = ! $this->showDeferred;
        $this->resetPage();
    }

    // --- Proyectos --------------------------------------------------------

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

    public function startRenamingProject(int $id): void
    {
        $project = $this->requireOwnedProject($id);

        $this->renamingProjectId = $project->id;
        $this->renameProjectName = $project->name;
        $this->resetValidation('renameProjectName');
    }

    public function cancelRenamingProject(): void
    {
        $this->reset('renamingProjectId', 'renameProjectName');
        $this->resetValidation('renameProjectName');
    }

    public function renameProject(): void
    {
        $project = $this->requireOwnedProject($this->renamingProjectId);

        $this->validate(['renameProjectName' => ['required', 'string', 'max:100']]);

        $name = trim($this->renameProjectName);

        if (auth()->user()->projects()->where('name', $name)->whereKeyNot($project->id)->exists()) {
            $this->addError('renameProjectName', 'Ya tenés un proyecto con ese nombre.');

            return;
        }

        $project->update(['name' => $name]);

        $this->reset('renamingProjectId', 'renameProjectName');
        unset($this->projects);
    }

    public function deleteProject(int $id): void
    {
        $this->requireOwnedProject($id)->delete();

        if ($this->activeProjectId === $id) {
            $this->activeProjectId = null;
        }

        if ($this->projectId === (string) $id) {
            $this->projectId = '';
        }

        unset($this->projects);
    }

    // --- Compartir proyecto ----------------------------------------------

    public function share(): void
    {
        $project = $this->requireOwnedProject($this->activeProjectId);

        $this->shareUsername = strtolower(trim($this->shareUsername));

        $this->validate([
            'shareUsername' => ['required', 'string', 'max:50'],
        ], [
            'shareUsername.required' => 'Decime el usuario de la persona con quien lo compartís.',
        ]);

        $user = \App\Models\User::where('username', $this->shareUsername)->first();

        if (! $user) {
            $this->addError('shareUsername', 'No encontré a nadie con ese usuario.');

            return;
        }

        if ($project->isOwnedBy($user)) {
            $this->addError('shareUsername', 'Ese proyecto ya es tuyo.');

            return;
        }

        if ($project->members()->whereKey($user->id)->exists()) {
            $this->addError('shareUsername', 'Ya lo estás compartiendo con esa persona.');

            return;
        }

        $project->members()->attach($user->id);

        $this->reset('shareUsername');
        unset($this->projects);
    }

    public function unshare(int $userId): void
    {
        $this->requireOwnedProject($this->activeProjectId)->members()->detach($userId);
        unset($this->projects);
    }

    private function requireOwnedProject(?int $id): Project
    {
        return auth()->user()->projects()->findOrFail($id);
    }

    // --- Helpers de validación y scoping ----------------------------------

    /**
     * Valida el formulario de tarea y devuelve los atributos listos para
     * guardar, o null si quedó un error cargado (la repetición sin fecha).
     */
    private function validatedTask(): ?array
    {
        try {
            $this->validate($this->taskRules());
        } catch (ValidationException $e) {
            if ($e->validator->errors()->hasAny(['dueDate', 'deferredUntil', 'repeat', 'notes', 'waitingFor'])) {
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
            : auth()->user()->accessibleProjects()->findOrFail((int) $this->projectId);

        return [
            'title' => trim($this->title),
            'notes' => trim($this->notes) === '' ? null : trim($this->notes),
            'due_date' => $this->dueDate === '' ? null : $this->dueDate,
            'deferred_until' => $this->deferredUntil === '' ? null : $this->deferredUntil,
            'repeat_interval' => $this->repeat === '' ? null : $this->repeat,
            'project_id' => $project?->id,
            'urgent' => $this->urgent,
            'important' => $this->important,
            'waiting' => $this->waiting,
            'waiting_for' => $this->waiting && trim($this->waitingFor) !== '' ? trim($this->waitingFor) : null,
            'status' => $this->someday ? Todo::STATUS_SOMEDAY : Todo::STATUS_ACTIVE,
        ];
    }

    private function defaultProjectId(): string
    {
        return $this->activeProjectId === null ? '' : (string) $this->activeProjectId;
    }

    /**
     * Base de tareas según el filtro: sin filtro son sólo las mías; con un
     * proyecto seleccionado son todas las de ese proyecto (mías y de quienes
     * lo comparten), aplicando después etiqueta y búsqueda.
     */
    private function scopedTodos()
    {
        if ($this->activeProjectId !== null) {
            $project = auth()->user()->accessibleProjects()->findOrFail($this->activeProjectId);
            $query = Todo::query()->where('project_id', $project->id);
        } else {
            $query = auth()->user()->todos();
        }

        if ($this->activeTagId !== null) {
            $query->whereHas('tags', fn ($tags) => $tags->whereKey($this->activeTagId));
        }

        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('notes', 'like', $like));
        }

        return $query->with(['project', 'tags', 'user'])
            ->withCount([
                'subtasks',
                'subtasks as subtasks_done_count' => fn ($q) => $q->whereNotNull('completed_at'),
            ]);
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

    // --- Computed ---------------------------------------------------------

    #[Computed]
    public function todos(): LengthAwarePaginator
    {
        $query = $this->scopedTodos();

        if ($this->view === 'hoy') {
            return $query->whereNull('completed_at')
                ->where('status', Todo::STATUS_ACTIVE)
                ->where('waiting', false)
                ->visibleToday()
                ->whereNotNull('due_date')
                ->where('due_date', '<', today()->addDay())
                ->orderByRaw(Todo::eisenhowerOrderSql())
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE);
        }

        if ($this->view === 'proximas') {
            return $query->whereNull('completed_at')
                ->where('status', Todo::STATUS_ACTIVE)
                ->where('waiting', false)
                ->visibleToday()
                ->where('due_date', '>=', today()->addDay())
                ->orderBy('due_date')
                ->orderByRaw(Todo::eisenhowerOrderSql())
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE);
        }

        if ($this->view === 'algun_dia') {
            return $query->whereNull('completed_at')
                ->where('status', Todo::STATUS_SOMEDAY)
                ->orderByRaw(Todo::eisenhowerOrderSql())
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE);
        }

        // Lista: pendientes activas, ordenadas en SQL por cuadrante, orden
        // manual y luego la más reciente. Las pospuestas quedan fuera salvo
        // que se pidan a propósito.
        $query->whereNull('completed_at')->where('status', Todo::STATUS_ACTIVE);

        if (! $this->showDeferred) {
            $query->visibleToday();
        }

        return $query
            ->orderByRaw(Todo::eisenhowerOrderSql())
            ->orderBy('position')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    #[Computed]
    public function completedTodos(): LengthAwarePaginator
    {
        return $this->scopedTodos()
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->paginate(self::PER_PAGE_DONE, pageName: 'completadas');
    }

    #[Computed]
    public function projects(): Collection
    {
        return auth()->user()->accessibleProjects()
            ->with('user')
            ->withCount([
                'todos as pending_count' => fn ($query) => $query->whereNull('completed_at')->where('status', Todo::STATUS_ACTIVE),
                'members as members_count',
            ])
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
    public function allTags(): Collection
    {
        return auth()->user()->tags()
            ->withCount(['todos as pending_count' => fn ($query) => $query->whereNull('completed_at')])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function editingSubtasks(): Collection
    {
        if ($this->editingId === null) {
            return collect();
        }

        return auth()->user()->todos()->findOrFail($this->editingId)->subtasks()->get();
    }

    #[Computed]
    public function pending(): int
    {
        return auth()->user()->todos()
            ->whereNull('completed_at')
            ->where('status', Todo::STATUS_ACTIVE)
            ->visibleToday()
            ->count();
    }

    #[Computed]
    public function deferredCount(): int
    {
        return auth()->user()->todos()
            ->whereNull('completed_at')
            ->where('status', Todo::STATUS_ACTIVE)
            ->where('deferred_until', '>', today())
            ->count();
    }

    #[Computed]
    public function somedayCount(): int
    {
        return auth()->user()->todos()
            ->whereNull('completed_at')
            ->where('status', Todo::STATUS_SOMEDAY)
            ->count();
    }

    #[Computed]
    public function myCompletedCount(): int
    {
        $query = auth()->user()->todos()->whereNotNull('completed_at');

        if ($this->activeProjectId !== null) {
            $query->where('project_id', $this->activeProjectId);
        }

        return $query->count();
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

    <nav aria-label="Vistas de Tareas" class="flex flex-wrap border-b border-cuero/20">
        @php
            $tabs = ['hoy' => 'Hoy', 'proximas' => 'Próximas', 'lista' => 'Lista'];
            if ($this->somedayCount > 0 || $view === 'algun_dia') {
                $tabs['algun_dia'] = 'Algún día';
            }
        @endphp
        @foreach ($tabs as $clave => $etiqueta)
            <button
                type="button"
                wire:click="setView('{{ $clave }}')"
                aria-pressed="{{ $view === $clave ? 'true' : 'false' }}"
                class="-mb-px flex min-h-11 items-center border-b-2 px-3 text-sm {{ $view === $clave ? 'border-vino font-semibold text-vino' : 'border-transparent text-cuero/70 hover:text-cuero' }}"
            >{{ $etiqueta }}</button>
        @endforeach
    </nav>

    {{-- Búsqueda --}}
    <div class="relative">
        <label for="search" class="sr-only">Buscar tareas</label>
        {{-- Heroicon: magnifying-glass (mini) --}}
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-cuero/50">
            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
        </svg>
        <input
            id="search"
            type="search"
            wire:model.live.debounce.400ms="search"
            placeholder="Buscar en tus tareas"
            autocomplete="off"
            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema pl-9 pr-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
        >
    </div>

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
                    >{{ $proyecto->name }}@if ($proyecto->pending_count > 0) ({{ $proyecto->pending_count }})@endif@unless ($proyecto->isOwnedBy(auth()->user()))<span class="ml-1 opacity-70" aria-hidden="true">·</span>@endunless</button>
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

        {{-- Etiquetas como filtro --}}
        @if ($this->allTags->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtrar por etiqueta">
                @foreach ($this->allTags as $etiqueta)
                    <button
                        type="button"
                        wire:key="filtro-tag-{{ $etiqueta->id }}"
                        wire:click="filterTag({{ $activeTagId === $etiqueta->id ? '' : $etiqueta->id }})"
                        aria-pressed="{{ $activeTagId === $etiqueta->id ? 'true' : 'false' }}"
                        class="min-h-9 rounded-sm px-2.5 text-sm {{ $activeTagId === $etiqueta->id ? 'bg-cuero font-medium text-crema' : 'border border-cuero/30 text-cuero/70 hover:text-cuero' }}"
                    >#{{ $etiqueta->name }}</button>
                @endforeach
            </div>
        @endif

        @if ($this->activeProject)
            <div class="space-y-2 rounded-sm border border-cuero/20 p-3 text-sm text-cuero/70">
                @if ($renamingProjectId === $this->activeProject->id)
                    <form wire:submit="renameProject" class="flex gap-2">
                        <label for="renameProjectName" class="sr-only">Nuevo nombre del proyecto</label>
                        <input
                            id="renameProjectName"
                            type="text"
                            wire:model="renameProjectName"
                            wire:keydown.escape="cancelRenamingProject"
                            autocomplete="off"
                            class="min-h-11 w-full min-w-0 rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                        >
                        <button type="submit" class="min-h-11 shrink-0 rounded-sm bg-monte px-4 text-sm font-medium text-crema hover:bg-monte/90">Guardar</button>
                        <button type="button" wire:click="cancelRenamingProject" class="min-h-11 shrink-0 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/70 hover:text-cuero">Cancelar</button>
                    </form>
                    @error('renameProjectName')
                        <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                    @enderror
                @else
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span>
                            Viendo «{{ $this->activeProject->name }}»
                            ({{ $this->activeProject->pending_count === 1 ? '1 pendiente' : $this->activeProject->pending_count.' pendientes' }}){{ $this->activeProject->isOwnedBy(auth()->user()) ? '' : ', compartido con vos por '.$this->activeProject->user->name }}.
                        </span>
                        @if ($this->activeProject->isOwnedBy(auth()->user()))
                            <span class="flex items-center gap-3">
                                <button type="button" wire:click="startRenamingProject({{ $this->activeProject->id }})" class="font-medium text-monte underline hover:no-underline">Renombrar</button>
                                <button
                                    type="button"
                                    wire:click="deleteProject({{ $this->activeProject->id }})"
                                    wire:confirm="Vas a eliminar el proyecto «{{ $this->activeProject->name }}». Sus tareas no se pierden: quedan sueltas."
                                    class="font-medium text-teja underline hover:no-underline"
                                >Eliminar</button>
                            </span>
                        @endif
                    </div>

                    @if ($this->activeProject->isOwnedBy(auth()->user()))
                        {{-- Compartir --}}
                        <div class="space-y-2 border-t border-cuero/15 pt-2">
                            @if ($this->activeProject->members->isNotEmpty())
                                <ul class="flex flex-wrap gap-2">
                                    @foreach ($this->activeProject->members as $member)
                                        <li wire:key="member-{{ $member->id }}" class="flex items-center gap-1 rounded-sm bg-cuero/10 px-2 py-1">
                                            <span>{{ $member->name }}</span>
                                            <button
                                                type="button"
                                                wire:click="unshare({{ $member->id }})"
                                                wire:confirm="Vas a dejar de compartir el proyecto con {{ $member->name }}. Ya no va a poder verlo."
                                                aria-label="Dejar de compartir con {{ $member->name }}"
                                                class="grid size-6 place-items-center rounded-sm text-cuero/60 hover:text-teja"
                                            >
                                                {{-- Heroicon: x-mark (mini) --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <form wire:submit="share" class="flex flex-wrap items-end gap-2">
                                <div class="min-w-0 flex-1">
                                    <label for="shareUsername" class="mb-1 block text-sm">Compartir con (usuario)</label>
                                    <input id="shareUsername" type="text" wire:model="shareUsername" autocomplete="off" autocapitalize="none"
                                        class="min-h-11 w-full min-w-0 rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40">
                                </div>
                                <button type="submit" class="min-h-11 shrink-0 rounded-sm bg-monte px-4 text-sm font-medium text-crema hover:bg-monte/90">Compartir</button>
                                @error('shareUsername') <p class="w-full text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                            </form>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>

    {{-- Formulario de alta / edición --}}
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

        @if ($naturalDateNotice !== '' && ! $editingId)
            <p class="text-sm text-yerba" role="status">{{ $naturalDateNotice }}</p>
        @endif

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
            {{ $showDetails ? 'Ocultar detalles' : 'Fecha, notas, etiquetas y más' }}
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

                {{-- Posponer (tickler) --}}
                <div>
                    <x-ui.date-field model="deferredUntil" label="No me la muestres hasta" accent="vino" preset="tarea" />
                    <p class="mt-1 text-xs text-cuero/50">La guardo y no te la muestro hasta esa fecha.</p>
                </div>
                @error('deferredUntil')
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

                {{-- En espera --}}
                <fieldset>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="$toggle('waiting')"
                            aria-pressed="{{ $waiting ? 'true' : 'false' }}"
                            class="min-h-11 rounded-sm px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero {{ $waiting ? 'bg-cuero text-crema' : 'border border-cuero/30 text-cuero/70 hover:text-cuero' }}"
                        >En espera</button>
                        @if ($waiting)
                            <input
                                type="text"
                                wire:model="waitingFor"
                                placeholder="¿De quién o de qué? (opcional)"
                                autocomplete="off"
                                class="min-h-11 min-w-0 flex-1 rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                            >
                        @endif
                    </div>
                    @if ($waiting)
                        <p class="mt-1 text-xs text-cuero/50">La dejo marcada como bloqueada por un tercero.</p>
                    @endif
                    @error('waitingFor')
                        <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- Algún día --}}
                <div>
                    <button
                        type="button"
                        wire:click="$toggle('someday')"
                        aria-pressed="{{ $someday ? 'true' : 'false' }}"
                        class="min-h-11 rounded-sm px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero {{ $someday ? 'bg-cuero text-crema' : 'border border-cuero/30 text-cuero/70 hover:text-cuero' }}"
                    >Guardar para algún día</button>
                    @if ($someday)
                        <p class="mt-1 text-xs text-cuero/50">La saco de la lista principal; la vas a encontrar en «Algún día».</p>
                    @endif
                </div>

                {{-- Etiquetas --}}
                <div>
                    <label for="tagDraft" class="mb-1 block text-sm text-cuero/70">Etiquetas</label>
                    @if ($selectedTags !== [])
                        <ul class="mb-2 flex flex-wrap gap-1.5">
                            @foreach ($selectedTags as $tag)
                                <li wire:key="sel-tag-{{ $loop->index }}">
                                    <button type="button" wire:click="toggleTag(@js($tag))" class="flex items-center gap-1 rounded-sm bg-cuero/10 px-2 py-1 text-sm text-cuero hover:bg-cuero/20">
                                        #{{ $tag }}
                                        <span aria-hidden="true">×</span>
                                        <span class="sr-only">Quitar etiqueta {{ $tag }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="flex gap-2">
                        <input
                            id="tagDraft"
                            type="text"
                            wire:model="tagDraft"
                            wire:keydown.enter.prevent="addTagFromDraft"
                            placeholder="@casa, comprar…"
                            autocomplete="off"
                            maxlength="40"
                            class="min-h-11 w-full min-w-0 rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                        >
                        <button type="button" wire:click="addTagFromDraft" class="min-h-11 shrink-0 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/70 hover:text-cuero">Sumar</button>
                    </div>
                    @if ($this->allTags->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($this->allTags as $etiqueta)
                                @php $elegida = collect($selectedTags)->contains(fn ($t) => mb_strtolower($t) === mb_strtolower($etiqueta->name)); @endphp
                                <button
                                    type="button"
                                    wire:key="sug-tag-{{ $etiqueta->id }}"
                                    wire:click="toggleTag(@js($etiqueta->name))"
                                    aria-pressed="{{ $elegida ? 'true' : 'false' }}"
                                    class="min-h-8 rounded-sm px-2 text-xs {{ $elegida ? 'bg-cuero text-crema' : 'border border-cuero/30 text-cuero/60 hover:text-cuero' }}"
                                >#{{ $etiqueta->name }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Notas --}}
                <div>
                    <label for="notes" class="mb-1 block text-sm text-cuero/70">Notas</label>
                    <textarea
                        id="notes"
                        wire:model="notes"
                        rows="2"
                        placeholder="Lo que necesites recordar de esta tarea."
                        class="w-full rounded-sm border border-cuero/30 bg-crema px-3 py-2 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                    ></textarea>
                    @error('notes')
                        <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Subtareas (sólo al editar una tarea ya guardada) --}}
                @if ($editingId)
                    <div class="border-t border-cuero/15 pt-3">
                        <p class="mb-1 text-sm text-cuero/70">Pasos</p>
                        @if ($this->editingSubtasks->isNotEmpty())
                            <ul class="mb-2 space-y-1">
                                @foreach ($this->editingSubtasks as $sub)
                                    <li wire:key="sub-{{ $sub->id }}" class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="toggleSubtask({{ $sub->id }})"
                                            aria-pressed="{{ $sub->isCompleted() ? 'true' : 'false' }}"
                                            aria-label="{{ $sub->isCompleted() ? 'Marcar paso como pendiente' : 'Marcar paso como hecho' }}: {{ $sub->title }}"
                                            class="grid size-9 shrink-0 place-items-center focus-visible:outline-2 focus-visible:outline-vino"
                                        >
                                            <span class="grid size-5 place-items-center rounded-sm border-2 {{ $sub->isCompleted() ? 'border-vino bg-vino' : 'border-cuero/50' }}">
                                                @if ($sub->isCompleted())
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 text-crema"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                                @endif
                                            </span>
                                        </button>
                                        <span class="min-w-0 flex-1 break-words text-sm {{ $sub->isCompleted() ? 'text-cuero/50 line-through' : '' }}">{{ $sub->title }}</span>
                                        <button type="button" wire:click="deleteSubtask({{ $sub->id }})" aria-label="Eliminar paso: {{ $sub->title }}" class="grid size-9 shrink-0 place-items-center text-cuero/50 hover:text-teja">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="flex gap-2">
                            <label for="newSubtask" class="sr-only">Nuevo paso</label>
                            <input
                                id="newSubtask"
                                type="text"
                                wire:model="newSubtask"
                                wire:keydown.enter.prevent="addSubtask"
                                placeholder="Sumá un paso"
                                autocomplete="off"
                                class="min-h-11 w-full min-w-0 rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                            >
                            <button type="button" wire:click="addSubtask" class="min-h-11 shrink-0 rounded-sm border border-cuero/30 px-3 text-sm text-cuero/70 hover:text-cuero">Sumar</button>
                        </div>
                        @error('newSubtask')
                            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>
        @endif
    </form>

    {{-- Aviso de pospuestas en Lista --}}
    @if ($view === 'lista' && $this->deferredCount > 0)
        <button type="button" wire:click="toggleDeferred" aria-pressed="{{ $showDeferred ? 'true' : 'false' }}" class="flex min-h-11 items-center gap-1 text-sm text-cuero/70 hover:text-cuero">
            @if ($showDeferred)
                Ocultar {{ $this->deferredCount === 1 ? 'la pospuesta' : 'las '.$this->deferredCount.' pospuestas' }}
            @else
                Tenés {{ $this->deferredCount === 1 ? '1 tarea pospuesta' : $this->deferredCount.' tareas pospuestas' }}. Mostrarlas.
            @endif
        </button>
    @endif

    {{-- Listado --}}
    @php
        $vacio = $this->todos->isEmpty() && ($view !== 'lista' || $this->completedTodos->isEmpty());
    @endphp

    @if ($vacio)
        <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
            @if (trim($search) !== '')
                No encontré nada con eso.
            @elseif ($view === 'hoy')
                Nada para hoy. Si querés adelantar, mirá las próximas.
            @elseif ($view === 'proximas')
                No tenés nada con fecha por venir.
            @elseif ($view === 'algun_dia')
                Acá no hay nada guardado para algún día.
            @elseif ($this->activeTagId)
                No hay tareas con esa etiqueta.
            @elseif ($this->activeProject)
                Este proyecto está vacío. Anotale la primera tarea.
            @else
                Todavía no anotaste nada. Cuando quieras, empezamos.
            @endif
        </p>
    @else
        @if ($view === 'lista' && $this->todos->isEmpty() && ! $this->completedTodos->isEmpty())
            <p class="text-sm text-yerba" role="status">No te queda nada pendiente. Buen trabajo.</p>
        @endif

        @if ($this->todos->isNotEmpty())
            <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                @foreach ($this->todos as $todo)
                    @php $propia = $todo->user_id === auth()->id(); @endphp
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
                                    if ($todo->isDeferred()) {
                                        $piezas[] = ['texto' => 'pospuesta hasta el '.$todo->deferred_until->format('d/m/Y'), 'clase' => ''];
                                    }
                                    if ($todo->repeat_interval) {
                                        $piezas[] = ['texto' => 'se repite', 'clase' => ''];
                                    }
                                    if ($todo->waiting) {
                                        $piezas[] = ['texto' => $todo->waiting_for ? 'esperando a '.$todo->waiting_for : 'en espera', 'clase' => 'text-cuero'];
                                    }
                                    if ($todo->subtasks_count > 0) {
                                        $piezas[] = ['texto' => 'pasos '.$todo->subtasks_done_count.'/'.$todo->subtasks_count, 'clase' => ''];
                                    }
                                    if ($todo->project && $activeProjectId === null) {
                                        $piezas[] = ['texto' => 'en '.$todo->project->name, 'clase' => ''];
                                    }
                                    if (! $propia) {
                                        $piezas[] = ['texto' => 'de '.$todo->user->name, 'clase' => ''];
                                    }
                                @endphp
                                @if ($todo->urgent || $todo->important || $todo->waiting || $piezas !== [] || $todo->tags->isNotEmpty())
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
                                        @foreach ($todo->tags as $tag)
                                            <span wire:key="todo-{{ $todo->id }}-tag-{{ $tag->id }}" class="rounded-sm bg-cuero/10 px-1.5 py-0.5 text-xs text-cuero">#{{ $tag->name }}</span>
                                        @endforeach
                                    </p>
                                @endif
                            @endunless

                            {{-- Acciones contextuales --}}
                            @if ($propia && ! $todo->isCompleted())
                                <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                    @if ($view === 'algun_dia')
                                        <button type="button" wire:click="toActive({{ $todo->id }})" class="font-medium text-monte underline hover:no-underline">Traer a la lista</button>
                                    @elseif (! $todo->isSomeday())
                                        <button type="button" wire:click="toSomeday({{ $todo->id }})" class="text-cuero/60 underline hover:text-cuero hover:no-underline">Algún día</button>
                                    @endif
                                    @if ($todo->isDeferred())
                                        <button type="button" wire:click="undefer({{ $todo->id }})" class="text-cuero/60 underline hover:text-cuero hover:no-underline">Traer ya</button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Orden manual (sólo Lista, tareas propias, no pospuestas) --}}
                        @if ($propia && $view === 'lista' && ! $todo->isCompleted() && ! $todo->isDeferred())
                            <div class="flex shrink-0 flex-col">
                                <button type="button" wire:click="moveUp({{ $todo->id }})" aria-label="Subir: {{ $todo->title }}" class="grid h-6 w-11 place-items-center text-cuero/50 hover:text-vino focus-visible:outline-2 focus-visible:outline-vino">
                                    {{-- Heroicon: chevron-up (mini) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4"><path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 0 1-1.06-.02L10 8.832 6.29 12.77a.75.75 0 1 1-1.08-1.04l4.25-4.5a.75.75 0 0 1 1.08 0l4.25 4.5a.75.75 0 0 1-.02 1.06Z" clip-rule="evenodd" /></svg>
                                </button>
                                <button type="button" wire:click="moveDown({{ $todo->id }})" aria-label="Bajar: {{ $todo->title }}" class="grid h-6 w-11 place-items-center text-cuero/50 hover:text-vino focus-visible:outline-2 focus-visible:outline-vino">
                                    {{-- Heroicon: chevron-down (mini) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                                </button>
                            </div>
                        @endif

                        @if ($propia)
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
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($this->todos->hasPages())
                <div class="pt-2">{{ $this->todos->links() }}</div>
            @endif
        @endif

        {{-- Completadas (sólo en Lista) --}}
        @if ($view === 'lista' && $this->completedTodos->isNotEmpty())
            <div class="space-y-2 pt-2">
                <p class="text-sm font-medium text-cuero/60">Completadas</p>
                <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                    @foreach ($this->completedTodos as $todo)
                        @php $propia = $todo->user_id === auth()->id(); @endphp
                        <li wire:key="done-{{ $todo->id }}" class="flex items-start gap-2 py-1">
                            <button
                                type="button"
                                wire:click="toggle({{ $todo->id }})"
                                aria-pressed="true"
                                aria-label="Marcar como pendiente: {{ $todo->title }}"
                                class="grid size-11 shrink-0 place-items-center focus-visible:outline-2 focus-visible:outline-vino"
                            >
                                <span class="grid size-5 place-items-center rounded-sm border-2 border-vino bg-vino">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4 text-crema"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                </span>
                            </button>
                            <div class="min-w-0 flex-1 py-2">
                                <p class="break-words text-cuero/50 line-through">{{ $todo->title }}</p>
                            </div>
                            @if ($propia)
                                <button
                                    type="button"
                                    wire:click="delete({{ $todo->id }})"
                                    wire:confirm="Vas a eliminar esta tarea. Esto no se puede deshacer."
                                    aria-label="Eliminar: {{ $todo->title }}"
                                    class="grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($this->completedTodos->hasPages())
                    <div class="pt-2">{{ $this->completedTodos->links() }}</div>
                @endif

                @if ($this->myCompletedCount > 0)
                    <button
                        type="button"
                        wire:click="clearCompleted"
                        wire:confirm="Vas a eliminar {{ $this->myCompletedCount === 1 ? 'la tarea completada' : 'las '.$this->myCompletedCount.' tareas completadas' }}. Esto no se puede deshacer."
                        class="min-h-11 w-full rounded-sm border border-cuero/30 px-4 text-sm font-medium text-cuero/70 hover:border-teja hover:text-teja focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teja"
                    >
                        {{ $this->myCompletedCount === 1 ? 'Limpiar la tarea completada' : 'Limpiar las '.$this->myCompletedCount.' completadas' }}
                    </button>
                @endif
            </div>
        @endif
    @endif
</section>
