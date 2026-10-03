# Tiendas de barrio (fase 4): mostrador, fiado y compras

Herramientas para la tienda de barrio que también vende en el local: el
tendero cobra con un lector de códigos (o la cámara), lleva el fiado y anota
lo que le llega del distribuidor. Ruta 2 "Tiquete y papel". Solo negocios de
**pedidos** (`tipo_negocio = 'pedidos'`); en la navegación: Mostrador y Fiado
para todo el equipo, Compras solo para el dueño. CSS: bloque
`/* ---------- Tiendas: mostrador, compras, fiado (fase 4) ---------- */` al
final de `app.css`. JS: `public/assets/js/tiendas.js` (cargado desde
`layouts/panel.php` solo en negocios de pedidos). Controladores aparte:
`MostradorController`, `FiadoController`, `ComprasController`.

Pensado para una mano y un celular de gama media, o un PC viejo con lector
USB/Bluetooth (el lector es un teclado: escribe el código y manda Enter).

## Datos (ver migraciones `2026-10-03_20` a `_24`)

- `productos.codigo_barras` (único por sede cuando existe), `costo`,
  `vende_por` (`unidad` | `peso`). **Por peso: `precio` y `costo` son del
  kilo y `stock` va en gramos.** "Cantidad de venta" = unidades o kilos;
  "cantidad de stock" = unidades o gramos. La conversión vive en
  `Producto::demandaDeStock` (la usan el mostrador y los pedidos de la tienda;
  en la tienda en línea 1 de un producto por peso = 1 kg).
- Precio de una línea por peso: `Producto::precioPorGramos`, redondeado a $50
  (la moneda más chica; así siempre hay vueltas), nunca menos de $50. El JS
  repite la misma regla para el total en vivo; el servidor manda.
- `ventas` + `venta_items` (cantidad DECIMAL(10,3), nombre y costo copiados).
  `token` único: el doble toque en "Cobrar" no registra dos ventas
  (`VentaDuplicada` lleva a la que sí quedó).
- `fiado_movimientos` (cargo/abono, `anulado`, `sede_id` donde entró el abono),
  `clientes.fiado_limite`, `fiado_recordatorios`.
- `compras` + `compra_items` (`stock_antes` NULL = empezó a contarse ahí).
- `codigos_barras`: catálogo compartido entre tiendas, **solo el nombre**.
  Entran solo GTIN válidos y que no sean de uso interno (prefijo 2 en EAN-13,
  los de la balanza). Se anota cuando un producto estrena código.

## Mostrador (`/panel/mostrador`)

```
Escanea o escribe el nombre
[▥ Código o nombre        ] [Agregar]     ← siempre enfocado; Enter agrega
📷 Escanear con la cámara
┌ VENTA EN CURSO ──── 3 PRODUCTOS ┐       ← la comanda, como el rollo
│ Gaseosa 400 ml ········ $7.500  │          de la registradora
│ $2.500 c/u   (− 3 +)        ×   │       ← la última escaneada arriba
│ Queso campesino ······· $9.000  │          y se ilumina un momento
│ 500 g · $18.000/kg  Cambiar peso│
│ ═══════════════════════════════ │
│ Total                  $16.500  │
└─────────────────────────────────┘
┌──────────────── visor ──────────┐       ← detalle propio: el visor de
│ TOTAL                  $16.500  │          la registradora, carbón con
│ VUELTAS                 $3.500  │          cifras grandes (verde / "Faltan"
└─────────────────────────────────┘          en coral)
[Efectivo][Nequi][Bre-B][Fiado]           ← radios como teclas
¿Con cuánto paga?  [ 20.000 ]
(Exacto)($2.000)…($100.000)(Borrar)       ← los billetes SE SUMAN
[        Cobrar $16.500        ]          ← verde caja
```

