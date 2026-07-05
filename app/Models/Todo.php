<?php

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $fillable = [
        'title',
        'completed_at',
        'project_id',
        'due_date',
        'urgent',
        'important',
        'repeat_interval',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'due_date' => 'date',
            'urgent' => 'boolean',
            'important' => 'boolean',
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
}
