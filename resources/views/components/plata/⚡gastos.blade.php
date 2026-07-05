<?php

use App\Models\Envelope;
use App\Support\MarketData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plata')] class extends Component
{
    public string $description = '';

    public string $category = '';

    public string $amount = '';

    public string $currency = 'ARS';

    public string $spentOn = '';

    public string $envelopeId = '';

    // Si el pago, además de descontar el saldo del sobre, cumple parte de su
    // objetivo: le baja la vara por el mismo monto. Solo aplica a sobres de
    // gasto con objetivo.
    public bool $reducesTarget = false;

    // Edición de un gasto ya cargado (null = estamos agregando uno nuevo).
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->spentOn = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::in(Envelope::CURRENCIES)],
            'spentOn' => ['required', 'date', 'before_or_equal:today'],
            'envelopeId' => ['nullable', 'integer'],
            'reducesTarget' => ['boolean'],
        ];
    }

    /**
     * Al cambiar de sobre, el "cumple el objetivo" arranca de cero: solo tiene
     * sentido para el sobre elegido, y no siempre está disponible.
     */
    public function updatedEnvelopeId(): void
    {
        $this->reducesTarget = false;
    }

    protected function messages(): array
    {
        return [
            'description.required' => 'Contame en qué se fue la plata.',
            'description.max' => 'Eso es muy largo — probá resumirlo.',
            'category.required' => 'Decime una categoría, aunque sea "varios".',
            'category.max' => 'Esa categoría es muy larga.',
            'amount.required' => 'Me falta el monto.',
            'amount.numeric' => 'El monto tiene que ser un número.',
            'amount.gt' => 'El monto tiene que ser mayor a cero.',
            'spentOn.required' => 'Me falta la fecha del gasto.',
            'spentOn.date' => 'Esa fecha no me cierra.',
            'spentOn.before_or_equal' => 'Los gastos se anotan cuando ya pasaron; para lo que viene, armá un sobre.',
        ];
    }

    public function add(): void
    {
        $this->validate();

        $envelope = $this->resolveEnvelope();

        if ($envelope === false) {
            return;
        }

        $date = Carbon::parse($this->spentOn);
        $rate = $this->currency === 'ARS' ? null : MarketData::rate('blue', $date);

        auth()->user()->expenses()->create([
            'envelope_id' => $envelope?->id,
            'description' => trim($this->description),
            'category' => trim($this->category),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'spent_on' => $date->toDateString(),
            'rate_ars' => $rate,
            'rate_source' => $rate === null ? null : 'blue',
            'reduces_target' => $this->cumpleObjetivo($envelope),
        ]);

        $this->reset('description', 'amount', 'envelopeId', 'reducesTarget');
    }

    public function startEditing(int $id): void
    {
        $expense = auth()->user()->expenses()->findOrFail($id);

        $this->editingId = $expense->id;
        $this->description = $expense->description;
        $this->category = $expense->category;
        $this->amount = rtrim(rtrim((string) $expense->amount, '0'), '.');
        $this->currency = $expense->currency;
        $this->spentOn = $expense->spent_on->format('Y-m-d');
        $this->envelopeId = $expense->envelope_id === null ? '' : (string) $expense->envelope_id;
        $this->reducesTarget = $expense->reduces_target;
        $this->resetValidation();
    }

    public function update(): void
    {
        $expense = auth()->user()->expenses()->findOrFail($this->editingId);

        $this->validate();

        $envelope = $this->resolveEnvelope();

        if ($envelope === false) {
            return;
        }

        $date = Carbon::parse($this->spentOn);
        $rate = $this->currency === 'ARS' ? null : MarketData::rate('blue', $date);

        $expense->update([
            'envelope_id' => $envelope?->id,
            'description' => trim($this->description),
            'category' => trim($this->category),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'spent_on' => $date->toDateString(),
            'rate_ars' => $rate,
            'rate_source' => $rate === null ? null : 'blue',
            'reduces_target' => $this->cumpleObjetivo($envelope),
        ]);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'description', 'amount', 'envelopeId', 'reducesTarget');
        $this->spentOn = now()->format('Y-m-d');
        $this->resetValidation();
    }

    /**
     * ¿El pago cumple parte del objetivo del sobre? Solo puede pasar si hay un
     * sobre de gasto con objetivo y el usuario lo marcó.
     */
    private function cumpleObjetivo(?Envelope $envelope): bool
    {
        return $this->reducesTarget
            && $envelope !== null
            && $envelope->target_amount !== null;
    }

    /**
     * El sobre elegido en el formulario (o null si es suelto). Sirve para saber
     * si ofrecer el "cumple el objetivo".
     */
    #[Computed]
    public function selectedEnvelope(): ?Envelope
    {
        if ($this->envelopeId === '') {
            return null;
        }

        return $this->gastoEnvelopes->firstWhere('id', (int) $this->envelopeId);
    }

    /**
     * Resuelve el sobre elegido (o null si es suelto). Devuelve false —y deja
     * un error en el formulario— si el sobre no está en la misma moneda.
     */
    private function resolveEnvelope(): Envelope|false|null
    {
        if ($this->envelopeId === '') {
            return null;
        }

        // Los gastos solo se imputan contra sobres de gasto previsto propios.
        $envelope = auth()->user()->envelopes()
            ->where('kind', Envelope::KIND_GASTO)
            ->findOrFail((int) $this->envelopeId);

        if ($envelope->currency !== $this->currency) {
            $this->addError('envelopeId', 'Ese sobre está en '.$envelope->currency.' y el gasto en '.$this->currency.'. Anotalo en la moneda del sobre, o dejalo suelto.');

            return false;
        }

        return $envelope;
    }

    public function delete(int $id): void
    {
        auth()->user()->expenses()->findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }

    #[Computed]
    public function expenses(): Collection
    {
        return auth()->user()->expenses()
            ->with('envelope')
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function gastoEnvelopes(): Collection
    {
        return auth()->user()->envelopes()
            ->where('kind', Envelope::KIND_GASTO)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        return auth()->user()->expenses()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    /**
     * Totales del mes en curso, por moneda nativa. Acá no hay conversión:
     * para el análisis cruzado están los lentes en Reportes.
     */
    #[Computed]
    public function monthTotals(): Collection
    {
        return auth()->user()->expenses()
            ->whereBetween('spent_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->get()
            ->groupBy('currency')
            ->map(fn (Collection $group) => $group->sum('amount'));
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
            aria-current="page"
            class="-mb-px flex min-h-11 items-center border-b-2 border-oliva px-3 text-sm font-semibold text-oliva"
        >Gastos</a>
        <a
            href="{{ route('plata.sobres') }}"
            class="-mb-px flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm text-cuero/70 hover:text-cuero"
        >Sobres</a>
        <a
            href="{{ route('plata.reportes') }}"
            class="-mb-px flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm text-cuero/70 hover:text-cuero"
        >Reportes</a>
    </nav>

    <form wire:submit="{{ $editingId ? 'update' : 'add' }}" class="space-y-3">
        @if ($editingId)
            <div class="flex items-center gap-2 rounded-sm bg-oliva/10 px-3 py-2 text-sm text-oliva" role="status">
                <span class="font-medium">Estás editando un gasto.</span>
                <button type="button" wire:click="cancelEdit" class="ml-auto font-medium underline hover:no-underline">Cancelar</button>
            </div>
        @endif
        <div class="flex gap-2">
            <div class="flex-1">
                <label for="amount" class="sr-only">Monto</label>
                <input
                    id="amount"
                    type="text"
                    inputmode="decimal"
                    wire:model="amount"
                    placeholder="¿Cuánto?"
                    autocomplete="off"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
            </div>
            <div>
                <label for="currency" class="sr-only">Moneda</label>
                <select
                    id="currency"
                    wire:model="currency"
                    class="min-h-11 rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                    @foreach (App\Models\Envelope::CURRENCIES as $moneda)
                        <option value="{{ $moneda }}">{{ $moneda }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @error('amount')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror

        <div>
            <label for="description" class="sr-only">Descripción</label>
            <input
                id="description"
                type="text"
                wire:model="description"
                placeholder="¿En qué se fue?"
                autocomplete="off"
                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
            >
            @error('description')
                <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-2">
            <div class="flex-1">
                <label for="category" class="sr-only">Categoría</label>
                <input
                    id="category"
                    type="text"
                    wire:model="category"
                    list="categorias-conocidas"
                    placeholder="Categoría"
                    autocomplete="off"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                <datalist id="categorias-conocidas">
                    @foreach ($this->categories as $categoria)
                        <option value="{{ $categoria }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div>
                <x-ui.date-field model="spentOn" label="Fecha" :srLabel="true" accent="oliva" preset="pasado" />
            </div>
        </div>
        @error('category')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror
        @error('spentOn')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror

        @if ($this->gastoEnvelopes->isNotEmpty())
            <div>
                <label for="envelopeId" class="mb-1 block text-sm text-cuero/70">Descontar de un sobre (opcional)</label>
                <select
                    id="envelopeId"
                    wire:model.live="envelopeId"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                    <option value="">Suelto, sin sobre</option>
                    @foreach ($this->gastoEnvelopes as $sobre)
                        <option value="{{ $sobre->id }}">{{ $sobre->name }} ({{ $sobre->currency }})</option>
                    @endforeach
                </select>
                @error('envelopeId')
                    <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p>
                @enderror

                @if ($this->selectedEnvelope && $this->selectedEnvelope->target_amount !== null)
                    <label class="mt-2 flex min-h-11 cursor-pointer items-start gap-3">
                        <input
                            type="checkbox"
                            wire:model="reducesTarget"
                            class="mt-0.5 size-5 rounded-sm border-cuero/50 text-oliva focus:ring-oliva/40"
                        >
                        <span class="text-sm text-cuero/80">
                            Este pago cumple parte del objetivo: le bajo la vara al sobre por el mismo monto, no solo el saldo.
                        </span>
                    </label>
                @endif
            </div>
        @endif

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="min-h-11 w-full rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
        >
            {{ $editingId ? 'Guardar cambios' : 'Anotar gasto' }}
        </button>
    </form>

    @if ($this->expenses->isEmpty())
        <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
            Todavía no anotaste ningún gasto. El día a día se anota acá, sin vueltas.
        </p>
    @else
        @if ($this->monthTotals->isNotEmpty())
            <p class="text-sm text-cuero/70">
                Este mes gastaste
                {{ $this->monthTotals->map(fn ($total, $moneda) => $this->plata($total, $moneda))->join(' y ') }}.
            </p>
        @endif

        <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
            @foreach ($this->expenses as $gasto)
                <li wire:key="gasto-{{ $gasto->id }}" class="flex items-center gap-2 py-1">
                    <div class="min-w-0 flex-1 py-2">
                        <p class="break-words">{{ $gasto->description }}</p>
                        <p class="text-sm text-cuero/60">
                            {{ $gasto->spent_on->format('d/m/Y') }} · {{ $gasto->category }}@if ($gasto->envelope) · Sobre: {{ $gasto->envelope->name }}@endif
                        </p>
                    </div>
                    <span class="shrink-0 font-medium {{ $editingId === $gasto->id ? 'text-oliva' : '' }}">{{ $this->plata($gasto->amount, $gasto->currency) }}</span>
                    <button
                        type="button"
                        wire:click="startEditing({{ $gasto->id }})"
                        aria-label="Editar gasto: {{ $gasto->description }}"
                        @if ($editingId === $gasto->id) aria-current="true" @endif
                        class="grid size-11 shrink-0 place-items-center focus-visible:outline-2 focus-visible:outline-oliva {{ $editingId === $gasto->id ? 'text-oliva' : 'text-cuero/60 hover:text-oliva' }}"
                    >
                        {{-- Heroicon: pencil-square (outline) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        wire:click="delete({{ $gasto->id }})"
                        wire:confirm="Vas a eliminar este gasto. Esto no se puede deshacer."
                        aria-label="Eliminar gasto: {{ $gasto->description }}"
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
