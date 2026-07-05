# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Transversal

- **Biometría (passkeys/WebAuthn) en el login.** Mejora del ingreso; requiere HTTPS y un paquete dedicado.

## Auto (`/auto`)

- **Alta de un segundo auto propio desde la interfaz.** El modelo y el selector ya contemplan varios autos; falta el botón de alta (hoy el formulario aparece solo cuando no hay ningún auto accesible).
- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos (mantenimientos y documentación) solo se ven al entrar al módulo.

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
