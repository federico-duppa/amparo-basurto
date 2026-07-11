# Salud (`/salud`)

Historias clínicas: la propia, la de un familiar, las de pacientes (si quien usa la app trabaja en el rubro) o la de una mascota. Cada historia junta una **ficha** con lo esencial del titular, secciones estructuradas (**vencimientos**, **carnet de vacunas**, **mediciones**, **contactos médicos**), **adjuntos** (certificados, estudios y recetas en PDF o foto) y un **timeline de entradas** libres (consultas, estudios, medicación, vacunas, notas). Es un módulo **compartido**: una historia tiene un dueño y puede compartirse con otras personas, con el mismo patrón que Auto.

Como en la ficha, **todas las secciones estructuradas son del día a día**: cualquier persona con acceso a la historia (dueño o compartida) las opera completas. Eliminar la historia borra también todo lo anotado en ellas.

## La historia y su titular

El titular de una historia puede ser de tres tipos, que se elige al darla de alta (y se puede corregir editándola). Cambia la voz, cómo se rotula el nombre y **qué campos trae la ficha**; el timeline, los vencimientos, el carnet de vacunas, las mediciones y los contactos son iguales para los tres:

- **Persona** (default): uno mismo, un familiar o un paciente sin cuenta.
- **Mascota:** para llevarle la salud a un animal (ver [la ficha](#ficha), que cambia especie/raza y veterinaria).
- **Documento:** una ficha de paciente sin una persona detrás — sirve como plantilla o registro suelto. No tiene fecha de nacimiento ni edad.

- El **titular** es una persona (o mascota, o documento), no un usuario de la app. La historia guarda su nombre y, cuando aplica, su fecha de nacimiento (si está, se muestra también la edad calculada; un documento no la lleva).
- **Alta:** pide el tipo, de quién es la historia (nombre) y —salvo en un documento— la fecha de nacimiento opcional; el resto de la ficha se completa después. Se pueden crear **varias historias** desde la interfaz ("+ Nueva historia").
- **Varias historias:** con más de una historia accesible aparece un selector, que marca las ajenas con "compartida". Al entrar al módulo se muestra la historia más antigua de las accesibles.
- **Editar al titular** (tipo, nombre y nacimiento) y **eliminar la historia** son acciones de dueño. Eliminarla borra también todas sus entradas y requiere confirmación.

## Ficha

Lo que dirías en una guardia sin pensar. Los campos que trae dependen del tipo de historia; todos son opcionales:

- **Persona:** **grupo sanguíneo**, **obra social/prepaga** (nombre y afiliado en texto libre), **alergias**, **condiciones crónicas** y **medicación actual** (droga y dosis, texto libre).
- **Mascota:** **especie** y **raza**, **veterinaria** (en el lugar de la obra social), y las mismas **alergias**, **condiciones** y **medicación**. No trae grupo sanguíneo.
- **Documento:** ficha neutra con solo **alergias**, **condiciones** y **medicación**.

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

## Adjuntos

Certificados, estudios, recetas, órdenes: los papeles de la historia, en PDF o como foto, siempre a mano en el teléfono. Un adjunto puede estar **suelto en la historia** (sección Adjuntos) o **colgar de una entrada del timeline** (se adjunta al anotar o al editar la entrada, y se ve como chip en la entrada).

- Se aceptan **PDF e imágenes (JPG, PNG, WebP, HEIC) de hasta 10 MB**, elegidos **de a uno**; una entrada puede juntar **hasta 10** antes de guardarse. Si un archivo no pasa (por tipo, por extensión o por peso), Amparo lo dice y no lo suma.
- En la sección Adjuntos el archivo suelto **se guarda apenas termina de subir** (sin formulario); la lista muestra nombre original, fecha, tamaño y quién lo subió, del más nuevo al más viejo, con un ícono que distingue foto de documento.
- Un adjunto se **descarga** por una URL autenticada que verifica el acceso a la historia y entrega el archivo directo, con su nombre original y su tipo real (PDF o imagen); se abre con el visor del teléfono. No hay URLs públicas ni navegación fuera de la app (pensado para la PWA instalada). Lo ajeno responde 404.
- Cualquier persona con acceso a la historia puede **subir y eliminar** adjuntos (con confirmación), igual que las entradas. Eliminar un adjunto, su entrada o la historia borra también el archivo del almacenamiento.

## Entradas (el timeline)

Información libre con fecha: el corazón de la historia.

- Cada entrada tiene **fecha** (precargada con hoy), **tipo**, **título** y **detalle opcional** (texto libre). Los tipos son cinco y livianos a propósito: **consulta, estudio, medicación, vacuna y nota** — sirven para filtrar y dar contexto, no para burocratizar la carga.
- La lista va de la más reciente a la más vieja y se muestra **de a 20**: "Ver más entradas" agranda la ventana (que vuelve al principio al cambiar de historia o de filtro). Cada entrada guarda **quién la anotó**.
- Cada entrada se puede **editar** en línea o **eliminar**, con confirmación (también las que anotó otra persona, igual que en Auto). Una entrada puede llevar **archivos adjuntos** (ver [Adjuntos](#adjuntos)); al eliminarla se van con ella.
- **Filtros:** por tipo (un toque activa el filtro, otro toque lo saca) y por **búsqueda de texto** sobre título y detalle, combinables. Sin resultados, Amparo lo dice ("No encontré nada con eso.").

## Reporte

La historia completa en una sola página **imprimible**, para llevar al médico (o guardarla como PDF desde el navegador).

- Se abre con el **ícono de impresora** junto al titular; cualquier persona con acceso a la historia puede generarlo (lo ajeno responde 404).
- Es una **página propia, sin la navegación de la app**, pensada para papel: encabezado con el titular (tipo, nacimiento y edad), la **ficha** completa (con las alergias resaltadas), **vencimientos** por urgencia, el **carnet de vacunas** agrupado, **todas las mediciones** por tipo, **contactos** y el **timeline completo** (acá no hay "Ver más"). Las secciones vacías no se imprimen.
- Los **adjuntos figuran como inventario** (nombre y fecha): el archivo no se imprime, pero el médico sabe qué papeles existen.
- El botón "Imprimir o guardar como PDF" usa la impresión del navegador y no sale en el papel; el reporte lleva la marca de agua del sello y el pie "Generado el … con Amparo Basurto".

## Compartir

Mismo patrón que Auto:

- El **dueño** comparte la historia por nombre de usuario exacto (insensible a mayúsculas). Amparo avisa si no encuentra a nadie con ese usuario, si la historia ya es de esa persona o si ya la estaba compartiendo.
- Puede **dejar de compartirla** cuando quiera ("Quitar", con confirmación); la otra persona deja de ver la historia.
- Una persona con la historia compartida puede **ver todo y operar el día a día**: editar la ficha y crear, editar y eliminar entradas (incluso las de otra persona). En su pantalla la historia figura como "Compartida por {dueño}".
- **Solo el dueño** puede editar al titular, eliminar la historia y administrar con quién se comparte.
- El scoping va por "historias accesibles" (propias ∪ compartidas) con chequeo de dueño aparte, como manda el patrón de la casa.

## Backlog

Lo pendiente de este módulo vive en [`TODO.md`](../TODO.md); lo descartado, en [`WONTDO.md`](../WONTDO.md).
