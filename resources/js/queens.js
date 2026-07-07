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
// lo que devuelve es esa única solución. La pista la usa SOLO para detectar
// errores del jugador; las sugerencias salen del motor de deducciones de abajo.
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

// --- Motor de deducciones lógicas para la pista ------------------------------
//
// La pista no "espía" la solución: deduce a partir de lo que se ve en el tablero
// (reinas puestas y casillas tachadas), igual que razonaría una persona. Cada
// llamada devuelve UNA deducción:
//
//   { kind: 'queen',     cells: [{r,c}],     message }  → reina 100 % segura
//   { kind: 'eliminate', cells: [{r,c},...], message }  → casillas descartables
//
// o null si no encuentra nada (con el último recurso de la suposición no debería
// pasar; el llamador muestra un mensaje honesto en ese caso). Reglas, de la más
// simple a la más profunda:
//
//  1. Única casilla posible en una región, fila o columna → reina segura. Es el
//     ÚNICO caso en que la pista recomienda poner una reina.
//  2. Región confinada a una sola fila/columna → esa línea es suya: se tacha el
//     resto de la línea.
//  3. Fila/columna confinada a una sola región → la región gasta su reina ahí:
//     se tacha el resto de la región.
//  4. Casilla que dejaría a una región/fila/columna sin ningún lugar (una reina
//     ahí ataca todas sus candidatas) → se tacha.
//  5. Palomar: k regiones que solo entran en k líneas se reparten esas líneas →
//     se tacha lo ajeno en esas líneas (y su dual: k líneas en k regiones).
//  6. Suposición a un paso: se prueba una reina y se propagan las jugadas
//     forzadas; si algo se queda sin lugar, la casilla se tacha.
//  7. Igual que 6 pero descartando también dentro de la suposición (raro; es el
//     último recurso para tableros retorcidos).

// Lee el estado visible y arma las candidatas por región, fila y columna. Una
// casilla es candidata si está libre y ninguna reina puesta la ataca.
function unitsOf(regions, cells) {
    const N = 8;
    const queens = [];
    for (let r = 0; r < N; r++) {
        for (let c = 0; c < N; c++) {
            if (cells[r][c] === 2) queens.push([r, c]);
        }
    }

    const regQ = Array(N).fill(false);
    const rowQ = Array(N).fill(false);
    const colQ = Array(N).fill(false);
    for (const [r, c] of queens) {
        rowQ[r] = true;
        colQ[c] = true;
        regQ[regions[r][c]] = true;
    }

    const attacked = (r, c) =>
        rowQ[r] || colQ[c] || regQ[regions[r][c]] ||
        queens.some(([qr, qc]) => Math.abs(qr - r) <= 1 && Math.abs(qc - c) <= 1);

    const regCands = Array.from({ length: N }, () => []);
    const rowCands = Array.from({ length: N }, () => []);
    const colCands = Array.from({ length: N }, () => []);
    for (let r = 0; r < N; r++) {
        for (let c = 0; c < N; c++) {
            if (cells[r][c] !== 0 || attacked(r, c)) continue;
            regCands[regions[r][c]].push([r, c]);
            rowCands[r].push([r, c]);
            colCands[c].push([r, c]);
        }
    }

    return { queens, regQ, rowQ, colQ, regCands, rowCands, colCands };
}

