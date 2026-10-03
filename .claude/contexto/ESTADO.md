# Estado de la sesión — 2026-10-03 (núcleo clientes + recompra: consentimiento, recuperado, Hoy, repetir, huecos; migraciones 32-33)

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
- Planes: Gratis $0 / Barrio $29.900 / Pro $69.900 al mes (bajados el
  2026-10-03 para competir: Treinta $39.900, Tiendanube $33.900, AgendaPro
  $50.000-99.000, Alegra POS $25.900). Anual = 2 meses gratis (×10).
  Exportar CSV en todos los planes; IA Gratis 3/mes, Barrio y Pro sin
  límite; "pedidos y reservas en la misma cuenta" quitado del sitio (no
  existe). Migración 26.
- Tiendas (migración 27): en línea lo que va por peso se pide por medias
  libras (½ lb, 1 lb, 1½ lb… y "1 kilo"), precio redondeado a $50 como en
  el mostrador; el renglón guarda los gramos. Combos sin productos por
  peso. Fiado sin WhatsApp (teléfono opcional; sin recordatorio). El
  recordatorio de cobro del fiado lo manda solo el dueño; el equipo anota
  fiados y abonos. "Ventas hoy" suma el mostrador (no cuenta en el límite
  de Gratis). Mostrador en la barra del celular si la sede vende en el
  local (ventas en 30 días o productos con código/peso).
- Salud (migración 28): "Aprobado en el consultorio" (queda quién y
  cuándo) y "Hacer uno nuevo a partir de este" para planes vencidos,
  rechazados o cancelados (rehecho_de; un vencido rehecho queda cancelado).
- Cobro → Híbrido: manual verificado por Bre-B ahora, pasarela después.
- Límite Gratis → por mes calendario (50 pedidos/citas, 3 análisis IA).
- Plan pago vencido → degrada a Gratis automáticamente, nunca bloquea la tienda.
- Multisede Pro → 3 sedes incluidas + $19.900/sede extra (cobro prorrateado
  hecho, ver migración 12).

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
- Nada a medias. El usuario preguntó por qué tienda y panel se ven
  distintos; se explicó (dos rutas a propósito) y pidió "realiza esos
  ajustes y pasa a la fase 2": puente con la tienda (color del negocio en
  insignia/cenefa/escaparate + comanda unificada) y fase 2 del panel, todo
  hecho y documentado en `references/panel.md`.
- Luego "sigue con el onboarding": hecho (ver `references/onboarding.md`),
  con el color del toldo elegible (antes todo negocio nacía rojo #E8452C y
  no había dónde cambiarlo) y 5 bugs reales corregidos (foto sin vista
  previa por CSP, horario sin días = callejón sin salida, cambios del
  catálogo perdidos, productos agregados nacían agotados y ocultos, 0.0 MB).
- Luego "revisa muy bien la lógica del onboarding": hecho. Foto opcional
  (carta a mano), varias fotos sin duplicar, foto achicada en navegador +
  normalizada en servidor (límite PHP de 2 MB rompía las fotos de celular),
  lectura con IA honesta (estados ok/vacio/fallo/sin_llave, modelo
  claude-opus-5-5, salida JSON estructurada, fallbacks "default"), ítems sin
  precio bloquean abrir, llave Bre-B validada y editable en Sedes, PRG al
  publicar, onboarding solo para el dueño. Detalle en `references/onboarding.md`.
- Luego pidió (1) que las tipografías del cliente y del dueño coincidan:
  hecho, una sola familia (Bricolage títulos + Inter Tight texto/cifras
  tabulares, tokens `--fuente-titulo`/`--fuente-texto`), se quitaron
  Instrument Serif, Inter y JetBrains Mono; y (2) horarios con pausa de
  almuerzo por día: hecho (franjas, ver `references/panel.md` → Horario).
  Luego el panel de administración: hecho (ver `references/admin.md`).
  Con esto tienda, panel, onboarding y admin están todos en v2.
