<?php

use App\Models\ShoppingList;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Compartir')] class extends Component
{
    // Lo que manda Android al compartir (Web Share Target, método GET).
    // WhatsApp pone el mensaje en `text`; otras apps usan también `title` y `url`.
    #[Url]
    public string $title = '';

    #[Url]
    public string $text = '';

    #[Url]
    public string $url = '';

    // El texto compartido, editable antes de decidir qué hacer con él.
    public string $draft = '';

    // Llegó sin nada para guardar (se abrió la pantalla a mano).
    public bool $cameEmpty = false;

    // Qué se guardó: '' (nada todavía) | 'tarea' | 'compra'.
    public string $saved = '';

    // Nombre de la lista donde cayó la compra, para contarlo en la confirmación.
    public string $savedListName = '';

    public function mount(): void
    {
        $pieces = array_values(array_unique(array_filter(array_map(
            'trim',
            [$this->title, $this->text, $this->url],
        ))));

        // WhatsApp y otras apps suelen repetir el título o el link dentro del
        // texto; si una parte ya vive dentro de otra, no la duplicamos.
        $pieces = array_filter($pieces, function (string $piece) use ($pieces): bool {
            foreach ($pieces as $other) {
                if ($other !== $piece && str_contains($other, $piece)) {
                    return false;
                }
            }

            return true;
        });

        $this->draft = implode("\n\n", $pieces);
        $this->cameEmpty = $this->draft === '';
    }

    public function saveTask(): void
    {
        $draft = $this->requireDraft();

        if ($draft === null) {
            return;
        }

        [$title, $notes] = $this->splitForTask($draft);

        auth()->user()->todos()->create([
            'title' => $title,
            'notes' => $notes,
        ]);

        $this->saved = 'tarea';
    }

    public function saveShoppingItem(): void
    {
        $draft = $this->requireDraft();

        if ($draft === null) {
            return;
        }

        $user = auth()->user();

        $list = $user->accessibleShoppingLists()->orderBy('name')->first()
            ?? $user->shoppingLists()->create(['name' => ShoppingList::DEFAULT_NAME]);

        // A la lista va una cosa corta: la primera línea, recortada si hace falta.
        $name = Str::limit(trim(preg_split('/\R/', $draft)[0]), 79, '…');

        $existing = $list->items()->get()
            ->first(fn ($item) => Str::lower(trim($item->name)) === Str::lower($name));

        if ($existing === null) {
            $item = $list->items()->make(['name' => $name]);
            $item->user_id = $user->id;
            $item->save();
        } elseif ($existing->isPurchased()) {
            // Estaba tachada en la lista: volver a mandarla la destacha.
            $existing->update(['purchased_at' => null]);
        }

        $this->savedListName = $list->name;
        $this->saved = 'compra';
    }

    /** El texto a guardar, o null (con error avisado) si no hay nada. */
    private function requireDraft(): ?string
    {
        $draft = trim($this->draft);

        if ($draft === '') {
            $this->addError('draft', 'No hay nada para guardar. Escribí algo o descartalo.');

            return null;
        }

        if (mb_strlen($draft) > 2000) {
            $this->addError('draft', 'Eso es muy largo. Guardá lo esencial.');

            return null;
        }

        return $draft;
    }

    /**
     * Cómo se vuelve tarea un texto compartido: la primera línea es el título
     * y, si había más que eso, el texto completo queda en las notas.
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitForTask(string $draft): array
    {
        $firstLine = trim(preg_split('/\R/', $draft)[0]);
        $title = Str::limit($firstLine, 254, '…');

        return [$title, $draft === $title ? null : $draft];
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-3">
        <span class="h-8 w-1.5 rounded-sm bg-cuero" aria-hidden="true"></span>
        <h1 class="font-brand text-2xl font-bold">Me compartieron algo</h1>
    </div>

    @if ($saved === 'tarea')
        <div class="space-y-3 rounded-sm border border-yerba/40 bg-yerba/10 px-4 py-4" role="status">
            <p class="font-medium text-yerba">Listo, quedó guardado.</p>
            <p class="text-sm">Lo anoté en tus tareas. Después le podés poner fecha, proyecto o lo que necesite.</p>
        </div>
        <a
            href="{{ route('todos') }}"
            wire:navigate
            class="inline-flex min-h-11 items-center rounded-sm bg-vino px-4 font-medium text-crema hover:bg-vino/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vino"
        >
            Ver mis tareas
        </a>
    @elseif ($saved === 'compra')
        <div class="space-y-3 rounded-sm border border-yerba/40 bg-yerba/10 px-4 py-4" role="status">
            <p class="font-medium text-yerba">Listo, quedó guardado.</p>
            <p class="text-sm">Lo sumé a la lista «{{ $savedListName }}».</p>
        </div>
        <a
            href="{{ route('compras') }}"
            wire:navigate
            class="inline-flex min-h-11 items-center rounded-sm bg-cobre px-4 font-medium text-crema hover:bg-cobre/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobre"
        >
            Ver la lista
        </a>
    @elseif ($cameEmpty)
        <p class="text-cuero/70">
            No me llegó nada esta vez. Cuando compartas un mensaje o un link desde otra aplicación
            y me elijas en el menú de compartir, va a aparecer acá para que decidamos qué hacer.
        </p>
        <a
            href="{{ route('todos') }}"
            wire:navigate
            class="inline-flex min-h-11 items-center rounded-sm border border-cuero/30 px-4 font-medium text-cuero hover:bg-cuero/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero"
        >
            Ir a mis tareas
        </a>
    @else
        <p>Esto es lo que me llegó. ¿Qué hago con eso?</p>

        <div class="space-y-1.5">
            <label for="draft" class="text-sm font-medium text-cuero/70">Lo que me llegó (podés retocarlo)</label>
            <textarea
                id="draft"
                wire:model="draft"
                rows="6"
                class="w-full rounded-sm border border-cuero/30 bg-crema px-3 py-2 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte"
            ></textarea>
            @error('draft')
                <p class="text-sm text-teja" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <button
                type="button"
                wire:click="saveTask"
                wire:loading.attr="disabled"
                class="min-h-11 rounded-sm bg-vino px-4 font-medium text-crema hover:bg-vino/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vino disabled:opacity-60"
            >
                Anotarlo como tarea
            </button>
            <button
                type="button"
                wire:click="saveShoppingItem"
                wire:loading.attr="disabled"
                class="min-h-11 rounded-sm border border-cobre px-4 font-medium text-cobre hover:bg-cobre/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cobre disabled:opacity-60"
            >
                Sumarlo a las compras
            </button>
            <a
                href="{{ route('todos') }}"
                wire:navigate
                class="inline-flex min-h-11 items-center justify-center rounded-sm border border-cuero/30 px-4 font-medium text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cuero"
            >
                Mejor nada
            </a>
        </div>
    @endif
</div>
