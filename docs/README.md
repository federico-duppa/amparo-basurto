# Specs funcionales

Acá vive la descripción funcional de cada módulo de Amparo Basurto: qué hace, con qué reglas y qué decisiones se tomaron. Es la referencia para entender el producto sin leer código ni hacer arqueología de PRs.

**Convención:** cada módulo nuevo agrega su spec acá (`docs/<módulo>.md`), y todo cambio funcional se refleja en la spec en el mismo PR que lo introduce.

| Módulo | Ruta | Spec |
|---|---|---|
| Tareas | `/tareas` | [tareas.md](tareas.md) |
| Auto | `/auto` | [auto.md](auto.md) |
| Salud | `/salud` | [salud.md](salud.md) |
| Compras | `/compras` | [compras.md](compras.md) |
| Plata | `/plata` | [plata.md](plata.md) |
| Juegos | `/juegos` | [juegos.md](juegos.md) |

## Base común a toda la app

- **Cuentas:** login con usuario + contraseña (sin email), en `/entrar`. Registro en `/registro`, restringido por whitelist: solo los usuarios listados en la env `ALLOWED_USERNAMES` pueden registrarse (lista vacía = registro cerrado). Logout por POST a `/salir`.
- **Datos por usuario:** cada persona ve solo lo suyo. Un recurso ajeno responde 404 — ni siquiera confirma que existe. Compartir entre usuarios se hace siempre con una relación explícita (el módulo Auto es el primer caso), nunca relajando ese scoping.
- **Regionalización:** es-AR en toda la interfaz — voseo, fechas `dd/mm/aaaa`, números `1.234,56`.
- La identidad visual, la voz de Amparo y las convenciones técnicas están en [CLAUDE.md](../CLAUDE.md); estas specs cubren solo lo funcional.
