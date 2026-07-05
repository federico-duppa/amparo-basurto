# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Transversal

- **Biometría (passkeys/WebAuthn) en el login.** Mejora del ingreso; requiere HTTPS y un paquete dedicado.

## Auto (`/auto`)

- **Alta de un segundo auto propio desde la interfaz.** El modelo y el selector ya contemplan varios autos; falta el botón de alta (hoy el formulario aparece solo cuando no hay ningún auto accesible).
- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos (mantenimientos y documentación) solo se ven al entrar al módulo.
- **Nota opcional en las realizaciones de mantenimiento.** Taller, qué se hizo exactamente, repuestos. Los documentos ya tienen nota; las realizaciones solo guardan fecha/km/costo.
- **Estimación de fecha para vencimientos por km.** Calcular el ritmo de uso real de cada auto (deducible de cargas y registros) para traducir "faltan 3.400 km" a una fecha aproximada, y reemplazar la escala fija 1 día ≈ 40 km en el orden por criticidad.
- **Mostrar quién anotó cada registro en autos compartidos.** El `user_id` ya se guarda en realizaciones, cargas y documentos, pero la interfaz no lo muestra.
- **Renovación de documentos con historial.** Hoy renovar (p. ej. el seguro) implica editar la fecha y pisar la anterior; una acción "renové" debería conservar las vigencias anteriores, como el historial de mantenimientos.
- **Periodicidad en documentos.** Seguro semestral, VTV anual…: al renovar, sugerir la próxima fecha de vencimiento automáticamente.
- **Adjuntar foto/archivo a los documentos.** Póliza, oblea de VTV, etc., para tenerlos a mano en el teléfono.
- **Gastos por período.** Hoy solo hay totales acumulados de toda la vida del auto; falta un desglose por mes/año de mantenimiento vs. combustible. (Es plata, no rendimiento: el consumo en litros sigue descartado en WONTDO.)
- **Transferir la propiedad del auto a otra persona.** Hoy solo se puede compartir; si el dueño deja de usar la app, el auto queda huérfano.
- **Acotar la lista de cargas de combustible.** Se muestran todas sin límite; con uso real la pantalla crece sin freno. Mostrar las últimas N con un "ver más".
- **Partir el componente `auto.panel`.** ~1.500 líneas y ~30 propiedades públicas en un solo single-file component; separar en hijos (mantenimientos, combustible, documentación, compartir) para bajar el payload de Livewire por interacción y hacerlo más manejable.

## Salud (`/salud`)

- **Documentos adjuntos (recetas, órdenes, estudios, resultados).** Primera funcionalidad con archivos de la app: por el scale to zero de Laravel Cloud no pueden vivir en el disco del contenedor — necesita object storage (S3-compatible) y URLs firmadas para servirlos. Un documento va a poder colgar de una entrada del timeline o suelto en la historia.
- **Vacunas como sección propia** (carnet/calendario). Hoy se registran como entradas de tipo "vacuna"; si el uso lo amerita, se estructura.
- **Vencimientos y recordatorios**: próximo control, receta que caduca, estudio anual. Mismo patrón de "vencimiento" que la documentación de Auto.
- **Contactos médicos por historia**: médico de cabecera, especialistas, teléfonos.
- **Mediciones** (peso, presión, glucemia…) con su evolución en el tiempo.
- **Reporte imprimible/exportable** de la historia para llevar al médico.

## Plata (`/plata`)

- **Más monedas además de ARS y USD.**

## Tareas (`/tareas`)

El rumbo del módulo no es "GTD completo" sino el híbrido que probaron las buenas apps de tareas: captura rápida + vista de hoy + fechas y recurrencia, con la matriz de Eisenhower como única priorización (ver límites en [WONTDO.md](WONTDO.md)).

- **Etiquetas.** Libres, por usuario; sirven también como contextos GTD (@casa, @calle) para quien quiera usarlas así, sin imponerlas.
- **Posponer ("no me lo muestres hasta").** La tarea desaparece de las vistas hasta una fecha elegida — el *tickler* de GTD, barato y saca mucho ruido.
- **Algún día.** Un estado aparte para lo que no se quiere perder pero no es ahora; evita que la lista principal se pudra.
- **En espera.** Marcar una tarea como bloqueada por un tercero ("esperando que confirme Juan").
- **Revisión guiada por Amparo.** Repaso semanal conversado de lo que quedó viejo ("Esta quedó de hace tres semanas, ¿la seguís queriendo hacer?").
- **Notas / descripción en la tarea.** Hoy solo hay título.
- **Subtareas / checklist** dentro de una tarea.
- **Fechas en lenguaje natural al anotar** ("mañana", "el viernes").
- **Orden manual (arrastrar)** dentro de las pendientes.
- **Búsqueda y filtros** cuando la lista crece.
- **Recordatorios activos (notificaciones) de vencimientos.** Comparte la infraestructura pendiente de Auto.
- **Renombrar proyectos.** Hoy los proyectos solo se crean y se eliminan.
- **Tareas desde otros módulos.** Que un vencimiento de Auto ("la VTV vence en 15 días") pueda generar una tarea con fecha.
- **Proyectos compartidos entre usuarios** (compras de la casa), con el patrón pivote + chequeo de dueño que estableció Auto — nunca relajando el scoping.
