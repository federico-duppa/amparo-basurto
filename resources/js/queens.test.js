import { describe, it, expect } from 'vitest';
import { generateHardQueensRegions, generateQueensRegions, nextDeduction, rateQueensDifficulty, solveQueens } from './queens';

const N = 8;

// ¿La solución cumple las reglas del juego para ese tablero?
function solutionIsValid(regions, sol) {
    if (!Array.isArray(sol) || sol.length !== N) return false;
    const cols = new Set();
    const regs = new Set();
    for (let r = 0; r < N; r++) {
        const c = sol[r];
        if (c < 0 || c >= N || cols.has(c)) return false;
        cols.add(c);
        const g = regions[r][c];
        if (regs.has(g)) return false;
        regs.add(g);
        if (r > 0 && Math.abs(c - sol[r - 1]) < 2) return false;
    }
    return true;
}

// Cada región es una sola mancha conexa (adyacencia ortogonal).
function contiguous(regions) {
    const cellsByRegion = new Map();
    for (let r = 0; r < N; r++) {
        for (let c = 0; c < N; c++) {
            const g = regions[r][c];
            if (!cellsByRegion.has(g)) cellsByRegion.set(g, []);
            cellsByRegion.get(g).push(r * N + c);
        }
    }
    for (const cells of cellsByRegion.values()) {
        const set = new Set(cells);
        const seen = new Set([cells[0]]);
        const stack = [cells[0]];
        while (stack.length) {
            const key = stack.pop();
            const r = Math.floor(key / N);
            const c = key % N;
            for (const [dr, dc] of [[-1, 0], [1, 0], [0, -1], [0, 1]]) {
                const nr = r + dr;
                const nc = c + dc;
                if (nr < 0 || nr >= N || nc < 0 || nc >= N) continue;
                const nk = nr * N + nc;
                if (set.has(nk) && !seen.has(nk)) {
                    seen.add(nk);
                    stack.push(nk);
                }
            }
        }
        if (seen.size !== set.size) return false;
    }
    return true;
}

// Contador de soluciones independiente del generador (fila por fila: columna
// única, región única y sin tocar a la fila anterior), cortando en `cap`.
function countSolutions(regions, cap = 2) {
    let count = 0;
    const usedCols = new Set();
    const usedRegions = new Set();
    const solve = (row, prev) => {
        if (count >= cap) return;
        if (row === N) {
            count++;
            return;
        }
        for (let col = 0; col < N; col++) {
            if (usedCols.has(col)) continue;
            if (prev !== null && Math.abs(col - prev) < 2) continue;
            const reg = regions[row][col];
            if (usedRegions.has(reg)) continue;
            usedCols.add(col);
            usedRegions.add(reg);
            solve(row + 1, col);
            usedCols.delete(col);
            usedRegions.delete(reg);
            if (count >= cap) return;
        }
    };
    solve(0, null);
    return count;
}

describe('generateQueensRegions', () => {
    it('arma tableros 8x8 con 8 regiones que cubren todo el tablero', () => {
        for (let i = 0; i < 40; i++) {
            const regions = generateQueensRegions();

            expect(regions).toHaveLength(8);
            const seen = new Set();
            let cells = 0;
            for (let r = 0; r < N; r++) {
                expect(regions[r]).toHaveLength(8);
                for (let c = 0; c < N; c++) {
                    const value = regions[r][c];
                    expect(Number.isInteger(value)).toBe(true);
                    expect(value).toBeGreaterThanOrEqual(0);
                    expect(value).toBeLessThanOrEqual(7);
                    seen.add(value);
                    cells++;
                }
            }
            expect(cells).toBe(64);
            expect(seen.size).toBe(8);
        }
    });

    it('las regiones son contiguas', () => {
        for (let i = 0; i < 40; i++) {
            expect(contiguous(generateQueensRegions())).toBe(true);
        }
    });

    it('cada tablero tiene exactamente una solución', () => {
        for (let i = 0; i < 40; i++) {
            expect(countSolutions(generateQueensRegions())).toBe(1);
        }
    });
});

