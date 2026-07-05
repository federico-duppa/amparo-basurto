<?php

use App\Models\Envelope;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sobres')] class extends Component
{
    public bool $creating = false;

    public string $name = '';

    public string $kind = Envelope::KIND_AHORRO;

    public string $currency = 'ARS';

    public bool $indexed = false;

    public string $targetAmount = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in([Envelope::KIND_AHORRO, Envelope::KIND_GASTO])],
            'currency' => ['required', Rule::in(Envelope::CURRENCIES)],
            'indexed' => ['boolean'],
            'targetAmount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Poneme un nombre al sobre, así sabemos para qué es.',
            'name.max' => 'Ese nombre es muy largo — probá resumirlo.',
            'targetAmount.numeric' => 'El objetivo tiene que ser un número.',
            'targetAmount.gt' => 'El objetivo tiene que ser mayor a cero.',
        ];
    }

    public function create(): void
    {
        $this->validate();

        // El objetivo indexado por IPC solo aplica a sobres de ahorro y se
        // ancla al peso: la moneda no se elige, es siempre ARS.
        $indexed = $this->kind === Envelope::KIND_AHORRO && $this->indexed;

        if ($indexed && $this->targetAmount === '') {
            $this->addError('targetAmount', 'Para cuidar el poder de compra necesito un objetivo: decime cuánto, en pesos de hoy.');

            return;
        }

        auth()->user()->envelopes()->create([
            'name' => trim($this->name),
            'kind' => $this->kind,
            'currency' => $indexed ? 'ARS' : $this->currency,
            'indexed' => $indexed,
            'target_amount' => $this->targetAmount === '' ? null : $this->targetAmount,
            'target_month' => $indexed ? now()->startOfMonth()->toDateString() : null,
        ]);

        $this->reset('creating', 'name', 'kind', 'currency', 'indexed', 'targetAmount');
    }

    #[Computed]
    public function envelopes(): Collection
    {
        // withFinancials precarga las sumas de movimientos y gastos: el saldo
        // y el objetivo de cada sobre salen de esta única consulta, sin ir a
        // la base por cada uno.
        return auth()->user()->envelopes()->withFinancials()->orderBy('name')->get();
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

    @if (! $creating)
        <button
            type="button"
            wire:click="$set('creating', true)"
            class="min-h-11 w-full rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte"
        >
            Armar un sobre nuevo
        </button>
    @else
        <form wire:submit="create" class="space-y-3 rounded-sm border border-cuero/20 p-4">
            <div>
                <label for="name" class="mb-1 block text-sm text-cuero/70">Nombre</label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    placeholder="Vacaciones, seguro, auto nuevo…"
                    autocomplete="off"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                @error('name')
                    <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <fieldset>
                <legend class="mb-1 text-sm text-cuero/70">¿Para qué es?</legend>
                <div class="flex gap-2">
                    <label class="flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-2 rounded-sm border px-3 text-sm {{ $kind === App\Models\Envelope::KIND_AHORRO ? 'border-oliva bg-oliva/10 font-semibold text-oliva' : 'border-cuero/30 text-cuero/70' }}">
                        <input type="radio" wire:model.live="kind" value="ahorro" class="sr-only">
                        Juntar en el tiempo
                    </label>
                    <label class="flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-2 rounded-sm border px-3 text-sm {{ $kind === App\Models\Envelope::KIND_GASTO ? 'border-oliva bg-oliva/10 font-semibold text-oliva' : 'border-cuero/30 text-cuero/70' }}">
                        <input type="radio" wire:model.live="kind" value="gasto" class="sr-only">
                        Reservar para un gasto
                    </label>
                </div>
            </fieldset>

            @if ($kind === App\Models\Envelope::KIND_AHORRO)
                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model.live="indexed"
                        class="size-5 rounded-sm border-cuero/50 text-oliva focus:ring-oliva/40"
                    >
                    <span class="text-sm">Cuidar el poder de compra (el objetivo se ajusta por inflación; siempre en pesos)</span>
                </label>
            @endif

            @if (! ($kind === App\Models\Envelope::KIND_AHORRO && $indexed))
                <div>
                    <label for="sobre-currency" class="mb-1 block text-sm text-cuero/70">Moneda</label>
                    <select
                        id="sobre-currency"
                        wire:model="currency"
                        class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                    >
                        @foreach (App\Models\Envelope::CURRENCIES as $moneda)
                            <option value="{{ $moneda }}">{{ $moneda }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="targetAmount" class="mb-1 block text-sm text-cuero/70">
                    Objetivo {{ $kind === App\Models\Envelope::KIND_AHORRO && $indexed ? '(en pesos de hoy)' : '(opcional)' }}
                </label>
                <input
                    id="targetAmount"
                    type="text"
                    inputmode="decimal"
                    wire:model="targetAmount"
                    placeholder="¿Cuánto querés juntar?"
                    autocomplete="off"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base placeholder:text-cuero/50 focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                @error('targetAmount')
                    <p class="mt-1 text-sm text-teja" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-2">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="min-h-11 flex-1 rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
                >
                    Crear sobre
                </button>
                <button
                    type="button"
                    wire:click="$set('creating', false)"
                    class="min-h-11 rounded-sm border border-cuero/30 px-4 font-medium text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte"
                >
                    Cancelar
                </button>
            </div>
        </form>
    @endif

    @if ($this->envelopes->isEmpty())
        <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
            Todavía no armaste ningún sobre. Sirven para ir juntando de a poco, o para reservar plata que ya sabés que se va.
        </p>
    @else
        <ul class="space-y-2">
            @foreach ($this->envelopes as $sobre)
                <li wire:key="sobre-{{ $sobre->id }}">
                    <a
                        href="{{ route('plata.sobre', $sobre) }}"
                        wire:navigate
                        class="flex items-center gap-3 rounded-sm border border-cuero/20 p-4 hover:border-oliva/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oliva"
                    >
                        <div class="min-w-0 flex-1">
                            @php
                                // Saldo y objetivo se resuelven una sola vez por sobre (en los
                                // indexados currentTarget() consulta el IPC); el progreso se
                                // deriva acá con la misma lógica de progress() para no repetirla.
                                $saldo = $sobre->balance();
                                $objetivo = $sobre->currentTarget();
                                $progreso = ($objetivo !== null && $objetivo > 0) ? max(0, $saldo) / $objetivo * 100 : null;
                            @endphp
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="break-words font-medium">{{ $sobre->name }}</span>
                                <span class="rounded-sm bg-oliva/10 px-1.5 py-0.5 text-xs font-medium text-oliva">
                                    {{ $sobre->isAhorro() ? 'Ahorro' : 'Gasto previsto' }}{{ $sobre->indexed ? ' · indexado' : '' }}
                                </span>
                            </p>
                            <p class="mt-1 text-sm text-cuero/60">
                                Saldo: {{ $this->plata($saldo, $sobre->currency) }}
                                @if ($objetivo !== null)
                                    de {{ $this->plata($objetivo, $sobre->currency) }}
                                @endif
                            </p>
                            @if ($progreso !== null)
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-sm bg-cuero/15" role="presentation">
                                    <div class="h-full bg-oliva" style="width: {{ min(100, $progreso) }}%"></div>
                                </div>
                            @endif
                        </div>
                        {{-- Heroicon: chevron-right (mini) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 shrink-0 text-cuero/50">
                            <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
