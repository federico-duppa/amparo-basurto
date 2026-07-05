<?php

namespace App\Models;

use Database\Factories\FrequentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrequentItem extends Model
{
    /** @use HasFactory<FrequentItemFactory> */
    use HasFactory;

    /**
     * Repertorio de arranque: cosas comunes de supermercado que se precargan
     * la primera vez que el usuario entra al módulo. Después las edita o borra
     * como cualquier otra (no se vuelven a sembrar).
     *
     * @var list<string>
     */
    public const DEFAULTS = [
        'Leche',
        'Pan',
        'Huevos',
        'Manteca',
        'Queso',
        'Yerba',
        'Café',
        'Azúcar',
        'Harina',
        'Arroz',
        'Fideos',
        'Aceite',
        'Sal',
        'Tomate',
        'Papa',
        'Cebolla',
        'Banana',
        'Manzana',
        'Pollo',
        'Carne picada',
        'Yogur',
        'Galletitas',
        'Papel higiénico',
        'Detergente',
        'Jabón',
        'Agua',
    ];

    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
