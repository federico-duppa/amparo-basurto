<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Estado de una fecha de vencimiento respecto de hoy, con los textos que
 * Amparo muestra. Mismo patrón que la documentación de Auto
 * (VehicleDocument::status()).
 */
trait HasExpiryStatus
{
    /** Días de margen antes del vencimiento a partir del cual avisamos "pronto". */
    private static int $soonDays = 30;

    /**
     * Niveles: 'overdue' (vencido), 'soon' (por vencer), 'ok' (al día).
     *
     * @return array{level: string, rank: int, urgency: int, headline: string, detail: string}
     */
    protected function statusFor(CarbonInterface $expiresOn, ?CarbonInterface $today = null): array
    {
        $today = $today ? Carbon::parse($today)->startOfDay() : Carbon::today();
        $expiresOn = Carbon::parse($expiresOn)->startOfDay();
        $remainingDays = $today->diffInDays($expiresOn, false);

        $level = $remainingDays < 0 ? 'overdue' : ($remainingDays <= self::$soonDays ? 'soon' : 'ok');

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
            'overdue' => 'Venció el '.$expiresOn->format('d/m/Y').'.',
            'soon' => 'Vence el '.$expiresOn->format('d/m/Y').'.',
            default => 'Al día hasta el '.$expiresOn->format('d/m/Y').'.',
        };

        return [
            'level' => $level,
            'rank' => $rank,
            'urgency' => $remainingDays,
            'headline' => $headline,
            'detail' => $detail,
        ];
    }
}
