# TODO

Backlog **centralizado** de lo que queda pendiente de implementar en Amparo Basurto, agrupado por módulo. Es la única fuente de verdad de "lo que falta": las specs de `docs/` describen el comportamiento **actual**, no el futuro.

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd). Lo que decidimos **no** hacer vive en [WONTDO.md](WONTDO.md).

## Transversal

- **Biometría (passkeys/WebAuthn) en el login.** Mejora del ingreso; requiere HTTPS y un paquete dedicado.
- **Trampa de foco en el date picker (`x-ui.date-field`).** La hoja del calendario cierra con Escape y con clic en el fondo, pero todavía no atrapa el foco del teclado dentro del diálogo mientras está abierta. Requiere el plugin Focus de Alpine (Livewire no lo trae por defecto).

## Auto (`/auto`)

- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos (mantenimientos y documentación) solo se ven al entrar al módulo.
- **Adjuntar foto/archivo a los documentos.** Póliza, oblea de VTV, etc., para tenerlos a mano en el teléfono.
- **Partir el componente `auto.panel`.** ~1.500 líneas y ~30 propiedades públicas en un solo single-file component; separar en hijos (mantenimientos, combustible, documentación, compartir) para bajar el payload de Livewire por interacción y hacerlo más manejable.

## Salud (`/salud`)

- **Documentos adjuntos (recetas, órdenes, estudios, resultados).** Primera funcionalidad con archivos de la app: por el scale to zero de Laravel Cloud no pueden vivir en el disco del contenedor — necesita object storage (S3-compatible) y URLs firmadas para servirlos. Un documento va a poder colgar de una entrada del timeline o suelto en la historia.
- **Recordatorios activos (notificaciones) de vencimientos.** Hoy los vencimientos (controles, recetas, próximas dosis) solo se ven al entrar al módulo. Bloqueado por la misma infraestructura transversal de notificaciones que Auto y Tareas.
- **Reporte imprimible/exportable** de la historia para llevar al médico.

## Plata (`/plata`)

- **Más monedas además de ARS y USD.**

## Tareas (`/tareas`)

El rumbo del módulo no es "GTD completo" sino el híbrido que probaron las buenas apps de tareas: captura rápida + vista de hoy + fechas y recurrencia, con la matriz de Eisenhower como única priorización (ver límites en [WONTDO.md](WONTDO.md)).

- **Revisión guiada por Amparo.** Repaso semanal conversado de lo que quedó viejo ("Esta quedó de hace tres semanas, ¿la seguís queriendo hacer?").
- **Recordatorios activos (notificaciones) de vencimientos.** Bloqueado por la infraestructura de notificaciones que todavía no existe (la app no tiene email, corre con scale to zero y no hay push): es transversal y se comparte con Auto. Hasta que esa infra exista, los vencimientos solo se ven al entrar al módulo.
- **Tareas desde otros módulos.** Que un vencimiento de Auto ("la VTV vence en 15 días") pueda generar una tarea con fecha. Requiere tocar el módulo Auto; queda pendiente hasta encarar esa integración.
