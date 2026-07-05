# Tareas (`/tareas`)

Lista de pendientes al estilo GTD mínimo: una sola lista por usuario, pensada para anotar rápido y tachar.

## Qué hace

- **Agregar:** un campo de texto siempre visible arriba. Solo pide el título (hasta 255 caracteres; se guarda sin espacios sobrantes en las puntas). Al guardar, el campo se limpia y la tarea aparece primera entre las pendientes. Si falta el título, Amparo pide "Contame qué tenés que hacer y la anoto."; si se pasa del largo, "Eso es muy largo para una tarea — probá resumirlo.".
- **Completar / reabrir:** cada tarea tiene un casillero; tocarlo alterna entre pendiente y completada. Las completadas quedan tachadas y atenuadas.
- **Editar el título:** el lápiz de cada tarea abre un campo en línea para corregir el texto (mismas reglas que al agregar: obligatorio, hasta 255 caracteres, sin espacios en las puntas). Se guarda con el tilde o se descarta con la cruz (o con Escape). Solo cambia el título; completar o reabrir sigue siendo el casillero.
- **Eliminar:** con confirmación previa ("Vas a eliminar esta tarea. Esto no se puede deshacer."). No hay papelera: eliminado es eliminado.
- **Limpiar completadas:** cuando hay al menos una completada, aparece abajo un botón para borrarlas todas de una, con confirmación que dice cuántas son. No se archivan solas: esta es la forma de sacarlas del medio cuando se acumulan.

## Reglas

- **Orden:** primero todas las pendientes, después las completadas; dentro de cada grupo, la más reciente arriba. "Más reciente" se mide por creación: completar o reabrir una tarea la mueve de grupo, pero no cambia su lugar dentro del grupo.
- **Contador:** un badge en el encabezado muestra cuántas pendientes hay ("3 pendientes"; en singular, "1 pendiente"). El badge desaparece cuando no queda ninguna. Con cero pendientes y tareas cargadas, Amparo felicita ("No te queda nada pendiente. Buen trabajo.").
- **Estado vacío:** "Todavía no anotaste nada. Cuando quieras, empezamos."
- **Scoping:** cada usuario ve y opera solo sus tareas.

## Backlog

Lo pendiente de este módulo vive en [`TODO.md`](../TODO.md); lo descartado, en [`WONTDO.md`](../WONTDO.md).
