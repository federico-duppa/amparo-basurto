# Tareas (`/tareas`)

Lista de pendientes pensada para anotar rápido y tachar, con fechas de vencimiento, proyectos, repetición y la matriz de Eisenhower como única forma de priorizar.

## Qué hace

- **Agregar:** un campo de texto siempre visible arriba. Solo pide el título (hasta 255 caracteres; se guarda sin espacios sobrantes en las puntas). Al guardar, el campo se limpia. Si falta el título, Amparo pide "Contame qué tenés que hacer y la anoto."; si se pasa del largo, "Eso es muy largo para una tarea — probá resumirlo.".
- **Detalles opcionales:** debajo del campo, el botón "Fecha, proyecto y prioridad" despliega los detalles: fecha de vencimiento, repetición, proyecto y los dos ejes de Eisenhower (Urgente / Importante). Anotar rápido sigue siendo un solo campo; los detalles quedan plegados por defecto. Si un error de validación cae en un detalle plegado, el panel se despliega solo para mostrarlo.
- **Prioridad (Eisenhower):** cada tarea puede marcarse **urgente**, **importante**, ambas o ninguna. No hay niveles P1–P4 ni escalas: el cuadrante es la prioridad. Al marcar los ejes en el formulario, Amparo lee el cuadrante ("Urgente e importante: de las primeras a encarar."). En la lista, las marcas se muestran como badges (Urgente sobre ocre con texto negro, Importante sobre vino con texto crema).
- **Vencimiento:** fecha opcional (sin hora). En la lista se muestra "vence hoy", "vence el dd/mm/aaaa" o, vencida, "venció el dd/mm/aaaa" en rojo teja y negrita (color acompañado de texto, nunca solo).
- **Repetición:** una tarea puede repetirse todos los días, semanas, meses o años. **Requiere fecha de vencimiento** (si falta, Amparo avisa: "Para repetirla necesito una fecha de vencimiento."). Al completarla, se crea sola la próxima ocurrencia (mismo título, proyecto y prioridad) con la fecha avanzada un ciclo; si la tarea estaba atrasada, la fecha avanza los ciclos necesarios para no nacer vencida. Reabrir una recurrente no toca la ocurrencia ya generada. Los meses avanzan sin desbordar (31/01 → 28/02).
- **Proyectos:** agrupan tareas multi-paso. Se crean desde el chip "+ Proyecto" (nombre obligatorio, hasta 100 caracteres, sin repetir: "Ya tenés un proyecto con ese nombre."). Los chips de proyecto muestran el conteo de pendientes y filtran la lista; con un proyecto seleccionado, las tareas nuevas se le asignan por defecto y aparece la acción de eliminarlo. **Eliminar un proyecto no borra sus tareas: quedan sueltas** (así lo dice la confirmación). Por ahora los proyectos no se renombran (pendiente en el backlog).
- **Vistas:** tres pestañas sobre la lista.
  - **Hoy:** pendientes vencidas o que vencen hoy, ordenadas por cuadrante de Eisenhower y después por fecha. Vacía: "Nada para hoy. Si querés adelantar, mirá las próximas."
  - **Próximas:** pendientes con fecha futura, ordenadas por fecha y después por cuadrante. Vacía: "No tenés nada con fecha por venir."
  - **Lista:** todas las tareas (las sin fecha viven solo acá). Es la vista inicial.
- **Completar / reabrir:** cada tarea tiene un casillero; tocarlo alterna entre pendiente y completada. Las completadas quedan tachadas y atenuadas, sin badges ni fechas.
- **Editar:** el lápiz carga la tarea en el formulario de arriba (con el aviso "Estás editando una tarea." y su botón Cancelar; Escape también cancela). Se editan título, fecha, repetición, proyecto y prioridad con las mismas reglas que al agregar.
- **Eliminar:** con confirmación previa ("Vas a eliminar esta tarea. Esto no se puede deshacer."). No hay papelera: eliminado es eliminado.
- **Limpiar completadas:** en la vista Lista, cuando hay al menos una completada, aparece abajo un botón para borrarlas todas de una, con confirmación que dice cuántas son. Respeta el filtro de proyecto activo: filtrando un proyecto, limpia solo las de ese proyecto.

## Reglas

- **Orden en Lista:** primero todas las pendientes, después las completadas. Dentro de las pendientes manda el cuadrante de Eisenhower (urgente e importante → importante → urgente → el resto) y, a igual cuadrante, la más reciente arriba. Las completadas van de más reciente a más vieja.
- **Contador:** un badge en el encabezado muestra cuántas pendientes hay en total ("3 pendientes"; en singular, "1 pendiente"), sin importar la vista ni el filtro. El badge desaparece cuando no queda ninguna. En la vista Lista, con cero pendientes y tareas cargadas, Amparo felicita ("No te queda nada pendiente. Buen trabajo.").
- **Estado vacío** (Lista, sin ninguna tarea): "Todavía no anotaste nada. Cuando quieras, empezamos." Filtrando un proyecto sin tareas: "Este proyecto está vacío. Anotale la primera tarea."
- **Scoping:** cada usuario ve y opera solo sus tareas y sus proyectos; lo ajeno responde 404 (asignar, filtrar o eliminar incluidos).

## Backlog

Lo pendiente de este módulo vive en [`TODO.md`](../TODO.md); lo descartado a propósito (niveles de prioridad, contextos GTD como entidad, la ceremonia GTD completa), en [`WONTDO.md`](../WONTDO.md).
