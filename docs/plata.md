# Plata (`/plata`)

Finanzas personales: gastos y previsión, multi-moneda con lentes. **No es un módulo de patrimonio** — no trackea cuánto tenés ni saldos de cuentas. Responde dos preguntas:

- **Retrospectiva:** ¿a dónde se me va la plata?
- **Prospectiva:** ¿qué gastos se vienen y cuánto necesito reservar?

No hay un número de "tu plata total". Hay gastos efectivos (pasado) y sobres (futuro), cada sobre con su propio saldo e historia.

Pantallas: **Gastos** (`/plata`), **Sobres** (`/plata/sobres` y `/plata/sobres/{id}`) y **Reportes** (`/plata/reportes`).

## Principio central: la plata es (monto + moneda)

Todo se guarda **siempre en su moneda nativa, nunca convertido**. "Gasté 60 USD" se almacena como 60 USD. El valor en otra moneda es siempre una **vista derivada**, calculada al mostrarla — nunca un dato guardado. Si convertís al guardar, perdés información que después no reconstruís (¿a qué cotización?, ¿blue u oficial?).

Monedas soportadas en v1: **ARS y USD**.

## Los tres conceptos

### 1. Sobre de ahorro

Un chanchito donde vas juntando en el tiempo para un objetivo. Es la fase donde la **inflación importa**. Dos flujos: **aportes** (entra) y **retiros** (sale), cada uno con fecha, monto y nota opcional. El **saldo emerge de los movimientos** — no se edita, se reconstruye desde la historia. De ahí salen el progreso contra el objetivo y el timeline.

Dos tipos, según el objetivo:

- **Nominal:** objetivo fijo en monto ("juntar $500.000"). Moneda elegible; default ARS.
- **Poder de compra (indexado):** objetivo indexado por IPC. Siempre en ARS — el poder de compra se ancla al índice argentino, la moneda no se pregunta. El mecanismo, con precisión:
  - **Lo guardado es siempre nominal:** los pesos que efectivamente aportaste. El saldo no se "infla" solo.
  - **Lo único que se indexa es el objetivo:** se guarda el monto y su **mes base** (el mes de creación del sobre), y la vara se re-expresa con el IPC acumulado desde entonces.
  - **Doble lectura:** saldo nominal contra objetivo indexado. El sobre te dice **"para mantener el poder de compra te falta aportar $X"** (la brecha entre la vara de hoy y lo que hay).
  - El objetivo indexado es obligatorio al crear el sobre (sin objetivo no hay vara).

Los dos tipos son las dos estrategias anti-licuación: un USD ahorrado (nominal en USD) y un ARS indexado. Cada sobre tiene **una sola verdad** en su denominación; el análisis cruzado se hace con los lentes a nivel reporte, nunca dentro del sobre.

### 2. Sobre de gasto previsto

Plata reservada para gastos que sabés que vienen (el seguro, el service, el alquiler de las vacaciones). Es corto y se consume rápido, así que es **siempre nominal** — nunca se indexa, aunque se marque lo contrario al crearlo.

- Se fondea con aportes directos o con una **transferencia desde otro sobre**.
- Los **gastos efectivos se imputan contra este sobre**, descontando su saldo.
- Saldo emergente = lo fondeado − lo gastado. **Puede quedar negativo**: registrar un gasto real nunca se bloquea porque el sobre no alcance; si te pasaste, el sobre lo muestra en rojo y te lo dice.
- Puede tener un **objetivo** opcional (cuánto se prevé gastar en total). Cuando lo tiene, un pago imputado puede marcarse como que **cumple parte del objetivo**: además de descontar el saldo, **le baja la vara por el mismo monto**. Ejemplo: objetivo 200, fondeado 100; un pago de 50 que cumple el objetivo deja el saldo en 50 y el objetivo en 150. Como el saldo, **el objetivo también emerge de la historia**: se reconstruye desde el `target_amount` menos lo que fueron cumpliendo los pagos (nunca baja de cero), así que editar o borrar el pago reajusta la vara sola.

### 3. Gasto efectivo

El evento real de gastar: descripción, categoría, monto, moneda y fecha (no se aceptan fechas futuras — para lo que viene están los sobres). Si el gasto no es en ARS, guarda además la **cotización blue del día, congelada** (snapshot de "lo que realmente me salió"; si no hay cotización disponible, el gasto se guarda igual, sin snapshot).

Dos formas:

- **Suelto:** el día a día, no pertenece a ningún sobre. Mantiene liviano el uso cotidiano.
- **Imputado a un sobre de gasto previsto:** descuenta el saldo de ese sobre. Solo se imputa a sobres de gasto (nunca de ahorro) y **en la misma moneda del sobre**; si no coincide, Amparo sugiere anotarlo suelto o en la moneda del sobre. Si el sobre tiene objetivo, el gasto puede marcarse como que **cumple parte del objetivo** y bajarlo por su monto (ver "Sobre de gasto previsto"); marcarlo no tiene efecto en gastos sueltos ni en sobres sin objetivo.

