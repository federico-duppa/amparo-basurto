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
- **Los ítems no se editan:** para cambiar el nombre o los intervalos de uno hay que eliminarlo y crearlo de nuevo — y **eliminar un ítem borra también su historial de realizaciones** (con confirmación previa que lo avisa).
- **Estado calculado:** contra el kilometraje actual y la fecha de hoy, cada ítem está en uno de cuatro estados:
  - **Sin registrar** — nunca se anotó una realización; no hay desde dónde calcular.
  - **Al día** — falta más de 1.000 km y más de 30 días para el vencimiento.
  - **Pronto** — quedan 1.000 km o menos, o 30 días o menos.
  - **Atrasado** — se pasó el intervalo en km o en fecha.
- Si el ítem tiene los dos intervalos, manda el más urgente. Para ordenar la lista por criticidad, km y tiempo compiten en una misma escala (1 día ≈ 40 km).
- **Orden de la lista:** atrasados primero, después los "pronto", los al día, y al final los sin registrar; dentro del mismo estado, el más urgente arriba.
- Un ítem **sin intervalos** no genera recordatorios: sirve solo para llevar historial. Con al menos una realización figura como "Al día", mostrando la última.
- **Registrar una realización:** fecha, kilometraje y costo opcional; el formulario precarga la fecha de hoy y el kilometraje actual del auto. Desde ahí se recalcula el próximo vencimiento. Una realización guardada no se puede editar ni eliminar suelta: solo desaparece con su ítem o con el auto.

## Combustible

- Cada carga registra fecha, kilometraje y costo opcional; el formulario precarga la fecha de hoy.
- La lista va de la más reciente a la más vieja (por fecha y, a igual fecha, por kilometraje) y muestra, para cada carga, los **km recorridos desde la carga anterior**. Si la cuenta diera negativa (cargas anotadas fuera de orden), ese dato no se muestra.
- Las cargas se pueden **eliminar** una a una, con confirmación. No se editan: se elimina y se vuelve a anotar.
- Totales acumulados de lo gastado en mantenimiento y en combustible, por separado (solo se muestran cuando hay algo gastado).

## Compartir

- El **dueño** comparte el auto por nombre de usuario exacto (insensible a mayúsculas). Amparo avisa si no encuentra a nadie con ese usuario, si es el propio dueño o si ya lo estaba compartiendo con esa persona.
- Puede **dejar de compartirlo** cuando quiera ("Quitar", con confirmación); la otra persona deja de ver el auto.
- Una persona con el auto compartido puede **ver todo y operar el día a día**: registrar mantenimientos y cargas, crear ítems nuevos, corregir el kilometraje, y también **eliminar** ítems de mantenimiento y cargas de combustible (incluso los que anotó otra persona). Cada registro guarda quién lo hizo. En su pantalla el auto figura como "Compartido por {dueño}".
- **Solo el dueño** puede editar los datos del auto, eliminarlo y administrar con quién se comparte.
- El scoping va por "autos accesibles" (propios ∪ compartidos); las acciones de dueño se chequean aparte contra la propiedad. Este es el patrón a seguir por cualquier módulo futuro que comparta.

## Fuera de alcance (por ahora)

- Alta de un segundo auto propio desde la interfaz (el selector ya contempla varios; falta el botón de alta).
- Edición de ítems de mantenimiento, realizaciones y cargas ya guardadas (se elimina y se vuelve a crear; las realizaciones ni siquiera se eliminan sueltas).
- Litros y consumo (las cargas guardan costo, no volumen).
- Recordatorios activos (notificaciones); los vencimientos se ven al entrar al módulo.
- Documentación del vehículo (seguro, VTV, patente) como vencimientos.
