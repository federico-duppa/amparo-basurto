<?php

use App\Models\Concerns\FormatsMoney;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Desglose de lo gastado en el auto por período (mes o año), separando
 * mantenimiento de combustible. Solo lectura: los datos se cargan en los
 * componentes de mantenimientos y combustible, que avisan con el evento
 * `vehiculo-actualizado` cuando algo cambia.
 */
new class extends Component
{
    use FormatsMoney;

    /** Períodos visibles por página en el desglose de gastos. */
    private const SPEND_PAGE = 12;

    #[Locked]
    public int $vehicleId;

    // Desglose de gastos por período: 'mes' o 'anio'.
    public string $spendPeriod = 'mes';
    public int $spendLimit = self::SPEND_PAGE;

    /**
     * Otro componente registró o corrigió un gasto: recibir el evento ya
     * re-renderiza el desglose con datos frescos.
     */
    #[On('vehiculo-actualizado')]
    public function refresh(): void {}

    public function setSpendPeriod(string $period): void
    {
        $this->spendPeriod = in_array($period, ['mes', 'anio'], true) ? $period : 'mes';
        $this->reset('spendLimit');
    }

    public function showMoreSpending(): void
    {
        $this->spendLimit += self::SPEND_PAGE;
    }

    #[Computed]
    public function vehicle(): ?Vehicle
    {
        return auth()->user()->accessibleVehicles()->find($this->vehicleId);
    }

    /**
     * Desglose de lo gastado por período (mes o año), del más reciente al
     * más viejo, separando mantenimiento de combustible. Se agrupa en PHP
     * para que funcione igual en SQLite y Postgres; de la base solo viajan
     * las filas con costo.
     */
    #[Computed]
    public function spendingByPeriod(): Collection
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return collect();
        }

        $format = $this->spendPeriod === 'anio' ? 'Y' : 'Y-m';

        $maintenance = $vehicle->maintenanceRecords()->whereNotNull('cost')->get(['performed_on', 'cost'])
            ->groupBy(fn ($record) => $record->performed_on->format($format))
            ->map(fn (Collection $rows) => (float) $rows->sum('cost'));

        $fuel = $vehicle->fuelLogs()->whereNotNull('cost')->get(['filled_on', 'cost'])
            ->groupBy(fn ($log) => $log->filled_on->format($format))
            ->map(fn (Collection $rows) => (float) $rows->sum('cost'));

        return $maintenance->keys()->concat($fuel->keys())->unique()
            ->sortDesc()->values()
            ->map(fn (string $period) => [
                'period' => $period,
                'label' => $this->spendPeriod === 'anio' ? $period : Carbon::createFromFormat('!Y-m', $period)->format('m/Y'),
                'mantenimiento' => $maintenance->get($period, 0.0),
                'combustible' => $fuel->get($period, 0.0),
                'total' => $maintenance->get($period, 0.0) + $fuel->get($period, 0.0),
            ]);
    }

    /**
     * Períodos visibles, de a SPEND_PAGE; "Ver más" agranda la ventana.
     */
    #[Computed]
    public function spending(): Collection
    {
        return $this->spendingByPeriod->take($this->spendLimit)->values();
    }

    #[Computed]
    public function hasMoreSpending(): bool
    {
        return $this->spendingByPeriod->count() > $this->spendLimit;
    }
};
?>

{{-- Gastos por período --}}
<div>
    @if ($this->spending->isNotEmpty())
        <div class="space-y-3">
            <div>
                <h2 class="font-brand text-lg font-bold">Gastos</h2>
                <p class="text-sm text-cuero/60">Cuánto se llevó el vehículo en cada período, entre mantenimiento y combustible.</p>
            </div>

            <div class="flex flex-wrap gap-2" role="group" aria-label="Agrupar los gastos">
                @foreach (['mes' => 'Por mes', 'anio' => 'Por año'] as $valor => $etiqueta)
                    <button type="button" wire:click="setSpendPeriod('{{ $valor }}')"
                        aria-pressed="{{ $this->spendPeriod === $valor ? 'true' : 'false' }}"
                        class="min-h-11 rounded-sm border px-3 text-sm {{ $this->spendPeriod === $valor ? 'border-grafito bg-grafito/10 font-semibold text-grafito' : 'border-cuero/30 text-cuero/70 hover:text-cuero' }} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-grafito">
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>

            <ul class="divide-y divide-cuero/15 border-y border-cuero/15">
                @foreach ($this->spending as $row)
                    <li wire:key="gasto-{{ $this->spendPeriod }}-{{ $row['period'] }}" class="flex items-baseline justify-between gap-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ $row['label'] }}</p>
                            <p class="text-xs text-cuero/60">
                                @if ($row['mantenimiento'] > 0)Mantenimiento {{ $this->pesos($row['mantenimiento']) }}@endif
                                @if ($row['mantenimiento'] > 0 && $row['combustible'] > 0) · @endif
                                @if ($row['combustible'] > 0)Combustible {{ $this->pesos($row['combustible']) }}@endif
                            </p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold">{{ $this->pesos($row['total']) }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($this->hasMoreSpending)
                <button type="button" wire:click="showMoreSpending" wire:loading.attr="disabled"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 px-4 text-sm font-medium text-cuero/70 hover:text-cuero focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-grafito disabled:opacity-60">
                    Ver más períodos
                </button>
            @endif
        </div>
    @endif
</div>
