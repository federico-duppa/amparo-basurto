<?php

namespace App\Models;

use Database\Factories\HealthEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected static function booted(): void
    {
        // Los adjuntos de la entrada se van con ella: por el modelo, para que
        // además de la fila se borre el archivo del disco.
        static::deleting(function (HealthEntry $entry) {
            $entry->attachments()->get()->each->delete();
        });
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

    /**
     * Los archivos que cuelgan de esta entrada (el estudio, la receta, la foto de la orden).
     *
     * @return HasMany<HealthAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(HealthAttachment::class);
    }
}
