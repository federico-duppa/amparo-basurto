# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es Amparo Basurto

Aplicación multipropósito de asistencia personal (GTD, listas, reportes y otros módulos). El nombre funciona como persona: Amparo Basurto es "alguien" que ayuda al usuario en todo lo que necesita, y la voz de la interfaz (textos, mensajes, estados vacíos) debe sostener esa idea — cercana y servicial, nunca robótica.

## Stack

- **Laravel 13** (backend y estructura general)
- **Livewire** (interactividad; preferir componentes Livewire antes que JS a medida)
- **Tailwind CSS** (todo el estilo; la paleta y tipografías de abajo se definen como design tokens en la config de Tailwind, no como valores sueltos en las vistas)

## Mobile first

La app es **mobile first**: toda vista se diseña primero para pantalla de teléfono y se expande hacia desktop, nunca al revés. En Tailwind esto significa que los estilos base (sin prefijo) son los de móvil y los breakpoints (`sm:`, `md:`, `lg:`…) agregan la adaptación a pantallas más grandes. Implicaciones prácticas:

- Navegación, formularios y listas deben ser cómodos con una mano: targets táctiles generosos, acciones principales al alcance del pulgar.
- Las tablas y reportes densos necesitan una variante móvil pensada (cards apiladas, scroll horizontal contenido), no una tabla de desktop encogida.
- Probar/razonar cada componente Livewire primero en viewport móvil antes de ajustar desktop.

### Comandos

```bash
composer install && npm install   # dependencias
php artisan serve                 # servidor de desarrollo
npm run dev                       # Vite en modo watch
npm run build                     # build de assets (los tests no lo necesitan: TestCase usa withoutVite)
php artisan test                  # suite completa (PHPUnit)
php artisan test --filter=Nombre  # un solo test
vendor/bin/pint                   # formateo de código PHP (correr antes de commitear)
php artisan migrate               # migraciones (SQLite en database/database.sqlite)
```

### Arquitectura

- **Livewire 4 con componentes single-file**: cada componente vive en `resources/views/components/<módulo>/⚡<nombre>.blade.php` (clase anónima PHP arriba, template abajo). Se rutean como páginas completas con `Route::livewire('/ruta', 'módulo.nombre')` en `routes/web.php`.
- **Layout único** en `resources/views/layouts/app.blade.php` (`layouts::app`, el default de Livewire): trae fuentes, ícono, la bottom nav móvil que se vuelve sidebar en `lg:`, y el slot de contenido. Todo módulo nuevo se cuelga de este layout y agrega su entrada en la nav.
- **Design tokens** en `resources/css/app.css` vía `@theme` de Tailwind 4 (config CSS-first, no hay `tailwind.config.js`): ahí viven la paleta (`crema`, `cuero`, `ocre`, `monte`, `teja`, `yerba`, acentos de módulo…) y las fuentes (`font-sans` = Inter, `font-brand` = Bitter). Usar siempre los tokens, nunca hex sueltos en las vistas.
- **Fuentes**: Bitter e Inter se cargan con `<link>` a fonts.bunny.net en el layout. No usar la opción `fonts` del plugin de Vite (descarga en build time) — no está disponible en todos los entornos de build.
- Modelos y migraciones estándar de Laravel; los tests de módulos usan `Livewire::test('módulo.nombre')` con `RefreshDatabase` y `actingAs()`.

### Deploy (Laravel Cloud)

- Deploy automático en cada push a `main`, con **scale to zero** y **Postgres 18**. En desarrollo local se sigue usando SQLite; Cloud inyecta las `DB_*` de Postgres.
- Por el scale to zero, **ningún estado puede vivir en el contenedor**: sesión, cache y cola usan driver `database` (ya configurado) y no se cambian a `file`. Todo SQL crudo nuevo debe funcionar en SQLite *y* Postgres.
- En los deploy commands de Cloud tiene que estar `php artisan migrate --force`.
- Variables de entorno a setear en Cloud: `APP_LOCALE=es`, `APP_FAKER_LOCALE=es_AR`, `ALLOWED_USERNAMES` (sin ella el registro queda cerrado).
- Los proxies confiables están configurados en `bootstrap/app.php` (`trustProxies '*'`) porque la app corre detrás del balanceador de Cloud.

### Autenticación y datos por usuario

