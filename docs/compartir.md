# Compartir — `/compartir`

Puerta de entrada de lo que llega desde el menú de compartir de Android: un mensaje de WhatsApp reenviado, un link, un texto de otra app. Amparo lo recibe y pregunta qué hacer con eso, en vez de decidir sola.

No es un módulo con entrada en la navegación: a esta pantalla solo se llega compartiendo algo desde otra aplicación (o escribiendo la URL a mano, que la deja en su estado vacío).

## Cómo se registra en Android

- La app declara un **web app manifest** (`public/manifest.json`) con `share_target` (método GET hacia `/compartir`, parámetros `title`, `text` y `url`) e íconos PNG (192, 512 y variante maskable, generados del `icon.svg` del sello).
- Con el manifest enlazado desde el layout, Chrome instala la PWA como **WebAPK** al usar "Agregar a pantalla principal", y es ese WebAPK el que aparece en el share sheet del sistema. Sin reinstalar (si ya estaba agregada como acceso directo viejo), no aparece.
- Requiere HTTPS (en producción ya está; en desarrollo local el share target no se puede probar).
- Alcance actual: **solo Android**. iOS no soporta Web Share Target (ver [WONTDO.md](../WONTDO.md)).

## Qué hace la pantalla

- Junta `title`, `text` y `url` en un solo texto editable. Si una parte ya viene repetida dentro de otra (WhatsApp suele meter el link dentro del texto), no la duplica.
- Si hay sesión abierta, muestra el texto y ofrece tres caminos; si no, manda a `/entrar` y después del login vuelve con lo compartido intacto.
- El texto se puede **retocar antes de guardar** (recortar la paja del mensaje reenviado).

### Los tres caminos

| Acción | Qué pasa |
|---|---|
| **Anotarlo como tarea** | La primera línea es el título (recortado a 255 si es eterna); si había más que eso, el texto completo queda en las notas. La tarea nace sin fecha ni proyecto — se completa después en Tareas. |
| **Sumarlo a las compras** | La primera línea (recortada a 80) va como cosa a la primera lista accesible del usuario (por nombre); si no tiene ninguna, se crea «Súper». No anota dos veces la misma cosa. |
| **Mejor nada** | Descarta y va a Tareas. |

Después de guardar, Amparo confirma y ofrece ir al módulo donde quedó lo guardado.

## Reglas

- Todo lo guardado queda **a nombre del usuario autenticado**, con el scoping de siempre.
- No interpreta fechas en lenguaje natural (eso es del alta rápida de Tareas): un mensaje reenviado que dice "mañana" no genera una tarea fechada sola.
- Texto de más de 2.000 caracteres no se guarda (mismo límite que las notas de una tarea); Amparo pide resumirlo.
