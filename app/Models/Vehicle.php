<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'marca',
        'modelo',
        'patente',
        'kilometraje',
    ];

    protected function casts(): array
    {
        return [
            'kilometraje' => 'integer',
        ];
    }

    /**
     * Adelanta el kilometraje del auto si la lectura recibida es mayor.
     * Nunca lo hace retroceder: una carga vieja no baja el km actual.
     */
    public function bumpMileage(int $mileage): void
    {
        if ($mileage > $this->kilometraje) {
            $this->update(['kilometraje' => $mileage]);
        }
    }

    public function nombre(): string
    {
        return trim($this->marca.' '.$this->modelo);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MaintenanceItem, $this>
     */
    public function maintenanceItems(): HasMany
    {
        return $this->hasMany(MaintenanceItem::class);
    }

    /**
     * @return HasMany<MaintenanceRecord, $this>
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * @return HasMany<FuelLog, $this>
     */
    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }
}
