import { describe, it, expect } from 'vitest';
import { generateQueensRegions } from './queens';

const N = 8;

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
