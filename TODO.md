# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Transversal

- **Biometría (passkeys/WebAuthn) en el login.** Mejora del ingreso; requiere HTTPS y un paquete dedicado.
- **Trampa de foco en el date picker (`x-ui.date-field`).** La hoja del calendario cierra con Escape y con clic en el fondo, pero todavía no atrapa el foco del teclado dentro del diálogo mientras está abierta. Requiere el plugin Focus de Alpine (Livewire no lo trae por defecto).

## Auto (`/auto`)

- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos (mantenimientos y documentación) solo se ven al entrar al módulo.
- **Adjuntar foto/archivo a los documentos.** Póliza, oblea de VTV, etc., para tenerlos a mano en el teléfono.
- **Partir el componente `auto.panel`.** ~1.900 líneas y ~40 propiedades públicas en un solo single-file component. Livewire dehidrata y rehidrata **todas** las propiedades en cada interacción aunque haya un solo formulario abierto, así que el payload y el costo de checksum crecen sin necesidad. Seguir el patrón que ya usa Salud (`salud.panel` cuelga cuatro hijos Livewire) y separar en `auto.mantenimientos`, `auto.combustible`, `auto.documentos` y `auto.gastos`. Baja el payload por request y hace el módulo manejable.
- **`spendingByPeriod()` agrupa en PHP, no en SQL.** Trae todos los mantenimientos y cargas con costo y agrupa por mes/año en memoria (decisión deliberada por portabilidad SQLite/Postgres). Con años de historia carga todo en cada render del panel; vigilar si escala y, llegado el caso, resolverlo con una query agregada compatible con ambos motores.

## Salud (`/salud`)

- **Documentos adjuntos (recetas, órdenes, estudios, resultados).** Primera funcionalidad con archivos de la app: por el scale to zero de Laravel Cloud no pueden vivir en el disco del contenedor — necesita object storage (S3-compatible) y URLs firmadas para servirlos. Un documento va a poder colgar de una entrada del timeline o suelto en la historia.
- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos (controles, recetas, próximas dosis) solo se ven al entrar al módulo. Bloqueado por la misma infraestructura transversal de notificaciones que Auto y Tareas.
- **Reporte imprimible/exportable** de la historia para llevar al médico.
- **Índice compuesto de vencimientos en `health_reminders` y `health_vaccines`.** Las tablas solo traen los índices de FK. `health_measurements` ya trae `(health_record_id, type, measured_on)` y `vehicle_documents` (el par de Auto) con `(vehicle_id, expires_on)`, pero el `(health_record_id, expires_on)` equivalente para recordatorios y vacunas quedó afuera de la migración de índices. Agregarlo por consistencia y para no depender de scans a medida que crece la historia.
- **`latestByType()` hace una query por tipo de medición.** `salud.mediciones` recorre `HealthMeasurement::TYPES` (peso, presión, glucemia, temperatura…) y dispara un `SELECT ... LIMIT 1` por cada tipo en cada render del panel. Resolverlo con una sola query agrupada (último registro por tipo) en vez de N consultas.

## Compras (`/compras`)

- **Cantidades por ítem.** Hoy se anota la cosa, no "2 de leche". Sumar una cantidad/unidad opcional sin volver pesado el gesto de anotar.
- **Pasarle la lista a otra persona (transferir dueño).** Como en Auto, para que una lista compartida no quede huérfana si el dueño deja de usar la app. Hoy solo se comparte y se deja de compartir.
- **Ordenar las cosas a mano o por sector.** Hoy la lista va alfabética; para el recorrido del súper conviene poder agrupar por góndola o reordenar.

## Plata (`/plata`)

- **Más monedas además de ARS y USD.**

## Tareas (`/tareas`)

El rumbo del módulo no es "GTD completo" sino el híbrido que probaron las buenas apps de tareas: captura rápida + vista de hoy + fechas y recurrencia, con la matriz de Eisenhower como única priorización (ver límites en [WONTDO.md](WONTDO.md)).

