# Salud (`/salud`)

Historias clínicas de personas: la propia, la de un familiar o las de pacientes, si quien usa la app trabaja en el rubro. Cada historia junta una **ficha** con lo esencial del titular y un **timeline de entradas** libres (consultas, estudios, medicación, vacunas, notas). Es un módulo **compartido**: una historia tiene un dueño y puede compartirse con otras personas, con el mismo patrón que Auto.

## La historia y su titular

- El **titular** es una persona, no un usuario de la app: puede ser uno mismo, un familiar o un paciente sin cuenta. La historia guarda su nombre y, opcionalmente, su fecha de nacimiento (si está, se muestra también la edad calculada).
- **Alta:** solo pide de quién es la historia (nombre) y la fecha de nacimiento opcional; el resto de la ficha se completa después. Se pueden crear **varias historias** desde la interfaz ("+ Nueva historia").
- **Varias historias:** con más de una historia accesible aparece un selector, que marca las ajenas con "compartida". Al entrar al módulo se muestra la historia más antigua de las accesibles.
- **Editar al titular** (nombre y nacimiento) y **eliminar la historia** son acciones de dueño. Eliminarla borra también todas sus entradas y requiere confirmación.

## Ficha

Lo que dirías en una guardia sin pensar: **grupo sanguíneo**, **obra social/prepaga** (nombre y afiliado en texto libre), **alergias**, **condiciones crónicas** y **medicación actual** (droga y dosis, texto libre). Todos los campos son opcionales.

- La ficha se muestra siempre arriba, antes del timeline; los campos vacíos figuran con "—".
- Las **alergias se resaltan** visualmente (fondo ocre) porque son el dato crítico de la ficha.
- Cualquier persona con acceso a la historia (dueño o compartida) puede **editar la ficha**: es información de día a día, no un dato del titular.

## Entradas (el timeline)

Información libre con fecha: el corazón de la historia.

- Cada entrada tiene **fecha** (precargada con hoy), **tipo**, **título** y **detalle opcional** (texto libre). Los tipos son cinco y livianos a propósito: **consulta, estudio, medicación, vacuna y nota** — sirven para filtrar y dar contexto, no para burocratizar la carga.
- La lista va de la más reciente a la más vieja. Cada entrada guarda **quién la anotó**.
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

Lo pendiente de este módulo (documentos adjuntos, vacunas como sección propia, recordatorios y más) vive en [`TODO.md`](../TODO.md); lo descartado, en [`WONTDO.md`](../WONTDO.md).
