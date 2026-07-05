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
});