// Propaga jugadas forzadas sobre una copia del tablero: pone la reina donde a
// una unidad le queda una sola casilla y aplica confinamientos, hasta quedarse
// quieta. Devuelve la unidad que quedó sin lugar ({type, index}) o null si todo
// cierra. Muta `cells` (usar siempre sobre una copia).
function propagate(regions, cells) {
    const N = 8;
    for (let guard = 0; guard < 300; guard++) {
        const u = unitsOf(regions, cells);

        for (let i = 0; i < N; i++) {
            if (!u.regQ[i] && u.regCands[i].length === 0) return { type: 'region', index: i };
            if (!u.rowQ[i] && u.rowCands[i].length === 0) return { type: 'row', index: i };
            if (!u.colQ[i] && u.colCands[i].length === 0) return { type: 'col', index: i };
        }

        let changed = false;

        // Únicas: reina forzada.
        for (let i = 0; i < N && !changed; i++) {
            for (const [flags, cands] of [[u.regQ, u.regCands], [u.rowQ, u.rowCands], [u.colQ, u.colCands]]) {
                if (!flags[i] && cands[i].length === 1) {
                    const [r, c] = cands[i][0];
                    cells[r][c] = 2;
                    changed = true;
                    break;
                }
            }
        }
        if (changed) continue;

        // Confinamientos: región en una sola línea.
        for (let g = 0; g < N && !changed; g++) {
            if (u.regQ[g] || u.regCands[g].length < 2) continue;
            const rows = new Set(u.regCands[g].map(([r]) => r));
            if (rows.size === 1) {
                const r = rows.values().next().value;
                for (const [rr, cc] of u.rowCands[r]) {
                    if (regions[rr][cc] !== g) {
                        cells[rr][cc] = 1;
                        changed = true;
                    }
                }
            }
            if (changed) break;
            const cols = new Set(u.regCands[g].map(([, c]) => c));
            if (cols.size === 1) {
                const c = cols.values().next().value;
                for (const [rr, cc] of u.colCands[c]) {
                    if (regions[rr][cc] !== g) {
                        cells[rr][cc] = 1;
                        changed = true;
                    }
                }
            }
        }
        if (changed) continue;

        // Confinamientos: línea en una sola región.
        for (let i = 0; i < N && !changed; i++) {
            if (!u.rowQ[i] && u.rowCands[i].length >= 2) {
                const gs = new Set(u.rowCands[i].map(([r, c]) => regions[r][c]));
                if (gs.size === 1) {
                    const g = gs.values().next().value;
                    for (const [rr, cc] of u.regCands[g]) {
                        if (rr !== i) {
                            cells[rr][cc] = 1;
                            changed = true;
                        }
                    }
                }
            }
            if (changed) break;
            if (!u.colQ[i] && u.colCands[i].length >= 2) {
                const gs = new Set(u.colCands[i].map(([r, c]) => regions[r][c]));
                if (gs.size === 1) {
                    const g = gs.values().next().value;
                    for (const [rr, cc] of u.regCands[g]) {
                        if (cc !== i) {
                            cells[rr][cc] = 1;
                            changed = true;
                        }
                    }
                }
            }
        }
        if (!changed) return null;
    }
    return null;
}

// ¿La suposición ya aplicada en `cells` lleva sí o sí a contradicción? Además de
// propagar, descarta (dentro de la simulación) las casillas cuya reina hipotética
// contradice, y vuelve a propagar. Muta `cells`; usar sobre una copia.
function deepContradicts(regions, cells) {
    for (let guard = 0; guard < 40; guard++) {
        if (propagate(regions, cells)) return true;

        const u = unitsOf(regions, cells);
        const elims = [];
        for (const [r, c] of u.rowCands.flat()) {
            const sim = cells.map((row) => row.slice());
            sim[r][c] = 2;
            if (propagate(regions, sim)) elims.push([r, c]);
        }
        if (!elims.length) return false;
        for (const [r, c] of elims) cells[r][c] = 1;
    }
    return false;
}

