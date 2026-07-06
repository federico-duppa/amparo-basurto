# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Transversal

- **Biometría (passkeys/WebAuthn) en el login.** Mejora del ingreso; requiere HTTPS y un paquete dedicado.
- **Trampa de foco en el date picker (`x-ui.date-field`).** La hoja del calendario cierra con Escape y con clic en el fondo, pero todavía no atrapa el foco del teclado dentro del diálogo mientras está abierta. Requiere el plugin Focus de Alpine (Livewire no lo trae por defecto).

## Auto (`/auto`)

- **Adjuntar foto/archivo a los documentos.** Póliza, oblea de VTV, etc., para tenerlos a mano en el teléfono.
- **Partir el componente `auto.panel`.** ~1.900 líneas y ~40 propiedades públicas en un solo single-file component. Livewire dehidrata y rehidrata **todas** las propiedades en cada interacción aunque haya un solo formulario abierto, así que el payload y el costo de checksum crecen sin necesidad. Seguir el patrón que ya usa Salud (`salud.panel` cuelga cuatro hijos Livewire) y separar en `auto.mantenimientos`, `auto.combustible`, `auto.documentos` y `auto.gastos`. Baja el payload por request y hace el módulo manejable.
- **`spendingByPeriod()` agrupa en PHP, no en SQL.** Trae todos los mantenimientos y cargas con costo y agrupa por mes/año en memoria (decisión deliberada por portabilidad SQLite/Postgres). Con años de historia carga todo en cada render del panel; vigilar si escala y, llegado el caso, resolverlo con una query agregada compatible con ambos motores.

## Salud (`/salud`)

- **Documentos adjuntos (recetas, órdenes, estudios, resultados).** Primera funcionalidad con archivos de la app: por el scale to zero de Laravel Cloud no pueden vivir en el disco del contenedor — necesita object storage (S3-compatible) y URLs firmadas para servirlos. Un documento va a poder colgar de una entrada del timeline o suelto en la historia.
- **Reporte imprimible/exportable** de la historia para llevar al médico.

## Compras (`/compras`)

- **Cantidades por ítem.** Hoy se anota la cosa, no "2 de leche". Sumar una cantidad/unidad opcional sin volver pesado el gesto de anotar.
- **Pasarle la lista a otra persona (transferir dueño).** Como en Auto, para que una lista compartida no quede huérfana si el dueño deja de usar la app. Hoy solo se comparte y se deja de compartir.
- **Ordenar las cosas a mano o por sector.** Hoy la lista va alfabética; para el recorrido del súper conviene poder agrupar por góndola o reordenar.

## Plata (`/plata`)

- **Más monedas además de ARS y USD.**

## Tareas (`/tareas`)

El rumbo del módulo no es "GTD completo" sino el híbrido que probaron las buenas apps de tareas: captura rápida + vista de hoy + fechas y recurrencia, con la matriz de Eisenhower como única priorización (ver límites en [WONTDO.md](WONTDO.md)).

- **Revisión guiada por Amparo.** Repaso semanal conversado de lo que quedó viejo ("Esta quedó de hace tres semanas, ¿la seguís queriendo hacer?").
- **Tareas desde otros módulos.** Que un vencimiento de Auto ("la VTV vence en 15 días") pueda generar una tarea con fecha. Requiere tocar el módulo Auto; queda pendiente hasta encarar esa integración.

## Técnico (mantenimiento y performance)

Deuda transversal de código y datos, no de producto. Va acá porque no pertenece a un módulo puntual.

- **Un único lugar para formateo de moneda y números.** El formateo `($currency === 'ARS' ? '$' : 'US$').number_format(..., 2, ',', '.')` está copiado en `plata.sobre`, `plata.reportes`, `plata.sobres` y `plata.gastos`; Auto tiene sus propios `pesos()` y `km()`. Y el idiom `rtrim(rtrim((string) $value, '0'), '.')` (limpiar decimales para inputs de edición) aparece en Auto y Plata repetido. No hay un hogar compartido para esto (`app/Support/` no tiene helper de formato). Mover a un trait `FormatsMoney` en `app/Models/Concerns` o a un helper Blade/`Number` para que no diverjan.
- **Trait de compartir (`SharesWithMembers`).** La lógica de compartir está triplicada casi idéntica en `auto.panel`, `salud.panel` y `todo.todo-list`: normalizar el username, `User::where('username', …)->first()`, chequeo de dueño, chequeo de duplicado, `members()->attach()`, con los mismos textos de error. Colapsar las tres copias en un trait (o un servicio chico).
- **Centralizar el scoping `accessible*()`.** `accessibleProjects()`, `accessibleVehicles()` y `accessibleHealthRecords()` en `User` son la misma query "propio ∪ compartido" cambiando solo el modelo. Son scope sensible a seguridad repetido a mano tres veces; un builder/macro compartido centraliza la regla de acceso y evita que una copia se desalinee.
- **Memoizar los helpers de scoping por request.** `requireRecord()`, `requireVehicle()` y `requireEnvelope()` corren un `accessible…()->findOrFail()` en cada llamada y no están memoizados; en un solo render se invocan varias veces (p. ej. `mediciones` los llama en `mount()`, `latestByType()` y `historyWindow()`). Rutearlos por la propiedad `#[Computed]` que ya existe (`record()`/`vehicle()`/`envelope()`) o cachear el modelo resuelto en la instancia elimina las consultas repetidas.
- **Tests unitarios de la lógica de dominio de los modelos.** `tests/Unit/` solo cubre `Lens` y `NaturalDate`. Los cálculos más intrincados —`Envelope::balance()`/`currentTarget()` (indexación IPC)/`gap()`/`progress()`, `MaintenanceItem::status()` (km↔tiempo), `Vehicle::kmPerDay()`, `Todo::nextDueDate()`/`eisenhowerWeight()`— son puros y con muchos casos de tabla, pero solo se ejercitan indirectamente por los tests de componente. Sumar tests unitarios enfocados es cobertura barata y de bajo riesgo.
- **Renumerar migraciones con timestamp duplicado.** Hay cuatro pares de migraciones que comparten prefijo (`2026_07_04_165025`, `2026_07_05_130000`, `2026_07_05_130001`, `2026_07_05_140000`). Entre migraciones con el mismo prefijo el orden queda librado al alfabético del nombre de archivo, que es frágil. Renumerar para tener orden determinístico.
- **Análisis estático (Larastan/PHPStan) en CI.** El workflow ya corre la suite y `pint --test`; falta evaluar sumar análisis estático al pipeline.
