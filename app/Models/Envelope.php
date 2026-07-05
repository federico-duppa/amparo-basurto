<?php

namespace App\Models;

use Database\Factories\EnvelopeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Envelope extends Model
{
    /** @use HasFactory<EnvelopeFactory> */
    use HasFactory;

    public const KIND_AHORRO = 'ahorro';

    public const KIND_GASTO = 'gasto';

    public const CURRENCIES = ['ARS', 'USD'];

    protected $fillable = [
        'name',
        'kind',
        'currency',
        'indexed',
        'target_amount',
        'target_month',
    ];

    protected function casts(): array
    {
        return [
            'indexed' => 'boolean',
            'target_amount' => 'decimal:2',
            'target_month' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<EnvelopeMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(EnvelopeMovement::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isAhorro(): bool
    {
        return $this->kind === self::KIND_AHORRO;
    }

    public function isGasto(): bool
    {
        return $this->kind === self::KIND_GASTO;
    }

    /**
     * Precarga en una sola consulta los agregados de los que emergen el saldo
     * y el objetivo vigente, para listar muchos sobres sin ir a la base por
     * cada uno. balance() y targetReducedByPayments() los usan si están.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithFinancials(Builder $query): Builder
    {
        return $query
            ->withSum(['movements as inflow_sum' => fn ($q) => $q->whereIn('type', [EnvelopeMovement::APORTE, EnvelopeMovement::TRANSFER_IN])], 'amount')
            ->withSum(['movements as outflow_sum' => fn ($q) => $q->whereIn('type', [EnvelopeMovement::RETIRO, EnvelopeMovement::TRANSFER_OUT])], 'amount')
            ->withSum('expenses as spent_sum', 'amount')
            ->withSum(['expenses as reduces_target_sum' => fn ($q) => $q->where('reduces_target', true)], 'amount');
    }

    /**
     * El saldo emerge de la historia: entradas − salidas − gastos imputados.
     * Nunca se edita a mano.
     */
    public function balance(): float
    {
        if (array_key_exists('inflow_sum', $this->attributes)) {
            return (float) $this->attributes['inflow_sum']
                - (float) $this->attributes['outflow_sum']
                - (float) $this->attributes['spent_sum'];
        }

        $in = (float) $this->movements()
            ->whereIn('type', [EnvelopeMovement::APORTE, EnvelopeMovement::TRANSFER_IN])
            ->sum('amount');

        $out = (float) $this->movements()
            ->whereIn('type', [EnvelopeMovement::RETIRO, EnvelopeMovement::TRANSFER_OUT])
            ->sum('amount');

        $spent = (float) $this->expenses()->sum('amount');

        return $in - $out - $spent;
    }

    /**
     * El objetivo vigente. En sobres indexados lo guardado es siempre nominal;
     * lo único que se indexa es la vara: el objetivo re-expresado por IPC
     * desde su mes base hasta hoy. Los pagos que cumplen el objetivo lo van
     * bajando: como el saldo, la vara también emerge de la historia.
     */
    public function currentTarget(): ?float
    {
        if ($this->target_amount === null) {
            return null;
        }

        $target = (float) $this->target_amount;

        if ($this->indexed && $this->target_month !== null) {
            $target *= InflationRate::factorBetween($this->target_month, now());
        }

        return max(0, $target - $this->targetReducedByPayments());
    }

    /**
     * Cuánto se le bajó al objetivo por pagos marcados como "cumplen el objetivo".
     * Solo los gastos imputados a un sobre de gasto pueden hacerlo.
     */
    public function targetReducedByPayments(): float
    {
        if (array_key_exists('reduces_target_sum', $this->attributes)) {
            return (float) $this->attributes['reduces_target_sum'];
        }

        return (float) $this->expenses()->where('reduces_target', true)->sum('amount');
    }

    /**
     * Cuánto falta aportar (nominal, hoy) para re-alcanzar el objetivo vigente.
     */
    public function gap(): ?float
    {
        $target = $this->currentTarget();

        return $target === null ? null : max(0, $target - $this->balance());
    }

    /**
     * Progreso contra el objetivo vigente, en porcentaje (sin tope).
     */
    public function progress(): ?float
    {
        $target = $this->currentTarget();

        if ($target === null || $target <= 0) {
            return null;
        }

        return max(0, $this->balance()) / $target * 100;
    }
}
