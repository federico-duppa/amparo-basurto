// Sol y luna — generador y motor de deducciones. Módulo puro (sin Alpine ni
// DOM) para poder testearlo con Vitest.
//
// El tablero es una grilla de 6×6 que se llena con soles (1) y lunas (2):
//   - nunca tres iguales seguidos, ni en fila ni en columna;
//   - cada fila y cada columna termina con tres de cada uno;
//   - un vínculo `=` entre dos casillas vecinas obliga a que sean iguales,
//     y un `×` a que sean distintas.
//
// Un puzzle es { solution, givens, constraints }: la solución completa, las
// casillas que vienen dadas (0 = libre) y los vínculos visibles. El generador
// garantiza solución única; las pistas deducen sobre lo visible.

export const N = 6;
export const EMPTY = 0;
export const SOL = 1;
export const LUNA = 2;

const HALF = N / 2;

const opposite = (s) => (s === SOL ? LUNA : SOL);

const shuffled = (arr) => {
    const out = arr.slice();
    for (let i = out.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [out[i], out[j]] = [out[j], out[i]];
    }
    return out;
};

const cloneGrid = (grid) => grid.map((row) => row.slice());

const emptyGrid = () => Array.from({ length: N }, () => Array(N).fill(EMPTY));

// --- Reglas -----------------------------------------------------------------

// ¿Poner `s` en (r,c) rompe algo, mirando solo lo ya puesto? Se usa durante el
// backtracking (relleno en orden fila por fila, así alcanza con mirar atrás).
function fitsBackwards(grid, r, c, s) {
    // Tres seguidos hacia la izquierda / hacia arriba.
    if (c >= 2 && grid[r][c - 1] === s && grid[r][c - 2] === s) return false;
    if (r >= 2 && grid[r - 1][c] === s && grid[r - 2][c] === s) return false;

    // Mitades: nunca más de tres por fila ni por columna.
    let rowCount = 1;
    for (let i = 0; i < c; i++) if (grid[r][i] === s) rowCount++;
    if (rowCount > HALF) return false;

    let colCount = 1;
    for (let i = 0; i < r; i++) if (grid[i][c] === s) colCount++;
    if (colCount > HALF) return false;

    return true;
}

/** Genera una grilla completa válida, al azar. */
export function generateFullGrid() {
    const grid = emptyGrid();

    const fill = (idx) => {
        if (idx === N * N) return true;
        const r = Math.floor(idx / N);
        const c = idx % N;
        for (const s of shuffled([SOL, LUNA])) {
            if (fitsBackwards(grid, r, c, s)) {
                grid[r][c] = s;
                if (fill(idx + 1)) return true;
                grid[r][c] = EMPTY;
            }
        }
        return false;
    };

    fill(0);
    return grid;
}

// Los vínculos tocando (r,c) cuya otra casilla ya está puesta en el orden
// fila por fila (la de la izquierda o la de arriba).
function violatesConstraintsBackwards(grid, r, c, s, constraints) {
    for (const k of constraints) {
        let other = null;
        if (k.dir === 'h' && k.r === r && k.c === c - 1) other = grid[r][c - 1];
        if (k.dir === 'v' && k.r === r - 1 && k.c === c) other = grid[r - 1][c];
        if (other === null || other === EMPTY) continue;
        if (k.kind === 'eq' && other !== s) return true;
        if (k.kind === 'ne' && other === s) return true;
    }
    return false;
}

/**
 * Cuenta soluciones de un puzzle (dados + vínculos), cortando en `cap`.
 * Independiente del generador: es la vara de la unicidad.
 */
export function countSolutions(givens, constraints, cap = 2) {
    const grid = cloneGrid(givens);
    let count = 0;

    const fill = (idx) => {
        if (count >= cap) return;
        if (idx === N * N) {
            count++;
            return;
        }
        const r = Math.floor(idx / N);
        const c = idx % N;

        if (grid[r][c] !== EMPTY) {
            fill(idx + 1);
            return;
        }

        for (const s of [SOL, LUNA]) {
            if (!fitsBackwards(grid, r, c, s)) continue;
            if (violatesConstraintsBackwards(grid, r, c, s, constraints)) continue;
            grid[r][c] = s;
            fill(idx + 1);
            grid[r][c] = EMPTY;
        }
    };

    fill(0);
    return count;
}

