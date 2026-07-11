import { describe, expect, it } from 'vitest';
import { seededRng } from './rng';

describe('seededRng', () => {
    it('la misma semilla produce siempre la misma secuencia', () => {
        const a = seededRng('queens-2026-07-11');
        const b = seededRng('queens-2026-07-11');

        for (let i = 0; i < 1000; i++) {
            expect(a()).toBe(b());
        }
    });

    it('semillas distintas producen secuencias distintas', () => {
        const a = seededRng('queens-2026-07-11');
        const b = seededRng('queens-2026-07-12');

        const seqA = Array.from({ length: 20 }, () => a());
        const seqB = Array.from({ length: 20 }, () => b());

        expect(seqA).not.toEqual(seqB);
    });

    it('devuelve flotantes en [0, 1), como Math.random', () => {
        const rng = seededRng('cualquier-cosa');

        for (let i = 0; i < 5000; i++) {
            const x = rng();
            expect(x).toBeGreaterThanOrEqual(0);
            expect(x).toBeLessThan(1);
        }
    });
});