- **Sin JS** todo funciona: el tiquete vive en la sesión (por sede) y cada
  botón es un formulario (`escanear`, `agregar`, `linea`, `vaciar`,
  `cobrar`). Buscar por nombre con varios resultados lleva a `?q=`; un
  producto por peso abre la báscula con `?pesar=ID` (dos formularios: los
  atajos y "otro peso", para que el Enter del campo libre no dispare 125 g).
- **Con JS** (`.pq-mostrador-vivo`): el catálogo compacto de la sede se baja
  una vez (`/panel/mostrador/catalogo`) y escanear no espera al servidor. El
  tiquete se copia a la sesión en segundo plano (`/panel/mostrador/carrito`)
  para que recargar no lo borre. Al cobrar viajan `items[id]=cantidad` +
  `items_presentes` (un tiquete vaciado en el navegador NO cobra lo que
  quedaba en la sesión). Los formularios se interceptan en fase de captura:
  así "enviando…" de `interacciones.js` no apaga botones que no recargan.
- Lo que se teclee fuera de un campo de texto va al lector (el lector es un
  teclado). Pitido corto al agregar y grave al no encontrar.
- Cámara: `BarcodeDetector` (Chrome Android). Si no existe (iPhone, muchos
  PC), el botón explica que use un lector o escriba los números. Para eso la
  cabecera `Permissions-Policy` permite `camera=(self)`.
- Código desconocido: aviso con "Crearlo" (`/panel/productos/nuevo?codigo=`)
  y, si otra tienda Veci ya lo usó, "Otras tiendas lo llaman: «…»".
- Cobro: efectivo vacío = pagó exacto; con menos del total no deja. Fiado:
  elegir cliente (primero los que ya tienen cuenta, con saldo y límite) o
  "+ Cliente nuevo" con nombre, WhatsApp y la casilla **"El cliente autorizó
  guardar su nombre y número"** (Ley 1581): sin ella no se crea. A un
  cliente que ya existe no se le cambia nada.
- `Venta::crear` en una transacción: cliente `FOR UPDATE` (dos fiados a la
  vez no pasan juntos el límite), stock `FOR UPDATE` en orden de id; si algo
  no alcanza, error claro y **no se vende nada**. Productos ocultos de la
  tienda en línea sí se venden en el mostrador.
- Tras cobrar: banda verde `.pq-mostrador-hecha` con las vueltas en grande y
  "Ver tiquete". El tiquete (`/panel/mostrador/ventas/{id}`) es la comanda
  imprimible con sello Pagada / Fiado / Anulada.
- Anular: solo el dueño y solo el mismo día (anular la de ayer descuadraría
  un cierre ya contado). Devuelve el stock (con combos y gramos) y anula el
  cargo del fiado. Dos veces no devuelve doble (`FOR UPDATE`).

## Cierre de caja

`CierreCaja::resumenDelDia` suma `mostrador` (por método, sin anuladas),
`abonos_fiado` (por método) y `fiado_hoy`. Cada renglón del tiquete Z dice
"N ped. · N mostr." y suma pedidos + mostrador, para que los renglones sigan
sumando "Vendido". **El fiado no es plata que entró**: va bajo el total como
"Fiado hoy · no entró plata" y no suma; los abonos van ahí mismo por método.
Efectivo esperado = base + efectivo de pedidos (+ servicios) +
`CierreCaja::efectivoDeTienda()` (mostrador en efectivo + abonos en efectivo).
Los cierres guardados antes de la fase 4 no traen esas claves y siguen
leyéndose igual. Un cargo a mano en el fiado no mueve la caja.

## Fiado (`/panel/fiado`)

- Lista: la cifra grande "Por cobrar" (`.pq-fiado-resumen`), clientes que
  deben **del más viejo al más nuevo** ("debe desde": los abonos pagan
  primero lo más viejo, `Fiado::pendientes`), a igual fecha el que más debe;
  "Debe desde hace 45 días" en rojo pasados 30. Debajo: abrir la cuenta de
  cualquier cliente (GET, sin JS) y "+ Cliente nuevo" (con lo que debía en el
  cuaderno de papel como cargo inicial).
