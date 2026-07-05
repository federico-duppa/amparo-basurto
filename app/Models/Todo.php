<?php

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    /**
     * Intervalos de repetición admitidos, con su etiqueta para la interfaz.
     *
     * @var array<string, string>
     */
    public const REPEAT_INTERVALS = [
        'diaria' => 'Todos los días',
        'semanal' => 'Todas las semanas',
        'mensual' => 'Todos los meses',
        'anual' => 'Todos los años',
    ];

    /** Tarea en el flujo del día. */
    public const STATUS_ACTIVE = 'activa';

    /** Guardada para «algún día», fuera de la lista principal. */
    public const STATUS_SOMEDAY = 'algun_dia';

    protected $fillable = [
        'title',
        'notes',
        'completed_at',
        'status',
        'project_id',
        'due_date',
        'deferred_until',
        'position',
        'urgent',
        'important',
        'waiting',
        'waiting_for',
        'repeat_interval',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'due_date' => 'date',
            'deferred_until' => 'date',
            'position' => 'integer',
            'urgent' => 'boolean',
            'important' => 'boolean',
            'waiting' => 'boolean',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted()
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }

    public function isSomeday(): bool
    {
        return $this->status === self::STATUS_SOMEDAY;
    }

    public function isWaiting(): bool
    {
        return $this->waiting;
    }

    /**
     * ¿Está pospuesta a futuro? Mientras el tickler no llegue, la tarea no
     * aparece en las vistas (salvo que se pidan las pospuestas a propósito).
     */
    public function isDeferred(): bool
    {
        return $this->deferred_until !== null && $this->deferred_until->gt(today());
    }

    /**
     * Sólo las pendientes de verdad reciben el tickler; una completada no se
     * pospone.
     *
     * @param  Builder<Todo>  $query
     */
    public function scopeVisibleToday(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('deferred_until')->orWhere('deferred_until', '<=', today());
        });
    }

    /**
     * Peso para ordenar por cuadrante de Eisenhower: primero lo urgente e
     * importante, después lo importante, después lo urgente, al final el resto.
     */
    public function eisenhowerWeight(): int
    {
        return match (true) {
            $this->urgent && $this->important => 0,
            $this->important => 1,
            $this->urgent => 2,
            default => 3,
        };
    }

    /**
     * Expresión SQL del peso de Eisenhower, para ordenar en la base sin traer
     * todo a PHP. Funciona igual en SQLite y Postgres.
     */
    public static function eisenhowerOrderSql(): string
    {
        return 'case when urgent and important then 0'
            .' when important then 1'
            .' when urgent then 2 else 3 end';
    }

    /**
     * Próximo vencimiento de una tarea que se repite: avanza desde la fecha
     * actual de vencimiento tantas veces como haga falta para no caer en el
     * pasado (completar una recurrente atrasada no genera otra atrasada).
     */
    public function nextDueDate(): Carbon
    {
        $next = $this->due_date->copy();

        do {
            $next = match ($this->repeat_interval) {
                'diaria' => $next->addDay(),
                'semanal' => $next->addWeek(),
                'mensual' => $next->addMonthNoOverflow(),
                'anual' => $next->addYearNoOverflow(),
            };
        } while ($next->lt(today()));

        return $next;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /**
     * @return HasMany<Subtask, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position')->orderBy('id');
    }
}
