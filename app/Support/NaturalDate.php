<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Interpreta fechas escritas en lenguaje natural (es-AR) dentro del título de
 * una tarea: «mañana», «el viernes», «en 3 días», «la semana que viene»,
 * «15/8»… La idea es que anotar rápido no obligue a abrir el calendario.
 *
 * Es deliberadamente conservador: sólo reconoce expresiones claras y con
 * límites de palabra, para no ponerle fecha a una tarea por casualidad.
 */
class NaturalDate
{
    /** ISO del día de la semana (1 = lunes … 7 = domingo). */
    private const WEEKDAYS = [
        'lunes' => 1,
        'martes' => 2,
        'miercoles' => 3,
        'miércoles' => 3,
        'jueves' => 4,
        'viernes' => 5,
        'sabado' => 6,
        'sábado' => 6,
        'domingo' => 7,
    ];

    /**
     * Busca una expresión de fecha en el texto. Devuelve la fecha ISO y el
     * fragmento exacto que la disparó (para poder sacarlo del título), o null
     * si no encontró nada reconocible.
     *
     * @return array{date: string, match: string}|null
     */
    public static function parse(string $text): ?array
    {
        $base = today();

        foreach (self::matchers() as [$pattern, $resolver]) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                $date = $resolver($m, $base);

                if ($date !== null) {
                    return [
                        'date' => $date->toDateString(),
                        'match' => $m[0][0],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Aplica {@see self::parse()} sobre un título: si encuentra una fecha,
     * devuelve el título sin ese fragmento y la fecha. Si al sacar el fragmento
     * el título queda vacío, no toca nada (la fecha sola no es una tarea).
     *
     * @return array{title: string, date: string}|null
     */
    public static function extract(string $title): ?array
    {
        $found = self::parse($title);

        if ($found === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s{2,}/u', ' ', str_replace($found['match'], ' ', $title)));

        // Sacarle «para el» / «el» que suele quedar colgando delante de la fecha.
        $clean = trim(preg_replace('/\b(para\s+el|para|el)\s*$/iu', '', $clean));

        if ($clean === '') {
            return null;
        }

        return ['title' => $clean, 'date' => $found['date']];
    }

    /**
     * @return list<array{0: string, 1: callable(array<int, array{0: string, 1: int}>, Carbon): ?Carbon}>
     */
    private static function matchers(): array
    {
        return [
            // «pasado mañana» va antes que «mañana» para no cortarla por la mitad.
            ['/\bpasado\s+ma(?:ñ|n)ana\b/iu', fn ($m, Carbon $base) => $base->copy()->addDays(2)],
            ['/\bma(?:ñ|n)ana\b/iu', fn ($m, Carbon $base) => $base->copy()->addDay()],
            ['/\bhoy\b/iu', fn ($m, Carbon $base) => $base->copy()],

            // «en 3 días», «en 2 semanas», «en 1 mes».
            ['/\ben\s+(\d{1,3})\s+d(?:í|i)as?\b/iu', fn ($m, Carbon $base) => $base->copy()->addDays((int) $m[1][0])],
            ['/\ben\s+(\d{1,3})\s+semanas?\b/iu', fn ($m, Carbon $base) => $base->copy()->addWeeks((int) $m[1][0])],
            ['/\ben\s+(\d{1,3})\s+mes(?:es)?\b/iu', fn ($m, Carbon $base) => $base->copy()->addMonthsNoOverflow((int) $m[1][0])],
            ['/\ben\s+una\s+semana\b/iu', fn ($m, Carbon $base) => $base->copy()->addWeek()],

            // «la semana que viene» / «la próxima semana» → próximo lunes.
            ['/\b(?:la\s+)?(?:pr(?:ó|o)xima\s+semana|semana\s+que\s+viene)\b/iu', fn ($m, Carbon $base) => self::nextWeekday($base, 1)],

            // Días de la semana, con prefijos opcionales «el / este / próximo».
            ['/\b(?:el\s+|este\s+|pr(?:ó|o)ximo\s+|el\s+pr(?:ó|o)ximo\s+)?(lunes|martes|mi(?:é|e)rcoles|jueves|viernes|s(?:á|a)bado|domingo)\b/iu',
                fn ($m, Carbon $base) => self::nextWeekday($base, self::WEEKDAYS[mb_strtolower($m[1][0])])],

            // Fecha explícita: 15/8, 15/08/2026, 15-8…
            ['/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/u', fn ($m, Carbon $base) => self::explicitDate($m, $base)],
        ];
    }

    private static function nextWeekday(Carbon $base, int $isoDay): Carbon
    {
        $diff = ($isoDay - $base->dayOfWeekIso + 7) % 7;

        return $base->copy()->addDays($diff === 0 ? 7 : $diff);
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $m
     */
    private static function explicitDate(array $m, Carbon $base): ?Carbon
    {
        $day = (int) $m[1][0];
        $month = (int) $m[2][0];
        $year = isset($m[3]) && $m[3][0] !== '' ? (int) $m[3][0] : $base->year;

        if ($year < 100) {
            $year += 2000;
        }

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = Carbon::create($year, $month, $day)->startOfDay();

        // Sin año explícito y ya pasó: se entiende el año que viene.
        if ((! isset($m[3]) || $m[3][0] === '') && $date->lt($base)) {
            $date->addYear();
        }

        return $date;
    }
}
