<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /** Días mínimos entre la primera y la última lectura para estimar el ritmo de uso. */
    private const MIN_USAGE_DAYS = 14;

    /** Tipos de vehículo, con su etiqueta para la interfaz. */
    public const TIPOS = [
        'auto' => 'Auto',
        'moto' => 'Moto',
    ];

    /**
     * Mantenimientos que se precargan al dar de alta el vehículo, según su tipo.
     * Son sugerencias comunes: el usuario las edita o borra como cualquier otra.
     */
    public const PRESETS = [
        'auto' => [
            ['name' => 'Cambio de aceite', 'interval_km' => 10000, 'interval_months' => 12],
            ['name' => 'Cambio de bujías', 'interval_km' => 40000, 'interval_months' => null],
            ['name' => 'Correa de distribución', 'interval_km' => 60000, 'interval_months' => 60],
        ],
        'moto' => [
            ['name' => 'Cambio de aceite', 'interval_km' => 5000, 'interval_months' => 12],
            ['name' => 'Kit de arrastre', 'interval_km' => 20000, 'interval_months' => null],
            ['name' => 'Cambio de bujía', 'interval_km' => 10000, 'interval_months' => null],
        ],
    ];

    protected $fillable = [
        'tipo',
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

    protected static function booted(): void
    {
        // Los documentos y el resto del historial caen por cascade de la base,
        // que no dispara eventos Eloquent: los adjuntos se borran acá vía
        // modelo para que cada uno saque también su archivo del disco.
        static::deleting(function (Vehicle $vehicle) {
            $vehicle->documentAttachments()->get()->each->delete();
        });
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

    /** Si el vehículo es una moto (el otro tipo es "auto"). */
    public function esMoto(): bool
    {
        return $this->tipo === 'moto';
    }

    /** El sustantivo del vehículo para la voz de Amparo: "auto" o "moto". */
    public function sustantivo(): string
    {
        return $this->esMoto() ? 'moto' : 'auto';
    }

    /**
     * Mantenimientos sugeridos para este tipo de vehículo, con un fallback a los
     * del auto si el tipo no tuviera presets propios.
     *
     * @return array<int, array{name: string, interval_km: ?int, interval_months: ?int}>
     */
    public function presets(): array
    {
        return self::PRESETS[$this->tipo] ?? self::PRESETS['auto'];
    }

    /**
     * Ritmo de uso real del auto en km por día, deducido de las lecturas de
     * kilometraje con fecha (cargas de combustible y realizaciones de
     * mantenimiento). Compara la primera y la última lectura; si no hay al
     * menos dos lecturas con {@see self::MIN_USAGE_DAYS} días y kilómetros
     * recorridos entre medio, no hay datos suficientes y devuelve null.
     */
    public function kmPerDay(): ?float
    {
        $readings = $this->fuelLogs()->get(['filled_on', 'mileage'])
            ->map(fn ($log) => ['on' => $log->filled_on, 'km' => $log->mileage])
            ->concat(
                $this->maintenanceRecords()->get(['performed_on', 'mileage'])
                    ->map(fn ($record) => ['on' => $record->performed_on, 'km' => $record->mileage])
            )
            ->sortBy([['on', 'asc'], ['km', 'asc']])
            ->values();

        if ($readings->count() < 2) {
            return null;
        }

        $first = $readings->first();
        $last = $readings->last();

        $days = $first['on']->diffInDays($last['on']);
        $km = $last['km'] - $first['km'];

        if ($days < self::MIN_USAGE_DAYS || $km <= 0) {
            return null;
        }

        return $km / $days;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * El dueño del auto (quien lo creó). Puede editarlo, eliminarlo y
     * decidir con quién se comparte.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Personas con las que se comparte el auto (además del dueño).
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vehicle_user')->withTimestamps();
    }

    /**
     * @return HasMany<MaintenanceItem, $this>
     */
    public function maintenanceItems(): HasMany
    {
        return $this->hasMany(MaintenanceItem::class);
    }

    /** Da de alta un ítem de mantenimiento a seguir (el alta manual y los presets). */
    public function addMaintenanceItem(string $name, ?int $intervalKm, ?int $intervalMonths, int $userId): void
    {
        $item = $this->maintenanceItems()->make([
            'name' => $name,
            'interval_km' => $intervalKm,
            'interval_months' => $intervalMonths,
        ]);
        $item->user_id = $userId;
        $item->save();
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

    /**
     * Documentación con vencimiento (seguro, VTV, patente…).
     *
     * @return HasMany<VehicleDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    /**
     * Los adjuntos de todos los documentos del auto. Sirve para el scoping
     * ("adjuntos accesibles" al eliminar uno) y la limpieza al borrar el auto.
     *
     * @return HasManyThrough<VehicleDocumentAttachment, VehicleDocument, $this>
     */
    public function documentAttachments(): HasManyThrough
    {
        return $this->hasManyThrough(VehicleDocumentAttachment::class, VehicleDocument::class);
    }
}
