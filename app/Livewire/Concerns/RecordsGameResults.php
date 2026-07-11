<?php

namespace App\Livewire\Concerns;

use App\Models\GameResult;
use Carbon\CarbonImmutable;

/**
 * Guardar victorias de un juego y servir sus números (mejor tiempo, racha,
 * puzzle del día): mismo flujo en Queens y Sol y luna. La partida entera vive
 * en el navegador; el servidor solo se entera al ganar, y si ese aviso falla
 * la partida no se pierde — solo queda sin anotar.
 */
trait RecordsGameResults
{
    /**
     * Clave del juego en game_results ('queens', 'solyluna').
     */
    abstract protected function gameKey(): string;

    /**
     * "Hoy" para el puzzle del día, en la zona horaria de la casa. El
     * servidor fija la fecha al servir la página; el cliente la usa como
     * semilla del tablero y la devuelve al ganar.
     */
    public function today(): string
    {
        return CarbonImmutable::now(config('amparo.zona_horaria'))->toDateString();
    }

    /**
     * Números para el render inicial de la página del juego. Público porque
     * el template lo pasa al tablero Alpine con @js (y como método Livewire
     * no expone más que los números del propio usuario).
     *
     * @return array{date: string, dailySolved: bool, dailySeconds: int|null, streak: int, best: int|null}
     */
    public function gameState(): array
    {
        $user = auth()->user();
        $today = $this->today();
        $daily = GameResult::dailyResult($user, $this->gameKey(), $today);

        return [
            'date' => $today,
            'dailySolved' => $daily !== null,
            'dailySeconds' => $daily?->seconds,
            'streak' => GameResult::streak($user, $this->gameKey(), $today),
            'best' => GameResult::bestTime($user, $this->gameKey()),
        ];
    }

    /**
     * Anota una partida ganada y devuelve los números frescos para que el
     * tablero actualice racha y mejor tiempo sin recargar.
     *
     * Del puzzle del día se guarda solo la primera victoria de cada fecha
     * (repetirlo no pisa el tiempo original). Si la fecha llegó vieja — una
     * pestaña abierta de otro día —, la victoria vale como partida libre:
     * el tablero resuelto no era el del día.
     *
     * @return array{date: string, dailySolved: bool, dailySeconds: int|null, streak: int, best: int|null}
     */
    public function recordWin(string $mode, int $seconds, string $date): array
    {
        abort_unless(in_array($mode, ['daily', 'free'], true), 422);

        $seconds = max(1, min($seconds, 86_400));
        $today = CarbonImmutable::parse($this->today());
        $puzzleDate = rescue(fn () => CarbonImmutable::parse($date)->toDateString(), null, false);

        $isDaily = $mode === 'daily'
            && $puzzleDate !== null
            && CarbonImmutable::parse($puzzleDate)->diffInDays($today, true) <= 1;

        if ($isDaily) {
            // Solo la primera victoria de cada fecha: repetir el del día no
            // pisa el tiempo original. (whereDate y no firstOrCreate: el valor
            // guardado de una columna date casteada no coincide textualmente
            // con la fecha pelada en SQLite.)
            GameResult::dailyResult(auth()->user(), $this->gameKey(), $puzzleDate)
                ?? auth()->user()->gameResults()->create([
                    'game' => $this->gameKey(),
                    'daily' => true,
                    'played_on' => $puzzleDate,
                    'seconds' => $seconds,
                ]);
        } else {
            auth()->user()->gameResults()->create([
                'game' => $this->gameKey(),
                'daily' => false,
                'played_on' => $today->toDateString(),
                'seconds' => $seconds,
            ]);
        }

        return $this->gameState();
    }
}
