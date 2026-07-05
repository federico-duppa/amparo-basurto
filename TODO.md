# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Transversal

- **Biometría (passkeys/WebAuthn) en el login.** Mejora del ingreso; requiere HTTPS y un paquete dedicado.
- **Trampa de foco en el date picker (`x-ui.date-field`).** La hoja del calendario cierra con Escape y con clic en el fondo, pero todavía no atrapa el foco del teclado dentro del diálogo mientras está abierta. Requiere el plugin Focus de Alpine (Livewire no lo trae por defecto).

## Auto (`/auto`)

- **Alta de un segundo auto propio desde la interfaz.** El modelo y el selector ya contemplan varios autos; falta el botón de alta (hoy el formulario aparece solo cuando no hay ningún auto accesible).
- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos (mantenimientos y documentación) solo se ven al entrar al módulo.
- **Adjuntar foto/archivo a los documentos.** Póliza, oblea de VTV, etc., para tenerlos a mano en el teléfono.
- **Gastos por período.** Hoy solo hay totales acumulados de toda la vida del auto; falta un desglose por mes/año de mantenimiento vs. combustible. (Es plata, no rendimiento: el consumo en litros sigue descartado en WONTDO.)
- **Transferir la propiedad del auto a otra persona.** Hoy solo se puede compartir; si el dueño deja de usar la app, el auto queda huérfano.
- **Acotar la lista de cargas de combustible.** Se muestran todas sin límite; con uso real la pantalla crece sin freno. Mostrar las últimas N con un "ver más".
- **Acotar el historial de realizaciones de mantenimiento.** El acordeón de cada ítem trae todas las realizaciones con `->get()` sin límite; con años de historia conviene paginar o mostrar las últimas N con "ver más".
- **Partir el componente `auto.panel`.** ~1.500 líneas y ~30 propiedades públicas en un solo single-file component; separar en hijos (mantenimientos, combustible, documentación, compartir) para bajar el payload de Livewire por interacción y hacerlo más manejable.

## Salud (`/salud`)

- **Documentos adjuntos (recetas, órdenes, estudios, resultados).** Primera funcionalidad con archivos de la app: por el scale to zero de Laravel Cloud no pueden vivir en el disco del contenedor — necesita object storage (S3-compatible) y URLs firmadas para servirlos. Un documento va a poder colgar de una entrada del timeline o suelto en la historia.
- **Vacunas como sección propia** (carnet/calendario). Hoy se registran como entradas de tipo "vacuna"; si el uso lo amerita, se estructura.
- **Vencimientos y recordatorios**: próximo control, receta que caduca, estudio anual. Mismo patrón de "vencimiento" que la documentación de Auto.
- **Contactos médicos por historia**: médico de cabecera, especialistas, teléfonos.
- **Mediciones** (peso, presión, glucemia…) con su evolución en el tiempo.
- **Reporte imprimible/exportable** de la historia para llevar al médico.
- **Paginar el timeline de la historia.** `entries()` trae todas las entradas con `->get()` sin límite y las pinta en cada render; una historia con años de consultas crece sin techo. Paginar (o `simplePaginate`) ordenando por `occurred_on desc, id desc`.

## Plata (`/plata`)

- **Más monedas además de ARS y USD.**
- **Precalcular los saldos en la lista de sobres (N+1).** Por cada sobre el listado llama `balance()` (dos sumas sobre movimientos + una sobre gastos), `currentTarget()` y `progress()` —que a su vez re-llama a los dos anteriores—, así que son ~9 queries por sobre. Con muchos sobres la pantalla dispara cientos de consultas. Precargar los agregados con `withSum`/subqueries en la computed `envelopes()` y derivar saldo/objetivo/progreso desde ahí. (La ficha individual del sobre ya resuelve saldo y objetivo una sola vez por render.)
- **Evitar el N+1 de cotización/inflación en Reportes.** `Lens::value()` corre por cada gasto de los últimos 12 meses y por gasto puede pegarle a `ExchangeRate` y a `InflationRate`. La cotización de referencia es constante en todo el reporte (resolverla una vez fuera del `map`); `factorBetween` solo varía por mes (memoizar por `Y-m` o precargar el rango en una query); la serie de `ExchangeRate` se puede precargar e indexar en memoria por fecha.
- **Paginar el historial del sobre.** `timeline()` carga todos los movimientos y todos los gastos del sobre, los concatena y ordena en PHP sin límite. Ordenar por fecha en SQL en cada query y acotar el merge (paginación o "ver más").

## Tareas (`/tareas`)

El rumbo del módulo no es "GTD completo" sino el híbrido que probaron las buenas apps de tareas: captura rápida + vista de hoy + fechas y recurrencia, con la matriz de Eisenhower como única priorización (ver límites en [WONTDO.md](WONTDO.md)).

- **Revisión guiada por Amparo.** Repaso semanal conversado de lo que quedó viejo ("Esta quedó de hace tres semanas, ¿la seguís queriendo hacer?").
- **Recordatorios activos (notificaciones) de vencimientos.** Bloqueado por la infraestructura de notificaciones que todavía no existe (la app no tiene email, corre con scale to zero y no hay push): es transversal y se comparte con Auto. Hasta que esa infra exista, los vencimientos solo se ven al entrar al módulo.
- **Tareas desde otros módulos.** Que un vencimiento de Auto ("la VTV vence en 15 días") pueda generar una tarea con fecha. Requiere tocar el módulo Auto; queda pendiente hasta encarar esa integración.