- Fuentes alojadas en `public/assets/fonts/` (CSP sin Google).
- Hecho: "empieza a hacer todos los features" (12, todos ✓): cupones →
  fidelidad → zonas de domicilio → agotado hoy + inventario → combos y lo
  más pedido → cierre de caja → reseñas → paquetes → avisos de estado →
  Wompi → referidos. Una migración `database/migrations/2026-10-03_NN_*.sql`
  por feature + `schema.sql` y sus `INSERT IGNORE INTO migraciones` al día;
  `php bin/migrar.php` las aplica. Detalle en `references/crecimiento.md`.

- Features por línea de negocio: plan en `.claude/contexto/PLAN-LINEAS.md`.
  Fase 1 (imprevistos) hecha y revisada (migración 15 con los ajustes);
  fase 2 (belleza: equipo con foto/servicios/horario, adicionales, fila
  virtual, comisiones; migración 14) hecha; fase 4 (tiendas) en un subagente
  (worktree, migraciones 20-25) fusionada y revisada; fase 3 (visitas a
  domicilio: modalidad 'domicilio' de reservas, franjas, técnico asignado,
  cotización aprobable, evidencia, zonas, "toca repetir"; migraciones 16-17,
  demo en database/demo_visitas.sql) hecha y revisada (franja en
  imprevistos y recados, candado anti doble reserva, anticipo de materiales
  una sola vez, fotos borradas con el cliente); fase 5 (salud: rubro 'salud',
  motivo con permiso de datos sensibles, planes de tratamiento por fases con
  abonos, caja y comisiones; migración 18, demo en database/demo_salud.sql)
  hecha y revisada (solo se vinculan citas sin atender, sin anticipo y con
  cupo en la fase; cerrar el plan suelta las citas agendadas; un plan
  terminado con saldo sigue recibiendo abonos; recordatorio de saldo uno por
  semana por paciente; sin títulos de tratamiento en WhatsApp ni push).
  Aprobación presencial y rehacer planes: hechos (migración 28).

- "sigue haciéndole pruebas de funcionalidad": 3 subagentes de QA
  (cuenta/seguridad, pedidos/tiendas, reservas) en worktrees con su propia
  base; ~40 hallazgos corregidos. Lo más importante:
  - Sesión: cambiar la contraseña o suspender el negocio cierra las demás
    sesiones (`usuarios.sesion_version`, migración 29).
  - Inventario: el pedido y la venta guardan qué stock movieron
    (`inventario_movido`, migración 30) y cancelar/anular devuelve eso
    exacto; reabrir un pedido sin stock suficiente se niega con mensaje.
  - Cupones/fidelidad: un uso de pedido o cita cancelada ya no cuenta.
  - Sedes sobre el cupo del plan quedan "en pausa" (no salen en la tienda
    pública ni reciben pedidos) en vez de seguir abiertas tras bajar de plan.
  - Reservas: candado y fecha pasada al reprogramar; "cerrar el día" de un
    profesional bloquea solo su agenda (`empleado_dias_libres`, migración
    31); citas sin profesional asignado sí ocupan cupo; el cliente que
    cancela recupera su anticipo como cupón; el anticipo nunca supera lo
    que se paga tras el cupón; lista de espera: solo quien se anotó puede
    salir, conserva profesional/adicionales y no se ofrece en días cerrados.
  - Caja: las citas cuentan cuando ya pasó su hora o se atendieron (las que
    faltan van como "por venir"); los bonos vendidos el día suman.
  - "Citas de mañana" lista el día entero (el cron sigue con 24-30 h).
  - Fila virtual: solo ofrece y reparte la espera entre quienes trabajan
    ahora (si nadie, los de hoy; si no, todo el equipo).
  - Terminar un plan de tratamiento deja sus citas agendadas dentro del
    plan ($0); cancelarlo las suelta para cobrarlas aparte.
  - Decididos a propósito: el colaborador puede editar el catálogo
    (productos y servicios, precios incluidos), solo el dueño borra;
    historial/exportar detalle en Gratis se mantiene como estaba.
  Suites de regresión (scratchpad `yo/correr.sh <suite>`) y rastreador
  (`barrido.js`) en verde tras los arreglos.