export function nextDeduction(regions, board, regionLabels = null) {
    const N = 8;
    const cells = board.map((row) => row.slice());
    const u = unitsOf(regions, cells);

    const nameOf = (g) => (regionLabels && regionLabels[g] ? `el color ${regionLabels[g]}` : `la región ${g + 1}`);
    const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
    const asCells = (list) => list.map(([r, c]) => ({ r, c }));
    const unitName = (unit) =>
        unit.type === 'region' ? nameOf(unit.index) : `la ${unit.type === 'row' ? 'fila' : 'columna'} ${unit.index + 1}`;

    // 1) Reina segura: única casilla posible de una región, fila o columna.
    for (let g = 0; g < N; g++) {
        if (!u.regQ[g] && u.regCands[g].length === 1) {
            return {
                kind: 'queen',
                cells: asCells(u.regCands[g]),
                message: `A ${nameOf(g)} le queda una sola casilla posible: la reina va acá, seguro.`,
            };
        }
    }
    for (let i = 0; i < N; i++) {
        if (!u.rowQ[i] && u.rowCands[i].length === 1) {
            return {
                kind: 'queen',
                cells: asCells(u.rowCands[i]),
                message: `En la fila ${i + 1} queda una sola casilla posible: la reina va acá, seguro.`,
            };
        }
        if (!u.colQ[i] && u.colCands[i].length === 1) {
            return {
                kind: 'queen',
                cells: asCells(u.colCands[i]),
                message: `En la columna ${i + 1} queda una sola casilla posible: la reina va acá, seguro.`,
            };
        }
    }

    // 2) Región confinada a una línea → tachar el resto de la línea.
    for (let g = 0; g < N; g++) {
        if (u.regQ[g] || u.regCands[g].length < 2) continue;
        const rows = new Set(u.regCands[g].map(([r]) => r));
        if (rows.size === 1) {
            const r = rows.values().next().value;
            const elims = u.rowCands[r].filter(([rr, cc]) => regions[rr][cc] !== g);
            if (elims.length) {
                return {
                    kind: 'eliminate',
                    cells: asCells(elims),
                    message: `${cap(nameOf(g))} solo entra en la fila ${r + 1}, así que la reina de esa fila va a salir de ahí: el resto de la fila se puede tachar.`,
                };
            }
        }
        const cols = new Set(u.regCands[g].map(([, c]) => c));
        if (cols.size === 1) {
            const c = cols.values().next().value;
            const elims = u.colCands[c].filter(([rr, cc]) => regions[rr][cc] !== g);
            if (elims.length) {
                return {
                    kind: 'eliminate',
                    cells: asCells(elims),
                    message: `${cap(nameOf(g))} solo entra en la columna ${c + 1}, así que la reina de esa columna va a salir de ahí: el resto de la columna se puede tachar.`,
                };
            }
        }
    }

    // 3) Línea confinada a una región → tachar el resto de la región.
    for (let i = 0; i < N; i++) {
        if (!u.rowQ[i] && u.rowCands[i].length >= 2) {
            const gs = new Set(u.rowCands[i].map(([r, c]) => regions[r][c]));
            if (gs.size === 1) {
                const g = gs.values().next().value;
                const elims = u.regCands[g].filter(([rr]) => rr !== i);
                if (elims.length) {
                    return {
                        kind: 'eliminate',
                        cells: asCells(elims),
                        message: `La fila ${i + 1} solo tiene lugar en ${nameOf(g)}, y ese grupo gasta su reina ahí: el resto de ${nameOf(g)} se puede tachar.`,
                    };
                }
            }
        }
        if (!u.colQ[i] && u.colCands[i].length >= 2) {
            const gs = new Set(u.colCands[i].map(([r, c]) => regions[r][c]));
            if (gs.size === 1) {
                const g = gs.values().next().value;
                const elims = u.regCands[g].filter(([, cc]) => cc !== i);
                if (elims.length) {
                    return {
                        kind: 'eliminate',
                        cells: asCells(elims),
                        message: `La columna ${i + 1} solo tiene lugar en ${nameOf(g)}, y ese grupo gasta su reina ahí: el resto de ${nameOf(g)} se puede tachar.`,
                    };
                }
            }
        }
    }

    // 4) Casillas que dejarían a una región, fila o columna sin ningún lugar.
    const allCands = u.rowCands.flat();
    const attacks = (xr, xc, r, c) =>
        xr === r || xc === c || regions[xr][xc] === regions[r][c] || (Math.abs(xr - r) <= 1 && Math.abs(xc - c) <= 1);
    const units = [];
    for (let g = 0; g < N; g++) {
        if (!u.regQ[g] && u.regCands[g].length) units.push({ type: 'region', index: g, cands: u.regCands[g] });
    }
    for (let i = 0; i < N; i++) {
        if (!u.rowQ[i] && u.rowCands[i].length) units.push({ type: 'row', index: i, cands: u.rowCands[i] });
        if (!u.colQ[i] && u.colCands[i].length) units.push({ type: 'col', index: i, cands: u.colCands[i] });
    }
    for (const unit of units) {
        const wipers = allCands.filter(([xr, xc]) => {
            if (unit.type === 'region' && regions[xr][xc] === unit.index) return false;
            if (unit.type === 'row' && xr === unit.index) return false;
            if (unit.type === 'col' && xc === unit.index) return false;
            return unit.cands.every(([r, c]) => attacks(xr, xc, r, c));
        });
        if (wipers.length) {
            return {
                kind: 'eliminate',
                cells: asCells(wipers),
                message: wipers.length === 1
                    ? `Una reina acá dejaría a ${unitName(unit)} sin ningún lugar: esa casilla se puede tachar.`
                    : `Una reina en cualquiera de las casillas marcadas dejaría a ${unitName(unit)} sin ningún lugar: se pueden tachar.`,
            };
        }
    }

    // 5) Palomar: k regiones que solo entran en k líneas (y su dual), k = 2 o 3.
    const combos = (arr, k, start = 0, cur = [], out = []) => {
        if (cur.length === k) {
            out.push(cur.slice());
            return out;
        }
        for (let i = start; i <= arr.length - (k - cur.length); i++) {
            cur.push(arr[i]);
            combos(arr, k, i + 1, cur, out);
            cur.pop();
        }
        return out;
    };
    const groupWord = regionLabels ? 'colores' : 'regiones';
    const listRegs = (gs) => {
        const names = gs.map((g) => (regionLabels && regionLabels[g]) || `región ${g + 1}`);
        return names.length === 1 ? names[0] : `${names.slice(0, -1).join(', ')} y ${names[names.length - 1]}`;
    };
    const listLines = (word, idxs) => {
        const ns = [...idxs].map((i) => i + 1).sort((a, b) => a - b);
        return ns.length === 1 ? `la ${word} ${ns[0]}` : `las ${word}s ${ns.slice(0, -1).join(', ')} y ${ns[ns.length - 1]}`;
    };
    const lineIdxOf = { row: (cell) => cell[0], col: (cell) => cell[1] };
    const lineCandsOf = { row: u.rowCands, col: u.colCands };
    const lineWordOf = { row: 'fila', col: 'columna' };

    const freeRegs = [];
    for (let g = 0; g < N; g++) {
        if (!u.regQ[g] && u.regCands[g].length) freeRegs.push(g);
    }
    for (const type of ['row', 'col']) {
        for (const k of [2, 3]) {
            for (const combo of combos(freeRegs, k)) {
                const lines = new Set();
                for (const g of combo) {
                    for (const cell of u.regCands[g]) lines.add(lineIdxOf[type](cell));
                }
                if (lines.size !== k) continue;
                const elims = [];
                for (const i of lines) {
                    for (const [r, c] of lineCandsOf[type][i]) {
                        if (!combo.includes(regions[r][c])) elims.push([r, c]);
                    }
                }
                if (elims.length) {
                    return {
                        kind: 'eliminate',
                        cells: asCells(elims),
                        message: `Los ${groupWord} ${listRegs(combo)} solo entran en ${listLines(lineWordOf[type], lines)}: esas ${lineWordOf[type]}s son de ellos, tachá las casillas marcadas.`,
                    };
                }
            }
        }
    }
    for (const type of ['row', 'col']) {
        const lineQ = type === 'row' ? u.rowQ : u.colQ;
        const freeLines = [];
        for (let i = 0; i < N; i++) {
            if (!lineQ[i] && lineCandsOf[type][i].length) freeLines.push(i);
        }
        for (const k of [2, 3]) {
            for (const combo of combos(freeLines, k)) {
                const gs = new Set();
                for (const i of combo) {
                    for (const [r, c] of lineCandsOf[type][i]) gs.add(regions[r][c]);
                }
                if (gs.size !== k) continue;
                const elims = [];
                for (const g of gs) {
                    for (const cell of u.regCands[g]) {
                        if (!combo.includes(lineIdxOf[type](cell))) elims.push(cell);
                    }
                }
                if (elims.length) {
                    return {
                        kind: 'eliminate',
                        cells: asCells(elims),
                        message: `${cap(listLines(lineWordOf[type], combo))} solo tienen lugar en los ${groupWord} ${listRegs([...gs])}: esos grupos no van en ninguna otra ${lineWordOf[type]}, tachá las casillas marcadas.`,
                    };
                }
            }
        }
    }

    // 6) Suposición a un paso: probar una reina y propagar jugadas forzadas.
    for (const [r, c] of allCands) {
        const sim = cells.map((row) => row.slice());
        sim[r][c] = 2;
        const contra = propagate(regions, sim);
        if (contra) {
            return {
                kind: 'eliminate',
                cells: [{ r, c }],
                message: `Si acá hubiera una reina, las jugadas forzadas dejan a ${unitName(contra)} sin lugar: se puede tachar.`,
            };
        }
    }

    // 7) Suposición profunda (último recurso, raro).
    for (const [r, c] of allCands) {
        const sim = cells.map((row) => row.slice());
        sim[r][c] = 2;
        if (deepContradicts(regions, sim)) {
            return {
                kind: 'eliminate',
                cells: [{ r, c }],
                message: 'Una reina acá termina en un callejón sin salida: se puede tachar.',
            };
        }
    }

    return null;
}
