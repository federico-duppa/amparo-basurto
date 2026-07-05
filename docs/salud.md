# Salud (`/salud`)

Historias clínicas: la propia, la de un familiar, las de pacientes (si quien usa la app trabaja en el rubro), la de una mascota o una ficha de paciente genérica. Cada historia junta una **ficha** con lo esencial del titular, secciones estructuradas (**vencimientos**, **carnet de vacunas**, **mediciones**, **contactos médicos**) y un **timeline de entradas** libres (consultas, estudios, medicación, vacunas, notas). Es un módulo **compartido**: una historia tiene un dueño y puede compartirse con otras personas, con el mismo patrón que Auto.

Como en la ficha, **todas las secciones estructuradas son del día a día**: cualquier persona con acceso a la historia (dueño o compartida) las opera completas. Eliminar la historia borra también todo lo anotado en ellas.

## La historia y su titular

- El **titular** no es un usuario de la app y puede ser de tres tipos: una **persona** (uno mismo, un familiar, un paciente sin cuenta — el default), una **mascota**, o un **documento** (una ficha de paciente genérica, sin persona real detrás). La historia guarda su nombre y, opcionalmente, su fecha de nacimiento (si está, se muestra también la edad calculada).
- El tipo se elige al crear la historia y el dueño puede corregirlo al editar al titular; cuando no es persona se marca con una etiqueta junto al nombre. Por ahora el tipo solo se guarda y se muestra — misma ficha y mismas secciones para los tres —; lo que se diferencie por tipo vive en [`TODO.md`](../TODO.md).
- **Alta:** solo pide el tipo, de quién es la historia (nombre) y la fecha de nacimiento opcional; el resto de la ficha se completa después. Se pueden crear **varias historias** desde la interfaz ("+ Nueva historia").
- **Varias historias:** con más de una historia accesible aparece un selector, que marca las ajenas con "compartida". Al entrar al módulo se muestra la historia más antigua de las accesibles.
- **Editar al titular** (nombre y nacimiento) y **eliminar la historia** son acciones de dueño. Eliminarla borra también todas sus entradas y requiere confirmación.

## Ficha

Lo que dirías en una guardia sin pensar: **grupo sanguíneo**, **obra social/prepaga** (nombre y afiliado en texto libre), **alergias**, **condiciones crónicas** y **medicación actual** (droga y dosis, texto libre). Todos los campos son opcionales.

- La ficha se muestra siempre arriba, antes del timeline; los campos vacíos figuran con "—".
- Las **alergias se resaltan** visualmente (fondo ocre) porque son el dato crítico de la ficha.
- Cualquier persona con acceso a la historia (dueño o compartida) puede **editar la ficha**: es información de día a día, no un dato del titular.

## Vencimientos

El próximo control, la receta que caduca, el estudio anual: cosas con fecha que no se pueden pasar. Mismo patrón de "vencimiento" que la documentación de Auto.

- Cada vencimiento tiene **nombre**, **fecha**, **periodicidad opcional** (en meses) y **nota opcional**.
- La lista va **ordenada por urgencia** y cada uno muestra su estado: **Vencido** (rojo teja), **Por vencer** (ocre, faltan 30 días o menos) o **Al día** (verde yerba).
- **"Ya está"** (fue al control, renovó la receta) pasa el vencimiento a la fecha nueva; si tiene periodicidad, Amparo sugiere la próxima fecha y se puede corregir. No se guarda historial de fechas anteriores: para eso está el timeline.
- Se pueden editar y eliminar (con confirmación).

## Vacunas (el carnet)

Sección propia, estructurada: cada **aplicación** tiene vacuna, **dosis opcional** ("1ª dosis", "Refuerzo"…), **fecha de aplicación**, **próxima dosis opcional** y **nota opcional** (marca, lote…).

- El carnet se muestra **agrupado por vacuna** (en orden alfabético), y dentro de cada grupo de la primera dosis a la última.
- Si una aplicación tiene próxima dosis anotada, se muestra con aviso cuando se acerca (30 días) y como **"Dosis pendiente"** cuando ya pasó.
- La próxima dosis tiene que ser posterior a la aplicación. Las aplicaciones se editan y eliminan (con confirmación).
- El tipo "vacuna" del timeline sigue existiendo para notas libres; el carnet es la versión estructurada.

## Mediciones

Peso, presión, glucemia y temperatura, con su **evolución en el tiempo**.

- Los tipos son fijos, cada uno con su unidad (kg, mmHg, mg/dl, °C); la presión guarda **máxima y mínima**.
- Un chip por tipo muestra la **última medición**; al elegir un tipo se ve su evolución, de la más reciente a la más vieja, con la **diferencia contra la medición anterior** (salvo la presión). La lista se acota con "Ver más".
- Al entrar, la sección arranca en el tipo que ya tiene datos más recientes.
- Los valores se muestran en formato es-AR ("78,5 kg", "120/80 mmHg").
- Una medición mal cargada se **elimina** (con confirmación) y se anota de nuevo; no hay edición en línea.

## Contactos

Médico de cabecera, especialistas y sus teléfonos, por historia.

- Cada contacto tiene **nombre**, **especialidad opcional**, **teléfono opcional** y **nota opcional** (consultorio, horarios…).
- La lista va por orden alfabético y el teléfono es un **link para llamar** directo desde el teléfono.
- Se editan y eliminan (con confirmación).

## Entradas (el timeline)

Información libre con fecha: el corazón de la historia.

- Cada entrada tiene **fecha** (precargada con hoy), **tipo**, **título** y **detalle opcional** (texto libre). Los tipos son cinco y livianos a propósito: **consulta, estudio, medicación, vacuna y nota** — sirven para filtrar y dar contexto, no para burocratizar la carga.
- La lista va de la más reciente a la más vieja y se muestra **de a 20**: "Ver más entradas" agranda la ventana (que vuelve al principio al cambiar de historia o de filtro). Cada entrada guarda **quién la anotó**.
- Cada entrada se puede **editar** en línea o **eliminar**, con confirmación (también las que anotó otra persona, igual que en Auto).
- **Filtros:** por tipo (un toque activa el filtro, otro toque lo saca) y por **búsqueda de texto** sobre título y detalle, combinables. Sin resultados, Amparo lo dice ("No encontré nada con eso.").

## Compartir

Mismo patrón que Auto:

- El **dueño** comparte la historia por nombre de usuario exacto (insensible a mayúsculas). Amparo avisa si no encuentra a nadie con ese usuario, si la historia ya es de esa persona o si ya la estaba compartiendo.
- Puede **dejar de compartirla** cuando quiera ("Quitar", con confirmación); la otra persona deja de ver la historia.
- Una persona con la historia compartida puede **ver todo y operar el día a día**: editar la ficha y crear, editar y eliminar entradas (incluso las de otra persona). En su pantalla la historia figura como "Compartida por {dueño}".
- **Solo el dueño** puede editar al titular, eliminar la historia y administrar con quién se comparte.
- El scoping va por "historias accesibles" (propias ∪ compartidas) con chequeo de dueño aparte, como manda el patrón de la casa.

## Backlog

Lo pendiente de este módulo (documentos adjuntos, reporte imprimible y más) vive en [`TODO.md`](../TODO.md); lo descartado, en [`WONTDO.md`](../WONTDO.md).
