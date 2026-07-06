// Amparo Basurto — comportamiento de cliente mínimo sobre el Alpine que trae Livewire.

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

    // Tablero de Queens: toda la interacción (marcar, poner reina, detectar
    // conflictos, cronómetro y victoria) vive en el cliente. El servidor solo
    // arma el tablero y lo pasa por config.regions; acá nunca se conoce la
    // solución, así que no hay forma de "espiarla" desde el navegador.
    Alpine.data('queens', (config = {}) => ({
        size: 8,
        regions: config.regions || [],
        marks: [], // 0 vacía · 1 marca (X) · 2 reina
        badCells: new Set(),
        won: false,
        elapsed: 0,
        startedAt: null,
        timer: null,

        init() {
            this.reset();
        },

        destroy() {
            this.stopTimer();
        },

        // Vacía el tablero sin cambiar el puzzle.
        reset() {
            this.marks = Array.from({ length: this.size }, () => Array(this.size).fill(0));
            this.badCells = new Set();
            this.won = false;
            this.elapsed = 0;
            this.startedAt = null;
            this.stopTimer();
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

        // Un toque cicla vacía → marca → reina → vacía (como el juego original).
        cycle(r, c) {
            if (this.won) return;
            if (!this.startedAt) this.startTimer();
            this.marks[r][c] = (this.marks[r][c] + 1) % 3;
            this.check();
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
            this.won = bad.size === 0 && queens.length === this.size;
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
            const estado = this.marks[r][c] === 2 ? 'reina' : this.marks[r][c] === 1 ? 'marcada' : 'vacía';
            return 'Fila ' + (r + 1) + ', columna ' + (c + 1) + ', ' + estado;
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
