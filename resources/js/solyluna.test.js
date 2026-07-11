import { describe, expect, it } from 'vitest';
import {
    countSolutions,
    EMPTY,
    generateFullGrid,
    generateHardSolYLuna,
    generateSolYLunaPuzzle,
    LUNA,
    N,
    nextDeduction,
    rateSolYLunaDifficulty,
    SOL,
} from './solyluna';

// ¿La grilla completa cumple las reglas del juego?
function gridIsValid(grid) {
    for (let i = 0; i < N; i++) {
        let rowSol = 0;
        let colSol = 0;
        for (let j = 0; j < N; j++) {
            if (![SOL, LUNA].includes(grid[i][j])) return false;
            if (grid[i][j] === SOL) rowSol++;
            if (grid[j][i] === SOL) colSol++;
            if (j >= 2 && grid[i][j] === grid[i][j - 1] && grid[i][j] === grid[i][j - 2]) return false;
            if (j >= 2 && grid[j][i] === grid[j - 1][i] && grid[j][i] === grid[j - 2][i]) return false;
        }
        if (rowSol !== N / 2 || colSol !== N / 2) return false;
    }
    return true;
}

describe('generateFullGrid', () => {
    it('genera grillas completas válidas', () => {
        for (let i = 0; i < 20; i++) {
            expect(gridIsValid(generateFullGrid())).toBe(true);
        }
    });
});

describe('generateSolYLunaPuzzle', () => {
    it('los dados y los vínculos salen de la solución', () => {
        for (let i = 0; i < 10; i++) {
            const { solution, givens, constraints } = generateSolYLunaPuzzle();

            expect(gridIsValid(solution)).toBe(true);

            for (let r = 0; r < N; r++) {
                for (let c = 0; c < N; c++) {
                    if (givens[r][c] !== EMPTY) expect(givens[r][c]).toBe(solution[r][c]);
                }
            }

            for (const k of constraints) {
                const a = solution[k.r][k.c];
                const b = k.dir === 'h' ? solution[k.r][k.c + 1] : solution[k.r + 1][k.c];
                expect(k.kind).toBe(a === b ? 'eq' : 'ne');
            }
        }
    });

    it('cada puzzle tiene solución única', () => {
        for (let i = 0; i < 10; i++) {
            const { givens, constraints } = generateSolYLunaPuzzle();
            expect(countSolutions(givens, constraints, 3)).toBe(1);
        }
    });

    it('las pistas quedan minimizadas: sacar cualquiera rompe la unicidad', () => {
        const { givens, constraints } = generateSolYLunaPuzzle();

        for (const k of constraints) {
            const sin = constraints.filter((c) => c !== k);
            expect(countSolutions(givens, sin, 2)).toBeGreaterThan(1);
        }
        for (let r = 0; r < N; r++) {
            for (let c = 0; c < N; c++) {
                if (givens[r][c] === EMPTY) continue;
                const sin = givens.map((row) => row.slice());
                sin[r][c] = EMPTY;
                expect(countSolutions(sin, constraints, 2)).toBeGreaterThan(1);
            }
        }
    });
});

describe('nextDeduction', () => {
    it('resuelve puzzles enteros sin contradecir nunca la solución', () => {
        const usadas = {};

        for (let i = 0; i < 15; i++) {
            const { solution, givens, constraints } = generateSolYLunaPuzzle();
            const board = givens.map((row) => row.slice());

            let guard = 0;
            while (board.some((row) => row.includes(EMPTY))) {
                const d = nextDeduction(board, constraints);
                expect(d).not.toBeNull();
                for (const { r, c } of d.cells) {
                    // La deducción jamás pisa una casilla puesta ni se equivoca.
                    expect(board[r][c]).toBe(EMPTY);
                    expect(d.symbol).toBe(solution[r][c]);
                    board[r][c] = d.symbol;
                }
                usadas[d.technique] = (usadas[d.technique] ?? 0) + 1;
                expect(++guard).toBeLessThan(200);
            }

            expect(board).toEqual(solution);
        }

        // El motor razona con las reglas "humanas": el conteo de soluciones
        // como salida de emergencia tiene que ser rareza, no rutina.
        const total = Object.values(usadas).reduce((a, b) => a + b, 0);
        expect((usadas.profunda ?? 0) / total).toBeLessThan(0.05);
    });
});

describe('countSolutions', () => {
    it('un tablero pre-puesto que rompe una regla cuenta cero soluciones', () => {
        // Regresión: las casillas pre-puestas se validan al pasar por ellas.
        // Saltearlas dejaba contar "soluciones" con tres seguidos, y ese
        // fantasma rompía la deducción de último recurso (dificultad null).
        const givens = Array.from({ length: N }, () => Array(N).fill(EMPTY));
        givens[0][0] = SOL;
        givens[0][1] = SOL;
        givens[0][2] = SOL;

        expect(countSolutions(givens, [], 2)).toBe(0);

        const conVinculo = Array.from({ length: N }, () => Array(N).fill(EMPTY));
        conVinculo[2][2] = SOL;
        conVinculo[2][3] = LUNA;
        expect(countSolutions(conVinculo, [{ r: 2, c: 2, dir: 'h', kind: 'eq' }], 2)).toBe(0);
    });
});

describe('dificultad', () => {
    it('todo puzzle generado se puede puntuar', () => {
        for (let i = 0; i < 40; i++) {
            const score = rateSolYLunaDifficulty(generateSolYLunaPuzzle());
            expect(score).not.toBeNull();
            expect(score).toBeGreaterThan(0);
        }
    });

    it('el generador sesgado devuelve un puzzle válido y único', () => {
        const puzzle = generateHardSolYLuna({ candidates: 6, budgetMs: 1500 });
        expect(gridIsValid(puzzle.solution)).toBe(true);
        expect(countSolutions(puzzle.givens, puzzle.constraints, 2)).toBe(1);
    });
});