/**
 * Genera un puzzle con solución única: una grilla completa, y el menor juego
 * de dados + vínculos (elegidos al azar) que la deja como única salida. Se
 * minimizan primero los dados, así el puzzle queda sesgado a resolverse por
 * vínculos, que son el sabor del juego.
 */
export function generateSolYLunaPuzzle() {
    const solution = generateFullGrid();

    // Candidatos: cada casilla como dado, cada par vecino como vínculo.
    const givenPool = [];
    for (let r = 0; r < N; r++) {
        for (let c = 0; c < N; c++) givenPool.push({ type: 'given', r, c });
    }
    const constraintPool = [];
    for (let r = 0; r < N; r++) {
        for (let c = 0; c < N; c++) {
            if (c + 1 < N) constraintPool.push({ type: 'constraint', r, c, dir: 'h', kind: solution[r][c] === solution[r][c + 1] ? 'eq' : 'ne' });
            if (r + 1 < N) constraintPool.push({ type: 'constraint', r, c, dir: 'v', kind: solution[r][c] === solution[r + 1][c] ? 'eq' : 'ne' });
        }
    }

    // Arrancamos con un juego de pistas de sobra y lo achicamos.
    let clues = shuffled([...shuffled(givenPool).slice(0, 14), ...shuffled(constraintPool).slice(0, 14)]);

    const materialize = (set) => {
        const givens = emptyGrid();
        const constraints = [];
        for (const clue of set) {
            if (clue.type === 'given') givens[clue.r][clue.c] = solution[clue.r][clue.c];
            else constraints.push(clue);
        }
        return { givens, constraints };
    };

    // Si con eso no alcanza (raro), sumamos candidatos hasta que sea único.
    let extras = shuffled([...givenPool, ...constraintPool]).filter((c) => !clues.includes(c));
    while (true) {
        const { givens, constraints } = materialize(clues);
        if (countSolutions(givens, constraints) === 1) break;
        clues.push(extras.shift());
    }

    // Minimizar: probar sacar cada pista (dados primero) y quedarnos sin ella
    // si el puzzle sigue siendo único.
    const ordered = [...clues.filter((c) => c.type === 'given'), ...clues.filter((c) => c.type === 'constraint')];
    for (const clue of ordered) {
        const without = clues.filter((c) => c !== clue);
        const { givens, constraints } = materialize(without);
        if (countSolutions(givens, constraints) === 1) clues = without;
    }

    const { givens, constraints } = materialize(clues);
    return { solution, givens, constraints };
}

// --- Deducciones ------------------------------------------------------------

const NOMBRES = { [SOL]: 'sol', [LUNA]: 'luna' };
const PLURALES = { [SOL]: 'soles', [LUNA]: 'lunas' };

const lineCells = (kind, i) =>
    Array.from({ length: N }, (_, j) => (kind === 'row' ? { r: i, c: j } : { r: j, c: i }));

const lineLabel = (kind, i) => (kind === 'row' ? 'esa fila' : 'esa columna');

/**
 * La próxima deducción lógica sobre lo visible, con las técnicas del juego en
 * orden de dificultad. Devuelve { cells, symbol, message, technique } — las
 * casillas donde va `symbol` con certeza — o null si no encuentra nada.
 * La solución no se mira: todo sale de las reglas.
 */
export function nextDeduction(board, constraints) {
    for (const technique of [dosJuntos, sandwich, conteo, vinculo]) {
        const d = technique(board, constraints);
        if (d) return d;
    }

    return suposicion(board, constraints);
}

