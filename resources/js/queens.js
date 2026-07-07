// Generador de tableros de Queens (8x8). Módulo puro (sin DOM ni Alpine) para
// poder testearlo con Vitest; lo usa el componente de tablero en app.js.
//
// Antes lo armaba el backend y lo mandaba en el HTML; ahora la partida se calcula
// en el navegador y no hace falta ninguna llamada al servidor para jugar ni para
// pedir un tablero nuevo.
//
// Devuelve las regiones (int[8][8], índice 0..7) de un tablero con SOLUCIÓN ÚNICA
// y REGIONES CONTIGUAS. En tres pasos: (1) una colocación válida de reinas; (2)
// regiones que crecen como manchas alrededor de cada reina hasta cubrir todo; (3)
// un "tallado" que, mientras exista otra solución además de la de origen, pasa una
// celda de borde a una región vecina para invalidarla, cuidando no partir ninguna
// región. Si un tablero se traba antes de quedar único, se descarta y se prueba
// otro (la tasa de éxito real es ~1 de cada 4).
export function generateQueensRegions(maxAttempts = 200) {
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

// Resuelve un tablero: devuelve la solución (columna de la reina por fila) o null
// si no tiene. Como los tableros de generateQueensRegions tienen solución única,
// lo que devuelve es esa única solución. Lo usa el botón de pista.
export function solveQueens(regions) {
    const N = 8;
    let found = null;
    const current = [];
    const usedCols = new Set();
    const usedRegions = new Set();
    const solve = (row, prev) => {
        if (found) return;
        if (row === N) {
            found = current.slice();
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
}
