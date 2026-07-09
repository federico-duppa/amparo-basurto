<?php

namespace App\Models;

use Database\Factories\HealthRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthRecord extends Model
{
    /** @use HasFactory<HealthRecordFactory> */
    use HasFactory;

    /** Tipos de titular, con su etiqueta para la interfaz. */
    public const TIPOS = [
        'persona' => 'Persona',
        'mascota' => 'Mascota',
        'documento' => 'Documento',
    ];

    protected $fillable = [
        'tipo',
        'titular',
        'nacimiento',
        'especie',
        'raza',
        'grupo_sanguineo',
        'obra_social',
        'alergias',
        'condiciones',
        'medicacion',
    ];

    protected function casts(): array
    {
        return [
            'nacimiento' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Las filas caen por cascada en la base (incluidas las de las entradas,
        // que no disparan eventos de Eloquent), pero los archivos de los
        // adjuntos hay que borrarlos del disco: pasa cada uno por el modelo.
        static::deleting(function (HealthRecord $record) {
            $record->attachments()->get()->each->delete();
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function esPersona(): bool
    {
        return $this->tipo === 'persona';
    }

    public function esMascota(): bool
    {
        return $this->tipo === 'mascota';
    }

    public function esDocumento(): bool
    {
        return $this->tipo === 'documento';
    }

    /** Etiqueta del campo "nombre" del titular, según el tipo de historia. */
    public function titularLabel(): string
    {
        return match ($this->tipo) {
            'mascota' => 'Nombre de la mascota',
            'documento' => 'Nombre del documento',
            default => 'Titular',
        };
    }

    /**
     * Edad del titular en años, si conocemos su nacimiento. Aplica a personas y
     * mascotas; un documento no tiene edad.
     */
    public function edad(): ?int
    {
        if ($this->esDocumento()) {
            return null;
        }

        return $this->nacimiento?->age;
    }

    /**
     * El dueño de la historia (quien la creó). Puede editar al titular,
     * eliminarla y decidir con quién se comparte.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Personas con las que se comparte la historia (además del dueño).
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'health_record_user')->withTimestamps();
    }

    /**
     * @return HasMany<HealthEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(HealthEntry::class);
    }

    /**
     * Todos los adjuntos de la historia: los sueltos y los que cuelgan de una entrada.
     *
     * @return HasMany<HealthAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(HealthAttachment::class);
    }

    /**
     * Vencimientos con fecha: próximo control, receta que caduca, estudio anual…
     *
     * @return HasMany<HealthReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(HealthReminder::class);
    }

    /**
     * Contactos médicos: médico de cabecera, especialistas, teléfonos.
     *
     * @return HasMany<HealthContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(HealthContact::class);
    }

    /**
     * El carnet de vacunas: cada aplicación con su dosis y fecha.
     *
     * @return HasMany<HealthVaccine, $this>
     */
    public function vaccines(): HasMany
    {
        return $this->hasMany(HealthVaccine::class);
    }

    /**
     * Mediciones (peso, presión, glucemia…) para seguir su evolución.
     *
     * @return HasMany<HealthMeasurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(HealthMeasurement::class);
    }
}