En ambos casos el gasto cuenta igual en los reportes. Las categorías son texto libre, con sugerencias a partir de las ya usadas. Comprar dólares para gastar después no se registra como nada; cuando gastás esos USD, es un gasto efectivo en USD a la cotización de ese día.

## Transferencias entre sobres

- Desde el detalle de un sobre se pasa plata a cualquier otro sobre propio. No se puede pasar más de lo que hay.
- La transferencia crea **dos movimientos vinculados** (sale de uno, entra al otro); eliminar cualquiera de las dos patas borra la transferencia entera, en ambos sobres.
- **Si cambia la moneda**, convierte a la cotización blue del día y la deja registrada en el movimiento. Sin cotización disponible, la transferencia entre monedas no se hace.
- **La indexación se congela en el pase:** al salir de un sobre indexado, la plata sale del mundo indexado en su valor nominal. El destino no arrastra indexación. Es lo correcto: el poder de compra importaba mientras juntabas, no mientras gastás.

## Edición

- **Gastos:** desde la lista, el lápiz de cada gasto lo carga en el formulario de arriba para corregir cualquier campo (descripción, categoría, monto, moneda, fecha, sobre imputado). Al guardar se recalcula la cotización congelada igual que al anotarlo: si queda en dólares, se vuelve a tomar el snapshot blue de la fecha; si pasa a pesos, se borra. Un cartel avisa que estás editando y se puede cancelar.
- **Movimientos** (aportes y retiros) se editan en línea desde la historia del sobre: monto, fecha y nota. La edición nunca puede dejar el saldo en rojo (subir un retiro más allá de lo disponible, o bajar un aporte por debajo de lo ya gastado/retirado, se rechaza con un aviso). Las **transferencias no se editan**: son un par vinculado con conversión, así que se eliminan enteras y se rehacen.

## Eliminaciones

- **Movimientos** (aportes/retiros) se eliminan individualmente, con confirmación; el saldo se recalcula solo.
- **Eliminar un sobre** borra su historia de movimientos, pero **los gastos ya anotados no se borran: quedan sueltos**. Son historia real y siguen contando en los reportes.
- **Gastos** se eliminan individualmente, con confirmación.

## Reportes: los lentes

El usuario no elige "una moneda base": elige un **lente** que compone dos ejes independientes sobre el mismo dataset:

- **Eje FX:** pesos, USD blue, USD oficial o USD MEP.
- **Eje temporal:** nominal, o **valores de hoy** (ajustado por IPC).

Se eligen por separado y se combinan. **FX e inflación son preguntas distintas:** mostrar en USD no ajusta por inflación, y ajustar por inflación no cambia la moneda.

### Cómo se calcula un movimiento bajo un lente (referencia = hoy)

1. **Llevar a ARS** a la cotización del día de la transacción — el snapshot congelado si existe; si no, la serie blue de ese día. (Si ya era ARS, no se toca.)
2. **Ajustar por IPC** de la fecha del gasto a hoy, solo si el eje temporal es "valores de hoy". La inflación **vive en espacio ARS**: no se ajustan dólares por inflación argentina. "USD real" significa ARS-real-de-hoy pasado al dólar de hoy.
3. Si el lente pide USD, **recién ahí** convertir a la cotización del lente (blue/oficial/MEP) **de hoy**.

El orden importa y está fijado por tests.

### Qué muestra

- Ventana: **últimos 12 meses**. Total, desglose por categoría (con barras proporcionales) y por mes.
- Si a un gasto le falta una cotización imprescindible, **queda afuera del cálculo y se avisa** ("Dejé afuera N gastos porque me faltan cotizaciones") — nunca se inventa un número.
- Si el lente pide valores de hoy y no hay datos de inflación cargados, se avisa y se muestra sin ajustar.

## Datos de mercado

- Series históricas de **cotizaciones** (blue, oficial, MEP) e **inflación mensual** (IPC), desde argentinadatos.com, guardadas en la base como cache.
- Sincronización completa con `php artisan plata:mercado`, programado a diario (9:00).
- Si al momento de necesitar una cotización falta el dato del día (o el más cercano quedó a más de una semana), se intenta traer puntualmente de la API; si falla, **fallback al último valor conocido** y se deja de insistir por unos minutos. Estar offline nunca bloquea al usuario.
- Los meses sin dato de inflación cuentan como 0% (equivale a "último valor conocido").

## Backlog

Lo pendiente de este módulo vive en [`TODO.md`](../TODO.md); lo descartado, en [`WONTDO.md`](../WONTDO.md).
