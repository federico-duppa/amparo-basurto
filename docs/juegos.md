# Juegos (`/juegos`)

Un rincón para despejarse. `/juegos` es una lista de juegos; cada uno se abre en su propia pantalla. Por ahora hay uno: **Queens**.

## Panel de juegos (`/juegos`)

- Lista los juegos disponibles como tarjetas (nombre + resumen + acceso). Sumar un juego nuevo es agregar una entrada al catálogo y su pantalla.
- No guarda nada por usuario: es una puerta de entrada, no un tablero de estado.

## Queens (`/juegos/queens`)

Rompecabezas de lógica en una grilla de **8×8** dividida en **8 regiones de color**. La meta: poner **8 reinas**, una por fila, una por columna y una por región, sin que **dos reinas se toquen** (tampoco en diagonal).

### Cómo se juega

- **Un toque cicla la casilla:** vacía → cruz → reina → vacía. La cruz es una nota para descartar una casilla mientras razonás; no cuenta como reina.
- **Conflictos en rojo (teja):** si dos reinas comparten fila, columna o color, o quedan pegadas (incluida la diagonal), se marcan las dos. Es una ayuda de lectura, no un puntaje.
- **Se gana** cuando hay 8 reinas y ningún conflicto. Ahí aparece la felicitación de Amparo con el tiempo que tardaste.
- **Cronómetro:** arranca con el primer toque y se frena al resolver. **Vaciar** limpia el tablero (mismo puzzle); **Tablero nuevo** genera otro.

### Reglas y decisiones

- **Cada tablero tiene solución única y regiones contiguas.** Se genera en el servidor: se arma una colocación válida de reinas, se hacen crecer las regiones como manchas alrededor de cada una y se "tallan" (pasando celdas de borde a regiones vecinas, sin partir ninguna) hasta que la única solución posible sea la de origen. Si un tablero se traba antes de quedar único, se descarta y se prueba otro.
- **El cliente nunca recibe la solución.** El servidor solo manda las regiones; marcar, poner reinas, detectar conflictos, cronómetro y victoria pasan en el navegador (Alpine), así el juego responde al toque sin ida y vuelta. No hay forma de "espiar" la solución desde el navegador porque no viaja.
- **No guarda progreso ni tiempos.** Cada partida es de una sentada; salir y volver arranca un tablero nuevo. (Persistir rachas/mejores tiempos está en el backlog, ver [TODO.md](../TODO.md).)
- **Colores de región.** Ocho tierras apagadas y mutuamente distinguibles (tokens `q1`…`q8`), pensadas como fondos de casilla que sostienen el ícono de reina en cuero con contraste suficiente. El estado nunca se comunica solo con color: la reina es una forma (corona), la nota es una cruz y el conflicto suma un aro rojo.
- **La corona es arte del juego, no iconografía de UI.** Es la única figura fuera de Heroicons, tratada como pieza del juego (igual que el logo es una excepción), para que "Queens" se lea de un vistazo.

El acento del módulo es **pizarra** (`#3E4A47`). La identidad visual y la voz de Amparo están en [CLAUDE.md](../CLAUDE.md); esta spec cubre solo lo funcional.
