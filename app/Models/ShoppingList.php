<?php

namespace App\Models;

use Database\Factories\ShoppingListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingList extends Model
{
    /** @use HasFactory<ShoppingListFactory> */
    use HasFactory;

    /** Nombre de la lista que se crea sola la primera vez que entrás al módulo. */
    public const DEFAULT_NAME = 'Súper';

    protected $fillable = [
        'name',
    ];

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * El dueño de la lista (quien la creó). Puede renombrarla, eliminarla y
     * decidir con quién se comparte.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Personas con las que se comparte la lista (además del dueño).
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shopping_list_user')->withTimestamps();
    }

    /**
     * Las cosas por comprar de esta lista.
     *
     * @return HasMany<ShoppingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingItem::class);
    }
}
