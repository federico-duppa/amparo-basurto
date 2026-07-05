# Auto (`/auto`)

Mantenimiento del vehículo: qué le toca al auto y cuándo, historial de lo que se le hizo, cargas de combustible y cuánto va costando todo. Es además el primer módulo **compartido**: un auto tiene un dueño y puede compartirse con otras personas.

## El auto

- **Alta:** marca, modelo, patente (opcional, se guarda en mayúsculas) y kilometraje actual. El formulario de alta aparece solo cuando no hay ningún auto accesible; hoy **no hay forma de dar de alta un segundo auto propio desde la interfaz** (el modelo lo soporta).
- **Varios autos:** con más de un auto accesible (por ejemplo, uno propio y uno compartido) aparece un selector, que marca los ajenos con "compartido". Al entrar al módulo se muestra el auto más antiguo de los accesibles.
- **Kilometraje actual:** es la referencia contra la que se calculan los vencimientos. Se puede corregir a mano en cualquier momento (incluso hacia abajo), y además **sube solo**: al registrar un mantenimiento o una carga de combustible con un kilometraje mayor, el del auto se actualiza (nunca baja por esta vía).
- **Editar** los datos del auto y **eliminarlo** son acciones de dueño. Eliminar el auto borra también todo su historial (mantenimientos, registros y cargas) y requiere confirmación.

## Mantenimientos a seguir

Cada auto tiene una lista de ítems de mantenimiento ("Cambio de aceite", "Correa de distribución"…), cada uno con un intervalo en **km**, en **meses**, ambos o ninguno.

- **Presets:** al crear un auto se precargan tres sugerencias: cambio de aceite (10.000 km / 12 meses), bujías (40.000 km) y correa de distribución (60.000 km / 60 meses). Son ítems comunes que se pueden borrar como cualquier otro.
- **Editar un ítem:** el lápiz de cada ítem abre un formulario en línea para corregir el nombre y los intervalos (km, meses, ambos o ninguno; mismas reglas que al crearlo). Cambiar los intervalos recalcula el próximo vencimiento; el historial de realizaciones no se toca. **Eliminar un ítem borra también su historial de realizaciones** (con confirmación previa que lo avisa).
- **Estado calculado:** contra el kilometraje actual y la fecha de hoy, cada ítem está en uno de cuatro estados:
  - **Sin registrar** — nunca se anotó una realización; no hay desde dónde calcular.
  - **Al día** — falta más de 1.000 km y más de 30 días para el vencimiento.
  - **Pronto** — quedan 1.000 km o menos, o 30 días o menos.
  - **Atrasado** — se pasó el intervalo en km o en fecha.
- Si el ítem tiene los dos intervalos, manda el más urgente. Para ordenar la lista por criticidad, km y tiempo compiten en una misma escala de días: los km que faltan se traducen con el **ritmo de uso real del auto** (km por día, deducido de las lecturas con fecha de cargas y realizaciones — hacen falta al menos dos lecturas con 14 días y kilómetros recorridos entre medio). Con ritmo conocido, el detalle además estima la **fecha aproximada** del vencimiento por km ("faltan 3.400 km (aprox. el 15/09/2026)"). Sin datos suficientes se supone 1 día ≈ 40 km y no se estima fecha.
- **Orden de la lista:** atrasados primero, después los "pronto", los al día, y al final los sin registrar; dentro del mismo estado, el más urgente arriba.
- Un ítem **sin intervalos** no genera recordatorios: sirve solo para llevar historial. Con al menos una realización figura como "Al día", mostrando la última.
- **Registrar una realización:** fecha, kilometraje, costo opcional y **nota opcional** (taller, qué se hizo exactamente, repuestos); el formulario precarga la fecha de hoy y el kilometraje actual del auto. Desde ahí se recalcula el próximo vencimiento. La nota de la última realización se muestra junto al "Último:".
- **Historial de realizaciones:** cada ítem con al menos una realización muestra un "Ver historial" que despliega todas sus realizaciones (de la más reciente a la más vieja). Desde ahí cada una se puede **editar** (fecha, kilometraje, costo, nota) o **eliminar** suelta, con confirmación. Editar o registrar con un kilometraje mayor adelanta el del auto (nunca lo baja).

## Documentación

Vencimientos con fecha del auto: seguro, VTV, patente y cualquier otro papel que caduque.

- Cada documento tiene **nombre**, **fecha de vencimiento**, una **nota opcional** (compañía, número de póliza…) y una **periodicidad opcional en meses** (seguro semestral, VTV anual…). El nombre sugiere Seguro, VTV y Patente, pero es texto libre.
- **Estado calculado** contra la fecha de hoy, en tres niveles: **Vencido** (ya pasó), **Por vencer** (faltan 30 días o menos) y **Al día** (falta más). La lista se ordena por urgencia: lo vencido y lo que se viene primero.
- Se pueden **editar** (nombre, fecha, nota, periodicidad) y **eliminar**, con confirmación. Igual que el resto del auto, quien tiene el auto compartido también puede operar la documentación.
- **Renovar ("Lo renové"):** en vez de pisar la fecha editándola, la acción de renovar guarda la vigencia que termina como **historial** y pasa el documento al vencimiento nuevo. Si el documento tiene periodicidad, la fecha nueva viene **sugerida** (vencimiento actual + periodicidad) y se puede cambiar. Las vigencias anteriores se ven con "Ver vigencias anteriores"; eliminar el documento borra también ese historial (la confirmación lo avisa).
- Es la misma idea de "vencimiento" que los mantenimientos, pero anclada solo a una fecha (no al kilometraje).

## Combustible

- Cada carga registra fecha, kilometraje y costo opcional; el formulario precarga la fecha de hoy.
- La lista va de la más reciente a la más vieja (por fecha y, a igual fecha, por kilometraje) y muestra, para cada carga, los **km recorridos desde la carga anterior**. Si la cuenta diera negativa (cargas anotadas fuera de orden), ese dato no se muestra.
- Cada carga se puede **editar** en línea (fecha, kilometraje, costo) o **eliminar**, con confirmación. Editar con un kilometraje mayor adelanta el del auto (nunca lo baja).
- Totales acumulados de lo gastado en mantenimiento y en combustible, por separado (solo se muestran cuando hay algo gastado).

## Compartir

- El **dueño** comparte el auto por nombre de usuario exacto (insensible a mayúsculas). Amparo avisa si no encuentra a nadie con ese usuario, si es el propio dueño o si ya lo estaba compartiendo con esa persona.
- Puede **dejar de compartirlo** cuando quiera ("Quitar", con confirmación); la otra persona deja de ver el auto.
- Una persona con el auto compartido puede **ver todo y operar el día a día**: registrar mantenimientos y cargas, crear ítems nuevos, corregir el kilometraje, renovar documentos, y también **editar y eliminar** ítems de mantenimiento, realizaciones y cargas de combustible (incluso los que anotó otra persona). Cada registro guarda quién lo hizo y, **cuando el auto está compartido, la interfaz muestra quién anotó** cada realización, carga, documento y renovación ("Anotó {nombre}"); en un auto de una sola persona no se muestra. En su pantalla el auto figura como "Compartido por {dueño}".
- **Solo el dueño** puede editar los datos del auto, eliminarlo y administrar con quién se comparte.
- El scoping va por "autos accesibles" (propios ∪ compartidos); las acciones de dueño se chequean aparte contra la propiedad. Este es el patrón a seguir por cualquier módulo futuro que comparta.

## Backlog

Lo pendiente de este módulo vive en [`TODO.md`](../TODO.md); lo descartado, en [`WONTDO.md`](../WONTDO.md).
