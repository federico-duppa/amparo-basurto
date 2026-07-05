<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\VehicleDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocument extends Model
{
    /** @use HasFactory<VehicleDocumentFactory> */
    use HasFactory;

    /** Días de margen antes del vencimiento a partir del cual avisamos "pronto". */
    private const SOON_DAYS = 30;

    protected $fillable = [
        'name',
        'expires_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
        ];
    }

    /**
     * Estado del documento respecto de la fecha de hoy. Devuelve el nivel
     * (para ordenar y colorear) y los textos que Amparo muestra.
     *
     * Niveles: 'overdue' (vencido), 'soon' (por vencer), 'ok' (al día).
     *
     * @return array{level: string, rank: int, urgency: int, headline: string, detail: string}
     */
    public function status(?CarbonInterface $today = null): array
    {
        $today = $today ? Carbon::parse($today)->startOfDay() : Carbon::today();
        $remainingDays = $today->diffInDays($this->expires_on, false);

        $level = $remainingDays < 0 ? 'overdue' : ($remainingDays <= self::SOON_DAYS ? 'soon' : 'ok');

        $rank = match ($level) {
            'overdue' => 0,
            'soon' => 1,
            default => 2,
        };

        $headline = match ($level) {
            'overdue' => 'Vencido',
            'soon' => 'Por vencer',
            default => 'Al día',
        };

        $detail = match ($level) {
            'overdue' => 'Venció el '.$this->expires_on->format('d/m/Y').'.',
            'soon' => 'Vence el '.$this->expires_on->format('d/m/Y').'.',
            default => 'Al día hasta el '.$this->expires_on->format('d/m/Y').'.',
        };

        return [
            'level' => $level,
            'rank' => $rank,
            'urgency' => $remainingDays,
            'headline' => $headline,
            'detail' => $detail,
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
