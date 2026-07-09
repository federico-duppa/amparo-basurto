<?php

use App\Livewire\Concerns\SharesWithMembers;
use App\Models\FrequentItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Compras')] class extends Component
{
    use SharesWithMembers;

    // Lista abierta (null hasta que exista alguna).
    public ?int $listId = null;

    // Sumar una cosa escribiéndola.
    public string $newItem = '';

    // Alta de una lista nueva.
    public bool $addingList = false;
    public string $newListName = '';

    // Renombrar la lista abierta (solo el dueño).
    public bool $editingList = false;
    public string $editListName = '';

    // Panel para editar el repertorio de frecuentes.
    public bool $managingFrequents = false;
    public string $newFrequent = '';

    // Compartir la lista con otra persona (solo el dueño).
    public bool $sharing = false;
    public string $shareUsername = '';

    /**
     * La primera vez que alguien entra al módulo le dejamos una lista lista
     * para usar y le sembramos los frecuentes comunes de supermercado, así no
     * arranca con la pantalla en blanco. Si ya tiene listas, no tocamos nada.
     */
    public function mount(): void
    {
        $user = auth()->user();

        if ($user->accessibleShoppingLists()->doesntExist()) {
            $list = $user->shoppingLists()->create(['name' => ShoppingList::DEFAULT_NAME]);
            $this->seedFrequents($user);
            $this->listId = $list->id;

            return;
        }

        // Nos quedamos en una lista accesible: respetamos la abierta si vale.
        if ($this->listId === null || ! $this->lists->contains('id', $this->listId)) {
            $this->listId = $this->lists->first()?->id;
        }
    }

    private function seedFrequents(User $user): void
    {
        if ($user->frequentItems()->exists()) {
            return;
        }

        $now = now();

        $user->frequentItems()->insert(
            collect(FrequentItem::DEFAULTS)
                ->map(fn (string $name) => [
                    'user_id' => $user->id,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function selectList(int $id): void
    {
        $this->listId = $id;
        $this->reset('newItem', 'editingList', 'sharing', 'managingFrequents', 'shareUsername', 'newFrequent');
        $this->resetValidation();
    }

    // --- Listas -----------------------------------------------------------

    public function createList(): void
    {
        $data = $this->validate([
            'newListName' => ['required', 'string', 'max:60'],
        ], [
            'newListName.required' => '¿Cómo le ponemos a la lista?',
        ]);

        $list = auth()->user()->shoppingLists()->create(['name' => trim($data['newListName'])]);

        $this->reset('newListName', 'addingList');
        $this->selectList($list->id);
    }

    public function startEditingList(): void
    {
        $list = $this->requireOwnedList();

        $this->editListName = $list->name;
        $this->editingList = true;
        $this->resetValidation();
    }

    public function saveList(): void
    {
        $list = $this->requireOwnedList();

        $data = $this->validate([
            'editListName' => ['required', 'string', 'max:60'],
        ], [
            'editListName.required' => 'La lista necesita un nombre.',
        ]);

        $list->update(['name' => trim($data['editListName'])]);

        $this->reset('editingList', 'editListName');
    }

    public function deleteList(): void
    {
        $this->requireOwnedList()->delete();

        $this->reset('editingList', 'sharing', 'managingFrequents');
        $this->listId = $this->lists->first()?->id;
    }

    // --- Cosas de la lista -------------------------------------------------

    public function addItem(): void
    {
        $list = $this->requireList();

        $data = $this->validate([
            'newItem' => ['required', 'string', 'max:80'],
        ], [
            'newItem.required' => '¿Qué anotamos?',
        ]);

        $name = trim($data['newItem']);

        // Todo lo anotado queda también en el repertorio de frecuentes.
        $this->rememberFrequent($name);

        if ($this->putOnList($list, $name)) {
            $this->bumpFrequent($name);
        }

        $this->reset('newItem');
    }

    /**
     * Sacar una cosa de la lista: el gesto de "ya la tengo". Un toque y afuera,
     * sin confirmación — tiene que ser rápido. Comprarla pesa: el frecuente que
     * coincida gana ponderación para aparecer antes.
     */
    public function removeItem(int $id): void
    {
        $item = $this->requireList()->items()->findOrFail($id);
        $item->delete();

        $this->bumpFrequent($item->name);
    }

    // --- Frecuentes --------------------------------------------------------

    /**
     * Un toque en un frecuente lo suma a la lista; si ya estaba, lo saca. Así
     * el mismo botón sirve para poner y para arrepentirse.
     */
    public function toggleFrequent(int $frequentId): void
    {
        $frequent = auth()->user()->frequentItems()->findOrFail($frequentId);
        $list = $this->requireList();

        $matching = $list->items()->get()
            ->filter(fn ($item) => $this->sameName($item->name, $frequent->name));

        if ($matching->isNotEmpty()) {
            $list->items()->whereKey($matching->modelKeys())->delete();

            // Arrepentirse devuelve el peso que había sumado el toque de ida.
            $this->bumpFrequent($frequent->name, -1);

            return;
        }

        if ($this->putOnList($list, $frequent->name)) {
            $this->bumpFrequent($frequent->name);
        }
    }

    public function addFrequent(): void
    {
        $data = $this->validate([
            'newFrequent' => ['required', 'string', 'max:80'],
        ], [
            'newFrequent.required' => '¿Qué frecuente sumamos?',
        ]);

        $this->rememberFrequent(trim($data['newFrequent']));

        $this->reset('newFrequent');
    }

    public function removeFrequent(int $id): void
    {
        auth()->user()->frequentItems()->findOrFail($id)->delete();
    }

    // --- Compartir ---------------------------------------------------------

    protected function shareableOwned(): ShoppingList
    {
        return $this->requireOwnedList();
    }

    protected function shareableNoun(): array
    {
        return ['noun' => 'lista', 'genero' => 'f'];
    }

    // --- Helpers -----------------------------------------------------------

    /**
     * Suma una cosa a la lista, salvo que ya esté anotada (mismo nombre).
     * Devuelve si efectivamente la sumó.
     */
    private function putOnList(ShoppingList $list, string $name): bool
    {
        $alreadyThere = $list->items()->get()
            ->contains(fn ($item) => $this->sameName($item->name, $name));

        if ($alreadyThere) {
            return false;
        }

        $item = $list->items()->make(['name' => $name]);
        $item->user_id = auth()->id();
        $item->save();

        return true;
    }

    /**
     * Ajusta la ponderación del frecuente que coincida con ese nombre: anotar
     * y comprar suman, arrepentirse resta. El peso ordena los frecuentes para
     * que lo más comprado quede primero.
     */
    private function bumpFrequent(string $name, int $delta = 1): void
    {
        $frequent = auth()->user()->frequentItems()->get()
            ->first(fn ($frequent) => $this->sameName($frequent->name, $name));

        $frequent?->update(['weight' => max(0, $frequent->weight + $delta)]);
    }

    /** Guarda un frecuente para el usuario, salvo que ya lo tenga. */
    private function rememberFrequent(string $name): void
    {
        $user = auth()->user();

        $alreadyThere = $user->frequentItems()->get()
            ->contains(fn ($frequent) => $this->sameName($frequent->name, $name));

        if ($alreadyThere) {
            return;
        }

        $user->frequentItems()->create(['name' => $name]);
    }

    private function sameName(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    /**
     * Lista a la que el usuario tiene acceso (propia o compartida). Cualquiera
     * con acceso anota y saca cosas.
     */
    private function requireList(): ShoppingList
    {
        return auth()->user()->accessibleShoppingLists()->findOrFail($this->listId);
    }

    /**
     * Lista de la que el usuario es dueño. Renombrar, eliminar y compartir son
     * cosa del dueño.
     */
    private function requireOwnedList(): ShoppingList
    {
        return auth()->user()->shoppingLists()->findOrFail($this->listId);
    }

    #[Computed]
    public function lists(): Collection
    {
        return auth()->user()->accessibleShoppingLists()->with('user')->orderBy('name')->get();
    }

    #[Computed]
    public function list(): ?ShoppingList
    {
        return $this->lists->firstWhere('id', $this->listId) ?? $this->lists->first();
    }

    #[Computed]
    public function isOwner(): bool
    {
        return $this->list !== null && $this->list->user_id === auth()->id();
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->list?->members()->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function isShared(): bool
    {
        return $this->members->isNotEmpty();
    }

    #[Computed]
    public function items(): Collection
    {
        return $this->list?->items()->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function frequents(): Collection
    {
        return auth()->user()->frequentItems()->orderByDesc('weight')->orderBy('name')->get();
    }

    /**
     * Nombres (en minúscula) de lo que ya está en la lista, para prender los
     * chips de frecuentes que correspondan.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function itemNames(): array
    {
        return $this->items->map(fn ($item) => mb_strtolower($item->name))->all();
    }
};
?>

<div class="space-y-5" wire:key="compras-{{ $this->list?->id ?? 'vacio' }}">
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <h1 class="font-brand text-2xl font-bold">Compras</h1>
            <p class="text-sm text-cuero/60">Anotá lo que falta y, cuando ya lo tenés, tocalo para sacarlo.</p>
        </div>
    </div>

    {{-- Selector de listas --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($this->lists as $l)
            <button
                type="button"
                wire:key="tab-{{ $l->id }}"
                wire:click="selectList({{ $l->id }})"
                @class([
                    'min-h-11 rounded-sm border px-3 text-sm focus-visible:outline-2 focus-visible:outline-cobre',
                    'border-cobre bg-cobre/10 font-medium text-cobre' => $this->list?->id === $l->id,
                    'border-cuero/30 text-cuero/80 hover:text-cuero' => $this->list?->id !== $l->id,
                ])
            >
                {{ $l->name }}
                @unless ($l->user_id === auth()->id())
                    <span class="text-cuero/50">· compartida</span>
                @endunless
            </button>
        @endforeach

        @unless ($this->addingList)
            <button
                type="button"
                wire:click="$set('addingList', true)"
                aria-label="Nueva lista"
                class="grid size-11 place-items-center rounded-sm border border-cuero/30 text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-cobre"
            >
                {{-- Heroicon: plus (mini) --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                    <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                </svg>
            </button>
        @endunless
    </div>

    @if ($this->addingList)
        <form wire:submit="createList" class="flex flex-wrap items-end gap-2 rounded-sm border border-cuero/20 p-3">
            <div class="min-w-0 flex-1">
                <label for="newListName" class="mb-1 block text-sm font-medium">Nombre de la lista</label>
                <input id="newListName" type="text" wire:model="newListName" autocomplete="off"
                    placeholder="Súper, farmacia, ferretería…"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-cobre focus:outline-none focus:ring-2 focus:ring-cobre/40">
                @error('newListName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit"
                class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                Crear
            </button>
            <button type="button" wire:click="$set('addingList', false)"
                class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
        </form>
    @endif

    @if ($this->list === null)
        <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
            No te queda ninguna lista. Cuando quieras, armamos una nueva.
        </p>
    @else
        {{-- Encabezado de la lista abierta + acciones del dueño --}}
        <div class="border-t border-cuero/15 pt-4">
            @if ($this->editingList)
                <form wire:submit="saveList" class="flex flex-wrap items-end gap-2">
                    <div class="min-w-0 flex-1">
                        <label for="editListName" class="mb-1 block text-sm font-medium">Nombre de la lista</label>
                        <input id="editListName" type="text" wire:model="editListName" autocomplete="off"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-cobre focus:outline-none focus:ring-2 focus:ring-cobre/40">
                        @error('editListName') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit"
                        class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                        Guardar
                    </button>
                    <button type="button" wire:click="$set('editingList', false)"
                        class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero">Cancelar</button>
                </form>
            @else
                <div class="flex items-center gap-2">
                    <h2 class="min-w-0 flex-1 truncate font-brand text-lg font-bold">{{ $this->list->name }}</h2>

                    @if ($this->isOwner)
                        <button type="button" wire:click="startEditingList"
                            aria-label="Renombrar la lista"
                            class="grid size-9 place-items-center text-cuero/50 hover:text-cobre focus-visible:outline-2 focus-visible:outline-cobre">
                            {{-- Heroicon: pencil (mini) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                            </svg>
                        </button>
                        <button type="button" wire:click="deleteList"
                            wire:confirm="Vas a eliminar la lista {{ $this->list->name }} con todo lo anotado. Esto no se puede deshacer."
                            aria-label="Eliminar la lista"
                            class="grid size-9 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                            {{-- Heroicon: trash (mini) --}}
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @else
                        <span class="shrink-0 text-xs text-cuero/50">de {{ '@'.$this->list->user->username }}</span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Sumar una cosa escribiéndola --}}
        <form wire:submit="addItem" class="space-y-2">
            <div class="flex items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="newItem" class="sr-only">Anotar algo</label>
                    <input id="newItem" type="text" wire:model="newItem" autocomplete="off"
                        placeholder="Anotá algo…"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-cobre focus:outline-none focus:ring-2 focus:ring-cobre/40">
                </div>
                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 shrink-0 rounded-sm bg-cobre px-4 font-medium text-crema hover:bg-cobre/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobre disabled:opacity-60">
                    Anotar
                </button>
            </div>
            @error('newItem') <p class="text-sm text-teja" role="alert">{{ $message }}</p> @enderror
        </form>

        {{-- La lista --}}
        @if ($this->items->isEmpty())
            <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
                Por ahora no falta nada. Sumá algo escribiéndolo o tocá un frecuente de abajo.
            </p>
        @else
            <ul class="divide-y divide-cuero/10 rounded-sm border border-cuero/20">
                @foreach ($this->items as $item)
                    <li wire:key="item-{{ $item->id }}">
                        <button
                            type="button"
                            wire:click="removeItem({{ $item->id }})"
                            aria-label="Ya tengo {{ $item->name }}, sacar de la lista"
                            class="group flex min-h-12 w-full items-center gap-3 px-3 text-left hover:bg-cobre/5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-cobre"
                        >
                            <span class="grid size-6 shrink-0 place-items-center rounded-full border border-cuero/40 text-transparent group-hover:border-cobre group-hover:text-cobre" aria-hidden="true">
                                {{-- Heroicon: check (mini) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1 truncate group-hover:text-cuero/50 group-hover:line-through">{{ $item->name }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Frecuentes: un toque para sumarlos --}}
        <div class="space-y-2">
            <div class="flex items-center gap-2">
                <h2 class="min-w-0 flex-1 font-brand text-lg font-bold">Frecuentes</h2>
                <button type="button" wire:click="$toggle('managingFrequents')"
                    class="min-h-9 shrink-0 rounded-sm px-2 text-sm text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-cobre">
                    {{ $this->managingFrequents ? 'Listo' : 'Editar' }}
                </button>
            </div>

            @if ($this->frequents->isEmpty())
                <p class="text-sm text-cuero/60">Todavía no tenés frecuentes. Lo que anotes en la lista queda guardado acá.</p>
            @else
                <ul class="flex flex-wrap gap-2">
                    @foreach ($this->frequents as $frequent)
                        @php $enLista = in_array(mb_strtolower($frequent->name), $this->itemNames, true); @endphp
                        <li wire:key="freq-{{ $frequent->id }}" class="flex items-stretch">
                            <button
                                type="button"
                                wire:click="toggleFrequent({{ $frequent->id }})"
                                aria-pressed="{{ $enLista ? 'true' : 'false' }}"
                                @class([
                                    'flex min-h-11 items-center gap-1.5 rounded-sm border px-3 text-sm focus-visible:outline-2 focus-visible:outline-cobre',
                                    'rounded-r-none' => $this->managingFrequents,
                                    'border-cobre bg-cobre/10 font-medium text-cobre' => $enLista,
                                    'border-cuero/30 text-cuero/80 hover:text-cuero' => ! $enLista,
                                ])
                            >
                                @if ($enLista)
                                    {{-- Heroicon: check (mini) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                                {{ $frequent->name }}
                            </button>
                            @if ($this->managingFrequents)
                                <button
                                    type="button"
                                    wire:click="removeFrequent({{ $frequent->id }})"
                                    aria-label="Olvidar {{ $frequent->name }} de los frecuentes"
                                    class="grid min-h-11 w-9 place-items-center rounded-sm rounded-l-none border border-l-0 border-cuero/30 text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja"
                                >
                                    {{-- Heroicon: x-mark (mini) --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                                    </svg>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($this->managingFrequents)
                <form wire:submit="addFrequent" class="flex flex-wrap items-end gap-2 pt-1">
                    <div class="min-w-0 flex-1">
                        <label for="newFrequent" class="mb-1 block text-sm font-medium">Sumar un frecuente</label>
                        <input id="newFrequent" type="text" wire:model="newFrequent" autocomplete="off"
                            placeholder="Algo que compres seguido…"
                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-cobre focus:outline-none focus:ring-2 focus:ring-cobre/40">
                        @error('newFrequent') <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit"
                        class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                        Guardar
                    </button>
                </form>
            @endif
        </div>

        {{-- Compartir (solo el dueño) --}}
        @if ($this->isOwner)
            <div class="space-y-3 border-t border-cuero/15 pt-4">
                <button type="button" wire:click="$toggle('sharing')"
                    class="flex min-h-9 items-center gap-1.5 text-sm text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-cobre">
                    {{-- Heroicon: user-plus (mini) --}}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                        <path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003ZM19.75 7.5a.75.75 0 0 0-1.5 0v1.75h-1.75a.75.75 0 0 0 0 1.5h1.75v1.75a.75.75 0 0 0 1.5 0v-1.75h1.75a.75.75 0 0 0 0-1.5h-1.75V7.5Z" />
                    </svg>
                    {{ $this->isShared ? 'Compartida con '.$this->members->count().' '.($this->members->count() === 1 ? 'persona' : 'personas') : 'Compartir la lista' }}
                </button>

                @if ($this->sharing)
                    <div class="space-y-3 rounded-sm border border-cuero/20 p-3">
                        @if ($this->members->isNotEmpty())
                            <ul class="space-y-2">
                                @foreach ($this->members as $member)
                                    <li wire:key="member-{{ $member->id }}" class="flex items-center gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium">{{ $member->name }}</p>
                                            <p class="text-xs text-cuero/50">{{ '@'.$member->username }}</p>
                                        </div>
                                        <button type="button" wire:click="unshare({{ $member->id }})"
                                            aria-label="Dejar de compartir con {{ $member->name }}"
                                            class="grid size-9 place-items-center text-cuero/50 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja">
                                            {{-- Heroicon: x-mark (mini) --}}
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
                                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                                            </svg>
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
                                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-cobre focus:outline-none focus:ring-2 focus:ring-cobre/40">
                            </div>
                            <button type="submit"
                                class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte">
                                Compartir
                            </button>
                            @error('shareUsername') <p class="w-full text-sm text-teja" role="alert">{{ $message }}</p> @enderror
                        </form>
                        <p class="text-xs text-cuero/50">La otra persona va a poder anotar y sacar cosas. Renombrarla, eliminarla y compartirla quedan de tu lado.</p>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
