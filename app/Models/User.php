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
     * Etiquetas del módulo Tareas.
     *
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Proyectos de los que este usuario es dueño.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Proyectos que otras personas compartieron con este usuario.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function sharedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')->withTimestamps();
    }

    /**
     * Proyectos a los que el usuario tiene acceso: los propios más los
     * compartidos. Se usa como relación de scoping (findOrFail sobre lo ajeno
     * responde 404).
     *
     * @return Builder<Project>
     */
    public function accessibleProjects(): Builder
    {
        return Project::query()->where(fn (Builder $query) => $query
            ->where('projects.user_id', $this->id)
            ->orWhereHas('members', fn (Builder $members) => $members->whereKey($this->id)));
    }

    /**
     * Tareas que el usuario puede ver y tachar: las propias más las que viven
     * en un proyecto compartido con él. Editar, eliminar y reordenar siguen
     * siendo cosa del dueño de cada tarea (se chequean aparte).
     *
     * @return Builder<Todo>
     */
    public function accessibleTodos(): Builder
    {
        $accessibleProjectIds = $this->accessibleProjects()->select('projects.id');

        return Todo::query()->where(fn (Builder $query) => $query
            ->where('todos.user_id', $this->id)
            ->orWhereIn('todos.project_id', $accessibleProjectIds));
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
     * Historias clínicas de las que este usuario es dueño.
     *
     * @return HasMany<HealthRecord, $this>
     */
    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    /**
     * Historias clínicas que otras personas compartieron con este usuario.
     *
     * @return BelongsToMany<HealthRecord, $this>
     */
    public function sharedHealthRecords(): BelongsToMany
    {
        return $this->belongsToMany(HealthRecord::class, 'health_record_user')->withTimestamps();
    }

    /**
     * Historias clínicas a las que el usuario tiene acceso: las propias más
     * las compartidas. Se usa como relación de scoping (findOrFail sobre lo
     * ajeno responde 404).
     *
     * @return Builder<HealthRecord>
     */
    public function accessibleHealthRecords(): Builder
    {
        return HealthRecord::query()->where(fn (Builder $query) => $query
            ->where('health_records.user_id', $this->id)
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
     * Listas de compras de las que este usuario es dueño.
     *
     * @return HasMany<ShoppingList, $this>
     */
    public function shoppingLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class);
    }

    /**
     * Listas de compras que otras personas compartieron con este usuario.
     *
     * @return BelongsToMany<ShoppingList, $this>
     */
    public function sharedShoppingLists(): BelongsToMany
    {
        return $this->belongsToMany(ShoppingList::class, 'shopping_list_user')->withTimestamps();
    }

    /**
     * Listas de compras a las que el usuario tiene acceso: las propias más las
     * compartidas. Se usa como relación de scoping (findOrFail sobre lo ajeno
     * responde 404).
     *
     * @return Builder<ShoppingList>
     */
    public function accessibleShoppingLists(): Builder
    {
        return ShoppingList::query()->where(fn (Builder $query) => $query
            ->where('shopping_lists.user_id', $this->id)
            ->orWhereHas('members', fn (Builder $members) => $members->whereKey($this->id)));
    }

    /**
     * Frecuentes del usuario: su repertorio personal de cosas que suele
     * comprar, para sumarlas a cualquiera de sus listas con un toque.
     *
     * @return HasMany<FrequentItem, $this>
     */
    public function frequentItems(): HasMany
    {
        return $this->hasMany(FrequentItem::class);
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
