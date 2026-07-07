// Amparo Basurto — comportamiento de cliente mínimo sobre el Alpine que trae Livewire.

// Generador de tableros de Queens (8x8), en el navegador. Antes lo armaba el
// backend y lo mandaba en el HTML; ahora la partida se calcula acá y no hace
// falta ninguna llamada al servidor para jugar ni para pedir un tablero nuevo.
//
// Devuelve las regiones (int[8][8], índice 0..7) de un tablero con SOLUCIÓN
// ÚNICA y REGIONES CONTIGUAS. En tres pasos: (1) una colocación válida de
// reinas; (2) regiones que crecen como manchas alrededor de cada reina hasta
// cubrir todo; (3) un "tallado" que, mientras exista otra solución además de la
// de origen, pasa una celda de borde a una región vecina para invalidarla,
// cuidando no partir ninguna región. Si un tablero se traba antes de quedar
// único, se descarta y se prueba otro (la tasa de éxito real es ~1 de cada 4).
function generateQueensRegions(maxAttempts = 200) {
    const N = 8;
    const CARVE_STEPS = 300;

    const shuffle = (arr) => {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
    };

    const orthogonal = (r, c) => {
        const out = [];
        for (const [dr, dc] of [[-1, 0], [1, 0], [0, -1], [0, 1]]) {
            const nr = r + dr;
            const nc = c + dc;
            if (nr >= 0 && nr < N && nc >= 0 && nc < N) out.push([nr, nc]);
        }
        return out;
    };

    // Una por fila y columna, con filas contiguas en columnas no contiguas (así
    // ninguna reina toca a otra). Backtracking con orden al azar.
    const randomSolution = () => {
        const cols = [];
        const used = new Set();
        const place = (row, prev) => {
            if (row === N) return true;
            for (const col of shuffle([...Array(N).keys()])) {
                if (used.has(col)) continue;
                if (prev !== null && Math.abs(col - prev) < 2) continue;
                cols[row] = col;
                used.add(col);
                if (place(row + 1, col)) return true;
                used.delete(col);
            }
            return false;
        };
        place(0, null);
        return cols.slice();
    };

    const growRegions = (solution) => {
        const region = Array.from({ length: N }, () => Array(N).fill(-1));
        let frontier = [];
        solution.forEach((col, row) => {
            region[row][col] = row; // índice de región = fila de su reina (0..7)
            frontier.push([row, col]);
        });
        let remaining = N * N - N;
        while (remaining > 0 && frontier.length) {
            const idx = Math.floor(Math.random() * frontier.length);
            const [r, c] = frontier[idx];
            const free = orthogonal(r, c).filter(([nr, nc]) => region[nr][nc] === -1);
            if (!free.length) {
                frontier.splice(idx, 1);
                continue;
            }
            const [nr, nc] = free[Math.floor(Math.random() * free.length)];
            region[nr][nc] = region[r][c];
            frontier.push([nr, nc]);
            remaining--;
        }
        return region;
    };

    // Primera solución distinta de la de origen, o null si no hay otra.
    const firstOtherSolution = (regions, solution) => {
        let found = null;
        const current = [];
        const usedCols = new Set();
        const usedRegions = new Set();
        const solve = (row, prev) => {
            if (found) return;
            if (row === N) {
                if (current.some((v, i) => v !== solution[i]) || current.length !== solution.length) {
                    found = current.slice();
                }
                return;
            }
            for (let col = 0; col < N; col++) {
                if (usedCols.has(col)) continue;
                if (prev !== null && Math.abs(col - prev) < 2) continue;
                const reg = regions[row][col];
                if (usedRegions.has(reg)) continue;
                usedCols.add(col);
                usedRegions.add(reg);
                current[row] = col;
                solve(row + 1, col);
                usedCols.delete(col);
                usedRegions.delete(reg);
                if (found) return;
            }
        };
        solve(0, null);
        return found;
    };

    // ¿La región g sigue conexa si le sacamos la celda (exR,exC)?
    const regionStaysConnectedWithout = (regions, g, exR, exC) => {
        const cells = new Set();
        for (let r = 0; r < N; r++) {
            for (let c = 0; c < N; c++) {
                if (regions[r][c] === g && !(r === exR && c === exC)) cells.add(r * N + c);
            }
        }
        if (!cells.size) return false;
        const start = cells.values().next().value;
        const seen = new Set([start]);
        const stack = [start];
        while (stack.length) {
            const key = stack.pop();
            const r = Math.floor(key / N);
            const c = key % N;
            for (const [nr, nc] of orthogonal(r, c)) {
                const nk = nr * N + nc;
                if (cells.has(nk) && !seen.has(nk)) {
                    seen.add(nk);
                    stack.push(nk);
                }
            }
        }
        return seen.size === cells.size;
    };

    // Muda una celda reina de la otra solución a una región vecina (sin partir la
    // de origen) para invalidar esa solución. Devuelve true si pudo.
    const carveStep = (regions, solution, other) => {
        const rows = [];
        for (let r = 0; r < N; r++) if (other[r] !== solution[r]) rows.push(r);
        shuffle(rows);

        for (const r of rows) {
            const c = other[r];
            if (solution[r] === c) continue;
            const g = regions[r][c];
            const neighborRegions = new Set();
            for (const [nr, nc] of orthogonal(r, c)) {
                const h = regions[nr][nc];
                if (h !== g) neighborRegions.add(h);
            }
            if (!neighborRegions.size) continue;
            if (!regionStaysConnectedWithout(regions, g, r, c)) continue;
            const targets = [...neighborRegions];
            regions[r][c] = targets[Math.floor(Math.random() * targets.length)];
            return true;
        }
        return false;
    };

    const carveToUnique = (regions, solution) => {
        for (let step = 0; step < CARVE_STEPS; step++) {
            const other = firstOtherSolution(regions, solution);
            if (!other) return true; // sin otra solución: es única
            if (!carveStep(regions, solution, other)) return false; // se trabó
        }
        return false;
    };

    let solution = randomSolution();
    let regions = growRegions(solution);
    for (let i = 0; i < maxAttempts; i++) {
        solution = randomSolution();
        regions = growRegions(solution);
        if (carveToUnique(regions, solution)) return regions;
    }
    return regions;
}

