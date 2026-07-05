<?php

namespace App\Models;

use App\Enums\HealthSubjectType;
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

    protected $fillable = [
        'titular',
        'titular_tipo',
        'nacimiento',
        'grupo_sanguineo',
        'obra_social',
        'alergias',
        'condiciones',
        'medicacion',
    ];

    protected $attributes = [
        'titular_tipo' => 'persona',
    ];

    protected function casts(): array
    {
        return [
            'titular_tipo' => HealthSubjectType::class,
            'nacimiento' => 'date',
        ];
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Edad del titular en años, si conocemos su nacimiento.
     */
    public function edad(): ?int
    {
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