function dosJuntos(board) {
    for (const kind of ['row', 'col']) {
        for (let i = 0; i < N; i++) {
            const cells = lineCells(kind, i);
            for (let j = 0; j + 1 < N; j++) {
                const a = board[cells[j].r][cells[j].c];
                const b = board[cells[j + 1].r][cells[j + 1].c];
                if (a === EMPTY || a !== b) continue;
                for (const k of [j - 1, j + 2]) {
                    if (k < 0 || k >= N) continue;
                    const cell = cells[k];
                    if (board[cell.r][cell.c] === EMPTY) {
                        return {
                            cells: [cell],
                            symbol: opposite(a),
                            technique: 'dos-juntos',
                            message: `Dos ${PLURALES[a]} juntos: la casilla pegada tiene que ser ${NOMBRES[opposite(a)]}, o serían tres seguidos.`,
                        };
                    }
                }
            }
        }
    }
    return null;
}

function sandwich(board) {
    for (const kind of ['row', 'col']) {
        for (let i = 0; i < N; i++) {
            const cells = lineCells(kind, i);
            for (let j = 0; j + 2 < N; j++) {
                const a = board[cells[j].r][cells[j].c];
                const mid = cells[j + 1];
                const b = board[cells[j + 2].r][cells[j + 2].c];
                if (a === EMPTY || a !== b || board[mid.r][mid.c] !== EMPTY) continue;
                return {
                    cells: [mid],
                    symbol: opposite(a),
                    technique: 'sandwich',
                    message: `Entre dos ${PLURALES[a]} va ${NOMBRES[opposite(a)]}: si no, quedarían tres seguidos.`,
                };
            }
        }
    }
    return null;
}

function conteo(board) {
    for (const kind of ['row', 'col']) {
        for (let i = 0; i < N; i++) {
            const cells = lineCells(kind, i);
            for (const s of [SOL, LUNA]) {
                const placed = cells.filter(({ r, c }) => board[r][c] === s).length;
                const empties = cells.filter(({ r, c }) => board[r][c] === EMPTY);
                if (placed === HALF && empties.length > 0) {
                    return {
                        cells: empties,
                        symbol: opposite(s),
                        technique: 'conteo',
                        message: `${lineLabel(kind, i)[0].toUpperCase() + lineLabel(kind, i).slice(1)} ya tiene sus tres ${PLURALES[s]}: lo que queda va con ${PLURALES[opposite(s)]}.`,
                    };
                }
            }
        }
    }
    return null;
}

function vinculo(board, constraints) {
    for (const k of constraints) {
        const a = { r: k.r, c: k.c };
        const b = k.dir === 'h' ? { r: k.r, c: k.c + 1 } : { r: k.r + 1, c: k.c };
        const va = board[a.r][a.c];
        const vb = board[b.r][b.c];
        if ((va === EMPTY) === (vb === EMPTY)) continue; // ninguno o los dos puestos

        const known = va === EMPTY ? vb : va;
        const target = va === EMPTY ? a : b;
        const symbol = k.kind === 'eq' ? known : opposite(known);
        return {
            cells: [target],
            symbol,
            technique: 'vinculo',
            message:
                k.kind === 'eq'
                    ? `El = obliga a repetir: al lado del ${NOMBRES[known]} va otro ${NOMBRES[known]}.`
                    : `El × obliga a cambiar: al lado del ${NOMBRES[known]} va ${NOMBRES[opposite(known)]}.`,
        };
    }
    return null;
}

// ¿El tablero (parcial) rompe alguna regla a la vista?
export function hasViolation(board, constraints) {
    for (const kind of ['row', 'col']) {
        for (let i = 0; i < N; i++) {
            const cells = lineCells(kind, i);
            for (let j = 0; j + 2 < N; j++) {
                const a = board[cells[j].r][cells[j].c];
                if (a !== EMPTY && a === board[cells[j + 1].r][cells[j + 1].c] && a === board[cells[j + 2].r][cells[j + 2].c]) return true;
            }
            for (const s of [SOL, LUNA]) {
                if (cells.filter(({ r, c }) => board[r][c] === s).length > HALF) return true;
            }
        }
    }
    for (const k of constraints) {
        const a = board[k.r][k.c];
        const b = k.dir === 'h' ? board[k.r][k.c + 1] : board[k.r + 1][k.c];
        if (a === EMPTY || b === EMPTY) continue;
        if (k.kind === 'eq' && a !== b) return true;
        if (k.kind === 'ne' && a === b) return true;
    }
    return false;
}

