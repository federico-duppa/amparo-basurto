# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Transversal

- **Biometría (passkeys/WebAuthn) en el login.** Mejora del ingreso; requiere HTTPS y un paquete dedicado.

## Auto (`/auto`)

- **Alta de un segundo auto propio desde la interfaz.** El modelo y el selector ya contemplan varios autos; falta el botón de alta (hoy el formulario aparece solo cuando no hay ningún auto accesible).
- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos (mantenimientos y documentación) solo se ven al entrar al módulo.

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

- **Proyectos, etiquetas, fechas de vencimiento y prioridades.** Hoy es GTD mínimo: una sola lista por usuario.
