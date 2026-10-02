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

## Domicilios por zona y pedido mínimo (`/panel/domicilios`, Operación)

- Por **sede** (cada sede reparte en su barrio), solo negocios de pedidos.
  Panel: tarifario (`.pq-tarifario`: zona ← puntos guía → valor, como la
  lista pegada en la nevera), kebab con editar/pausar/eliminar, formulario
  colapsable y pedido mínimo de la sede.
- Carrito: si la sede tiene zonas, en "Entrega → Domicilio" aparece
  "¿A qué zona te lo llevamos?" (`.pq-zona`, radio dibujado, valor a la
  derecha, mínimo propio debajo del nombre) antes de la dirección. El JS
  (`actualizarDomicilio` en interacciones.js) muestra la línea
  `#pq-comanda-domicilio` y suma al total y a la barra pegajosa a partir de
  `data-total-base`; sin JS, la nota dice que se suma y el servidor lo hace.
  Sin zonas el carrito sigue igual que antes ("se acuerda por WhatsApp").
- Pedido mínimo: aviso dentro de la comanda (`.pq-comanda-minimo`, viene en
  `_comanda_ajustes.php`, se re-pinta con el JSON del carrito) y el
  servidor rechaza con el monto que falta. Se mide sobre los productos,
  antes del cupón.
- El pedido copia `costo_domicilio` y `zona_domicilio`; `total` ya incluye
  el domicilio. `_pedido_ajustes.php` pinta subtotal/cupón/domicilio en la
  confirmación y en el panel. La info de la tienda lista zonas y mínimos
  (`#domicilios`).

## Agotado por hoy e inventario (catálogo del panel y tienda)

- `Producto::COLUMNAS` hace que `agotado` sea el **efectivo**: marcado a
  mano (`agotado_fijo`), solo por hoy (`agotado_hasta`, vuelve solo
  mañana) o sin unidades (`stock` en 0). `motivo_agotado` dice cuál.
- Tienda: "Agotado por hoy" solo cuando de verdad vuelve mañana; si no,
  "Agotado" a secas (no prometer fecha). Con ≤ 5 unidades,
  `.pq-plato-quedan` ("Quedan 3", tono de marca, no rojo).
- Carrito: el "+" no pasa de las unidades (se apaga, `disabled`); si el
  inventario bajó mientras se llenaba el formulario, se vuelve al carrito
  con el aviso y la cantidad ajustada (nunca se manda menos en silencio).
  `Pedido::crear` descuenta con `FOR UPDATE`; cancelar devuelve unidades.
- Panel: campo "Unidades disponibles" (vacío = no contar), kebab con "Se
  acabó por hoy" / "Agotado hasta nuevo aviso"; sin unidades, el kebab
  lleva a "Cargar unidades" en vez de un "Marcar disponible" que no
  serviría. Chips: Quedan N / Agotado hoy / Sin unidades / Agotado.
