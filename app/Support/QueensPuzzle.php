<?php

namespace App\Support;

/**
 * Generador de tableros de Queens (estilo LinkedIn) de 8x8.
 *
 * Un tablero se divide en 8 regiones de color. Resolverlo es poner exactamente
 * una reina por fila, una por columna y una por región, sin que dos reinas se
 * toquen (tampoco en diagonal). Este generador entrega un tablero con **solución
 * única** y con **regiones contiguas** (cada color es una sola mancha conectada).
 *
 * Cómo lo arma, en tres pasos:
 *  1. Una colocación válida de reinas (la solución de origen).
 *  2. Regiones que crecen como manchas alrededor de cada reina hasta cubrir todo.
 *  3. Un "tallado": mientras exista otra solución además de la de origen, se pasa
 *     una celda de borde a una región vecina para invalidar esa otra solución,
 *     cuidando no partir ninguna región. Casi siempre converge a solución única;
 *     si se traba, se descarta y se prueba con otro tablero.
 *
 * Todo es PHP puro y sin estado: no persiste nada. Cada partida pide un tablero
 * nuevo. El cliente nunca recibe la solución, solo las regiones.
 */
class QueensPuzzle
{
    public const SIZE = 8;

    /** Pasos máximos de tallado por intento antes de descartar el tablero. */
    private const CARVE_STEPS = 300;

    /**
     * Devuelve ['regions' => int[8][8], 'solution' => int[8]].
     *
     * - regions[fila][columna] = índice de región (0..7).
     * - solution[fila] = columna de la reina en esa fila.
     *
     * Reintenta hasta lograr un tablero de solución única; con la tasa de éxito
     * real (~1 de cada 4 intentos) agotar los intentos es astronómicamente
     * improbable. Si aun así pasara, devuelve el último tablero armado —siempre
     * resoluble por su solución de origen.
     */
    public static function generate(int $maxAttempts = 200): array
    {
        $solution = self::randomSolution();
        $regions = self::growRegions($solution);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $solution = self::randomSolution();
            $regions = self::growRegions($solution);

            if (self::carveToUnique($regions, $solution)) {
                return ['regions' => $regions, 'solution' => $solution];
            }
        }

