# Estado de la sesión — 2026-10-02 (actualizado tras la fase 1 del panel v2)

## Objetivo actual
- Último pedido: "crea la skill y empieza por la tienda pública" → skill
  `.claude/skills/diseno-veci/` (sistema de diseño + proceso + capturas.js)
  y rediseño v2 de la tienda pública (pedidos y reservas). Orden acordado
  para lo que sigue: tienda (resto de pantallas) → panel → onboarding → admin.
- La skill de terceros "ui-ux-pro-max" NO se instaló: el clasificador de
  seguridad bloqueó clonar el repo; el usuario eligió crear una propia.
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
- Nada a medias. Último pedido: "sigue con el panel del dueño". Fase 1
  hecha (shell fusionado en celular, kanban, historial en tarjetas, comanda
  imprimible, productos con inicial, agenda por día, push en Mi cuenta);
  documentada en `.claude/skills/diseno-veci/references/panel.md`.
- Fuentes alojadas en `public/assets/fonts/` (CSP sin Google).

## Pendiente (en orden)
0. Diseño: tienda pública completa en v2. Panel fase 1 completa. Sigue
   panel fase 2: servicios (varios "Guardar" por tarjeta, formularios
   densos), horario, fechas bloqueadas, copiloto, recordatorios, sedes,
   colaboradores, empleados, cuenta, plan, producto_form y
   productos/_gestor.php. Después onboarding y admin.
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
  `/opt/pw-browsers/chromium-1194/chrome-linux/chrome`. Capturas:
  `node .claude/skills/diseno-veci/scripts/capturas.js --rutas ... --anchos 360,390,1280 --out <dir> [--cookies jar]`.
- Tras pruebas: `DELETE FROM limites_tasa;` y borrar pedidos/clientes de prueba.
- Sesiones de prueba del panel: cookie jars de curl (login con CSRF) en el
  scratchpad; `capturas.js --cookies jar`. En Puppeteer, los botones que
  quedan bajo la bottomnav fija se pulsan con `$eval(sel, e => e.click())`.
- Las citas de prueba de Luis Pérez (3011112222) quedan en el pasado y la
  agenda solo muestra de hoy en adelante: mover `fecha_hora` para probar.

## Errores ya resueltos (no repetir)
- curl exit 7 → servidor PHP caído tras reinicio del contenedor → relanzar.
- Puppeteer muerto (exit 137) en pruebas largas → usar curl con cookie jar.
- `column` no existe en el contenedor → usar awk.
- Chromium no confiaba en el proxy para Google Fonts → se resolvió alojando
  las fuentes en el propio servidor (no desactivar TLS).
- Texto con tildes insertado por `mysql` sin `--default-character-set=utf8mb4`
  queda dañado ("trifÃ¡sico"): es del dato de prueba, no de la app.
