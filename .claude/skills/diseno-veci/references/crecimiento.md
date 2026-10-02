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

## Tarjeta de sellos (`/panel/fidelidad`, `panel/fidelidad.php`)

- Componente compartido `tienda/_tarjeta_sellos.php` (`.pq-tarjeta-sellos`):
  casillas redondas punteadas; cada compra es un sello de caucho del color
  del negocio (`.pq-casilla-sello-puesta`: relleno `--marca`, doble aro
  interno, girado `--giro` según la posición — nunca al azar, para que no
  "baile" al recargar). La última casilla es el premio (regalo sobre
  `--marca-suave`). Hasta 6 casillas en una fila; más, en dos filas.
  El sello que acaba de ganar cae una vez (`.pq-casilla-sello-nueva`,
  400 ms, sin animación con `prefers-reduced-motion`).
- Dónde sale: confirmación de pedido y de cita (después del "cómo
  pagar"), info de la tienda (`.pq-info-sellos`), vista previa del panel
  sobre papel de la tienda (`.pq-fidelidad-papel`, es Ruta 1).
- Panel: formulario (activa, meta 5/6/8/10/12 con el mismo segmentado de
  cupones, premio, compra mínima) + vista previa pegajosa en escritorio;
  "Listos para premio" con **Entregar premio**; "A punto de completarla"
  con **Avisarle** por WhatsApp.
- Detalle del pedido: `.pq-premio-aviso` (borde punteado de marca) si el
  cliente completó la tarjeta, con el botón para entregarlo ahí mismo.

Reglas: los sellos no se guardan, se cuentan (pedidos no cancelados y citas
no canceladas, en cualquier sede, desde `fidelidad.desde` y desde la compra
mínima); cada premio anota los sellos que gastó. Entregar premio lo puede
hacer dueño o colaborador, revisa de nuevo bajo `FOR UPDATE` (doble clic no
entrega dos). El mensaje de WhatsApp al negocio dice "Tarjeta de sellos:
N/M" o "le toca {premio}".
