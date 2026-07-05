<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return HasMany<Todo, $this>
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    /**
     * Proyectos del módulo Tareas.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Autos de los que este usuario es dueño.
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Autos que otras personas compartieron con este usuario.
     *
     * @return BelongsToMany<Vehicle, $this>
     */
    public function sharedVehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_user')->withTimestamps();
    }

    /**
     * Autos a los que el usuario tiene acceso: los propios más los compartidos.
     * Se usa como relación de scoping (findOrFail sobre lo ajeno responde 404).
     *
     * @return Builder<Vehicle>
     */
    public function accessibleVehicles(): Builder
    {
        return Vehicle::query()->where(fn (Builder $query) => $query
            ->where('vehicles.user_id', $this->id)
            ->orWhereHas('members', fn (Builder $members) => $members->whereKey($this->id)));
    }

    /**
     * Sobres del módulo Plata (de ahorro y de gasto previsto).
     *
     * @return HasMany<Envelope, $this>
     */
    public function envelopes(): HasMany
    {
        return $this->hasMany(Envelope::class);
    }

    /**
     * Gastos efectivos del módulo Plata.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