document.addEventListener('alpine:init', () => {
    const MESES = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];
    const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const DIAS = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do']; // semana que empieza en lunes (es-AR)

    const pad = (n) => String(n).padStart(2, '0');
    const toISO = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
    const parseISO = (s) => {
        if (!s || typeof s !== 'string') return null;
        const [y, m, d] = s.split('-').map(Number);
        if (!y || !m || !d) return null;
        return { y, m: m - 1, d };
    };

    // Date picker propio, con la voz y la forma de sello de Amparo.
    // El valor se sincroniza con la propiedad Livewire vía x-modelable.
    Alpine.data('dateField', (config = {}) => ({
        value: '',
        open: false,
        mode: 'days', // 'days' | 'months' | 'years'
        viewYear: 0,
        viewMonth: 0,
        preset: config.preset || 'pasado',
        min: config.min || null,
        max: config.max || null,
        startView: config.startView || 'days',
        MESES,
        MESES_CORTOS,
        DIAS,

        init() {
            this.syncView();
            // Si Livewire hidrata el valor después del init (formularios de edición),
            // reacomodamos el calendario mientras esté cerrado.
            this.$watch('value', () => {
                if (!this.open) this.syncView();
            });
        },

        get today() {
            const t = new Date();
            return { y: t.getFullYear(), m: t.getMonth(), d: t.getDate() };
        },

        get todayISO() {
            const t = this.today;
            return toISO(t.y, t.m, t.d);
        },

        syncView() {
            const p = parseISO(this.value) || this.today;
            this.viewYear = p.y;
            this.viewMonth = p.m;
        },

        get display() {
            const p = parseISO(this.value);
            return p ? `${pad(p.d)}/${pad(p.m + 1)}/${p.y}` : '';
        },

        get monthLabel() {
            return this.MESES[this.viewMonth];
        },

        beforeMin(iso) {
            return this.min && iso < this.min;
        },

        afterMax(iso) {
            return this.max && iso > this.max;
        },

        outOfRange(iso) {
            return this.beforeMin(iso) || this.afterMax(iso);
        },

        get days() {
            const first = new Date(this.viewYear, this.viewMonth, 1);
            const lead = (first.getDay() + 6) % 7; // corrimiento para arrancar en lunes
            const count = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
            const cells = [];
            for (let i = 0; i < lead; i++) cells.push({ blank: true });
            for (let d = 1; d <= count; d++) {
                const iso = toISO(this.viewYear, this.viewMonth, d);
                cells.push({
                    d,
                    iso,
                    blank: false,
                    selected: iso === this.value,
                    isToday: iso === this.todayISO,
                    disabled: this.outOfRange(iso),
                });
            }
            // Rellenamos con celdas vacías hasta completar siempre 6 filas (42 celdas):
            // así el alto del calendario no cambia entre meses y el encabezado no se
            // mueve al navegar, permitiendo tocar "mes siguiente" varias veces sin
            // reacomodar el dedo.
            while (cells.length < 42) cells.push({ blank: true });
            return cells;
        },

        get years() {
            const top = this.max ? parseISO(this.max).y : this.today.y + 10;
            const bottom = this.min ? parseISO(this.min).y : top - 120;
            const arr = [];
            for (let y = top; y >= bottom; y--) arr.push(y);
            return arr;
        },

        get chips() {
            const t = this.today;
            const base = new Date(t.y, t.m, t.d);
            const mk = (dt) => toISO(dt.getFullYear(), dt.getMonth(), dt.getDate());
            const addDays = (n) => {
                const x = new Date(base);
                x.setDate(x.getDate() + n);
                return x;
            };
            const addMonths = (n) => {
                const x = new Date(base);
                x.setMonth(x.getMonth() + n);
                return x;
            };

            if (this.preset === 'tarea') {
                const sat = ((6 - base.getDay() + 7) % 7) || 7; // próximo sábado (nunca hoy)
                const mon = ((1 - base.getDay() + 7) % 7) || 7; // próximo lunes
                return [
                    { label: 'Hoy', iso: mk(base) },
                    { label: 'Mañana', iso: mk(addDays(1)) },
                    { label: 'Finde', iso: mk(addDays(sat)) },
                    { label: 'Próx. semana', iso: mk(addDays(mon)) },
                ];
            }
            if (this.preset === 'vencimiento') {
                return [
                    { label: 'En 6 meses', iso: mk(addMonths(6)) },
                    { label: 'En 1 año', iso: mk(addMonths(12)) },
                ];
            }
            if (this.preset === 'nacimiento') {
                return [];
            }
            return [
                { label: 'Hoy', iso: mk(base) },
                { label: 'Ayer', iso: mk(addDays(-1)) },
            ];
        },

        openSheet() {
            this.syncView();
            this.mode = this.startView;
            this.open = true;
            if (this.mode === 'years') this.focusYear();
        },

        close() {
            this.open = false;
            this.mode = 'days';
        },

        pick(iso) {
            if (this.outOfRange(iso)) return;
            this.value = iso;
            this.close();
        },

        clear() {
            this.value = '';
            this.close();
        },

        prevMonth() {
            if (this.viewMonth === 0) {
                this.viewMonth = 11;
                this.viewYear -= 1;
            } else {
                this.viewMonth -= 1;
            }
        },

        nextMonth() {
            if (this.viewMonth === 11) {
                this.viewMonth = 0;
                this.viewYear += 1;
            } else {
                this.viewMonth += 1;
            }
        },

        toMonths() {
            this.mode = 'months';
        },

        toYears() {
            this.mode = 'years';
            this.focusYear();
        },

        pickYear(y) {
            this.viewYear = y;
            this.mode = 'months';
        },

        pickMonth(m) {
            this.viewMonth = m;
            this.mode = 'days';
        },

        focusYear() {
            this.$nextTick(() => {
                const el = this.$refs.yearsBox && this.$refs.yearsBox.querySelector('[data-current="true"]');
                if (el) el.scrollIntoView({ block: 'center' });
            });
        },
    }));

    // Tablero de Queens: TODO vive en el cliente, incluido armar la partida.
    // No hay ninguna llamada al backend para jugar ni para pedir un tablero
    // nuevo; la generación está en generateQueensRegions(), acá arriba.
    Alpine.data('queens', () => ({
        size: 8,
        regions: [],
        // marks = lo que el jugador puso a mano: 0 vacía · 1 cruz · 2 reina.
        marks: [],
        // autoCross[r][c] = cuántas reinas puestas cruzan esa casilla (fila,
        // columna, color o adyacencia). Una casilla se ve cruzada si el jugador
        // la cruzó (marks===1) o si alguna reina la cruza (autoCross>0). Guardar
        // el conteo por casilla permite deshacer al sacar una reina solo lo que
        // esa reina agregó, sin borrar cruces previas ni las de otra reina.
        autoCross: [],
        badCells: new Set(),
        won: false,
        elapsed: 0,
        startedAt: null,
        timer: null,
        // Gesto de deslizar para pintar cruces. mode: 'mark' pinta, 'erase' borra.
        drag: { active: false, moved: false, mode: null, startR: 0, startC: 0 },
        // Un deslizamiento termina con un click sintético sobre la celda inicial;
        // esta bandera hace que ese click no cicle la casilla.
        suppressClick: false,
        // Sonido. Se sintetiza con Web Audio (sin archivos). Se puede silenciar y
        // la preferencia queda guardada.
        muted: false,
        audioCtx: null,
        lastCrossAt: 0,
        // Pila de deshacer: un snapshot (marks + autoCross) por acción. Cada toque
        // es una acción; un deslizamiento entero cuenta como una sola.
        history: [],

        init() {
            this.muted = localStorage.getItem('queens-muted') === '1';
            this.newGame();
        },

        destroy() {
            this.stopTimer();
        },

        // Arma un tablero nuevo (en el navegador) y arranca de cero.
        newGame() {
            this.regions = generateQueensRegions();
            this.clearBoard();
            this.resetTimer();
            this.history = [];
        },

        // Vacía el tablero sin cambiar el puzzle. Es un punto de partida limpio:
        // también borra el historial de deshacer.
        vaciar() {
            this.clearBoard();
            this.resetTimer();
            this.history = [];
        },

        clearBoard() {
            this.marks = Array.from({ length: this.size }, () => Array(this.size).fill(0));
            this.autoCross = Array.from({ length: this.size }, () => Array(this.size).fill(0));
            this.badCells = new Set();
            this.won = false;
        },

        resetTimer() {
            this.elapsed = 0;
            this.startedAt = null;
            this.stopTimer();
        },

        // --- Deshacer ---------------------------------------------------------
        // Antes de cada acción guardamos una foto del tablero; deshacer la
        // restaura, paso a paso hacia atrás.

        snapshot() {
            this.history.push({
                marks: this.marks.map((row) => row.slice()),
                autoCross: this.autoCross.map((row) => row.slice()),
            });
        },

        get canUndo() {
            return this.history.length > 0 && !this.won;
        },

        undo() {
            if (!this.canUndo) return;
            const prev = this.history.pop();
            this.marks = prev.marks.map((row) => row.slice());
            this.autoCross = prev.autoCross.map((row) => row.slice());
            this.sfxUndo();
            this.check();
        },

        startTimer() {
            if (this.timer) return;
            this.startedAt = Date.now() - this.elapsed * 1000;
            this.timer = setInterval(() => {
                this.elapsed = Math.floor((Date.now() - this.startedAt) / 1000);
            }, 250);
        },

        stopTimer() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        // Un toque cicla según lo que se ve: vacía → cruz → reina → vacía. Al
        // poner la reina se cruzan solas las casillas que quedan prohibidas por
        // ella; al sacarla se deshacen exactamente esas.
        cycle(r, c) {
            if (this.won) return;
            if (!this.startedAt) this.startTimer();
            this.snapshot();

            const shown = this.displayState(r, c);
            if (shown === 0) {
                this.marks[r][c] = 1; // vacía → cruz a mano
                this.sfxCross();
            } else if (shown === 1) {
                this.marks[r][c] = 2; // cruz → reina
                this.applyQueenCrosses(r, c);
                this.sfxQueen();
            } else {
                this.marks[r][c] = 0; // reina → vacía
                this.removeQueenCrosses(r, c);
            }

            this.check();
        },

        // --- Gesto de tocar / deslizar ---------------------------------------
        // El toque (tap) y el teclado ciclan la casilla vía el click; deslizar
        // pinta o borra cruces. Distinguimos uno de otro por si el dedo cambió
        // de casilla entre pointerdown y pointerup.

        onPointerDown(r, c) {
            this.suppressClick = false;
            if (this.won) return;
            this.drag = { active: true, moved: false, mode: null, startR: r, startC: c };
        },

        onPointerMove(event) {
            if (!this.drag.active || this.won) return;

            const cell = this.cellFromPoint(event.clientX, event.clientY);
            if (!cell) return;

            const isStart = cell.r === this.drag.startR && cell.c === this.drag.startC;
            if (isStart && ! this.drag.moved) return; // todavía no salió de la casilla inicial

            if (!this.drag.moved) {
                // Primer salto a otra casilla: esto es un deslizamiento. El modo
                // lo fija la casilla donde arrancó (sobre cruz borra, si no pinta).
                // Guardamos una sola foto para todo el trazo (una acción a deshacer).
                this.drag.moved = true;
                if (!this.startedAt) this.startTimer();
                this.snapshot();
                this.drag.mode = this.marks[this.drag.startR][this.drag.startC] === 1 ? 'erase' : 'mark';
                this.paint(this.drag.startR, this.drag.startC);
            }

            this.paint(cell.r, cell.c);
        },

        onPointerUp() {
            if (!this.drag.active) return;
            if (this.drag.moved) this.suppressClick = true; // fue deslizamiento, no toque
            this.drag.active = false;
        },

        onPointerCancel() {
            this.drag.active = false;
            this.drag.moved = false;
        },

        // Click real (toque corto o teclado): cicla, salvo que venga de soltar un
        // deslizamiento.
        onCellClick(r, c) {
            if (this.suppressClick) {
                this.suppressClick = false;

                return;
            }
            this.cycle(r, c);
        },

        // Pinta o borra una cruz a mano. No pisa reinas ni cruces automáticas: el
        // modo 'mark' solo cruza casillas que se ven vacías; 'erase' solo levanta
        // cruces puestas a mano (las que dejó una reina se van al sacar la reina).
        paint(r, c) {
            if (this.drag.mode === 'mark' && this.displayState(r, c) === 0) {
                this.marks[r][c] = 1;
                this.sfxCross();
            } else if (this.drag.mode === 'erase' && this.marks[r][c] === 1) {
                this.marks[r][c] = 0;
            }
        },

        // Casilla del tablero bajo un punto de la pantalla (para seguir el dedo).
        cellFromPoint(x, y) {
            const el = document.elementFromPoint(x, y);
            if (!el) return null;
            const btn = el.closest('[data-cell]');
            if (!btn || !this.$root.contains(btn)) return null;
            const [r, c] = btn.dataset.cell.split(',').map(Number);

            return { r, c };
        },

        queenCount() {
            let n = 0;
            for (let r = 0; r < this.size; r++) {
                for (let c = 0; c < this.size; c++) {
                    if (this.marks[r][c] === 2) n++;
                }
            }
            return n;
        },

        // Marca en rojo toda reina que rompa una regla: misma fila, columna o
        // color que otra, o pegada a otra (incluida la diagonal).
        check() {
            const queens = [];
            for (let r = 0; r < this.size; r++) {
                for (let c = 0; c < this.size; c++) {
                    if (this.marks[r][c] === 2) queens.push([r, c]);
                }
            }

            const bad = new Set();
            for (let i = 0; i < queens.length; i++) {
                for (let j = i + 1; j < queens.length; j++) {
                    const [r1, c1] = queens[i];
                    const [r2, c2] = queens[j];
                    const clash =
                        r1 === r2 ||
                        c1 === c2 ||
                        this.regions[r1][c1] === this.regions[r2][c2] ||
                        (Math.abs(r1 - r2) <= 1 && Math.abs(c1 - c2) <= 1);
                    if (clash) {
                        bad.add(r1 + ',' + c1);
                        bad.add(r2 + ',' + c2);
                    }
                }
            }

            this.badCells = bad;
            const nowWon = bad.size === 0 && queens.length === this.size;
            if (nowWon && !this.won) this.sfxVictory();
            this.won = nowWon;
            if (this.won) this.stopTimer();
        },

        isBad(r, c) {
            return this.badCells.has(r + ',' + c);
        },

        // Fondo por región vía las variables --color-q1..q8 del tema.
        cellBg(r, c) {
            return 'background-color: var(--color-q' + (this.regions[r][c] + 1) + ')';
        },

        // Bordes: hairline dentro de una región, trazo cuero entre regiones y en
        // el marco. Así cada color se lee como un bloque, sin depender solo del tono.
        cellBorder(r, c) {
            const mine = this.regions[r][c];
            const same = (a, b) =>
                a >= 0 && a < this.size && b >= 0 && b < this.size && this.regions[a][b] === mine;
            const line = 'color-mix(in srgb, var(--color-cuero) 16%, transparent)';
            const edge = 'var(--color-cuero)';
            const side = (ok) => (ok ? '1px solid ' + line : '2px solid ' + edge);
            return [
                'border-top:' + side(same(r - 1, c)),
                'border-right:' + side(same(r, c + 1)),
                'border-bottom:' + side(same(r + 1, c)),
                'border-left:' + side(same(r, c - 1)),
            ].join(';');
        },

        cellLabel(r, c) {
            const shown = this.displayState(r, c);
            const estado = shown === 2 ? 'reina' : shown === 1 ? 'marcada' : 'vacía';
            return 'Fila ' + (r + 1) + ', columna ' + (c + 1) + ', ' + estado;
        },

        // Lo que se ve en la casilla: reina (2) manda; si no, cruz (1) ya sea a
        // mano o por una reina; si no, vacía (0).
        displayState(r, c) {
            if (this.marks[r][c] === 2) return 2;
            if (this.marks[r][c] === 1 || this.autoCross[r][c] > 0) return 1;

            return 0;
        },

        showQueen(r, c) {
            return this.marks[r][c] === 2;
        },

        showCross(r, c) {
            return this.marks[r][c] !== 2 && (this.marks[r][c] === 1 || this.autoCross[r][c] > 0);
        },

        // Casillas que una reina en (qr,qc) deja prohibidas: toda su fila, su
        // columna, su color y las que la tocan (incluida la diagonal). Sin la
        // propia. Set de claves para no contar dos veces la misma casilla.
        affectedCells(qr, qc) {
            const keys = new Set();
            const add = (r, c) => {
                if (r < 0 || r >= this.size || c < 0 || c >= this.size) return;
                if (r === qr && c === qc) return;
                keys.add(r + ',' + c);
            };

            for (let i = 0; i < this.size; i++) {
                add(qr, i); // fila
                add(i, qc); // columna
            }
            const region = this.regions[qr][qc];
            for (let r = 0; r < this.size; r++) {
                for (let c = 0; c < this.size; c++) {
                    if (this.regions[r][c] === region) add(r, c); // mismo color
                }
            }
            for (let dr = -1; dr <= 1; dr++) {
                for (let dc = -1; dc <= 1; dc++) {
                    add(qr + dr, qc + dc); // adyacentes (la diagonal es lo nuevo)
                }
            }

            return keys;
        },

        applyQueenCrosses(qr, qc) {
            for (const key of this.affectedCells(qr, qc)) {
                const [r, c] = key.split(',').map(Number);
                this.autoCross[r][c]++;
            }
        },

        removeQueenCrosses(qr, qc) {
            for (const key of this.affectedCells(qr, qc)) {
                const [r, c] = key.split(',').map(Number);
                if (this.autoCross[r][c] > 0) this.autoCross[r][c]--;
            }
        },

        // --- Sonido -----------------------------------------------------------
        // Tonos cortos sintetizados con Web Audio: un tic apagado para la cruz,
        // algo más lindo para la reina y un arpegio al ganar. Sin archivos.

        toggleMute() {
            this.muted = !this.muted;
            localStorage.setItem('queens-muted', this.muted ? '1' : '0');
            if (!this.muted) this.ensureAudio();
        },

        ensureAudio() {
            if (this.muted) return null;
            if (!this.audioCtx) {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return null;
                try {
                    this.audioCtx = new AC();
                } catch (e) {
                    return null;
                }
            }
            if (this.audioCtx.state === 'suspended') this.audioCtx.resume();

            return this.audioCtx;
        },

        // Reproduce una secuencia de notas (una tras otra) con envolvente suave.
        blip(freqs, { type = 'sine', gain = 0.15, dur = 0.12, gap = 0 } = {}) {
            const ctx = this.ensureAudio();
            if (!ctx) return;
            const step = gap || dur * 0.9;
            freqs.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const vol = ctx.createGain();
                const start = ctx.currentTime + i * step;
                osc.type = type;
                osc.frequency.value = freq;
                vol.gain.setValueAtTime(0.0001, start);
                vol.gain.linearRampToValueAtTime(gain, start + 0.008);
                vol.gain.exponentialRampToValueAtTime(0.0001, start + dur);
                osc.connect(vol);
                vol.connect(ctx.destination);
                osc.start(start);
                osc.stop(start + dur + 0.02);
            });
        },

        sfxCross() {
            if (this.muted) return;
            // Al pintar de corrido llegan muchas cruces juntas: no encimamos tics.
            const now = performance.now();
            if (now - this.lastCrossAt < 45) return;
            this.lastCrossAt = now;
            this.blip([180], { type: 'triangle', gain: 0.05, dur: 0.05 });
        },

        sfxQueen() {
            this.blip([659.25, 987.77], { type: 'triangle', gain: 0.12, dur: 0.13, gap: 0.075 });
        },

        sfxVictory() {
            this.blip([523.25, 659.25, 783.99, 1046.5], { type: 'sine', gain: 0.16, dur: 0.26, gap: 0.13 });
        },

        sfxUndo() {
            // Dos notas que bajan: "vuelvo atrás".
            this.blip([440, 330], { type: 'triangle', gain: 0.07, dur: 0.09, gap: 0.06 });
        },

        get timeLabel() {
            const m = Math.floor(this.elapsed / 60);
            const s = this.elapsed % 60;
            return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },

        // Celdas en orden (fila, columna) para un solo x-for sobre el tablero.
        get cellList() {
            const out = [];
            for (let r = 0; r < this.size; r++) {
                for (let c = 0; c < this.size; c++) {
                    out.push({ r, c });
                }
            }
            return out;
        },
    }));
});
