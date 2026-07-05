<?php

namespace App\Models;

use Database\Factories\HealthEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthEntry extends Model
{
    /** @use HasFactory<HealthEntryFactory> */
    use HasFactory;

    /**
     * Tipos de entrada del timeline, livianos a propósito: sirven para
     * filtrar y dar contexto, no para burocratizar la carga.
     */
    public const TYPES = [
        'consulta' => 'Consulta',
        'estudio' => 'Estudio',
        'medicacion' => 'Medicación',
        'vacuna' => 'Vacuna',
        'nota' => 'Nota',
    ];

    protected $fillable = [
        'occurred_on',
        'type',
        'title',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * @return BelongsTo<HealthRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'health_record_id');
    }

    /**
     * Quién anotó la entrada (dueño o alguien con la historia compartida).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
