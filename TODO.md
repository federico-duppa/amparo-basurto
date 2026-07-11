# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Auto (`/auto`)

- **`spendingByPeriod()` agrupa en PHP, no en SQL.** Trae todos los mantenimientos y cargas con costo y agrupa por mes/año en memoria (decisión deliberada por portabilidad SQLite/Postgres). Con años de historia carga todo en cada render del componente de gastos; vigilar si escala y, llegado el caso, resolverlo con una query agregada compatible con ambos motores.

## Tareas (`/tareas`)

El rumbo del módulo no es "GTD completo" sino el híbrido que probaron las buenas apps de tareas: captura rápida + vista de hoy + fechas y recurrencia, con la matriz de Eisenhower como única priorización (ver límites en [WONTDO.md](WONTDO.md)).

- **Revisión guiada por Amparo.** Repaso semanal conversado de lo que quedó viejo ("Esta quedó de hace tres semanas, ¿la seguís queriendo hacer?").
- **Tareas desde otros módulos.** Que un vencimiento de Auto ("la VTV vence en 15 días") pueda generar una tarea con fecha. Requiere tocar el módulo Auto; queda pendiente hasta encarar esa integración.

## Compartir (`/compartir`)

- **Recibir imágenes y archivos compartidos.** Hoy el share target solo acepta texto (método GET). Recibir una foto o un PDF requiere `share_target` con POST `multipart/form-data` (`enctype` y `files` en el manifest, más un endpoint que reciba el upload); para guardarlos ya existe el patrón de los adjuntos de Salud (object storage + URLs firmadas).
- **Más destinos al compartir.** Hoy Amparo ofrece tarea o lista de compras; podrían sumarse otros destinos (un gasto de Plata, una entrada del timeline de Salud) cuando haya un caso de uso real.

## Juegos (`/juegos`)

- **Más juegos en el catálogo.** El módulo está pensado para crecer; hoy tiene Queens y Sol y luna.
- **Guardar progreso y tiempos.** Hoy cada partida (de cualquier juego) es de una sentada y no persiste nada. Sumar mejores tiempos / racha requiere un modelo por usuario (con el scoping de siempre).
- **Puzzle del día.** Un tablero fijo por día, igual para todos, como los juegos originales. Necesita generación determinística por fecha (semilla): los generadores de `resources/js/queens.js` y `resources/js/solyluna.js` usan `Math.random`, habría que pasarles un RNG sembrado por la fecha (y, si además hay ranking, validar del lado del servidor).
- **Dificultad elegible o tamaños de grilla.** Los tableros ya salen sesgados a difíciles (medidos con el motor de deducciones); falta, si algún día se quiere, ofrecer niveles a elección ("tranquilo/difícil") o grillas más chicas/grandes. La grilla es fija 8×8.

## Técnico (mantenimiento y performance)

Deuda transversal de código y datos, no de producto. Va acá porque no pertenece a un módulo puntual.

- **Análisis estático (Larastan/PHPStan) en CI.** El workflow ya corre la suite y `pint --test`; falta sumar análisis estático al pipeline. Se intentó instalar `larastan/larastan` pero `phpstan/phpstan` es un paquete solo-dist (sin fuente git) que Packagist resuelve vía `api.github.com/repos/.../zipball/...`, y ese host devuelve 403 en el sandbox de desarrollo (política de red de la sesión no lo permite, y no corresponde intentar sortearla). Falta: instalar la dependencia desde un entorno con acceso a `api.github.com`, correr el análisis para ver qué tan lejos está el código base de pasar un nivel razonable, y recién ahí sumar el paso de CI (agregarlo a ciegas, sin poder correrlo, arriesga dejar el pipeline en rojo).
