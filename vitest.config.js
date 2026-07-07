import { defineConfig } from 'vitest/config';

// Config propia de Vitest (no la de Vite): así los tests de JS no cargan los
// plugins de Laravel/Tailwind, que necesitan el contexto de build. Los módulos
// de juego son puros (sin DOM), por eso alcanza el entorno 'node'.
export default defineConfig({
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.js'],
    },
});
