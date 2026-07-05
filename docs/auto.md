# Auto (`/auto`)

Mantenimiento del vehículo: qué le toca al auto y cuándo, historial de lo que se le hizo, cargas de combustible y cuánto va costando todo. Es además el primer módulo **compartido**: un auto tiene un dueño y puede compartirse con otras personas.

## El auto

- **Alta:** marca, modelo, patente (opcional, se guarda en mayúsculas) y kilometraje actual. Se pueden tener varios autos; con más de uno aparece un selector.
- **Kilometraje actual:** es la referencia contra la que se calculan los vencimientos. Se puede corregir a mano en cualquier momento, y además **sube solo**: al registrar un mantenimiento o una carga de combustible con un kilometraje mayor, el del auto se actualiza (nunca baja por esta vía).
- **Editar** los datos del auto y **eliminarlo** son acciones de dueño. Eliminar el auto borra también todo su historial (mantenimientos, registros y cargas) y requiere confirmación.

## Mantenimientos a seguir

Cada auto tiene una lista de ítems de mantenimiento ("Cambio de aceite", "Correa de distribución"…), cada uno con un intervalo en **km**, en **meses**, ambos o ninguno.

- **Presets:** al crear un auto se precargan tres sugerencias editables: cambio de aceite (10.000 km / 12 meses), bujías (40.000 km) y correa de distribución (60.000 km / 60 meses). Se pueden borrar o modificar.
- **Estado calculado:** contra el kilometraje actual y la fecha de hoy, cada ítem está en uno de cuatro estados:
  - **Sin registrar** — nunca se anotó una realización; no hay desde dónde calcular.
  - **Al día** — falta más de 1.000 km y más de 30 días para el vencimiento.
  - **Pronto** — quedan 1.000 km o menos, o 30 días o menos.
  - **Atrasado** — se pasó el intervalo en km o en fecha.
- Si el ítem tiene los dos intervalos, manda el más urgente. Para ordenar la lista por criticidad, km y tiempo compiten en una misma escala (1 día ≈ 40 km).
- Un ítem **sin intervalos** no genera recordatorios: sirve solo para llevar historial.
- **Registrar una realización:** fecha, kilometraje y costo opcional. Desde ahí se recalcula el próximo vencimiento.

## Combustible

- Cada carga registra fecha, kilometraje y costo opcional.
- La lista muestra, para cada carga, los **km recorridos desde la carga anterior**.
- Totales acumulados de lo gastado en mantenimiento y en combustible, por separado.

## Compartir

- El **dueño** comparte el auto por nombre de usuario, y puede dejar de compartirlo cuando quiera.
- Una persona con el auto compartido puede **ver todo y cargar**: mantenimientos realizados, cargas de combustible, kilometraje, ítems nuevos. Cada registro guarda quién lo hizo.
- **Solo el dueño** puede editar los datos del auto, eliminarlo y administrar con quién se comparte.
- El scoping va por "autos accesibles" (propios ∪ compartidos); las acciones de dueño se chequean aparte contra la propiedad. Este es el patrón a seguir por cualquier módulo futuro que comparta.

## Fuera de alcance (por ahora)

- Litros y consumo (las cargas guardan costo, no volumen).
- Recordatorios activos (notificaciones); los vencimientos se ven al entrar al módulo.
- Documentación del vehículo (seguro, VTV, patente) como vencimientos.
