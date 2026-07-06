<?php

use App\Models\Concerns\FormatsMoney;
use App\Models\Expense;
use App\Models\InflationRate;
use App\Support\Lens;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reportes')] class extends Component
{
    use FormatsMoney;

    public string $fx = 'ars';

    public string $tiempo = 'nominal';

    /**
     * El lente: la misma historia de gastos, proyectada. El eje FX y el eje
     * temporal se eligen por separado y se combinan; nada de esto se guarda.
     */
    #[Computed]
    public function lens(): Lens
    {
        $fx = in_array($this->fx, Lens::FX, true) ? $this->fx : 'ars';
        $tiempo = in_array($this->tiempo, Lens::TIEMPOS, true) ? $this->tiempo : 'nominal';

        return new Lens($fx, $tiempo, now());
    }

    #[Computed]
    public function expenses(): Collection
    {
        return auth()->user()->expenses()
            ->where('spent_on', '>=', now()->subMonths(11)->startOfMonth())
            ->orderByDesc('spent_on')
            ->get();
    }

    /**
     * Cada gasto proyectado bajo el lente. El valor puede ser null si falta
     * una cotización imprescindible; esos gastos se informan aparte.
     */
    #[Computed]
    public function valued(): Collection
    {
        return $this->expenses->map(fn (Expense $expense) => [
            'expense' => $expense,
            'value' => $this->lens->value(
                (float) $expense->amount,
                $expense->currency,
                $expense->spent_on,
                $expense->rate_ars === null ? null : (float) $expense->rate_ars,
            ),
        ]);
    }

    #[Computed]
    public function skipped(): int
    {
        return $this->valued->whereNull('value')->count();
    }

    #[Computed]
    public function byCategory(): Collection
    {
        return $this->valued
            ->whereNotNull('value')
            ->groupBy(fn (array $row) => $row['expense']->category)
            ->map(fn (Collection $rows) => $rows->sum('value'))
            ->sortDesc();
    }

    #[Computed]
    public function byMonth(): Collection
    {
        return $this->valued
            ->whereNotNull('value')
            ->groupBy(fn (array $row) => $row['expense']->spent_on->format('Y-m'))
            ->map(fn (Collection $rows) => $rows->sum('value'))
            ->sortKeysDesc();
    }

    #[Computed]
    public function total(): float
    {
        return (float) $this->valued->whereNotNull('value')->sum('value');
    }

    #[Computed]
    public function missingInflation(): bool
    {
        return $this->lens->tiempo === 'real' && InflationRate::query()->doesntExist();
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
            class="-mb-px flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm text-cuero/70 hover:text-cuero"
        >Sobres</a>
        <a
            href="{{ route('plata.reportes') }}"
            wire:navigate
            aria-current="page"
            class="-mb-px flex min-h-11 items-center border-b-2 border-oliva px-3 text-sm font-semibold text-oliva"
        >Reportes</a>
    </nav>

    <div class="space-y-3">
        <fieldset>
            <legend class="mb-1 text-sm text-cuero/70">Moneda y cotización</legend>
            <div class="flex flex-wrap gap-2">
                @foreach (['ars' => 'Pesos', 'blue' => 'USD blue', 'oficial' => 'USD oficial', 'mep' => 'USD MEP'] as $valor => $etiqueta)
                    <button
                        type="button"
                        wire:click="$set('fx', '{{ $valor }}')"
                        aria-pressed="{{ $this->lens->fx === $valor ? 'true' : 'false' }}"
                        class="min-h-11 rounded-sm border px-3 text-sm {{ $this->lens->fx === $valor ? 'border-oliva bg-oliva/10 font-semibold text-oliva' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oliva"
                    >
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend class="mb-1 text-sm text-cuero/70">Eje temporal</legend>
            <div class="flex flex-wrap gap-2">
                @foreach (['nominal' => 'Nominal', 'real' => 'Valores de hoy'] as $valor => $etiqueta)
                    <button
                        type="button"
                        wire:click="$set('tiempo', '{{ $valor }}')"
                        aria-pressed="{{ $this->lens->tiempo === $valor ? 'true' : 'false' }}"
                        class="min-h-11 rounded-sm border px-3 text-sm {{ $this->lens->tiempo === $valor ? 'border-oliva bg-oliva/10 font-semibold text-oliva' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oliva"
                    >
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>
        </fieldset>

        <p class="text-sm text-cuero/60">Mostrar en dólares no ajusta por inflación, y ajustar por inflación no cambia la moneda: son dos preguntas distintas.</p>
    </div>

    @if ($this->missingInflation)
        <p class="text-sm text-ocre-oscuro" role="status">
            Todavía no tengo datos de inflación cargados, así que los valores se muestran sin ajustar.
        </p>
    @endif

    @if ($this->skipped > 0)
        <p class="text-sm text-ocre-oscuro" role="status">
            Dejé afuera {{ $this->skipped === 1 ? 'un gasto' : $this->skipped.' gastos' }} porque me faltan cotizaciones para convertirlo{{ $this->skipped === 1 ? '' : 's' }}.
        </p>
    @endif

    @if ($this->expenses->isEmpty())
        <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
            Todavía no hay gastos para mirar. Anotá algunos y acá te cuento a dónde se va.
        </p>
    @else
        <div class="rounded-sm border border-cuero/20 p-4">
            <p class="text-sm text-cuero/70">Últimos 12 meses</p>
            <p class="text-2xl font-semibold">{{ $this->plata($this->total, $this->lens->currency()) }}</p>
        </div>

        @if ($this->byCategory->isNotEmpty())
            <div class="space-y-2">
                <h2 class="font-brand text-lg font-semibold">Por categoría</h2>
                <ul class="space-y-2">
                    @foreach ($this->byCategory as $categoria => $total)
                        <li wire:key="cat-{{ md5($categoria) }}">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="min-w-0 break-words text-sm">{{ $categoria }}</span>
                                <span class="shrink-0 text-sm font-medium">{{ $this->plata($total, $this->lens->currency()) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-sm bg-cuero/15" role="presentation">
                                <div class="h-full bg-oliva" style="width: {{ $this->byCategory->max() > 0 ? round($total / $this->byCategory->max() * 100) : 0 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($this->byMonth->isNotEmpty())
            <div class="space-y-2">
                <h2 class="font-brand text-lg font-semibold">Por mes</h2>
                <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                    @foreach ($this->byMonth as $mes => $total)
                        <li wire:key="mes-{{ $mes }}" class="flex items-baseline justify-between gap-2 py-2.5">
                            <span class="text-sm">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $mes)->format('m/Y') }}</span>
                            <span class="text-sm font-medium">{{ $this->plata($total, $this->lens->currency()) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</section>
