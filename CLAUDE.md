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

El proyecto aún no está scaffoldeado. Una vez creado el esqueleto de Laravel, los comandos estándar aplican:

```bash
composer install && npm install   # dependencias
php artisan serve                 # servidor de desarrollo
npm run dev                       # Vite en modo watch
npm run build                     # build de assets
php artisan test                  # suite completa
php artisan test --filter=Nombre  # un solo test
vendor/bin/pint                   # formateo de código PHP
php artisan migrate               # migraciones
```

Al scaffoldear, actualizar esta sección si los comandos reales difieren (p. ej. si se usa Pest directamente, un `Makefile`, o Laravel Sail).

## Sistema de diseño

Identidad: multipropósito con un **guiño discreto a lo criollo** — no un disfraz gauchesco literal, sino texturas y proporciones que recuerdan cuero curtido, papel de estancia, sellos y tipografía de otra época, aplicado con disciplina moderna de UI. La app tiene que seguir siendo legible y rápida de usar; lo criollo es acento, nunca obstáculo.

### Paleta (definir como tokens en Tailwind)

| Rol | Color | Referencia | Uso |
|---|---|---|---|
| Fondo base | Crema/hueso apagado | `#EDE6D6` | Fondo general, como papel viejo. **Nunca blanco puro.** |
| Texto/iconos principales | Marrón cuero oscuro | `#5B3A29` | Color por defecto de texto e íconos |
| Acento secundario | Ocre/mostaza | `#B8842E` | Badges, highlights, énfasis puntual |
| Acción/CTA | Verde monte oscuro | `#3F4A34` | Botones primarios, links de acción. **Reemplaza al típico azul** — no introducir azules. |
| Alto contraste | Negro casi puro | — | Solo para texto que necesite contraste máximo. **Nunca como fondo.** |

### Tipografía

- **Serif con carácter** para el nombre/marca y titulares de identidad: aire de tipografía de sello o cartel de campo — un serif tipo **slab**, no gótico ni script.
- **Sans-serif limpio y neutro** para todo el contenido funcional (reportes, listas, GTD, formularios).

### Logo/símbolo

Nada literal (lazo, sombrero). Una forma **abstracta y geométrica** que sugiera un sello o una marca de hacienda (marca de ganado) simplificada. Debe funcionar como ícono de app y como watermark discreto en reportes.

### Aplicación por módulo

Cada módulo/sección puede tener **su propio acento de color dentro de la misma paleta tierra**, pero comparte tipografía y textura de fondo con el resto: una sola "casa" con varios cuartos. Al crear un módulo nuevo, elegir su acento entre los tonos tierra existentes (o una variante cercana), nunca fuera de la familia cromática.