// Suposición a un paso: probar un símbolo en una casilla libre, propagar las
// jugadas forzadas con las técnicas simples y, si algo se rompe, quedarse con
// el contrario. Es el último recurso, y el que más cuesta en la dificultad.
function suposicion(board, constraints) {
    for (let r = 0; r < N; r++) {
        for (let c = 0; c < N; c++) {
            if (board[r][c] !== EMPTY) continue;
            for (const s of [SOL, LUNA]) {
                const trial = cloneGrid(board);
                trial[r][c] = s;

                let progressed = true;
                while (progressed && !hasViolation(trial, constraints)) {
                    progressed = false;
                    const d = dosJuntos(trial) || sandwich(trial) || conteo(trial) || vinculo(trial, constraints);
                    if (d) {
                        for (const cell of d.cells) trial[cell.r][cell.c] = d.symbol;
                        progressed = true;
                    }
                }

                if (hasViolation(trial, constraints)) {
                    return {
                        cells: [{ r, c }],
                        symbol: opposite(s),
                        technique: 'suposicion',
                        message: `Si acá fuera ${NOMBRES[s]}, más adelante algo se rompe: va ${NOMBRES[opposite(s)]}.`,
                    };
                }
            }
        }
    }

    // Con solución única no debería hacer falta, pero garantiza avanzar: la
    // única salida se encuentra contando soluciones (sigue siendo lógica,
    // solo que más fina de explicar).
    for (let r = 0; r < N; r++) {
        for (let c = 0; c < N; c++) {
            if (board[r][c] !== EMPTY) continue;
            for (const s of [SOL, LUNA]) {
                const trial = cloneGrid(board);
                trial[r][c] = s;
                if (countSolutions(trial, constraints, 1) === 0) {
                    return {
                        cells: [{ r, c }],
                        symbol: opposite(s),
                        technique: 'profunda',
                        message: `Acá solo cierra ${NOMBRES[opposite(s)]}; el porqué es fino, pero es la única salida.`,
                    };
                }
            }
        }
    }

    return null;
}

// --- Dificultad -------------------------------------------------------------

const COSTOS = { 'dos-juntos': 1, sandwich: 1, conteo: 1, vinculo: 1, suposicion: 6, profunda: 12 };

/**
 * Qué tan difícil es un puzzle, resolviéndolo "como razonaría una persona":
 * se suma el costo de cada deducción que hizo falta. Devuelve null si el
 * motor no llega al final (no debería pasar con solución única).
 */
export function rateSolYLunaDifficulty(puzzle) {
    const board = cloneGrid(puzzle.givens);
    let score = 0;

    while (board.some((row) => row.includes(EMPTY))) {
        const d = nextDeduction(board, puzzle.constraints);
        if (!d) return null;
        for (const cell of d.cells) board[cell.r][cell.c] = d.symbol;
        score += COSTOS[d.technique] ?? 1;
    }

    return score;
}

/**
 * Genera candidatos y se queda con el más difícil, cortando apenas uno alcanza
 * el objetivo o si se agota el presupuesto: el botón tiene que sentirse
 * inmediato. Mismo esquema que el generador de Queens.
 */
export function generateHardSolYLuna({ minScore = 16, candidates = 16, budgetMs = 300 } = {}) {
    const start = Date.now();
    let best = null;
    let bestScore = -1;

    for (let i = 0; i < candidates; i++) {
        const puzzle = generateSolYLunaPuzzle();
        const score = rateSolYLunaDifficulty(puzzle) ?? 0;
        if (score > bestScore) {
            best = puzzle;
            bestScore = score;
        }
        if (bestScore >= minScore || Date.now() - start > budgetMs) break;
    }

    return best;
}