- Detalle: saldo + barra de cuánto usa de su límite (mostaza; ají desde 90 %),
  anotar abono (efectivo/Nequi/Bre-B; no más de lo que debe), el recordatorio
  y, plegados, "Cargar a mano" (con nota obligatoria) y el límite (solo el
  dueño). **Detalle propio: el cuaderno del fiado** (`.pq-libreta`):
  renglones azules, margen rojo con la fecha, + cargo / − abono y el saldo
  que va quedando, como lo lleva el tendero a lápiz. Lo anulado, tachado.
- Recordatorio por WhatsApp (`App\Services\HorarioCobro` + `Fiado::puedeRecordar`),
  Ley 2300 de 2023 leída de forma conservadora: lunes a viernes 7:00–19:00,
  sábados 8:00–15:00, nunca domingos ni festivos de Colombia (fijos, Ley
  Emiliani al lunes y los de Pascua, calculados), y uno por cliente cada 7
  días (`fiado_recordatorios`, se anota al abrir WhatsApp). Si no se puede,
  el botón sale apagado con la razón y cuándo sí ("Domingo: los
  recordatorios de cobro solo se pueden enviar de lunes a sábado. Lo puedes
  enviar el lunes 5 de octubre desde las 7 a. m."). El texto se ve como
  burbuja de WhatsApp de solo lectura: amable, con el saldo y de qué es, sin
  amenazas, y "si ya pagaste, no tengas en cuenta este mensaje".

## Compras (`/panel/compras`, solo dueño)

- Toda la compra en curso es **un solo formulario** (borrador en la sesión):
  proveedor (con los anteriores en `datalist`), el campo del lector y las
  líneas con cantidad (unidades o kilos "2,5") y costo. El primer botón es
  "Agregar": el Enter del lector agrega el código **y guarda lo corregido**
  en las líneas. "Quitar", "Vaciar" y "Guardar compra" son botones con
  `name="accion"`.
- Producto con stock NULL: aviso "No llevabas la cuenta de este producto:
  desde esta compra se cuenta, empezando con lo que llegó".
- Código desconocido → `?nuevo=CODIGO`: crear el producto ahí (nombre
  sugerido por el catálogo compartido, por unidad/peso, precio de venta y
  costo) y entra a la compra. Margen en vivo por línea ("Te deja $600 por
  unidad (18 %)" o "Ojo: lo vendes por debajo de este costo").
- Guardar: transacción con `FOR UPDATE` por producto; suma stock y deja el
  último costo. Token de un solo uso. Historial y detalle como remisión en el
  papel de la comanda ("inventario 7 → 19").

## Producto: código, costo, margen

- En el formulario: "¿Cómo lo vendes?" (por unidad / por peso), "Precio" ↔
  "Precio del kilo", "Lo que te cuesta" con el margen en vivo, "Código de
  barras" (`data-campo-lector`: el Enter del lector consulta el código en vez
  de enviar; si otra tienda lo usa ofrece "Usar este nombre"; si ya es de
  otro producto lo dice) y "Unidades/Kilos disponibles".
- El formulario empieza con un `<button type="submit" disabled hidden>`: el
  botón por defecto apagado bloquea el envío con Enter (sin JS también).
  Antes, con foto, el Enter disparaba "Eliminar foto", que era el primer botón.
- Catálogo: chip "Margen 32 %" solo si hay costo ("Bajo costo" en rojo si se
  vende a pérdida); precio "/ kg" y "Quedan 1,5 kg" por peso.
- "Lo que más te deja · 30 días" (barras verdes, reutiliza `.pq-mas-vendidos`):
  ganancia = cobrado − costo en ventas de mostrador (costo copiado al vender)
  y pedidos entregados (costo actual del producto). Solo con ≥ 3 productos y
  ≥ 10 renglones con costo; si no, no aparece.
