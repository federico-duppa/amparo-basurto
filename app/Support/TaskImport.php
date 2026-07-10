<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Interpreta una lista de tareas pegada como texto plano (una por línea) para
 * el alta en bulto: «Título | vence: 2026-08-01 | repite: semanal | urgente».
 * Está pensado para que una IA genere el plan con la consigna de
 * {@see self::aiPrompt()} y la respuesta se pegue tal cual en Amparo.
 */
class TaskImport
{
    /** Cuántas tareas se aceptan por importación. */
    public const MAX_LINES = 100;

    /** Sinónimos aceptados para cada intervalo de repetición. */
    private const REPEATS = [
        'diaria' => 'diaria',
        'diario' => 'diaria',
        'semanal' => 'semanal',
        'mensual' => 'mensual',
        'anual' => 'anual',
    ];

    /**
     * Parsea el texto pegado. Devuelve una fila por línea con contenido, en
     * orden, con `error` cargado cuando la línea no se entendió (una fila con
     * error no garantiza el resto de sus campos).
     *
     * @return list<array{line: int, raw: string, title: string, due: ?string, repeat: ?string, urgent: bool, important: bool, notes: ?string, tags: list<string>, error: ?string}>
     */
    public static function parse(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\R/u', $text) as $index => $line) {
            $clean = trim($line);

            if ($clean === '') {
                continue;
            }

            // Tolerar viñetas, numeración y casilleros de markdown: las IA
            // suelen mandarlos aunque se les pida texto plano.
            $clean = preg_replace('/^(?:[-*•]|\d{1,3}[.)])\s+/u', '', $clean);
            $clean = preg_replace('/^\[[ xX]?\]\s*/u', '', $clean);

            $rows[] = self::parseLine($index + 1, trim($clean));
        }

        return $rows;
    }

    /**
     * La consigna para pegarle a una IA, para que devuelva el plan de tareas
     * en el formato que este parser entiende.
     */
    public static function aiPrompt(): string
    {
        $hoy = today()->format('d/m/Y');

        return <<<CONSIGNA
        Armame un plan de tareas para: [contá acá tu proyecto o rutina].

        Respondé solo con la lista de tareas, una por línea, en texto plano (sin encabezados, sin numeración, sin explicaciones), con este formato:

        Título de la tarea | vence: AAAA-MM-DD | repite: semanal | urgente | importante | notas: detalle breve | etiquetas: casa, higiene

        Reglas del formato:
        - Lo único obligatorio es el título; los demás campos son opcionales y van separados con «|».
        - vence: la fecha en formato AAAA-MM-DD (también sirve DD/MM/AAAA).
        - repite: solo puede ser diaria, semanal, mensual o anual, y siempre acompañada de su primer vence:.
        - urgente e importante van como palabras sueltas, solo cuando corresponden.
        - notas: texto corto opcional. etiquetas: separadas por coma.
        - Hoy es {$hoy}; tomalo como referencia para las fechas.
        CONSIGNA;
    }

    /**
     * @return array{line: int, raw: string, title: string, due: ?string, repeat: ?string, urgent: bool, important: bool, notes: ?string, tags: list<string>, error: ?string}
     */
    private static function parseLine(int $number, string $line): array
    {
        $row = [
            'line' => $number,
            'raw' => $line,
            'title' => '',
            'due' => null,
            'repeat' => null,
            'urgent' => false,
            'important' => false,
            'notes' => null,
            'tags' => [],
            'error' => null,
        ];

        $segments = array_map('trim', explode('|', $line));
        $row['title'] = array_shift($segments);

        if ($row['title'] === '') {
            return self::failed($row, 'Le falta el título.');
        }

        if (mb_strlen($row['title']) > 255) {
            return self::failed($row, 'El título es muy largo — probá resumirlo.');
        }

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (preg_match('/^(?:vence|fecha)\s*:\s*(.+)$/iu', $segment, $m)) {
                $date = self::parseDate(trim($m[1]));

                if ($date === null) {
                    return self::failed($row, 'Esa fecha no me cierra: «'.trim($m[1]).'».');
                }

                $row['due'] = $date;

                continue;
            }

            if (preg_match('/^(?:repite|se repite|repetir)\s*:\s*(.+)$/iu', $segment, $m)) {
                $repeat = self::REPEATS[mb_strtolower(trim($m[1]))] ?? null;

                if ($repeat === null) {
                    return self::failed($row, 'Esa repetición no la conozco: «'.trim($m[1]).'». Puede ser diaria, semanal, mensual o anual.');
                }

                $row['repeat'] = $repeat;

                continue;
            }

            if (preg_match('/^urgente$/iu', $segment)) {
                $row['urgent'] = true;

                continue;
            }

            if (preg_match('/^importante$/iu', $segment)) {
                $row['important'] = true;

                continue;
            }

            if (preg_match('/^notas?\s*:\s*(.+)$/iu', $segment, $m)) {
                $notes = trim($m[1]);

                if (mb_strlen($notes) > 2000) {
                    return self::failed($row, 'Esa nota es muy larga. Guardá lo esencial.');
                }

                $row['notes'] = $notes;

                continue;
            }

            if (preg_match('/^etiquetas?\s*:\s*(.+)$/iu', $segment, $m)) {
                foreach (explode(',', $m[1]) as $tag) {
                    if (! self::pushTag($row, ltrim(trim($tag), '#'))) {
                        return self::failed($row, 'La etiqueta «'.ltrim(trim($tag), '#').'» es muy larga.');
                    }
                }

                continue;
            }

            // Etiquetas sueltas estilo «#casa #higiene».
            if (str_starts_with($segment, '#')) {
                $sueltas = preg_split('/\s+/u', $segment);

                if (collect($sueltas)->every(fn ($tag) => str_starts_with($tag, '#'))) {
                    foreach ($sueltas as $tag) {
                        if (! self::pushTag($row, ltrim($tag, '#'))) {
                            return self::failed($row, 'La etiqueta «'.ltrim($tag, '#').'» es muy larga.');
                        }
                    }

                    continue;
                }
            }

            return self::failed($row, 'No entendí «'.$segment.'».');
        }

        if ($row['repeat'] !== null && $row['due'] === null) {
            return self::failed($row, 'Para repetirse necesita una fecha en «vence:».');
        }

        return $row;
    }

    /** Acepta fechas ISO (AAAA-MM-DD) y locales (DD/MM/AAAA), estrictas. */
    private static function parseDate(string $value): ?string
    {
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y'] as $format) {
            if (! Carbon::canBeCreatedFromFormat($value, $format)) {
                continue;
            }

            $date = Carbon::createFromFormat($format, $value);

            // Carbon desborda fechas imposibles (31/02 → 3/3): sólo vale si el
            // ida y vuelta devuelve exactamente lo pegado.
            if ($date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /** Suma una etiqueta a la fila si es válida; false sólo si es muy larga. */
    private static function pushTag(array &$row, string $tag): bool
    {
        if ($tag === '') {
            return true;
        }

        if (mb_strlen($tag) > 40) {
            return false;
        }

        $key = mb_strtolower($tag);

        if (! collect($row['tags'])->contains(fn ($t) => mb_strtolower($t) === $key)) {
            $row['tags'][] = $tag;
        }

        return true;
    }

    private static function failed(array $row, string $error): array
    {
        $row['error'] = $error;

        return $row;
    }
}