- Login con **usuario + contraseña** (sin email), hecho a mano con el core de Laravel — sin starter kits ni Fortify, para mantener la identidad y la voz de Amparo. Componentes `auth.login` (`/entrar`) y `auth.register` (`/registro`); logout por POST a `/salir`; rate limiting básico en el login.
- **Registro restringido por whitelist**: la env `ALLOWED_USERNAMES` (nombres separados por coma, comparados en minúsculas; config en `config/amparo.php`) define quién puede registrarse. Lista vacía = registro cerrado.
- **Cada usuario ve solo sus datos.** Todo modelo de módulo lleva `user_id` y las queries van **siempre** por la relación del usuario autenticado (`auth()->user()->todos()->findOrFail($id)` — lo ajeno responde 404, ni siquiera confirma que existe). Ningún módulo nuevo consulta modelos "globales"; los tests de módulo deben cubrir el scoping.
- **Compartir elementos entre usuarios es un plan futuro**: cuando llegue, será mediante una relación explícita (tabla pivote + policies), no relajando el scoping actual.
- **Biometría (passkeys/WebAuthn) pendiente** como mejora del login; requiere HTTPS y un paquete dedicado.

## Sistema de diseño

Identidad: multipropósito con un **guiño discreto a lo criollo** — no un disfraz gauchesco literal, sino texturas y proporciones que recuerdan cuero curtido, papel de estancia, sellos y tipografía de otra época, aplicado con disciplina moderna de UI. La app tiene que seguir siendo legible y rápida de usar; lo criollo es acento, nunca obstáculo.

**No hay modo oscuro y no se va a agregar.** La identidad "papel viejo" es inherentemente clara; la app es lo que es. No introducir `dark:` variants ni preparar tokens para un tema oscuro.

### Paleta (definir como tokens en Tailwind)

| Rol | Color | Referencia | Uso |
|---|---|---|---|
| Fondo base | Crema/hueso apagado | `#EDE6D6` | Fondo general, **tinte plano** — sin texturas, noise ni patrones. **Nunca blanco puro.** |
| Texto/iconos principales | Marrón cuero oscuro | `#5B3A29` | Color por defecto de texto e íconos (8.1:1 sobre crema) |
| Acento secundario | Ocre/mostaza | `#B8842E` | Solo como **fondo** de badges/highlights. Ver regla del ocre abajo. |
| Ocre oscuro (texto) | Variante de ocre | `#7A5417` | Versión del ocre apta para texto sobre crema (5.4:1) |
| Acción/CTA | Verde monte oscuro | `#3F4A34` | Botones primarios, links de acción. **Reemplaza al típico azul** — no introducir azules. Texto crema encima. |
| Alto contraste | Negro casi puro | `#1C1917` | Solo para texto que necesite contraste máximo. **Nunca como fondo** (excepto texto sobre ocre, ver abajo). |

**Regla del ocre:** `#B8842E` sobre crema da ~2.6:1 y **no pasa WCAG AA — nunca usarlo como color de texto o ícono**. Se usa como fondo (badge, highlight, barra de énfasis) con texto **negro casi puro** encima (6.4:1; el marrón cuero sobre ocre tampoco alcanza). Cuando el énfasis tiene que ser tipográfico, usar el ocre oscuro `#7A5417`.

#### Colores semánticos de estado

Dentro de la misma familia tierra — no introducir el rojo/verde/amarillo genéricos de librería:

| Estado | Color | Referencia | Uso |
|---|---|---|---|
| Error / destructivo | Rojo teja | `#8C3B2E` | Mensajes de error, botones destructivos (6.1:1 sobre crema; texto crema encima en botones) |
| Éxito | Verde yerba | `#5A6B42` | Confirmaciones, checks (4.7:1 sobre crema). Distinto del verde monte para no confundir estado con acción. |
| Advertencia | Ocre | `#B8842E` fondo / `#7A5417` texto | Sigue la regla del ocre |

### Tipografía

- **Marca y titulares de identidad: Bitter** (Google Fonts) — serif slab con calidez de imprenta, aire de sello/cartel de campo sin caer en lo gótico ni script. Pesos 600/700. Solo para el nombre de la app, titulares de sección y piezas de identidad (reportes impresos, splash).
- **Contenido funcional: Inter** (Google Fonts) — sans neutro y muy legible para reportes, listas, GTD y formularios. Pesos 400/500/600.
- Escala mobile-first contenida: cuerpo `text-base`, secundario `text-sm`, titulares de pantalla `text-2xl`/`text-3xl` en Bitter. No multiplicar tamaños intermedios.

### Voz de Amparo

Amparo habla en **primera persona y con voseo** (es-AR): es una persona que ayuda, no un sistema que reporta.

