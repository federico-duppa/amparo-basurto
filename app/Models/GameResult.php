<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GameResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una partida ganada en Juegos. Las del puzzle del día (daily = true) llevan
 * en played_on la fecha del puzzle y sostienen la racha; las libres solo
 * aportan al mejor tiempo. El cliente informa el tiempo (los juegos corren
 * enteros en el navegador): alcanza para uso personal, pero no serviría de
 * base para un ranking entre usuarios.
 */
class GameResult extends Model
{
    /** @use HasFactory<GameResultFactory> */
    use HasFactory;

    protected $fillable = [
        'game',
        'daily',
        'played_on',
        'seconds',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mejor tiempo del usuario en un juego (diarias y libres juntas), o null
     * si todavía no ganó ninguna.
     */
    public static function bestTime(User $user, string $game): ?int
    {
        return $user->gameResults()->where('game', $game)->min('seconds');
    }

    /**
     * La victoria del puzzle del día de una fecha, si existe.
     */
    public static function dailyResult(User $user, string $game, string $date): ?self
    {
        return $user->gameResults()
            ->where('game', $game)
            ->where('daily', true)
            ->whereDate('played_on', $date)
            ->first();
    }

    /**
     * Racha de puzzles del día: cuántos días seguidos, contando hacia atrás
     * desde $today. Si hoy todavía no jugó, la racha que viene de ayer sigue
     * viva (no se corta hasta que el día termina sin jugar).
     */
    public static function streak(User $user, string $game, string $today): int
    {
        $days = $user->gameResults()
            ->where('game', $game)
            ->where('daily', true)
            ->orderByDesc('played_on')
            ->pluck('played_on')
            ->map(fn ($day) => $day->toDateString())
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return 0;
        }

        $cursor = CarbonImmutable::parse($today);
        if ($days->first() !== $cursor->toDateString()) {
            $cursor = $cursor->subDay();
            if ($days->first() !== $cursor->toDateString()) {
                return 0;
            }
        }

        $streak = 0;
        foreach ($days as $day) {
            if ($day !== $cursor->toDateString()) {
                break;
            }
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily' => 'boolean',
            'played_on' => 'date',
        ];
    }
}
