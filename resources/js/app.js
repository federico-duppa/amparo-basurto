// Amparo Basurto — comportamiento de cliente mínimo sobre el Alpine que trae Livewire.

import focus from '@alpinejs/focus';

import { generateDailyQueensRegions, generateHardQueensRegions, nextDeduction, solveQueens } from './queens';
import { EMPTY, generateDailySolYLuna, generateHardSolYLuna, LUNA, N as SYL_N, nextDeduction as nextSolYLunaDeduction, SOL } from './solyluna';

// Nombres de los tintes de región del tablero de Queens (--color-q1..q8, mismo
// orden que los tokens del tema) para que la pista pueda decir "el color arena".
const QUEENS_REGION_LABELS = ['arena', 'greda', 'salvia', 'eucalipto', 'malva', 'mostaza', 'terracota', 'piedra'];

document.addEventListener('alpine:init', () => {
    // Plugin Focus (x-trap): atrapa el foco del teclado dentro de los diálogos
    // (la hoja del date picker) mientras están abiertos.
    Alpine.plugin(focus);

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

    // Tablero de Sol y luna: igual que Queens, la partida se genera y se juega
    // en el navegador (generateSolYLunaPuzzle en resources/js/solyluna.js). El
    // servidor solo entra al ganar, para anotar el tiempo y la racha.
    Alpine.data('solyluna', (opts = {}) => ({
        N: SYL_N,
        puzzle: null,
        board: [],
        badCells: new Set(),
        won: false,
        elapsed: 0,
        startedAt: null,
        timer: null,
        muted: false,
        audioCtx: null,
        // Puzzle del día y números del usuario: los sirve el componente
        // Livewire al cargar y recordWin los refresca al ganar.
        mode: 'daily', // 'daily' (el mismo para todos, sostiene la racha) | 'free'
        dailyDate: opts.date ?? null,
        dailySolved: !!opts.dailySolved,
        dailySeconds: opts.dailySeconds ?? null,
        streak: opts.streak ?? 0,
        best: opts.best ?? null,
        // Pila de deshacer: una foto del tablero por acción.
        history: [],
        // Pista: casillas resaltadas + cartelito. 'cell' señala dónde va un
        // símbolo (lo ponés vos); 'error' marca algo mal puesto.
        hintCells: [],
        hintKind: null,
        hintMessage: '',
        // Vínculos indexados por casilla para pintarlos rápido en el template.
        links: {},

        init() {
            this.muted = localStorage.getItem('solyluna-muted') === '1';
            // Se abre en el puzzle del día, salvo que ya esté resuelto.
            if (this.dailyDate && !this.dailySolved) {
                this.startDaily(true);
            } else {
                this.startFree(true);
            }
        },

        destroy() {
            this.stopTimer();
        },

        // El puzzle del día: fijo por fecha, igual para todos.
        startDaily(force = false) {
            if (!force && this.mode === 'daily') return;
            this.mode = 'daily';
            this.setPuzzle(generateDailySolYLuna(this.dailyDate));
        },

        startFree(force = false) {
            if (!force && this.mode === 'free') return;
            this.newGame();
        },

        newGame() {
            this.mode = 'free';
            this.setPuzzle(generateHardSolYLuna());
        },

        setPuzzle(puzzle) {
            this.puzzle = puzzle;
            this.links = {};
            for (const k of this.puzzle.constraints) {
                this.links[k.r + ',' + k.c + ',' + k.dir] = k.kind === 'eq' ? '=' : '×';
            }
            this.resetBoard();
        },

        // Vacía lo jugado sin cambiar el puzzle (los dados quedan).
        vaciar() {
            this.resetBoard();
        },

        resetBoard() {
            this.board = this.puzzle.givens.map((row) => row.slice());
            this.badCells = new Set();
            this.won = false;
            this.history = [];
            this.clearHint();
            this.elapsed = 0;
            this.startedAt = null;
            this.stopTimer();
        },

        isGiven(r, c) {
            return this.puzzle.givens[r][c] !== EMPTY;
        },

        // Un toque cicla la casilla: vacía → sol → luna → vacía. Los dados no
        // se tocan.
        cycle(r, c) {
            if (this.won || this.isGiven(r, c)) return;
            if (!this.startedAt) this.startTimer();
            this.clearHint();
            this.history.push(this.board.map((row) => row.slice()));

            const s = this.board[r][c];
            this.board[r][c] = s === EMPTY ? SOL : s === SOL ? LUNA : EMPTY;
            if (this.board[r][c] === SOL) this.sfxSol();
            if (this.board[r][c] === LUNA) this.sfxLuna();

            this.check();
        },

        get canUndo() {
            return this.history.length > 0 && !this.won;
        },

        undo() {
            if (!this.canUndo) return;
            this.clearHint();
            this.board = this.history.pop();
            this.blip([440, 330], { type: 'triangle', gain: 0.07, dur: 0.09, gap: 0.06 });
            this.check();
        },

        // Marca en rojo lo que rompe una regla a la vista: tres seguidos, más
        // de tres por línea, o un vínculo contradicho.
        check() {
            const bad = new Set();
            const mark = (r, c) => bad.add(r + ',' + c);

            for (let i = 0; i < this.N; i++) {
                for (const cells of [
                    Array.from({ length: this.N }, (_, j) => [i, j]),
                    Array.from({ length: this.N }, (_, j) => [j, i]),
                ]) {
                    for (let j = 0; j + 2 < this.N; j++) {
                        const s = this.board[cells[j][0]][cells[j][1]];
                        if (s !== EMPTY && s === this.board[cells[j + 1][0]][cells[j + 1][1]] && s === this.board[cells[j + 2][0]][cells[j + 2][1]]) {
                            mark(...cells[j]);
                            mark(...cells[j + 1]);
                            mark(...cells[j + 2]);
                        }
                    }
                    for (const s of [SOL, LUNA]) {
                        const ofS = cells.filter(([r, c]) => this.board[r][c] === s);
                        if (ofS.length > this.N / 2) ofS.forEach(([r, c]) => mark(r, c));
                    }
                }
            }

            for (const k of this.puzzle.constraints) {
                const b = k.dir === 'h' ? [k.r, k.c + 1] : [k.r + 1, k.c];
                const va = this.board[k.r][k.c];
                const vb = this.board[b[0]][b[1]];
                if (va === EMPTY || vb === EMPTY) continue;
                if ((k.kind === 'eq' && va !== vb) || (k.kind === 'ne' && va === vb)) {
                    mark(k.r, k.c);
                    mark(...b);
                }
            }

            this.badCells = bad;
            const full = this.board.every((row) => !row.includes(EMPTY));
            const nowWon = full && bad.size === 0;
            if (nowWon && !this.won) {
                this.sfxVictory();
                this.reportWin();
            }
            this.won = nowWon;
            if (this.won) this.stopTimer();
        },

        isBad(r, c) {
            return this.badCells.has(r + ',' + c);
        },

        // --- Tiempos y racha -------------------------------------------------
        // Anota la victoria en el servidor (recordWin del componente Livewire)
        // y refresca los números. Si falla — sin conexión, sesión vencida — la
        // partida no se pierde: solo queda sin anotar.

        reportWin() {
            let wire;
            try {
                wire = this.$wire;
            } catch (e) {
                return;
            }
            if (!wire) return;
            wire.recordWin(this.mode === 'daily' ? 'daily' : 'free', Math.max(1, this.elapsed), this.dailyDate ?? '')
                .then((state) => state && this.applyStats(state))
                .catch(() => {});
        },

        applyStats(state) {
            this.dailySolved = state.dailySolved;
            this.dailySeconds = state.dailySeconds;
            this.streak = state.streak;
            this.best = state.best;
        },

        fmt(seconds) {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },

        get statsLabel() {
            const parts = [];
            if (this.streak > 0) parts.push('Racha: ' + this.streak + (this.streak === 1 ? ' día' : ' días'));
            if (this.best !== null && this.best !== undefined) parts.push('Mejor: ' + this.fmt(this.best));
            return parts.join(' · ');
        },

        get streakLabel() {
            return this.streak + (this.streak === 1 ? ' día seguido' : ' días seguidos');
        },

        get dailyNotice() {
            if (this.mode !== 'daily' || !this.dailySolved || this.won) return '';
            if (this.dailySeconds) return 'El de hoy ya lo resolviste en ' + this.fmt(this.dailySeconds) + '. Jugalo de nuevo si querés; queda anotado el primer tiempo.';
            return 'El de hoy ya lo resolviste.';
        },

        // --- Pista ---------------------------------------------------------
        // Primero busca un error (para eso sí compara contra la solución: es
        // detección, no ayuda); si no hay, pide la próxima deducción al motor
        // y la señala para que la juegues vos.

        clearHint() {
            this.hintCells = [];
            this.hintKind = null;
            this.hintMessage = '';
        },

        isHint(r, c) {
            return this.hintCells.some((cell) => cell.r === r && cell.c === c);
        },

        pista() {
            if (this.won) return;

            for (let r = 0; r < this.N; r++) {
                for (let c = 0; c < this.N; c++) {
                    if (this.board[r][c] !== EMPTY && this.board[r][c] !== this.puzzle.solution[r][c]) {
                        return this.showHint([{ r, c }], 'error', 'Ojo: esa casilla no va así.');
                    }
                }
            }

            const d = nextSolYLunaDeduction(this.board, this.puzzle.constraints);
            if (!d) {
                this.clearHint();
                this.hintMessage = 'Acá no encuentro una deducción simple. Probá una jugada y deshacé si no cierra.';

                return;
            }

            const simbolo = d.symbol === SOL ? 'sol' : 'luna';
            this.showHint(d.cells, 'cell', d.message + (d.cells.length > 1 ? '' : ` (tocá hasta dejar ${simbolo}).`));
        },

        showHint(cells, kind, message) {
            this.hintCells = cells;
            this.hintKind = kind;
            this.hintMessage = message;
            this.blip([587.33, 880], { type: 'sine', gain: 0.09, dur: 0.11, gap: 0.07 });
        },

        // --- Cronómetro ------------------------------------------------------

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

        get timeLabel() {
            return this.fmt(this.elapsed);
        },

        // --- Presentación ----------------------------------------------------

        filledCount() {
            let n = 0;
            for (const row of this.board) for (const s of row) if (s !== EMPTY) n++;
            return n;
        },

        showSol(r, c) {
            return this.board[r][c] === SOL;
        },

        showLuna(r, c) {
            return this.board[r][c] === LUNA;
        },

        linkRight(r, c) {
            return this.links[r + ',' + c + ',h'] ?? null;
        },

        linkDown(r, c) {
            return this.links[r + ',' + c + ',v'] ?? null;
        },

        cellLabel(r, c) {
            const s = this.board[r][c];
            const estado = s === SOL ? 'sol' : s === LUNA ? 'luna' : 'vacía';
            return 'Fila ' + (r + 1) + ', columna ' + (c + 1) + ', ' + estado + (this.isGiven(r, c) ? ', fija' : '');
        },

        get cellList() {
            const out = [];
            for (let r = 0; r < this.N; r++) {
                for (let c = 0; c < this.N; c++) out.push({ r, c });
            }
            return out;
        },

        // --- Sonido (Web Audio, sin archivos, igual que Queens) --------------

        toggleMute() {
            this.muted = !this.muted;
            localStorage.setItem('solyluna-muted', this.muted ? '1' : '0');
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

        sfxSol() {
            this.blip([783.99], { type: 'triangle', gain: 0.09, dur: 0.1 });
        },

        sfxLuna() {
            this.blip([392], { type: 'sine', gain: 0.09, dur: 0.12 });
        },

        sfxVictory() {
            this.blip([523.25, 659.25, 783.99, 1046.5], { type: 'sine', gain: 0.16, dur: 0.26, gap: 0.13 });
        },
    }));

    // Tablero de Queens: la partida vive en el cliente, incluido armarla — no
    // hay llamadas al backend para jugar ni para pedir un tablero nuevo; la
    // generación (sesgada a difícil) está en generateHardQueensRegions()
    // (resources/js/queens.js). El servidor solo entra al ganar, para anotar
    // el tiempo y la racha.
    Alpine.data('queens', (opts = {}) => ({
        size: 8,
        // Puzzle del día y números del usuario: los sirve el componente
        // Livewire al cargar y recordWin los refresca al ganar.
        mode: 'daily', // 'daily' (el mismo para todos, sostiene la racha) | 'free'
        dailyDate: opts.date ?? null,
        dailySolved: !!opts.dailySolved,
        dailySeconds: opts.dailySeconds ?? null,
        streak: opts.streak ?? 0,
        best: opts.best ?? null,
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
        // Pista. La solución se calcula una vez por puzzle y se usa SOLO para
        // detectar errores; las sugerencias salen del motor de deducciones
        // (nextDeduction). hintCells resalta las casillas de la deducción.
        solution: null,
        hintCells: [],
        hintKind: null, // 'queen' (certeza) | 'eliminate' (descartes) | 'error' (algo mal)
        hintMessage: '',

        init() {
            this.muted = localStorage.getItem('queens-muted') === '1';
            // Se abre en el tablero del día, salvo que ya esté resuelto.
            if (this.dailyDate && !this.dailySolved) {
                this.startDaily(true);
            } else {
                this.startFree(true);
            }
        },

        destroy() {
            this.stopTimer();
        },

        // El tablero del día: fijo por fecha, igual para todos.
        startDaily(force = false) {
            if (!force && this.mode === 'daily') return;
            this.mode = 'daily';
            this.setRegions(generateDailyQueensRegions(this.dailyDate));
        },

        startFree(force = false) {
            if (!force && this.mode === 'free') return;
            this.newGame();
        },

        // Arma un tablero nuevo (en el navegador) y arranca de cero. Sesgado a
        // difícil: se generan candidatos y queda el que exige más deducción.
        newGame() {
            this.mode = 'free';
            this.setRegions(generateHardQueensRegions());
        },

        setRegions(regions) {
            this.regions = regions;
            this.solution = null; // otro puzzle: se recalcula cuando pidan pista
            this.clearBoard();
            this.resetTimer();
            this.history = [];
            this.clearHint();
        },

        // Vacía el tablero sin cambiar el puzzle. Es un punto de partida limpio:
        // también borra el historial de deshacer. La solución se mantiene (mismo
        // puzzle).
        vaciar() {
            this.clearBoard();
            this.resetTimer();
            this.history = [];
            this.clearHint();
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
            this.clearHint();
            const prev = this.history.pop();
            this.marks = prev.marks.map((row) => row.slice());
            this.autoCross = prev.autoCross.map((row) => row.slice());
            this.sfxUndo();
            this.check();
        },

        // --- Pista ------------------------------------------------------------
        // Ayuda al que está atascado DEDUCIENDO sobre lo que está a la vista, sin
        // espiar la solución. Primero busca un error que trabe y lo señala (para
        // eso sí compara contra la solución: es detección de errores, no ayuda).
        // Si no hay errores, pide la próxima deducción al motor (nextDeduction):
        // cuando hay una reina 100 % segura la señala (sin ponerla), y si todavía
        // no hay certeza tacha por vos las casillas descartables y explica por qué.

        clearHint() {
            this.hintCells = [];
            this.hintKind = null;
            this.hintMessage = '';
        },

        isHint(r, c) {
            return this.hintCells.some((cell) => cell.r === r && cell.c === c);
        },

        pista() {
            if (this.won) return;
            if (!this.solution) this.solution = solveQueens(this.regions);
            const sol = this.solution;
            if (!sol) return;

            // 1) ¿Hay algo que trabe? Una reina fuera de lugar o una cruz a mano
            // sobre una casilla donde sí va una reina.
            for (let r = 0; r < this.size; r++) {
                for (let c = 0; c < this.size; c++) {
                    if (this.marks[r][c] === 2 && sol[r] !== c) {
                        return this.showHint([{ r, c }], 'error', 'Ojo: una de tus reinas no va donde está.');
                    }
                    if (this.marks[r][c] === 1 && sol[r] === c) {
                        return this.showHint([{ r, c }], 'error', 'Tachaste una casilla donde en realidad va una reina.');
                    }
                }
            }

            // 2) Sin errores: la próxima deducción lógica sobre lo visible.
            const seen = this.marks.map((row, r) => row.map((m, c) => (m === 2 ? 2 : this.displayState(r, c) === 1 ? 1 : 0)));
            const d = nextDeduction(this.regions, seen, QUEENS_REGION_LABELS);

            if (!d) {
                // Con la suposición como último recurso no debería pasar; si pasa,
                // lo decimos sin inventar certezas.
                this.clearHint();
                this.hintMessage = 'Acá no encuentro una deducción simple. Probá suponer una jugada y deshacé si no cierra.';

                return;
            }

            if (d.kind === 'queen') {
                // Certeza lógica total: se señala, la ponés vos.
                return this.showHint(d.cells, 'queen', d.message);
            }

            // Descartes deducidos: la pista los tacha por vos (las cruces son
            // anotaciones; la reina siempre la ponés vos). Es una sola acción:
            // Deshacer las levanta juntas.
            if (!this.startedAt) this.startTimer();
            this.snapshot();
            for (const { r, c } of d.cells) {
                if (this.marks[r][c] === 0) this.marks[r][c] = 1;
            }
            this.showHint(d.cells, 'eliminate', d.message);
        },

        showHint(cells, kind, message) {
            this.hintCells = cells;
            this.hintKind = kind;
            this.hintMessage = message;
            this.sfxHint();
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
            this.clearHint();
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
                this.clearHint();
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
            if (nowWon && !this.won) {
                this.sfxVictory();
                this.reportWin();
            }
            this.won = nowWon;
            if (this.won) this.stopTimer();
        },

        isBad(r, c) {
            return this.badCells.has(r + ',' + c);
        },

        // --- Tiempos y racha ---------------------------------------------------
        // Anota la victoria en el servidor (recordWin del componente Livewire)
        // y refresca los números. Si falla — sin conexión, sesión vencida — la
        // partida no se pierde: solo queda sin anotar.

        reportWin() {
            let wire;
            try {
                wire = this.$wire;
            } catch (e) {
                return;
            }
            if (!wire) return;
            wire.recordWin(this.mode === 'daily' ? 'daily' : 'free', Math.max(1, this.elapsed), this.dailyDate ?? '')
                .then((state) => state && this.applyStats(state))
                .catch(() => {});
        },

        applyStats(state) {
            this.dailySolved = state.dailySolved;
            this.dailySeconds = state.dailySeconds;
            this.streak = state.streak;
            this.best = state.best;
        },

        fmt(seconds) {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },

        get statsLabel() {
            const parts = [];
            if (this.streak > 0) parts.push('Racha: ' + this.streak + (this.streak === 1 ? ' día' : ' días'));
            if (this.best !== null && this.best !== undefined) parts.push('Mejor: ' + this.fmt(this.best));
            return parts.join(' · ');
        },

        get streakLabel() {
            return this.streak + (this.streak === 1 ? ' día seguido' : ' días seguidos');
        },

        get dailyNotice() {
            if (this.mode !== 'daily' || !this.dailySolved || this.won) return '';
            if (this.dailySeconds) return 'El de hoy ya lo resolviste en ' + this.fmt(this.dailySeconds) + '. Jugalo de nuevo si querés; queda anotado el primer tiempo.';
            return 'El de hoy ya lo resolviste.';
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

        sfxHint() {
            // Dos notas suaves que suben: "mirá por acá".
            this.blip([587.33, 880], { type: 'sine', gain: 0.09, dur: 0.11, gap: 0.07 });
        },

        get timeLabel() {
            return this.fmt(this.elapsed);
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
