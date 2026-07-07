# Juegos (`/juegos`)

Un rincón para despejarse. `/juegos` es una lista de juegos; cada uno se abre en su propia pantalla. Por ahora hay uno: **Queens**.

## Panel de juegos (`/juegos`)

- Lista los juegos disponibles como tarjetas (nombre + resumen + acceso). Sumar un juego nuevo es agregar una entrada al catálogo y su pantalla.
- No guarda nada por usuario: es una puerta de entrada, no un tablero de estado.

## Queens (`/juegos/queens`)

Rompecabezas de lógica en una grilla de **8×8** dividida en **8 regiones de color**. La meta: poner **8 reinas**, una por fila, una por columna y una por región, sin que **dos reinas se toquen** (tampoco en diagonal).

### Cómo se juega

- **Un toque cicla la casilla:** vacía → cruz → reina → vacía. La cruz es una nota para descartar una casilla mientras razonás; no cuenta como reina.
- **Cruces automáticas al poner una reina:** al poner una reina se cruzan solas todas las casillas que ella deja prohibidas (su fila, su columna, su color y las que la tocan, incluida la diagonal). Sacar la reina deshace **exactamente** esas cruces: las que el jugador ya había puesto a mano quedan, y las que otra reina también cruza siguen cruzadas. Así una reina puesta por error no se lleva puestas las cruces previas.
- **Deslizar el dedo marca cruces de corrido:** al arrastrar por el tablero se van poniendo cruces en las casillas por las que pasás, sin tener que tocar una por una. Si el deslizamiento **arranca sobre una cruz a mano**, en cambio las borra. El gesto nunca pisa una reina ni las cruces automáticas de una reina. (Con teclado o mouse, cada casilla sigue ciclando con Enter/clic.)
- **Conflictos en rojo (teja):** si dos reinas comparten fila, columna o color, o quedan pegadas (incluida la diagonal), se marcan las dos. Es una ayuda de lectura, no un puntaje.
- **Sonido:** poner una cruz hace un tic apagado; poner una reina, un tono más lindo; ganar, un pequeño arpegio. Se sintetiza en el navegador (sin archivos) y se puede silenciar con el botón del encabezado; la preferencia queda guardada.
- **Deshacer:** vuelve atrás la última acción y, tocándolo de nuevo, desanda todo paso a paso. Cada toque es una acción; un deslizamiento entero cuenta como una sola. Queda deshabilitado cuando no hay nada que deshacer.
- **Pista:** ayuda al que está atascado **deduciendo con lógica sobre lo que está a la vista**, sin espiar la solución. Primero busca un error que trabe —una reina donde no va, o una cruz a mano sobre una casilla donde en realidad va una reina— y lo resalta en rojo. Si no hay errores, pide la próxima deducción al motor: cuando una casilla es **reina con certeza lógica total** (única posible de su color, fila o columna) la señala en ocre para que la juegues vos; si todavía no hay certeza, **tacha por vos un grupo de casillas descartables** y explica el porqué en el cartelito. Ese tachado cuenta como una sola acción: Deshacer lo levanta junto. El resaltado se va con la próxima acción.
- **Se gana** cuando hay 8 reinas y ningún conflicto. Ahí las reinas se vuelven doradas con una onda de victoria y aparece la felicitación de Amparo con el tiempo que tardaste.
- **Cronómetro:** arranca con el primer toque y se frena al resolver. **Vaciar** limpia el tablero (mismo puzzle, y reinicia el historial de deshacer); **Tablero nuevo** genera otro.

### Reglas y decisiones

- **Cada tablero tiene solución única y regiones contiguas.** Se arma una colocación válida de reinas, se hacen crecer las regiones como manchas alrededor de cada una y se "tallan" (pasando celdas de borde a regiones vecinas, sin partir ninguna) hasta que la única solución posible sea la de origen. Si un tablero se traba antes de quedar único, se descarta y se prueba otro. El generador vive en `resources/js/queens.js` (módulo puro) y tiene tests de Vitest que verifican validez, contigüidad y unicidad (`npm run test:js`, en CI).
- **Todo pasa en el navegador, sin backend.** La partida se genera y se juega en el cliente (Alpine + el generador de `resources/js/queens.js`): abrir la página o pedir un tablero nuevo **no hace ninguna llamada al servidor**, así responde al toque al instante y anda incluso sin conexión una vez cargada. El componente Livewire solo entrega el marco de la página. (Como la generación es local, la solución es derivable en el cliente; no importa porque el juego es de una sola persona, sin puntajes ni servidor que proteger. Si en el futuro hay ranking o puzzle del día compartido, la validación tendría que pasar al servidor — ver [TODO.md](../TODO.md).)
- **Las cruces automáticas cubren todo lo que la reina prohíbe** (fila, columna, color y adyacencia), no solo los "ataques" de ajedrez: en Queens el color también es una regla, así que se cruza también. Se guarda por casilla cuántas reinas la cruzan, de modo que deshacer al sacar una reina resta solo su aporte y respeta las cruces a mano y las de otras reinas.
- **El sonido se sintetiza con Web Audio, sin archivos** (tonos cortos): evita sumar assets binarios y funciona sin red. Se puede silenciar y la preferencia vive en `localStorage`.
- **La pista deduce, no espía.** El motor (`nextDeduction`, en `resources/js/queens.js`) razona solo sobre lo visible, con las técnicas clásicas del juego en orden de dificultad: única casilla posible (→ reina segura, el único caso en que recomienda poner una); región confinada a una línea y línea confinada a una región; casillas que dejarían a un grupo sin ningún lugar; palomar (k colores en k líneas, y su dual); y como último recurso la suposición a un paso (probar una reina, propagar las jugadas forzadas y descartar si algo se queda sin lugar). La solución (`solveQueens`) se usa **únicamente** para detectar errores del jugador. Un test de Vitest resuelve tableros enteros solo con pistas y verifica que nunca recomienda una reina equivocada ni tacha una casilla de la solución; medido sobre 100 tableros, la suposición aparece en menos del 1 % de las deducciones — el resto son reglas "humanas".
- **No guarda progreso ni tiempos.** Cada partida es de una sentada; salir y volver arranca un tablero nuevo. (Persistir rachas/mejores tiempos está en el backlog, ver [TODO.md](../TODO.md).)
- **Colores de región.** Ocho tierras apagadas y mutuamente distinguibles (tokens `q1`…`q8`), pensadas como fondos de casilla que sostienen el ícono de reina en cuero con contraste suficiente. El estado nunca se comunica solo con color: la reina es una forma (corona), la nota es una cruz y el conflicto suma un aro rojo.
- **La corona es arte del juego, no iconografía de UI.** Es la única figura fuera de Heroicons, tratada como pieza del juego (igual que el logo es una excepción), para que "Queens" se lea de un vistazo.

El acento del módulo es **pizarra** (`#3E4A47`). La identidad visual y la voz de Amparo están en [CLAUDE.md](../CLAUDE.md); esta spec cubre solo lo funcional.
