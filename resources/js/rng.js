// RNG sembrado para el puzzle del día: la misma semilla produce siempre la
// misma secuencia, en cualquier navegador (todo es aritmética entera de 32
// bits + IEEE 754, idéntica en todos los motores). Módulo puro, con tests.
//
// Los generadores de Queens y Sol y luna reciben esta función donde antes
// usaban Math.random: sembrada con la fecha, todos ven el mismo tablero.

// Hash xmur3: convierte una semilla de texto ("queens-2026-07-11") en el
// estado inicial de 32 bits del generador.
function hash32(str) {
    let h = 1779033703 ^ str.length;
    for (let i = 0; i < str.length; i++) {
        h = Math.imul(h ^ str.charCodeAt(i), 3432918353);
        h = (h << 13) | (h >>> 19);
    }
    h = Math.imul(h ^ (h >>> 16), 2246822507);
    h = Math.imul(h ^ (h >>> 13), 3266489909);

    return (h ^ (h >>> 16)) >>> 0;
}

/**
 * Un generador mulberry32 sembrado con un string: devuelve una función con el
 * mismo contrato que Math.random (flotantes en [0, 1)).
 */
export function seededRng(seed) {
    let state = hash32(String(seed));

    return function () {
        state = (state + 0x6d2b79f5) | 0;
        let t = Math.imul(state ^ (state >>> 15), 1 | state);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}