- Cálida y concisa; nunca robótica, nunca corporativa. Prohibido "Error 500", "Operación exitosa", "El registro ha sido creado".
- **Sobria cuando importa:** en errores graves y acciones destructivas el tono se vuelve serio y directo, sin calidez impostada.
- Sin emojis, sin signos de exclamación dobles, sin diminutivos empalagosos.

Ejemplos que fijan el patrón:

| Situación | Amparo dice |
|---|---|
| Estado vacío (GTD) | "Todavía no anotaste nada. Cuando quieras, empezamos." |
| Confirmación | "Listo, quedó guardado." |
| Error recuperable | "No pude guardar eso. Probá de nuevo en un momento." |
| Búsqueda sin resultados | "No encontré nada con ese nombre." |
| Acción destructiva | "Vas a eliminar 12 tareas. Esto no se puede deshacer." |

### Logo/símbolo

Nada literal (lazo, sombrero). Un **monograma "AB" construido como marca de hacienda**: trazos geométricos de grosor uniforme y terminaciones rectas — la "A" sin travesaño (silueta de marca de ganado) combinada con una "B" reducida a dos semicírculos sobre el asta derecha. Sin serifas en el símbolo; opcionalmente inscripto en un contorno circular de sello para las piezas de identidad.

Variantes obligatorias:
- **Ícono de app:** símbolo en crema sobre fondo verde monte, legible a 16px.
- **Watermark en reportes:** trazo marrón cuero al 6–8% de opacidad, esquina o centro según layout.
- **Monocromo puro** para contextos de una tinta.

### Forma de componentes: "como sello"

La materia de la UI es papel y sello, no vidrio ni plástico:

- **Esquinas casi rectas:** `rounded-sm` (2–4px) como máximo. Nada de píldoras ni cards muy redondeadas.
- **Sin sombras difusas:** el relieve se logra con **bordes de 1px** en marrón translúcido y contraste de fondos, no con `shadow-lg`. A lo sumo una sombra mínima y dura para elementos flotantes (modales, menús).
- Densidad cómoda pero no aireada al extremo: la app es una herramienta de uso diario.

### Iconografía

- **Heroicons**: outline (24px) para UI general, solid mini (20px) en contextos densos (tablas, badges). No mezclar con otros sets.
- Color por defecto: marrón cuero, igual que el texto. Íconos decorativos siempre con `aria-hidden`.

### Navegación

- **Móvil:** bottom nav fija con los 4–5 módulos principales; la acción primaria de cada módulo como botón prominente al alcance del pulgar. Header mínimo (título + acción contextual).
- **Desktop (`lg:`):** la bottom nav se convierte en sidebar izquierda; el contenido gana columnas, no otra estructura.

### Estados de carga y movimiento

- **Skeletons, no spinners**, para toda carga de contenido: bloques en tonos crema/marrón muy suaves que replican el layout final. Spinner solo para acciones puntuales de botón.
- Transiciones breves y discretas (150–200ms); el movimiento no es parte de la personalidad — la app debe sentirse rápida.
- Respetar `prefers-reduced-motion`.

### Accesibilidad (regla general)

La accesibilidad es un requisito de diseño, UX y desarrollo en toda la app, no una pasada final:

- Contraste **WCAG AA mínimo**: 4.5:1 para texto normal, 3:1 para texto grande y elementos de UI. Todo color nuevo se verifica contra su fondo antes de usarse.
- Targets táctiles de **44px mínimo** (coherente con mobile first).
- Foco visible en todo elemento interactivo; formularios siempre con `label` asociado.
- Nunca comunicar estado solo con color (acompañar con texto o ícono).
- HTML semántico y atributos ARIA donde Livewire genere UI dinámica (live regions para feedback de Amparo).

### Regionalización

Locale **es-AR**: voseo en toda la interfaz (coherente con la voz de Amparo), fechas `dd/mm/aaaa`, números `1.234,56`, moneda ARS cuando aplique.

### Aplicación por módulo

Cada módulo/sección tiene **su propio acento de color dentro de la misma paleta tierra**, pero comparte tipografía y fondo con el resto: una sola "casa" con varios cuartos.

**Al crear un módulo nuevo, preguntar siempre al usuario qué acento le asigna**, proponiendo 2–3 opciones de la familia tierra (o variantes cercanas — nunca fuera de la familia cromática, nunca azules), verificando contraste AA de cada opción. Registrar la decisión en esta tabla:

| Módulo | Acento | Referencia |
|---|---|---|
| Todo — "Tareas" (`/tareas`) | Vino tierra | `#6E3B3B` (token `vino`, 7.2:1 sobre crema) |
