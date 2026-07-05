# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Transversal

- **Biometría (passkeys/WebAuthn) en el login.** Mejora del ingreso; requiere HTTPS y un paquete dedicado.

## Auto (`/auto`)

- **Alta de un segundo auto propio desde la interfaz.** El modelo y el selector ya contemplan varios autos; falta el botón de alta (hoy el formulario aparece solo cuando no hay ningún auto accesible).
- **Edición de ítems de mantenimiento, realizaciones y cargas ya guardadas.** Hoy todo es eliminar-y-recrear, y las realizaciones ni siquiera se eliminan sueltas.
- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos solo se ven al entrar al módulo.
- **Documentación del vehículo (seguro, VTV, patente) como vencimientos.**

## Plata (`/plata`)

- **Más monedas además de ARS y USD.**
- **Edición de gastos y movimientos.** Hoy se eliminan y se vuelven a cargar.

## Tareas (`/tareas`)

- **Proyectos, etiquetas, fechas de vencimiento y prioridades.** Hoy es GTD mínimo: una sola lista por usuario.
- **Edición del título de una tarea existente.** Hoy se elimina y se vuelve a crear.
- **Archivado o limpieza de completadas.** Hoy las completadas no se archivan solas.
