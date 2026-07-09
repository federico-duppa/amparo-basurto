# Compras (`/compras`)

Listas de compras: anotar lo que falta, sacarlo de un toque cuando ya lo tenés, y un repertorio de **frecuentes** para sumar lo de siempre sin escribir. Una lista se puede compartir con otra persona.

## Qué hace

- **Listas.** Cada persona puede tener varias listas (súper, farmacia, ferretería…). La primera vez que entrás, Amparo te deja una lista **"Súper"** ya creada así no arrancás con la pantalla en blanco. Se crean, renombran y eliminan; el selector de arriba cambia de una a otra.
- **Anotar cosas.** Un campo de texto suma lo que escribas a la lista abierta. No se anota dos veces lo mismo (se compara sin distinguir mayúsculas ni espacios), así tocar un frecuente ya anotado no duplica. Todo lo que anotás queda además guardado en tu repertorio de **frecuentes** (si no estaba ya), sin pedir nada.
- **Tachar lo comprado.** Cuando ya tenés algo, lo tocás y queda **tachado** en la lista (con tilde y tachadura), sin confirmación — el gesto tiene que ser rápido. Otro toque lo destacha si fue un error o lo volvés a necesitar. Al lado de cada cosa tachada aparece un **tachito de basura** que la saca de la lista para limpiarla. Los ítems se ven como chips, igual que los frecuentes.
- **Re-anotar algo tachado lo destacha.** Escribir (o tocar el frecuente de) algo que ya está tachado en la lista no lo duplica: lo destacha, porque volvés a necesitarlo.
- **Frecuentes.** El repertorio personal de cosas que comprás seguido, como chips que se suman con un toque (y se sacan con otro toque: el mismo chip prende y apaga). Son **de la persona, no de la lista**: el mismo repertorio sirve para todas tus listas.
  - La primera vez se siembra con productos comunes de supermercado (leche, pan, huevos, yerba, fideos, papas…) para no empezar de cero. Después los editás o borrás como cualquier otro; **no se vuelven a sembrar**.
  - Todo lo que anotás escribiéndolo se guarda solo como frecuente. En el modo **Editar** de los frecuentes también se suman y se olvidan de a uno.
  - **Se ordenan por uso, no alfabéticamente.** Cada frecuente lleva una ponderación que sube al anotarlo en una lista y al tacharlo como comprado (y baja si te arrepentís: destachás el ítem o apagás el chip). Limpiar con el tachito no cambia el peso — el peso lo dio la compra. Lo que comprás seguido queda primero, a un toque; a igual peso, desempata el nombre.

## Compartir

Una lista tiene un **dueño** (quien la creó) y se puede compartir con otras personas, igual que el módulo Auto: con una relación explícita, nunca relajando el scoping.

- Se comparte por **nombre de usuario**. La otra persona pasa a ver la lista entre las suyas y puede **anotar y sacar cosas**.
- **Renombrar, eliminar y compartir** quedan reservadas al dueño. Para el resto, la lista aparece marcada como "compartida" y con el usuario del dueño.
- Cada quien tiene su propio repertorio de frecuentes: compartir una lista no comparte los frecuentes.

## Reglas y decisiones

- **Cada quien ve solo lo suyo.** Las listas se consultan siempre por "listas accesibles" (propias ∪ compartidas); una lista ajena responde 404. Anotar y sacar cosas va por acceso; renombrar, eliminar y compartir van por propiedad.
- **Los frecuentes son por usuario**, no globales ni por lista (coherente con el scoping por usuario de toda la app).
- **Tachar no borra; limpiar sí.** Lo tachado queda a la vista (sirve como repaso de lo que ya cayó al changuito) hasta que lo limpiás con el tachito, y ahí se borra de verdad: no hay papelera ni historial más allá de lo tachado.
- **Sin cantidades por ahora.** Se anota la cosa, no "2 de leche". Ver el backlog en [TODO.md](../TODO.md).

El acento del módulo es **cobre/terracota** (`#7A4522`). La identidad visual y la voz de Amparo están en [CLAUDE.md](../CLAUDE.md); esta spec cubre solo lo funcional.
