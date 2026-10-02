# Crecimiento (panel del dueño)

Herramientas para vender más y que el cliente vuelva. Viven en el grupo
"Crecimiento" del sidebar (solo dueño) y en `CrecimientoController`
(Copiloto sigue en `PanelController`). Todo es del **negocio**, no de la
sede: un cupón sirve en todas las sedes.

## Cupones (`/panel/cupones`, `panel/cupones.php`)

- **Tiquete recortable** (`.pq-cupon`): talón a la izquierda con el valor
  grande (`.pq-cupon-cifra`, Bricolage 800, `--talon` 84px → 104px desde
  480px), línea punteada y dos muescas (`::before/::after` del color del
  fondo) donde se "corta"; cuerpo con código espaciado, chip de estado,
  condiciones en una línea y "Usado N veces · $X descontados".
  Montos fijos usan `.pq-cupon-cifra-monto` (más chica, `nowrap`): "$10.000" no
  puede partirse en dos líneas.
- Estados: activo / pausado / vencido / agotado (`Cupon::estado`). Apagado
  = talón gris; vencido o agotado = cifra tachada.
- Acciones: Compartir (wa.me con texto armado + enlace de la tienda),
  Copiar, kebab con Pausar/Activar y Eliminar (solo si nunca se usó: el
  historial de pedidos lo nombra).
- Formulario colapsable (`.pq-cupon-nuevo`), abierto si no hay cupones.
  Selector %/$ segmentado (`.pq-cupon-tipo`, radios ocultos), condiciones
  opcionales en un `<details>` (mínimo, vence, usos totales, una vez por
  cliente — encendido por defecto).
- Cupones personales del Copiloto: lista plana aparte, "Lo usó" (verde) /
  "Sin usar" (pendiente).

## Reglas (no cambiar sin pensar)

- Se valida dos veces: al aplicar en el carrito (existe, activo, vigente,
  mínimo) y al crear el pedido/cita con el cliente conocido (personal de
  otro número, una vez por cliente). Si falla al enviar, se quita el cupón
  y se avisa: nunca se cobra distinto a lo que el cliente vio.
- `%` se redondea hacia abajo a $100; nunca descuenta más que el subtotal.
- `pedidos.total` ya es lo que paga el cliente (subtotal − descuento); en
  citas el valor a pagar es `precio − descuento`.
- Copiloto: con descuento, el mensaje lleva un código `VUELVE-XXXX`
  reservado en sesión y el cupón se crea al tocar "Abrir WhatsApp" (o "Ya
  le escribí"), no al mirar: cambiar el % o pedir otra versión no deja
  cupones sueltos. Dura `Cupon::DIAS_COPILOTO` (15) días, un uso, solo
  ese número.

## Tienda

- Carrito: `<details class="pq-cupon-entrada">` (tijeras ✂) antes de
  "Agregar algo más"; las líneas de subtotal/cupón van en
  `tienda/_comanda_ajustes.php`, que el JSON del carrito re-renderiza
  (`ajustes_html`) para que el JS no duplique reglas.
- Reservar: el mismo `<details>` dentro del formulario (`name="cupon"`).
- Pedido confirmado y detalle del panel: `tienda/_pedido_ajustes.php`.
