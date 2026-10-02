# Estado de la sesión — 2026-10-02

## Objetivo actual
Veci: SaaS en PHP 8.4 + MySQL para negocios de barrio en Colombia que venden
por WhatsApp (pedidos y reservas). Bloque más reciente de trabajo:
- "realiza todos los puntos. De igual manera, la idea es blindar completamente
  el aprovechamiento de los clientes. También después de esto vas a realizar
  una auditoría de seguridad para revisar que todo haya quedado perfecto [...]
  que no tengamos brechas de seguridad de datos."
- "incluyas una skill para compactar sin perder contexto" → `.claude/skills/compactar/`.

## Decisiones del usuario (no volver a preguntar)
- Planes: Gratis $0 / Barrio $59.000 / Pro $129.000 al mes.
- Cobro → Híbrido: manual verificado por Bre-B ahora, pasarela después.
- Límite Gratis → por mes calendario (50 pedidos/citas, 3 análisis IA).
- Plan pago vencido → degrada a Gratis automáticamente, nunca bloquea la tienda.
- Multisede Pro → 3 sedes incluidas + $30.000/sede extra (cobro de la extra
  aún no implementado: la cuarta sede se bloquea en el servidor).

## Hecho (verificado)
- `9afbef0`, `3ae398d` — sistema de planes y suscripciones + pulido.
- `1ab7f1f` — blindaje anti-abuso de planes: límite de sedes, plan vigente en
  tiempo real (`Sede::aplicarVencimiento`), bloqueo de fuerza bruta en
  /admin, límite de registros por IP, confirmar pago exige monto exacto y es
  atómico (`PagoPlan::confirmar` con FOR UPDATE), rechazar/cancelar solicitud.
- `2f8ef5c` — auditoría de seguridad: `LimiteTasa` genérico (login 20/IP/15
  min, reset 5/IP y 3/cuenta por hora, registro 3/IP/día, tienda 5/h por
  IP+sede y 20/h global), webhook exige monto, tokens de reset en SHA-256,
  manejador global de errores, bin/ solo CLI + .htaccess, HSTS +
  Permissions-Policy, contraseña mínima 8, sesión admin expira a 30 min,
  CSV anti-inyección de fórmulas, README "Seguridad y anti-abuso".
  Probado en vivo con curl (cookies + CSRF) y datos de prueba limpiados.
- Auditoría sin hallazgos en: SQLi, IDOR, CSRF, XSS, subidas, redirecciones.

## En curso
- Nada a medias.

## Pendiente (en orden)
1. Cobro automático de sede extra ($30.000) — proyecto aparte.
2. Pasarela de pago para planes (fase 2 del cobro híbrido).
3. Tareas viejas de la tienda pública de servicios (horario 7 días,
   disponibilidad de hoy por servicio, `sedes.direccion`) quedaron sin cerrar.

## Archivos clave tocados
- `src/Models/Sede.php` — `SELECT_CON_MARCA_Y_PLAN`, `aplicarVencimiento()`.
- `src/Models/LimiteTasa.php` — limitador de tasa (tabla `limites_tasa`).
- `src/Models/PagoPlan.php`, `src/Controllers/AdminController.php` — cobro manual.
- `src/Controllers/{Auth,Tienda,Webhook,Panel}Controller.php` — límites y fixes.
- `src/AdminAuth.php`, `src/Models/Admin.php` — bloqueo + inactividad.
- `src/bootstrap.php` — cabeceras de seguridad y manejador de errores.
- `database/migrations/2026-10-01_blindaje_planes.sql`.

## Convenciones del proyecto
- Rama: `claude/new-session-mde7lj`. No abrir PR sin que el usuario lo pida.
- Commits terminan con:
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` y
  `Claude-Session: https://claude.ai/code/session_01PDpnPyw4HtjJrfhMVnt1vD`.
- Migraciones: editar `database/schema.sql` (instalación nueva) **y** crear
  archivo en `database/migrations/` (bases existentes).
- PHP vanilla MVC, PDO preparado, helpers `e()`, `pesos()`, `redirigir()`,
  `flash_set/flash_obtener`, `csrf_verificar()`, `ip_cliente()`,
  `dinero_desde_texto()`. Comentarios en español explicando el porqué.
- Copy honesto: no prometer en README/UI lo que el código no hace.

## Entorno y pruebas
- `service mariadb start`; `nohup php -S localhost:8000 serve.php &`.
- Admin local: `admin@tuveci.co` / `admin123`. Dueños demo: `3001234567`,
  `3005556677` / `veci123`. Tiendas: `donamaria`, `salonbonita`, `donamaria-centro`.
- Puppeteer: `/tmp/pptr/node_modules/puppeteer-core`, Chromium en
  `/opt/pw-browsers/chromium-1194/chrome-linux/chrome`.
- Tras pruebas: `DELETE FROM limites_tasa;` y borrar pedidos/clientes de prueba.

## Errores ya resueltos (no repetir)
- curl exit 7 → servidor PHP caído tras reinicio del contenedor → relanzar.
- Puppeteer muerto (exit 137) en pruebas largas → usar curl con cookie jar.
- `column` no existe en el contenedor → usar awk.