        return ['regions' => $regions, 'solution' => $solution];
    }

    /**
     * Colocación válida de reinas: una por fila y columna (permutación) donde
     * filas contiguas no quedan en columnas contiguas (así ninguna reina toca a
     * otra, ni siquiera en diagonal). Backtracking con orden al azar para variar
     * el tablero; para 8x8 siempre existe al menos una.
     *
     * @return int[8] columna de la reina por fila
     */
    private static function randomSolution(): array
    {
        $n = self::SIZE;
        $placed = [];

        $place = function (int $row, ?int $prevCol) use (&$place, $n, &$placed): bool {
            if ($row === $n) {
                return true;
            }

            $order = range(0, $n - 1);
            shuffle($order);

            foreach ($order as $col) {
                if (in_array($col, $placed, true)) {
                    continue; // columna ya ocupada
                }
                if ($prevCol !== null && abs($col - $prevCol) < 2) {
                    continue; // tocaría a la reina de la fila anterior
                }

                $placed[$row] = $col;
                if ($place($row + 1, $col)) {
                    return true;
                }
                unset($placed[$row]);
            }

            return false;
        };

        $place(0, null);
        ksort($placed);

        return array_values($placed);
    }

    /**
     * Hace crecer 8 regiones contiguas, una por reina, hasta cubrir el tablero.
     * Crecimiento por frontera al azar (BFS multi-origen): como solo se agregan
     * celdas pegadas a la región, cada región queda conexa.
     *
     * @param  int[8]  $solution
     * @return int[8][8] índice de región por celda
     */
    private static function growRegions(array $solution): array
    {
        $n = self::SIZE;
        $region = array_fill(0, $n, array_fill(0, $n, -1));
        $frontier = [];

        foreach ($solution as $row => $col) {
            $region[$row][$col] = $row; // índice de región = fila de su reina (0..7, distintos)
            $frontier[] = [$row, $col];
        }

        $remaining = $n * $n - $n;

        while ($remaining > 0 && $frontier !== []) {
            $idx = array_rand($frontier);
            [$r, $c] = $frontier[$idx];

            $free = self::freeNeighbors($region, $r, $c);
            if ($free === []) {
                array_splice($frontier, $idx, 1); // celda rodeada: sale de la frontera

                continue;
            }

            [$nr, $nc] = $free[array_rand($free)];
            $region[$nr][$nc] = $region[$r][$c];
            $frontier[] = [$nr, $nc];
            $remaining--;
        }

        return $region;
    }

    /**
     * Talla las regiones hasta que la única solución sea la de origen. En cada
     * paso busca otra solución y le "roba" a su reina la celda que la habilita,
     * pasándola a una región vecina, siempre que eso no parta ninguna región.
     *
     * Devuelve true si quedó con solución única; false si se trabó (el llamador
     * descarta el tablero y prueba otro). Modifica $regions por referencia.
     *
     * @param  int[8][8]  $regions
     * @param  int[8]  $solution
     */
    private static function carveToUnique(array &$regions, array $solution): bool
    {
        for ($step = 0; $step < self::CARVE_STEPS; $step++) {
            $other = self::firstOtherSolution($regions, $solution);
            if ($other === null) {
                return true; // no hay otra solución: es única
            }

            if (! self::carveStep($regions, $solution, $other)) {
                return false; // no se pudo invalidar esta otra solución sin partir regiones
            }
        }

        return false;
    }

    /**
     * Un paso de tallado: elige, entre las filas donde $other difiere de la
     * solución, una celda reina de $other que se pueda mudar a una región vecina
     * sin partir su región de origen, y la muda. Así $other pasa a tener dos
     * reinas en una región y deja de ser válida.
     */
    private static function carveStep(array &$regions, array $solution, array $other): bool
    {
        $n = self::SIZE;

        $rows = [];
        for ($r = 0; $r < $n; $r++) {
            if ($other[$r] !== $solution[$r]) {
                $rows[] = $r;
            }
        }
        shuffle($rows);

        foreach ($rows as $r) {
            $c = $other[$r];
            if ($solution[$r] === $c) {
                continue; // nunca movemos una celda que es reina de la solución de origen
            }

            $g = $regions[$r][$c];

            $neighborRegions = [];
            foreach (self::orthogonal($r, $c) as [$nr, $nc]) {
                $h = $regions[$nr][$nc];
                if ($h !== $g) {
                    $neighborRegions[$h] = true;
                }
            }
            if ($neighborRegions === []) {
                continue; // celda interior de su región: sin vecino a donde mudarla
            }

            if (! self::regionStaysConnectedWithout($regions, $g, $r, $c)) {
                continue; // mudarla partiría la región de origen
            }

            $targets = array_keys($neighborRegions);
            $regions[$r][$c] = $targets[array_rand($targets)];

            return true;
        }

        return false;
    }

    /**
     * Primera solución del tablero distinta de $solution, o null si no hay otra.
     * Mismo backtracking que el contador, cortando apenas encuentra una.
     *
     * @return int[8]|null
     */
    private static function firstOtherSolution(array $regions, array $solution): ?array
    {
        $n = self::SIZE;
        $found = null;
        $current = [];
        $usedCols = [];
        $usedRegions = [];

        $solve = function (int $row, ?int $prevCol) use (&$solve, &$found, $n, $regions, &$usedCols, &$usedRegions, &$current, $solution): void {
            if ($found !== null) {
                return;
            }
            if ($row === $n) {
                $candidate = array_values($current);
                if ($candidate !== $solution) {
                    $found = $candidate;
                }

                return;
            }

            for ($col = 0; $col < $n; $col++) {
                if (isset($usedCols[$col])) {
                    continue;
                }
                if ($prevCol !== null && abs($col - $prevCol) < 2) {
                    continue;
                }
                $reg = $regions[$row][$col];
                if (isset($usedRegions[$reg])) {
                    continue;
                }

                $usedCols[$col] = true;
                $usedRegions[$reg] = true;
                $current[$row] = $col;
                $solve($row + 1, $col);
                unset($usedCols[$col], $usedRegions[$reg], $current[$row]);

                if ($found !== null) {
                    return;
                }
            }
        };

        $solve(0, null);

        return $found;
    }

    /**
     * Cuenta soluciones válidas del tablero, cortando al llegar a $cap. Se usa en
     * los tests; el generador se apoya en firstOtherSolution().
     */
    public static function countSolutions(array $regions, int $cap = 2): int
    {
        $n = self::SIZE;
        $count = 0;
        $usedCols = [];
        $usedRegions = [];

        $solve = function (int $row, ?int $prevCol) use (&$solve, &$count, $n, $regions, &$usedCols, &$usedRegions, $cap): void {
            if ($count >= $cap) {
                return;
            }
            if ($row === $n) {
                $count++;

                return;
            }

            for ($col = 0; $col < $n; $col++) {
                if (isset($usedCols[$col])) {
                    continue;
                }
                if ($prevCol !== null && abs($col - $prevCol) < 2) {
                    continue;
                }
                $reg = $regions[$row][$col];
                if (isset($usedRegions[$reg])) {
                    continue;
                }

                $usedCols[$col] = true;
                $usedRegions[$reg] = true;
                $solve($row + 1, $col);
                unset($usedCols[$col], $usedRegions[$reg]);

                if ($count >= $cap) {
                    return;
                }
            }
        };

        $solve(0, null);

        return $count;
    }

    /**
     * ¿La región $g sigue conexa si le sacamos la celda ($exR, $exC)? BFS sobre
     * las celdas restantes de la región desde cualquiera de ellas.
     */
    private static function regionStaysConnectedWithout(array $regions, int $g, int $exR, int $exC): bool
    {
        $n = self::SIZE;
        $cells = [];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($regions[$r][$c] === $g && ! ($r === $exR && $c === $exC)) {
                    $cells[$r * $n + $c] = true;
                }
            }
        }

        if ($cells === []) {
            return false;
        }

        $start = array_key_first($cells);
        $seen = [$start => true];
        $stack = [$start];

        while ($stack !== []) {
            $key = array_pop($stack);
            $r = intdiv($key, $n);
            $c = $key % $n;
            foreach (self::orthogonal($r, $c) as [$nr, $nc]) {
                $nk = $nr * $n + $nc;
                if (isset($cells[$nk]) && ! isset($seen[$nk])) {
                    $seen[$nk] = true;
                    $stack[] = $nk;
                }
            }
        }

        return count($seen) === count($cells);
    }

    /** Celdas ortogonales dentro del tablero. */
    private static function orthogonal(int $r, int $c): array
    {
        $n = self::SIZE;
        $out = [];
        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dr, $dc]) {
            $nr = $r + $dr;
            $nc = $c + $dc;
            if ($nr >= 0 && $nr < $n && $nc >= 0 && $nc < $n) {
                $out[] = [$nr, $nc];
            }
        }

        return $out;
    }

    /** Vecinas ortogonales todavía sin región (para el crecimiento). */
    private static function freeNeighbors(array $region, int $r, int $c): array
    {
        $free = [];
        foreach (self::orthogonal($r, $c) as [$nr, $nc]) {
            if ($region[$nr][$nc] === -1) {
                $free[] = [$nr, $nc];
            }
        }

        return $free;
    }
}