describe('solveQueens', () => {
    it('resuelve cada tablero con una solución válida', () => {
        for (let i = 0; i < 40; i++) {
            const regions = generateQueensRegions();
            const sol = solveQueens(regions);
            expect(solutionIsValid(regions, sol)).toBe(true);
        }
    });
});

// Réplica del comportamiento del tablero: poner una reina tacha todo lo que ella
// prohíbe (fila, columna, color y adyacencia), como hace la UI con autoCross.
function placeQueenWithCrosses(regions, cells, qr, qc) {
    cells[qr][qc] = 2;
    for (let r = 0; r < N; r++) {
        for (let c = 0; c < N; c++) {
            if (cells[r][c] !== 0) continue;
            if (r === qr || c === qc || regions[r][c] === regions[qr][qc] || (Math.abs(r - qr) <= 1 && Math.abs(c - qc) <= 1)) {
                cells[r][c] = 1;
            }
        }
    }
}

describe('nextDeduction (motor de pistas)', () => {
    it('resuelve tableros enteros solo con deducciones: reina solo con certeza y sin tachar nunca la solución', { timeout: 120_000 }, () => {
        for (let i = 0; i < 20; i++) {
            const regions = generateQueensRegions();
            const sol = solveQueens(regions);
            const cells = Array.from({ length: N }, () => Array(N).fill(0));
            let queens = 0;
            let steps = 0;

            while (queens < 8 && steps++ < 400) {
                const d = nextDeduction(regions, cells);

                // El motor nunca se queda sin deducción en un tablero resoluble.
                expect(d).not.toBeNull();
                expect(typeof d.message).toBe('string');
                expect(d.message.length).toBeGreaterThan(0);
                expect(d.cells.length).toBeGreaterThan(0);

                if (d.kind === 'queen') {
                    // Certeza real: la reina sugerida es LA de la solución única.
                    const { r, c } = d.cells[0];
                    expect(sol[r]).toBe(c);
                    placeQueenWithCrosses(regions, cells, r, c);
                    queens++;
                } else {
                    for (const { r, c } of d.cells) {
                        // Nunca tacha una casilla donde va una reina.
                        expect(sol[r]).not.toBe(c);
                        // Y siempre aporta información nueva.
                        expect(cells[r][c]).toBe(0);
                        cells[r][c] = 1;
                    }
                }
            }

            expect(queens).toBe(8);
        }
    });
});

describe('rateQueensDifficulty', () => {
    it('devuelve un puntaje determinístico y coherente', () => {
        for (let i = 0; i < 15; i++) {
            const regions = generateQueensRegions();
            const a = rateQueensDifficulty(regions);
            const b = rateQueensDifficulty(regions);

            // Mismo tablero, mismo puntaje (no hay azar en el motor).
            expect(a).toEqual(b);
            expect(a.score).toBeGreaterThanOrEqual(0);
            expect(a.hardest).toBeGreaterThanOrEqual(1);
            expect(a.hardest).toBeLessThanOrEqual(8);
        }
    });
});

describe('generateHardQueensRegions', () => {
    it('entrega tableros válidos sesgados a difíciles', { timeout: 60_000 }, () => {
        // El piso se elige bien por debajo del objetivo (14) para que el test no
        // sea flaky: con 24 candidatos, quedar debajo de 6 es rarísimo — y 6 ya
        // es la mediana de los tableros sin sesgo.
        for (let i = 0; i < 10; i++) {
            const regions = generateHardQueensRegions();

            const seen = new Set();
            for (let r = 0; r < N; r++) {
                for (let c = 0; c < N; c++) seen.add(regions[r][c]);
            }
            expect(seen.size).toBe(8);
            expect(solutionIsValid(regions, solveQueens(regions))).toBe(true);

            expect(rateQueensDifficulty(regions).score).toBeGreaterThanOrEqual(6);
        }
    });
});
