<?php

namespace App\Models;

use Database\Factories\FuelLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    /** @use HasFactory<FuelLogFactory> */
    use HasFactory;

    protected $fillable = [
        'filled_on',
        'mileage',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'filled_on' => 'date',
            'mileage' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
