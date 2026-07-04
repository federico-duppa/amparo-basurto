<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\MaintenanceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenanceItem extends Model
{
    /** @use HasFactory<MaintenanceItemFactory> */
    use HasFactory;

    /** Km de margen antes del vencimiento a partir del cual avisamos "pronto". */
    private const SOON_KM = 1000;

    /** Días de margen antes del vencimiento a partir del cual avisamos "pronto". */
    private const SOON_DAYS = 30;

    protected $fillable = [
        'name',
        'interval_km',
        'interval_months',
    ];

    protected function casts(): array
    {
        return [
            'interval_km' => 'integer',
            'interval_months' => 'integer',
        ];
    }

    /**
     * Calcula el estado del mantenimiento respecto del kilometraje actual del auto
     * y de la fecha de hoy. Devuelve el nivel (para ordenar y colorear) y los textos
     * que Amparo muestra.
     *
     * Niveles: 'none' (sin registro), 'ok', 'soon', 'overdue'.
     *
     * @return array{level: string, rank: int, urgency: int, headline: string, detail: string}
     */
    public function status(int $currentKm, ?CarbonInterface $today = null): array
    {
        $today = $today ? Carbon::parse($today)->startOfDay() : Carbon::today();
        $last = $this->latestRecord;

        if (! $last) {
            return [
                'level' => 'none',
                'rank' => 3,
                'urgency' => PHP_INT_MAX,
                'headline' => 'Sin registrar',
                'detail' => 'Cuando lo hagas, anotá la fecha y el kilometraje y te aviso del próximo.',
            ];
        }

        $levels = [];
        $parts = [];
        $urgency = PHP_INT_MAX;

        if ($this->interval_km) {
            $dueKm = $last->mileage + $this->interval_km;
            $remainingKm = $dueKm - $currentKm;
            $levels[] = $remainingKm < 0 ? 'overdue' : ($remainingKm <= self::SOON_KM ? 'soon' : 'ok');
            $urgency = min($urgency, $remainingKm);
            $parts[] = $remainingKm < 0
                ? 'te pasaste por '.self::km(abs($remainingKm))
                : 'faltan '.self::km($remainingKm);
        }

        if ($this->interval_months) {
            $dueDate = $last->performed_on->copy()->addMonths($this->interval_months);
            $remainingDays = $today->diffInDays($dueDate, false);
            $levels[] = $remainingDays < 0 ? 'overdue' : ($remainingDays <= self::SOON_DAYS ? 'soon' : 'ok');
            // Un día ≈ 40 km para que km y tiempo compitan en la misma escala de urgencia.
            $urgency = min($urgency, (int) ($remainingDays * 40));
            $parts[] = ($remainingDays < 0 ? 'vencía el ' : 'el ').$dueDate->format('d/m/Y');
        }

        // Sin intervalos configurados: sólo llevamos el historial, sin recordatorio.
        if ($levels === []) {
            return [
                'level' => 'ok',
                'rank' => 2,
                'urgency' => PHP_INT_MAX,
                'headline' => 'Al día',
                'detail' => 'Último: '.$last->performed_on->format('d/m/Y').' · '.self::km($last->mileage).'.',
            ];
        }

        $level = in_array('overdue', $levels, true) ? 'overdue'
            : (in_array('soon', $levels, true) ? 'soon' : 'ok');

        $rank = match ($level) {
            'overdue' => 0,
            'soon' => 1,
            default => 2,
        };

        $headline = match ($level) {
            'overdue' => 'Atrasado',
            'soon' => 'Pronto',
            default => 'Al día',
        };

        $prefix = match ($level) {
            'overdue' => 'Ya toca: ',
            'soon' => 'Se viene: ',
            default => 'Próximo: ',
        };

        return [
            'level' => $level,
            'rank' => $rank,
            'urgency' => $urgency,
            'headline' => $headline,
            'detail' => $prefix.implode(' · ', $parts).'.',
        ];
    }

    private static function km(int $n): string
    {
        return number_format($n, 0, ',', '.').' km';
    }

    /**
     * @return HasOne<MaintenanceRecord, $this>
     */
    public function latestRecord(): HasOne
    {
        return $this->hasOne(MaintenanceRecord::class)->latestOfMany('performed_on');
    }

    /**
     * @return HasMany<MaintenanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
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
