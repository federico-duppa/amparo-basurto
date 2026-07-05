# Compras (`/compras`)

Listas de compras: anotar lo que falta, sacarlo de un toque cuando ya lo tenés, y un repertorio de **frecuentes** para sumar lo de siempre sin escribir. Una lista se puede compartir con otra persona.

## Qué hace

- **Listas.** Cada persona puede tener varias listas (súper, farmacia, ferretería…). La primera vez que entrás, Amparo te deja una lista **"Súper"** ya creada así no arrancás con la pantalla en blanco. Se crean, renombran y eliminan; el selector de arriba cambia de una a otra.
- **Anotar cosas.** Un campo de texto suma lo que escribas a la lista abierta. No se anota dos veces lo mismo (se compara sin distinguir mayúsculas ni espacios), así tocar un frecuente ya anotado no duplica.
- **Sacar de la lista.** Cuando ya tenés algo, lo tocás y sale de la lista al toque, sin confirmación — el gesto tiene que ser rápido. Sacar una cosa la borra: la lista es lo que **falta**, no un historial.
- **Frecuentes.** El repertorio personal de cosas que comprás seguido, como chips que se suman con un toque (y se sacan con otro toque: el mismo chip prende y apaga). Son **de la persona, no de la lista**: el mismo repertorio sirve para todas tus listas.
  - La primera vez se siembra con productos comunes de supermercado (leche, pan, huevos, yerba, fideos, papas…) para no empezar de cero. Después los editás o borrás como cualquier otro; **no se vuelven a sembrar**.
  - Al anotar algo se puede marcar **"Recordarlo para la próxima"** y queda guardado como frecuente. En el modo **Editar** de los frecuentes se suman y se olvidan de a uno.

## Compartir

Una lista tiene un **dueño** (quien la creó) y se puede compartir con otras personas, igual que el módulo Auto: con una relación explícita, nunca relajando el scoping.

- Se comparte por **nombre de usuario**. La otra persona pasa a ver la lista entre las suyas y puede **anotar y sacar cosas**.
- **Renombrar, eliminar y compartir** quedan reservadas al dueño. Para el resto, la lista aparece marcada como "compartida" y con el usuario del dueño.
- Cada quien tiene su propio repertorio de frecuentes: compartir una lista no comparte los frecuentes.

## Reglas y decisiones

- **Cada quien ve solo lo suyo.** Las listas se consultan siempre por "listas accesibles" (propias ∪ compartidas); una lista ajena responde 404. Anotar y sacar cosas va por acceso; renombrar, eliminar y compartir van por propiedad.
- **Los frecuentes son por usuario**, no globales ni por lista (coherente con el scoping por usuario de toda la app).
- **Sacar es borrar.** No hay papelera ni "ya compradas": la lista muestra lo pendiente y nada más.
- **Sin cantidades por ahora.** Se anota la cosa, no "2 de leche". Ver el backlog en [TODO.md](../TODO.md).

El acento del módulo es **cobre/terracota** (`#7A4522`). La identidad visual y la voz de Amparo están en [CLAUDE.md](../CLAUDE.md); esta spec cubre solo lo funcional.