- **Revisión guiada por Amparo.** Repaso semanal conversado de lo que quedó viejo ("Esta quedó de hace tres semanas, ¿la seguís queriendo hacer?").
- **Recordatorios activos (notificaciones) de vencimientos.** Bloqueado por la infraestructura de notificaciones que todavía no existe (la app no tiene email, corre con scale to zero y no hay push): es transversal y se comparte con Auto. Hasta que esa infra exista, los vencimientos solo se ven al entrar al módulo.
- **Tareas desde otros módulos.** Que un vencimiento de Auto ("la VTV vence en 15 días") pueda generar una tarea con fecha. Requiere tocar el módulo Auto; queda pendiente hasta encarar esa integración.
- **`clearCompleted()` borra fila por fila.** "Limpiar completadas" hace `$query->get()->each->delete()`: hidrata cada tarea completada a modelo y dispara un `DELETE` por fila. Ningún modelo tiene hooks de `deleting` que necesiten trabajo por fila, así que puede ser un único `$query->delete()` masivo. Para quien acumuló cientos de completadas es una ráfaga de escrituras evitable.
- **`reorder()` reescribe toda la lista.** Cada mover-arriba/abajo trae la lista activa completa, ordena en PHP y hace un `UPDATE` de `position` por cada fila. Un swap de dos filas alcanza; hoy es una ráfaga de escrituras por cada clic de reordenar.
- **Índice `(user_id, status, completed_at)` para la vista "Lista".** Es la vista default y más usada (`user_id`, `completed_at IS NULL`, `status`, ordenada por Eisenhower + `position`). La migración de índices cubrió `(user_id, completed_at)` y `(user_id, due_date)` pero no el `status` que filtra esta vista.
- **Limpieza de tags huérfanos solo cuando cambian los tags.** Hoy `tags()->doesntHave('todos')->delete()` corre en **cada** guardado/edición de tarea, aunque no se hayan tocado etiquetas. Ejecutarlo solo cuando la selección de tags cambió, para sacar trabajo del camino caliente de alta/edición.

## Técnico (mantenimiento y performance)

Deuda transversal de código y datos, no de producto. Va acá porque no pertenece a un módulo puntual.

- **Un único lugar para formateo de moneda y números.** El formateo `($currency === 'ARS' ? '$' : 'US$').number_format(..., 2, ',', '.')` está copiado en `plata.sobre`, `plata.reportes`, `plata.sobres` y `plata.gastos`; Auto tiene sus propios `pesos()` y `km()`. Y el idiom `rtrim(rtrim((string) $value, '0'), '.')` (limpiar decimales para inputs de edición) aparece en Auto y Plata repetido. No hay un hogar compartido para esto (`app/Support/` no tiene helper de formato). Mover a un trait `FormatsMoney` en `app/Models/Concerns` o a un helper Blade/`Number` para que no diverjan.
- **Trait de compartir (`SharesWithMembers`).** La lógica de compartir está triplicada casi idéntica en `auto.panel`, `salud.panel` y `todo.todo-list`: normalizar el username, `User::where('username', …)->first()`, chequeo de dueño, chequeo de duplicado, `members()->attach()`, con los mismos textos de error. Colapsar las tres copias en un trait (o un servicio chico).
- **Centralizar el scoping `accessible*()`.** `accessibleProjects()`, `accessibleVehicles()` y `accessibleHealthRecords()` en `User` son la misma query "propio ∪ compartido" cambiando solo el modelo. Son scope sensible a seguridad repetido a mano tres veces; un builder/macro compartido centraliza la regla de acceso y evita que una copia se desalinee.
- **Memoizar los helpers de scoping por request.** `requireRecord()`, `requireVehicle()` y `requireEnvelope()` corren un `accessible…()->findOrFail()` en cada llamada y no están memoizados; en un solo render se invocan varias veces (p. ej. `mediciones` los llama en `mount()`, `latestByType()` y `historyWindow()`). Rutearlos por la propiedad `#[Computed]` que ya existe (`record()`/`vehicle()`/`envelope()`) o cachear el modelo resuelto en la instancia elimina las consultas repetidas.
- **Tests unitarios de la lógica de dominio de los modelos.** `tests/Unit/` solo cubre `Lens` y `NaturalDate`. Los cálculos más intrincados —`Envelope::balance()`/`currentTarget()` (indexación IPC)/`gap()`/`progress()`, `MaintenanceItem::status()` (km↔tiempo), `Vehicle::kmPerDay()`, `Todo::nextDueDate()`/`eisenhowerWeight()`— son puros y con muchos casos de tabla, pero solo se ejercitan indirectamente por los tests de componente. Sumar tests unitarios enfocados es cobertura barata y de bajo riesgo.
- **Renumerar migraciones con timestamp duplicado.** Hay cuatro pares de migraciones que comparten prefijo (`2026_07_04_165025`, `2026_07_05_130000`, `2026_07_05_130001`, `2026_07_05_140000`). Entre migraciones con el mismo prefijo el orden queda librado al alfabético del nombre de archivo, que es frágil. Renumerar para tener orden determinístico.
- **CI: `tests.yml` no corre en push a `main`.** El workflow dispara en push a `master` y `*.x`, pero la rama default es `main`; los push directos a `main` no corren la suite (solo los pull requests la corren). Corregir los triggers para cubrir `main`. De paso, sumar `vendor/bin/pint --test` y evaluar análisis estático (Larastan/PHPStan) al pipeline, que hoy no existen.