- Rumbo acordado con el usuario (no volver a discutir): Veci = el
  copiloto comercial del negocio que vende por WhatsApp. Toda feature
  nueva debe mejorar vender, operar, cobrar o hacer volver; si no, va al
  backlog. Acción primero, información después. IA invisible (explicable,
  sin "probabilidades" inventadas). Sin comisión por venta.
- Bloque "núcleo clientes + recompra" (pedido: "arranca por el punto 1 y
  sigue con el resto"):
  1. Consentimiento real (migración 32, `Consentimiento`, `/preferencias/
     {token}`, `PreferenciasController`): el copiloto solo propone
     promociones a quien autorizó; "Pedir permiso" una sola vez; cada
     mensaje lleva el enlace de baja; la reserva ofrece la casilla; olvidar
     marcarla ya no borra el permiso. Política de privacidad al día.
  2. Dinero recuperado (`Copiloto::recuperado`): primera compra/reserva en
     14 días tras un mensaje, una venta cuenta una vez. Abrir WhatsApp
     desde el copiloto ya registra el contacto. Se ve en copiloto, plan e
     inicio.
  3. Inicio "Hoy" (`Hoy::tareas`): tareas por urgencia antes que cifras.
  4. "Volver a pedir" (cookie `veci_r{negocio}` con `clientes.
     token_recompra`, migración 33; "No soy X") y "¿Reservas lo de
     siempre?"; "Llenar huecos" (`Huecos`, `/panel/copiloto/huecos`): el
     próximo día que abre, clientes en su ritmo cuyo servicio cabe.
  5. Auditoría de seguridad integral + `tests/aislamiento.php` (todas las
     rutas del panel con ids de otros negocios; probado con 5 cuentas;
     control negativo comprobado).

## Pendiente (en orden)
0. Diseño: tienda, panel, onboarding y admin completos en v2. Sitio `docs/`
   pasado a Bricolage + Inter Tight alojadas (ver `references/sitio.md`).
1. Sede extra ($19.900/mes en Pro, antes $30.000): hecho (`2026-10-03_12_sedes_extra.sql`,
   prorrateo hasta el vencimiento, renovación con extras, `/panel/sedes`).
2. Wompi ya está en código: falta poner llaves reales en
   `config/config.php` y registrar `/webhooks/wompi` en el panel de Wompi.
3. Tienda de servicios (horario 7 días, próxima apertura, cupo de hoy,
   `sedes.direccion`): verificado, ya estaba hecho en f94702c.
4. "Precio fundador": pendiente de que el usuario decida si se limita a
   los primeros 100 negocios o a una fecha de corte. No aplicado.

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
- Onboarding de prueba: `registrar.sh jar nombre tipo 31199900NN` y
  `flujo-onboarding3.js S out ancho` (cuentas o3/o4/o5 = 3119990031-33,
  usa `menu-celular.jpg` de 10 MB con EXIF) en el scratchpad; limpiar con `limpiar-onb.sh`
  (borra negocios 31199900xx y sus fotos). Registro: 3 por IP al día, así
  que `DELETE FROM limites_tasa` entre corridas.
- Admin de prueba: `php bin/crear_admin.php "Prueba QA" qa@tuveci.co prueba12345`
  (borrarlo después: `delete from admins where correo='qa@tuveci.co'`);
  `login-admin.sh` y `prueba-admin.js` en el scratchpad.
- Copiloto bloqueado en plan Gratis: para probarlo, `update negocios set
  plan_id=3, plan_vence_en='2027-12-31' where id=1` y luego volver a
  `plan_id=1, plan_vence_en=NULL`.
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
