# WONTDO

Cosas que **decidimos no hacer**. No son deuda ni "todavía no": son límites de producto tomados a propósito. **No sugerir estos ítems** ni proponerlos como mejora — si alguna vez cambiamos de idea, la decisión se revierte acá primero y recién ahí pasa a [TODO.md](TODO.md).

Cómo se mantiene este archivo está en [CLAUDE.md](CLAUDE.md#backlog-todomd--wontdomd).

## Plata (`/plata`)

Plata responde "¿a dónde se me va la plata?" y "¿qué se viene?" — **no es un módulo de patrimonio**. De ahí salen estos límites:

- **Cuentas y saldos globales / patrimonio.** No hay un número de "tu plata total"; hay gastos efectivos y sobres, cada uno con su historia.
- **Compra-venta de dólares como transferencia entre cuentas.** Comprar dólares para gastar después no se registra como nada; cuando gastás esos USD es un gasto efectivo en USD a la cotización de ese día.
- **Realizado vs. no-realizado.** Es análisis de patrimonio, fuera del alcance del módulo.
- **Gastos imputados en una moneda distinta a la del sobre.** La conversión entre monedas existe solo en transferencias; imputar un gasto va siempre en la moneda del sobre.

## Tareas (`/tareas`)

- **Niveles de prioridad (P1–P4 o alta/media/baja).** La priorización del módulo es la matriz de Eisenhower (urgente × importante); no se apila otra escala encima.
- **Contextos GTD como entidad propia.** Las etiquetas (pendientes en TODO) cubren ese uso sin agregar otra estructura; el contexto "@computadora" envejeció mal.
- **Energía o duración estimada por tarea.** Campos que nadie carga de forma sostenida; ruido en el formulario.
- **Ceremonia GTD completa como flujo obligatorio** (inbox → clarificar → organizar como pasos forzados). La captura rápida y tachar siguen siendo el corazón del módulo; todo lo demás es opcional.

## Auto (`/auto`)

- **Litros y consumo en las cargas de combustible.** Las cargas registran costo, no volumen; el módulo no calcula rendimiento.

## Técnico (mantenimiento y performance)

- **Renumerar migraciones con timestamp duplicado.** Hay cuatro pares de migraciones que comparten prefijo (`2026_07_04_165025`, `2026_07_05_130000`, `2026_07_05_130001`, `2026_07_05_140000`); el orden entre ellas queda librado al alfabético del nombre de archivo. Se evaluó renumerarlas, pero el deploy es automático en cada push a `main` (Laravel Cloud) y estas migraciones ya corrieron en producción: Laravel identifica cada migración por su nombre de archivo en la tabla `migrations`, así que renombrarlas haría que el próximo deploy las tratara como nuevas y fallara (tabla/columna ya existe). Además se confirmó que ningún par tiene una dependencia cruzada real entre sí (las dependencias entre tablas están cubiertas por el timestamp del grupo anterior), así que el orden alfabético actual es seguro aunque no sea explícito. No renombrar migraciones ya deployadas.
