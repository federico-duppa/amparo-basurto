<?php

namespace App\Models;

use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    /** @use HasFactory<MaintenanceRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'performed_on',
        'mileage',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'mileage' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MaintenanceItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MaintenanceItem::class, 'maintenance_item_id');
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
