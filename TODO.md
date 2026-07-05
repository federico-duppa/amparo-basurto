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

## Plata (`/plata`)

- **Más monedas además de ARS y USD.**

## Tareas (`/tareas`)

- **Proyectos, etiquetas, fechas de vencimiento y prioridades.** Hoy es GTD mínimo: una sola lista por usuario.
