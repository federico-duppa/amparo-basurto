<?php

use App\Models\Envelope;
use App\Models\EnvelopeMovement;
use App\Models\Expense;
use App\Models\InflationRate;
use App\Support\MarketData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sobre')] class extends Component
{
    private const TIMELINE_PAGE = 20;

    public int $envelopeId;

    public int $timelineLimit = self::TIMELINE_PAGE;

    public string $movementAmount = '';

    public string $movementDate = '';

    public string $movementNote = '';

    public string $transferAmount = '';

    public string $transferTo = '';

    // Edición de un movimiento suelto (aporte o retiro; las transferencias no se editan).
    public ?int $editingMovementId = null;

    public string $editMovementAmount = '';

    public string $editMovementDate = '';

    public string $editMovementNote = '';

    // Edición del objetivo del sobre.
    public bool $editingTarget = false;

    public string $targetAmountInput = '';

    public function mount(int $envelope): void
    {
        $this->envelopeId = $envelope;
        $this->requireEnvelope();
        $this->movementDate = now()->format('Y-m-d');
    }

    /**
     * Sobre propio. Lo ajeno responde 404: ni siquiera confirma que existe.
     */
    private function requireEnvelope(): Envelope
    {
        return auth()->user()->envelopes()->findOrFail($this->envelopeId);
    }

    private function validateMovement(): void
    {
        $this->validate([
            'movementAmount' => ['required', 'numeric', 'gt:0'],
            'movementDate' => ['required', 'date', 'before_or_equal:today'],
            'movementNote' => ['nullable', 'string', 'max:255'],
        ], [
            'movementAmount.required' => 'Me falta el monto.',
            'movementAmount.numeric' => 'El monto tiene que ser un número.',
            'movementAmount.gt' => 'El monto tiene que ser mayor a cero.',
            'movementDate.required' => 'Me falta la fecha.',
            'movementDate.date' => 'Esa fecha no me cierra.',
            'movementDate.before_or_equal' => 'Los movimientos se anotan cuando ya pasaron.',
        ]);
    }

    public function aporte(): void
    {
        $envelope = $this->requireEnvelope();

        $this->validateMovement();

        $this->saveMovement($envelope, EnvelopeMovement::APORTE);
    }

    public function retiro(): void
    {
        $envelope = $this->requireEnvelope();

        $this->validateMovement();

        if ((float) $this->movementAmount > $envelope->balance()) {
            $this->addError('movementAmount', 'En este sobre hay '.$this->plata($envelope->balance(), $envelope->currency).'; no llego a sacar '.$this->plata($this->movementAmount, $envelope->currency).'.');

            return;
        }

        $this->saveMovement($envelope, EnvelopeMovement::RETIRO);
    }

    private function saveMovement(Envelope $envelope, string $type): void
    {
        $movement = $envelope->movements()->make([
            'type' => $type,
            'amount' => $this->movementAmount,
            'currency' => $envelope->currency,
            'moved_on' => $this->movementDate,
            'note' => trim($this->movementNote) === '' ? null : trim($this->movementNote),
        ]);
        $movement->user_id = auth()->id();
        $movement->save();

        $this->reset('movementAmount', 'movementNote');
    }

    public function transfer(): void
    {
        $source = $this->requireEnvelope();

        $this->validate([
            'transferAmount' => ['required', 'numeric', 'gt:0'],
            'transferTo' => ['required', 'integer'],
        ], [
            'transferAmount.required' => 'Me falta el monto a pasar.',
            'transferAmount.numeric' => 'El monto tiene que ser un número.',
            'transferAmount.gt' => 'El monto tiene que ser mayor a cero.',
            'transferTo.required' => 'Decime a qué sobre lo paso.',
        ]);

        $destination = auth()->user()->envelopes()
            ->whereKeyNot($source->id)
            ->findOrFail((int) $this->transferTo);

        $amount = (float) $this->transferAmount;

        if ($amount > $source->balance()) {
            $this->addError('transferAmount', 'En este sobre hay '.$this->plata($source->balance(), $source->currency).'; no llego a pasar '.$this->plata($amount, $source->currency).'.');

            return;
        }

        $converted = $amount;
        $rate = null;

        if ($source->currency !== $destination->currency) {
            // La transferencia reusa la maquinaria de conversión: cotización del día.
            $rate = MarketData::rate('blue', now());

            if ($rate === null) {
                $this->addError('transferAmount', 'No tengo cotización para convertir entre monedas ahora. Probá de nuevo en un rato.');

                return;
            }

            $converted = round($source->currency === 'ARS' ? $amount / $rate : $amount * $rate, 2);
        }

        // Si el origen era indexado, acá la plata sale del mundo indexado y se
        // congela en su valor nominal: el destino no arrastra indexación.
        $group = (string) Str::uuid();
        $today = now()->toDateString();

        DB::transaction(function () use ($source, $destination, $amount, $converted, $rate, $group, $today) {
            $out = $source->movements()->make([
                'type' => EnvelopeMovement::TRANSFER_OUT,
                'amount' => $amount,
                'currency' => $source->currency,
                'moved_on' => $today,
                'note' => 'Hacia '.$destination->name,
                'transfer_group' => $group,
            ]);
            $out->user_id = auth()->id();
            $out->save();

            $in = $destination->movements()->make([
                'type' => EnvelopeMovement::TRANSFER_IN,
                'amount' => $converted,
                'currency' => $destination->currency,
                'moved_on' => $today,
                'note' => 'Desde '.$source->name,
                'transfer_group' => $group,
                'exchange_rate' => $rate,
            ]);
            $in->user_id = auth()->id();
            $in->save();
        });

        $this->reset('transferAmount', 'transferTo');
    }

    public function startEditingMovement(int $id): void
    {
        $movement = $this->requireEnvelope()->movements()->findOrFail($id);

        // Las transferencias no se editan (son un par vinculado con conversión):
        // se eliminan enteras y se rehacen.
        if ($movement->transfer_group !== null) {
            return;
        }

        $this->editingMovementId = $movement->id;
        $this->editMovementAmount = rtrim(rtrim((string) $movement->amount, '0'), '.');
        $this->editMovementDate = $movement->moved_on->format('Y-m-d');
        $this->editMovementNote = (string) $movement->note;
        $this->resetValidation();
    }

    public function updateMovement(): void
    {
        $envelope = $this->requireEnvelope();
        $movement = $envelope->movements()->findOrFail($this->editingMovementId);

        if ($movement->transfer_group !== null) {
            return;
        }

        $this->validate([
            'editMovementAmount' => ['required', 'numeric', 'gt:0'],
            'editMovementDate' => ['required', 'date', 'before_or_equal:today'],
            'editMovementNote' => ['nullable', 'string', 'max:255'],
        ], [
            'editMovementAmount.required' => 'Me falta el monto.',
            'editMovementAmount.numeric' => 'El monto tiene que ser un número.',
            'editMovementAmount.gt' => 'El monto tiene que ser mayor a cero.',
            'editMovementDate.required' => 'Me falta la fecha.',
            'editMovementDate.date' => 'Esa fecha no me cierra.',
            'editMovementDate.before_or_equal' => 'Los movimientos se anotan cuando ya pasaron.',
        ]);

        // El saldo nunca puede quedar negativo por un movimiento (solo los gastos
        // pueden dejarlo en rojo). Calculo el saldo sin este movimiento y verifico
        // que el nuevo monto no lo perfore.
        $current = (float) $movement->amount;
        $sign = $movement->isEntrada() ? 1 : -1;
        $balanceWithout = $envelope->balance() - $sign * $current;
        $nuevo = (float) $this->editMovementAmount;

        if ($balanceWithout + $sign * $nuevo < 0) {
            if ($movement->isEntrada()) {
                $this->addError('editMovementAmount', 'Con ese aporte el sobre queda en rojo. Tenés que aportar al menos '.$this->plata(-$balanceWithout, $envelope->currency).'.');
            } else {
                $this->addError('editMovementAmount', 'Sin este retiro en el sobre hay '.$this->plata($balanceWithout, $envelope->currency).'; no podés sacar más que eso.');
            }

            return;
        }

        $movement->update([
            'amount' => $this->editMovementAmount,
            'moved_on' => $this->editMovementDate,
            'note' => trim($this->editMovementNote) === '' ? null : trim($this->editMovementNote),
        ]);

        $this->cancelEditMovement();
    }

    public function cancelEditMovement(): void
    {
        $this->reset('editingMovementId', 'editMovementAmount', 'editMovementDate', 'editMovementNote');
        $this->resetValidation();
    }

    public function deleteMovement(int $id): void
    {
        $movement = $this->requireEnvelope()->movements()->findOrFail($id);

        if ($movement->transfer_group !== null) {
            // Una transferencia se borra entera: las dos patas, en ambos sobres.
            EnvelopeMovement::where('transfer_group', $movement->transfer_group)
                ->where('user_id', auth()->id())
                ->delete();
        } else {
            $movement->delete();
        }

        if ($this->editingMovementId === $id) {
            $this->cancelEditMovement();
        }
    }

    public function startEditingTarget(): void
    {
        $envelope = $this->requireEnvelope();

        $this->targetAmountInput = $envelope->target_amount === null
            ? ''
            : rtrim(rtrim((string) $envelope->target_amount, '0'), '.');
        $this->editingTarget = true;
        $this->resetValidation();
    }

    public function updateTarget(): void
    {
        $envelope = $this->requireEnvelope();

        $this->validate([
            'targetAmountInput' => ['nullable', 'numeric', 'gt:0'],
        ], [
            'targetAmountInput.numeric' => 'El objetivo tiene que ser un número.',
            'targetAmountInput.gt' => 'El objetivo tiene que ser mayor a cero.',
        ]);

        // En un sobre indexado el objetivo es obligatorio: sin vara no hay poder
        // de compra que cuidar, así que no se puede dejar en blanco.
        if ($envelope->indexed && trim($this->targetAmountInput) === '') {
            $this->addError('targetAmountInput', 'Este sobre cuida el poder de compra: necesito un objetivo, decime cuánto en pesos de hoy.');

            return;
        }

        $nuevo = trim($this->targetAmountInput) === '' ? null : $this->targetAmountInput;

        $envelope->update([
            'target_amount' => $nuevo,
            // Al reescribir el objetivo de un sobre indexado, el monto es "en pesos
            // de hoy": re-anclamos el mes base a este mes para que la vara vuelva a
            // arrancar desde acá. Sin objetivo (solo posible en nominales) no hay mes base.
            'target_month' => $envelope->indexed && $nuevo !== null
                ? now()->startOfMonth()->toDateString()
                : ($nuevo === null ? null : $envelope->target_month),
        ]);

        unset($this->envelope);
        $this->cancelEditingTarget();
    }

    public function cancelEditingTarget(): void
    {
        $this->reset('editingTarget', 'targetAmountInput');
        $this->resetValidation();
    }

    public function deleteEnvelope(): void
    {
        $this->requireEnvelope()->delete();

        $this->redirect(route('plata.sobres'), navigate: true);
    }

    #[Computed]
    public function envelope(): Envelope
    {
        return $this->requireEnvelope();
    }

    #[Computed]
    public function otherEnvelopes(): Collection
    {
        return auth()->user()->envelopes()
            ->whereKeyNot($this->envelopeId)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function hasInflationData(): bool
    {
        return InflationRate::query()->exists();
    }

    /**
     * La historia del sobre: movimientos y gastos imputados, intercalados.
     * Se muestra de a TIMELINE_PAGE entradas; "Ver más" agranda la ventana.
     */
    #[Computed]
    public function timeline(): Collection
    {
        return $this->timelineEntries->take($this->timelineLimit)->values();
    }

    #[Computed]
    public function hasMoreTimeline(): bool
    {
        return $this->timelineEntries->count() > $this->timelineLimit;
    }

    public function showMoreTimeline(): void
    {
        $this->timelineLimit += self::TIMELINE_PAGE;
    }

    /**
     * Las dos fuentes de la historia, ya ordenadas y acotadas en SQL: con
     * limit+1 de cada una alcanza para armar la página y saber si hay más,
     * sin cargar toda la historia para mergear en PHP.
     */
    #[Computed]
    public function timelineEntries(): Collection
    {
        $take = $this->timelineLimit + 1;

        $labels = [
            EnvelopeMovement::APORTE => 'Aporte',
            EnvelopeMovement::RETIRO => 'Retiro',
            EnvelopeMovement::TRANSFER_IN => 'Transferencia recibida',
            EnvelopeMovement::TRANSFER_OUT => 'Transferencia enviada',
        ];

        $movimientos = $this->envelope->movements()
            ->orderByDesc('moved_on')->orderByDesc('id')->limit($take)->get()
            ->map(fn (EnvelopeMovement $movement) => [
                'key' => 'mov-'.$movement->id,
                'id' => $movement->id,
                'kind' => 'movimiento',
                'date' => $movement->moved_on,
                'label' => $labels[$movement->type],
                'note' => $movement->note,
                'amount' => (float) $movement->amount,
                'entrada' => $movement->isEntrada(),
                // Las transferencias no se editan sueltas: son un par vinculado.
                'editable' => $movement->transfer_group === null,
            ]);

        $gastos = $this->envelope->expenses()
            ->orderByDesc('spent_on')->orderByDesc('id')->limit($take)->get()
            ->map(fn (Expense $expense) => [
                'key' => 'gasto-'.$expense->id,
                'id' => $expense->id,
                'kind' => 'gasto',
                'date' => $expense->spent_on,
                'label' => $expense->description,
                'note' => $expense->category,
                'amount' => (float) $expense->amount,
                'entrada' => false,
                'editable' => false,
            ]);

        return $movimientos->concat($gastos)
            ->sortByDesc(fn (array $row) => sprintf('%s|%010d', $row['date']->toDateString(), $row['id']))
            ->values();
    }

    public function plata(int|float|string|null $value, string $currency = 'ARS'): string
    {
        return ($currency === 'ARS' ? '$' : 'US$').number_format((float) $value, 2, ',', '.');
    }
};
?>

<section class="space-y-6">
    <header class="flex items-center gap-3">
        <span class="h-8 w-1.5 rounded-sm bg-oliva" aria-hidden="true"></span>
        <h1 class="font-brand text-3xl font-bold">Plata</h1>
    </header>

    <nav aria-label="Secciones de Plata" class="flex border-b border-cuero/20">
        <a
            href="{{ route('plata.gastos') }}"
            wire:navigate
            class="-mb-px flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm text-cuero/70 hover:text-cuero"
        >Gastos</a>
        <a
            href="{{ route('plata.sobres') }}"
            wire:navigate
            aria-current="page"
            class="-mb-px flex min-h-11 items-center border-b-2 border-oliva px-3 text-sm font-semibold text-oliva"
        >Sobres</a>
        <a
            href="{{ route('plata.reportes') }}"
            wire:navigate
            class="-mb-px flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm text-cuero/70 hover:text-cuero"
        >Reportes</a>
    </nav>

    <a href="{{ route('plata.sobres') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-cuero/70 hover:text-cuero">
        {{-- Heroicon: chevron-left (mini) --}}
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
            <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
        </svg>
        Todos los sobres
    </a>

    <div class="space-y-2 rounded-sm border border-cuero/20 p-4">
        <h2 class="flex flex-wrap items-center gap-2 font-brand text-2xl font-semibold">
            <span class="break-words">{{ $this->envelope->name }}</span>
            <span class="rounded-sm bg-oliva/10 px-1.5 py-0.5 text-xs font-medium text-oliva">
                {{ $this->envelope->isAhorro() ? 'Ahorro' : 'Gasto previsto' }}{{ $this->envelope->indexed ? ' · indexado' : '' }}
            </span>
        </h2>

        @php
            // Saldo y objetivo se resuelven una sola vez por render: balance() y
            // currentTarget() consultan la base, y progress()/gap() los vuelven
            // a llamar internamente. Derivamos progreso y faltante acá con la
            // misma lógica de los métodos del modelo para no repetir queries.
            $saldo = $this->envelope->balance();
            $objetivo = $this->envelope->currentTarget();
            $progreso = ($objetivo !== null && $objetivo > 0) ? max(0, $saldo) / $objetivo * 100 : null;
            $falta = $objetivo !== null ? max(0, $objetivo - $saldo) : null;
        @endphp

        <p class="text-2xl font-semibold {{ $saldo < 0 ? 'text-teja' : '' }}">
            {{ $this->plata($saldo, $this->envelope->currency) }}
        </p>

        @if ($this->envelope->isGasto() && $saldo < 0)
            <p class="text-sm text-teja">Te pasaste de lo que habías reservado en este sobre.</p>
        @endif

        @if ($editingTarget)
            <form wire:submit="updateTarget" class="space-y-2 border-t border-cuero/15 pt-3">
                <label for="targetAmountInput" class="block text-sm text-cuero/70">
                    Objetivo{{ $this->envelope->indexed ? ' (en pesos de hoy)' : '' }}
                </label>
                <input
                    id="targetAmountInput"
                    type="text"
                    inputmode="decimal"
                    wire:model="targetAmountInput"
                    placeholder="¿Cuánto querés juntar?"
                    autocomplete="off"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                @error('targetAmountInput')
                    <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                @enderror
                @if ($this->envelope->indexed)
                    <p class="text-sm text-cuero/60">Al cambiarlo, la vara vuelve a arrancar desde este mes.</p>
                @else
                    <p class="text-sm text-cuero/60">Dejalo en blanco si no querés fijarte un objetivo.</p>
                @endif
                <div class="flex gap-2">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
                    >
                        Guardar
                    </button>
                    <button
                        type="button"
                        wire:click="cancelEditingTarget"
                        class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero"
                    >Cancelar</button>
                </div>
            </form>
        @elseif ($objetivo !== null)
            <div class="flex items-start gap-2">
                <p class="min-w-0 flex-1 text-sm text-cuero/70">
                    Objetivo: {{ $this->plata($objetivo, $this->envelope->currency) }}
                    @if ($this->envelope->indexed && $this->envelope->target_month !== null)
                        (eran {{ $this->plata($this->envelope->target_amount) }} en {{ $this->envelope->target_month->format('m/Y') }}; la vara sube con el IPC)
                    @elseif ($this->envelope->targetReducedByPayments() > 0)
                        (eran {{ $this->plata($this->envelope->target_amount, $this->envelope->currency) }}; lo fueron bajando los pagos que cumpliste)
                    @endif
                </p>
                <button
                    type="button"
                    wire:click="startEditingTarget"
                    aria-label="Editar el objetivo"
                    class="-my-2 grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-oliva focus-visible:outline-2 focus-visible:outline-oliva"
                >
                    {{-- Heroicon: pencil-square (outline) --}}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                </button>
            </div>

            @if ($progreso !== null)
                <div class="h-1.5 w-full overflow-hidden rounded-sm bg-cuero/15" role="presentation">
                    <div class="h-full bg-oliva" style="width: {{ min(100, $progreso) }}%"></div>
                </div>
            @endif

            @if ($this->envelope->indexed)
                @if (! $this->hasInflationData)
                    <p class="text-sm text-ocre-oscuro" role="status">
                        Todavía no tengo datos de inflación cargados, así que muestro el objetivo sin ajustar.
                    </p>
                @elseif ($falta > 0)
                    <p class="text-sm text-cuero" role="status">
                        Para mantener el poder de compra te falta aportar {{ $this->plata($falta) }}.
                    </p>
                @else
                    <p class="text-sm text-yerba" role="status">
                        Estás cubriendo el poder de compra que te propusiste.
                    </p>
                @endif
            @endif
        @else
            <button
                type="button"
                wire:click="startEditingTarget"
                class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-oliva hover:text-oliva/80 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oliva"
            >
                {{-- Heroicon: plus (mini) --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                    <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                </svg>
                Ponerle un objetivo
            </button>
        @endif
    </div>

    <form class="space-y-3" wire:submit="aporte">
        <h3 class="font-brand text-lg font-semibold">Mover plata</h3>
        <div class="flex gap-2">
            <div class="flex-1">
                <label for="movementAmount" class="sr-only">Monto ({{ $this->envelope->currency }})</label>
                <input
                    id="movementAmount"
                    type="text"
                    inputmode="decimal"
                    wire:model="movementAmount"
                    placeholder="Monto en {{ $this->envelope->currency }}"
                    autocomplete="off"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
            </div>
            <div>
                <x-ui.date-field model="movementDate" label="Fecha" :srLabel="true" accent="oliva" preset="pasado" />
            </div>
        </div>
        <div>
            <label for="movementNote" class="sr-only">Nota</label>
            <input
                id="movementNote"
                type="text"
                wire:model="movementNote"
                placeholder="Nota (opcional)"
                autocomplete="off"
                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
            >
        </div>
        @error('movementAmount')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror
        @error('movementDate')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror
        <div class="flex gap-2">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="min-h-11 flex-1 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
            >
                Aportar
            </button>
            <button
                type="button"
                wire:click="retiro"
                wire:loading.attr="disabled"
                class="min-h-11 flex-1 rounded-sm border border-monte px-4 font-medium text-monte hover:bg-monte/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
            >
                Sacar
            </button>
        </div>
    </form>

    @if ($this->otherEnvelopes->isNotEmpty())
        <form class="space-y-3" wire:submit="transfer">
            <h3 class="font-brand text-lg font-semibold">Pasar a otro sobre</h3>
            <div class="flex flex-col gap-2 sm:flex-row">
                <div class="flex-1">
                    <label for="transferAmount" class="sr-only">Monto a pasar ({{ $this->envelope->currency }})</label>
                    <input
                        id="transferAmount"
                        type="text"
                        inputmode="decimal"
                        wire:model="transferAmount"
                        placeholder="Monto en {{ $this->envelope->currency }}"
                        autocomplete="off"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                    >
                </div>
                <div class="flex-1">
                    <label for="transferTo" class="sr-only">Sobre de destino</label>
                    <select
                        id="transferTo"
                        wire:model="transferTo"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                    >
                        <option value="">¿A qué sobre?</option>
                        @foreach ($this->otherEnvelopes as $destino)
                            <option value="{{ $destino->id }}">{{ $destino->name }} ({{ $destino->currency }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="text-sm text-cuero/60">Si el destino está en otra moneda, convierto a la cotización blue del día. Si venís de un sobre indexado, lo pasado se congela en su valor nominal.</p>
            @error('transferAmount')
                <p class="text-sm text-teja" role="alert">{{ $message }}</p>
            @enderror
            @error('transferTo')
                <p class="text-sm text-teja" role="alert">{{ $message }}</p>
            @enderror
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="min-h-11 w-full rounded-sm border border-monte px-4 font-medium text-monte hover:bg-monte/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
            >
                Pasar
            </button>
        </form>
    @endif

    <div class="space-y-2">
        <h3 class="font-brand text-lg font-semibold">Historia</h3>

        @if ($this->timeline->isEmpty())
            <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
                Este sobre todavía está vacío. Cuando muevas plata, la historia queda acá.
            </p>
        @else
            <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                @foreach ($this->timeline as $item)
                    <li wire:key="{{ $item['key'] }}" class="py-1">
                        @if ($item['editable'] && $this->editingMovementId === $item['id'])
                            <form wire:submit="updateMovement" class="space-y-2 py-2">
                                <p class="text-sm font-medium">Editando {{ Str::lower($item['label']) }}</p>
                                <div class="flex gap-2">
                                    <div class="flex-1">
                                        <label for="editMovementAmount" class="sr-only">Monto ({{ $this->envelope->currency }})</label>
                                        <input
                                            id="editMovementAmount"
                                            type="text"
                                            inputmode="decimal"
                                            wire:model="editMovementAmount"
                                            autocomplete="off"
                                            class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                                        >
                                    </div>
                                    <div>
                                        <x-ui.date-field model="editMovementDate" label="Fecha" :srLabel="true" accent="oliva" preset="pasado" />
                                    </div>
                                </div>
                                <div>
                                    <label for="editMovementNote" class="sr-only">Nota</label>
                                    <input
                                        id="editMovementNote"
                                        type="text"
                                        wire:model="editMovementNote"
                                        placeholder="Nota (opcional)"
                                        autocomplete="off"
                                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                                    >
                                </div>
                                @error('editMovementAmount')
                                    <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                                @enderror
                                @error('editMovementDate')
                                    <p class="text-sm text-teja" role="alert">{{ $message }}</p>
                                @enderror
                                <div class="flex gap-2">
                                    <button
                                        type="submit"
                                        class="min-h-11 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte"
                                    >
                                        Guardar
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="cancelEditMovement"
                                        class="min-h-11 rounded-sm px-3 text-cuero/70 hover:text-cuero"
                                    >Cancelar</button>
                                </div>
                            </form>
                        @else
                            <div class="flex items-center gap-2">
                                <div class="min-w-0 flex-1 py-2">
                                    <p class="break-words">{{ $item['label'] }}</p>
                                    <p class="text-sm text-cuero/60">
                                        {{ $item['date']->format('d/m/Y') }}@if ($item['note']) · {{ $item['note'] }}@endif
                                    </p>
                                </div>
                                <span class="shrink-0 font-medium {{ $item['entrada'] ? 'text-yerba' : '' }}">
                                    {{ $item['entrada'] ? '+' : '−' }}{{ $this->plata($item['amount'], $this->envelope->currency) }}
                                </span>
                                @if ($item['editable'])
                                    <button
                                        type="button"
                                        wire:click="startEditingMovement({{ $item['id'] }})"
                                        aria-label="Editar movimiento: {{ $item['label'] }} del {{ $item['date']->format('d/m/Y') }}"
                                        class="grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-oliva focus-visible:outline-2 focus-visible:outline-oliva"
                                    >
                                        {{-- Heroicon: pencil-square (outline) --}}
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </button>
                                @endif
                                @if ($item['kind'] === 'movimiento')
                                    <button
                                        type="button"
                                        wire:click="deleteMovement({{ $item['id'] }})"
                                        wire:confirm="Vas a eliminar este movimiento{{ $item['label'] === 'Transferencia recibida' || $item['label'] === 'Transferencia enviada' ? ' y su contraparte en el otro sobre' : '' }}. Esto no se puede deshacer."
                                        aria-label="Eliminar movimiento: {{ $item['label'] }} del {{ $item['date']->format('d/m/Y') }}"
                                        class="grid size-11 shrink-0 place-items-center text-cuero/60 hover:text-teja focus-visible:outline-2 focus-visible:outline-teja"
                                    >
                                        {{-- Heroicon: trash (outline) --}}
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                @else
                                    <span class="size-11 shrink-0" aria-hidden="true"></span>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($this->hasMoreTimeline)
                <button
                    type="button"
                    wire:click="showMoreTimeline"
                    wire:loading.attr="disabled"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 px-4 text-sm font-medium text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oliva disabled:opacity-60"
                >
                    Ver más historia
                </button>
            @endif
        @endif
    </div>

    <button
        type="button"
        wire:click="deleteEnvelope"
        wire:confirm="Vas a eliminar el sobre «{{ $this->envelope->name }}» con toda su historia de movimientos. Los gastos ya anotados no se borran: quedan sueltos. Esto no se puede deshacer."
        class="min-h-11 w-full rounded-sm border border-teja px-4 font-medium text-teja hover:bg-teja/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teja"
    >
        Eliminar este sobre
    </button>
</section>
