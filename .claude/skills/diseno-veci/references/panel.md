# Componentes del panel del dueño

Ruta 2 "Tiquete y papel": fondo `--tiquete`, texto `--carbon`, acción
`--sello` (#3B4CCA). Tipografía igual a la tienda: títulos en Bricolage
Grotesque, interfaz y datos en Inter Tight (cifras tabulares). CSS: bloque
"PANEL v2" al final de `app.css`. Layout: `src/Views/layouts/panel.php`.

Estado del rediseño: **todo el panel está en v2** (inicio, pedidos,
detalle, productos y su formulario, agenda, servicios, horario, días
bloqueados, empleados, recordatorios, copiloto, sedes, colaboradores,
cuenta, plan) y el onboarding (ver `onboarding.md`). Falta: admin.
`panel/_semana.php` (días con interruptor + copiar el lunes) lo comparten
el horario del panel y el del onboarding.

## El puente con la tienda (decisión del usuario)

El panel NO se vuelve tienda: sigue en "Tiquete y papel". Pero donde el
dueño piensa en *su* tienda, aparece la marca del negocio con piezas de
la Ruta 1, para que ambos lados se sientan de la misma familia:

- `layouts/panel.php` pone `--marca` y `--marca-sobre` en el `<body>`;
  `.pq-panel-bg` deriva `--marca-claro/-suave/-texto` (igual que la tienda).
- **Insignia**: el avatar del selector de sede es la insignia redonda de
  la tienda (color del negocio + Bricolage).
- **Cenefa**: franja de 4px con las rayas del toldo bajo la cabecera.
- **Escaparate** (`.pq-escaparate`): un pedazo de la tienda dentro del
  panel — papel, toldo corto, insignia, nombre en Bricolage y "así la ven
  tus clientes". Se usa en el inicio (enlace para compartir), en el
  horario (vista previa del horario guardado) y en Sedes (cada sede es una
  fachada). Ojo: `.pq-vitrina` ya existe en la tienda, no reutilizar ese
  nombre.
- **Comanda unificada**: el detalle del pedido usa la MISMA comanda de la
  confirmación de la tienda (`.pq-comanda.pq-comanda-final`), con sello de
  caucho mostrando el estado. En el panel el sello va arriba a la derecha.
- Hojita de almanaque de la reserva, tachada, para los días bloqueados.

Lo demás del panel (navegación, formularios, datos) va en Inter Tight con
el azul sello.

---

## Shell en celular (≤959px)

```
┌───────────────────────────────┐
│ veci              (M) Sede ⌄ │ ← UNA sola barra de 57px
├───────────────────────────────┤   (topbar + header fusionados)
│ …contenido…                   │
├───────────────────────────────┤
│ Panel  Pedidos  Copiloto  Más │ ← bottomnav
└───────────────────────────────┘
```

- `.pq-shell { --alto-barra-movil: 57px }`; `.pq-header` queda pegajoso con
  `margin-top` negativo, fondo transparente y `pointer-events: none`,
  y el selector de sede vuelve a recibir clics. Así no hay dos franjas
  apiladas comiéndose 120px de pantalla.
- `.pq-switcher-sub` ("Sede activa") se oculta en celular.
- Navegación lateral y bottomnav en Inter Tight: son rótulos, no datos.
- Nada global va al pie de todas las páginas: el botón de avisos push vive
  en Mi cuenta (`#push-seccion`, `hidden` hasta que `panel-push.js` confirme
  que el navegador soporta push).

## Cabecera de página

```html
<div class="pq-pagina-cabeza">
  <div><span class="pq-eyebrow">Pedidos</span><h1 class="pq-h1">Tus pedidos</h1></div>
  <a class="pq-btn pq-btn-ghost pq-btn-chico [pq-btn-bloqueado]">Exportar CSV · Pro</a>
</div>
```

Títulos de sección con contador: `<h2 class="pq-seccion-titulo">En curso
<span class="pq-seccion-cuenta">20</span></h2>`.

## Kanban de pedidos (`panel/pedidos.php`)

- Escritorio: 4 columnas. Celular: una sola columna con las columnas vacías
  ocultas (`.pq-kanban-col-vacia`); no hay scroll horizontal.
- Tarjeta: `#id · cliente` / icono de entrega + total (`.pq-kanban-total`,
  cifras tabulares) / nota entre comillas / tiempo de espera / **un solo** botón con el
  siguiente paso (`.pq-kanban-accion`).
- **Demora** (`.pq-card-demorado`): acento de 4px a la izquierda
  (`box-shadow: inset 4px 0 0 var(--aji)`), sin fondo rojo; el rojo queda
  solo en el texto de espera. La entrega no se pinta de rojo.
  Si un componente redefine `box-shadow` (p. ej. `.pq-agenda-cita`),
  repite el acento con doble clase o lo pierde.

## Historial → tarjetas en celular

≤639px la tabla se convierte en tarjetas con `grid-template-areas`; cada
celda tiene su clase (`pq-t-id`, `pq-t-cliente`, `pq-t-entrega`,
`pq-t-fecha`, `pq-t-estado`, `pq-t-total`, `pq-t-ver`). Los filtros pasan a
una grilla de 2 columnas con `.pq-filtro-boton`.

## Comanda imprimible (`panel/pedido_detalle.php`)

```
 Doña María              [EN COCINA]  ← nombre_publico_sede() + sello
 PEDIDO #50        1 OCT, 7:02 A. M.
 - - - - - - - - - - - - - - - - -
 1× Bandeja paisa ·········· $28.000  ← puntos guía, igual que la carta
 ═════════════════════════════════════
 Total                       $28.000
 - - - - - - - - - - - - - - - - -
 Cliente  Ana Ruiz · 3009998877
 Entrega  Recoge en el local
 \/\/\/\/\/\/\/\/\/\/\/\/\/\/  ← borde rasgado
```

- Es la comanda de la tienda (`.pq-comanda-imprimible` solo añade la letra
  Inter Tight y el nombre arriba). Sin chip de estado en la cabecera: el
  sello ya lo dice.
- Debajo: botón sello con el siguiente paso, luego "WhatsApp" e "Imprimir"
  (texto corto + `aria-label` largo), y `<details class="pq-detalle-mas">`
  con el cambio de estado manual y "Cancelar este pedido"
  (`.pq-boton-peligro`, con `data-confirmar`). Lo destructivo nunca queda a
  la vista.
- `@media print`: `@page { size: 80mm auto }`, todo `visibility: hidden`
  menos la comanda, que se posiciona a 72mm de ancho, sin máscara ni sello
  (a la cocina no le sirve el estado). Sale
  lista para el rollo térmico. Para probarla:
  `page.emulateMediaType('print')` con viewport de ~302px.
- Mensaje de WhatsApp: el texto va con `rawurlencode()` y **sin** `e()`
  (un "&" del nombre del negocio saldría como `&amp;`).

## `<details>` del panel

`.pq-detalle-mas` y `.pq-agenda-mas`: el `summary` es flex (pierde el
triángulo nativo), así que se dibuja un chevron con `::after` que gira al
abrir. Escape ya cierra cualquier `<details>` del panel.

## Inicio (`panel/dashboard.php`)

- Saludo al negocio (`negocio_nombre`), nunca a la sede ("Hola, Sede
  Norte" sonaba a saludar un local). La sede va en la bajada si hay varias.
- Cifras del día en `.pq-caja-dia`: una tira de casillas con divisiones
  punteadas (registradora), no cuatro tarjetas de color. Solo lleva color
  la cifra que pide acción (`.pq-caja-casilla-alerta`). `.pq-caja-dia-3`
  para tres casillas (copiloto).
- Listas cortas `.pq-fila-pedido` con `#id` u hora en la primera columna
  (`.pq-fila-hora`) y la fecha sin partir (`.pq-fila-fecha`).

## Formularios del panel

- `.pq-form-panel` (tarjeta con campos y `.pq-form-panel-botones`:
  Cancelar angosto + acción ancha).
- Interruptor `.pq-interruptor` (la etiqueta envuelve el texto: área
  táctil grande) en verde `--caja`, igual que `.pq-switch` del formulario
  de producto. `.pq-interruptor-con-texto` para título + ayuda.
- `.pq-agregar-panel`: "+ Nuevo X" como `<details>` que despliega el
  formulario (se abre solo si la lista está vacía).
- `.pq-enlace-boton` (y `-peligro`) para acciones secundarias: pausar,
  quitar, eliminar, cerrar sesión. Nunca un `<button class="pq-mono">`
  de 11px gris.
- `.pq-chip-check`: casillas como chips (sedes de un colaborador).
- Precios: `data-precio-cop` + `dinero_desde_texto()` en el servidor.
  `(int) "20.000"` es 20: nunca `(int)` sobre un precio con puntos.
- `.pq-sin-js`: botón de respaldo (p. ej. "Aplicar" junto a un select con
  `data-autoenviar`) que `confirmar.js` oculta cuando hay JS.

## Servicios (`panel/servicios.php`)

Fila que se lee como la carta (punto de color, nombre, duración · anticipo,
precio, "Editar"). Es un `<details>`: abre UN formulario con nombre,
precio, duración y anticipo, y un solo "Guardar cambios". El controlador
guarda el anticipo junto al servicio solo si es el dueño.

## Marco de app en escritorio (≥960 px)

- La hoja (`.pq-shell`) mide exactamente el alto de la ventana menos el
  margen del body, y **solo se desplaza `.pq-shell-main`** (la columna del
  contenido). El body no hace scroll (`overflow: hidden`).
- Sidebar y cabecera no usan `sticky` contra la ventana: el sidebar es una
  columna fija y la cabecera va `sticky; top: 0` dentro de la columna que
  se desplaza. Antes ambos se pegaban a 32 px y por ese hueco se veía pasar
  el contenido por encima de la cabecera.
- `.pq-shell-main` lleva `position: relative`: los `.pq-sr-solo` (absolutos)
  se medían contra la página y la estiraban.
- En impresión se desactiva el marco (alto automático, sin overflow) para
  no recortar la comanda.
- Un elemento nuevo que dependa del scroll de la ventana en el panel de
  escritorio debe escuchar el de `.pq-shell-main`, no el de `window`.

## Horario y días bloqueados

- `.pq-semana`: un día por fila con interruptor; apagado, las horas se
  esconden y aparece "Cerrado" (CSS `:has`, sin JS). En celular las horas
  bajan a su línea y se reparten el ancho (si no, se corta "a. m.").
- **Pausa del mediodía** (`.pq-semana-pausa`): casilla "Cierra al mediodía"
  por día; marcada, aparece el rango con campos de borde punteado y una
  taza (el letrerito "Volvemos a las…"; la taza se oculta en celular). Se
  guarda como franjas: `{"1":[["08:00","12:00"],["14:00","18:00"]]}`;
  `Sede::horario()` sigue leyendo el formato viejo `["08:00","18:00"]`.
  Una pausa fuera del día no se guarda y se avisa
  (`aviso_pausas_invalidas()`). "Copiar el lunes" copia también la pausa.
- Cupos (`Cita::calcularDisponibilidad`): un servicio cabe entero en una
  franja; la tarde arranca a la hora de regreso. Tienda: "En pausa · vuelve
  a las 2 p. m." con punto mostaza; horario con un tramo por línea y
  "12 m." para el mediodía.
- La pantalla de horario es también para negocios de **pedidos** (sin
  turnos ni días bloqueados): antes no podían poner horario.
- `.pq-semana` lleva `min-inline-size: 0`: un `<fieldset>` no se encoge
  por debajo de su contenido y la fila de la pausa se salía a 360 px.
- Debajo, escaparate con el horario guardado tal como lo ve el cliente.
- Días bloqueados: `.pq-dia.pq-dia-bloqueado` (hojita tachada en ají).

## Copiloto

- `.pq-segmentos`: grupos como pestañas en una fila con scroll lateral y
  la explicación del grupo activo a la vista (antes en un `title`).
- `.pq-cliente-fila`: avatar suave, nombre + chips mini, motivo.
- Mensaje: la burbuja verde de WhatsApp ES el `<textarea>` editable
  (`.pq-wa-fondo` + `.pq-burbuja-out.pq-wa-editable`). Recordatorios usan
  el mismo fondo con la burbuja de solo lectura.
- Textos: `hace_dias()` ("hoy", "ayer", "hace 5 días"; nunca "hace 1
  días"); "compraba cada N días" solo si de verdad ya se pasó de su ritmo.

## Catálogo de productos (`panel/productos.php`)

- `.pq-catalogo-grid { grid-template-columns: minmax(0, 1fr) }`: sin el
  `minmax(0, …)` un nombre largo empujaba la grilla 33px fuera de la
  pantalla.
- Toda la info es un enlace a editar (`.pq-producto-card-info`); el botón
  "Editar" se oculta en celular y queda el menú ⋮.
- **Sin foto, inicial honesta** (`.pq-producto-miniatura-inicial`): letra en
  Bricolage sobre el color del producto
  (`--color-producto: color_seguro(...)`). Nunca una foto genérica.

## Agenda de citas (`panel/citas.php`)

```
Hoy  viernes 2 de octubre           ← Bricolage + fecha_larga()
┌──────────┬─────────────────────────┐
│ 10:00    │ Luis Pérez   [Pendiente]│
│ a. m.    │ Corte · $20.000 · con X │
│          │ ◷ Sin confirmar · 22 h  │
│ 30 min   │ [Confirmar cita →]      │
│          │ Cambiar estado ⌄        │
└──────────┴─────────────────────────┘
```

- Agrupada por día: Hoy / Mañana / Ayer + fecha larga; otros días, la fecha
  larga sola. Se lee como la agenda de papel del salón.
- Columna de hora de 72px con borde punteado: la hora grande (`g:i`, cifras tabulares
  17px), y debajo, en líneas separadas, meridiano y duración (nunca
  "a. m. · 30 min" en una línea: se parte feo a 360px).
- El texto de espera **sí** puede partirse dentro de la agenda
  (`white-space: normal`); en el resto del panel es `nowrap`.
- Lista de espera arriba (`.pq-agenda-espera`) con "Avisar" por WhatsApp y
  ✓ para marcar contactada. Fechas con `fecha_larga()`: nunca `date('d M')`
  (sale el mes en inglés).

## Estado vacío

`.pq-vacio-panel`: icono de línea 1.6, frase en negrita + qué hacer, sin
ilustraciones.

## Cierre de caja (`/panel/caja`, Operación, todos los roles)

- **Tiquete Z**: el mismo papel de la comanda (`.pq-comanda` +
  `.pq-comanda-imprimible`, sale en rollo de 80 mm con "Imprimir cierre"),
  con sello "Día abierto" / "Sin cerrar" / "Cerrada". Renglones por forma de
  pago con cuántos pedidos (`.pq-cierre-z-cuenta`), servicios (citas del
  día), descuentos y domicilios como ajustes, "Vendido" como total y, ya
  cerrada, base / esperado / contado / diferencia / quién cerró.
- Navegación por días tipo talonario (`.pq-cierre-dias`: ‹ hoy ›, sin
  futuro). Aviso amarillo si hay pedidos del día sin entregar.
- Cuadre en vivo (`[data-caja-form]` en interacciones.js): base + efectivo
  de pedidos (+ efectivo de servicios en negocios con citas, que no guardan
  forma de pago) contra lo contado → chip "Cuadra" / "Sobran $X" /
  "Faltan $X". Se guarda una foto del resumen: si después cancelan un
  pedido, el cierre de ese día no cambia. Volver a cerrar reemplaza.
- Historial de los últimos 14 cierres con su diferencia.

## Avisos de estado al cliente (pedidos y citas)

- `App\Services\AvisoEstado`: un texto por estado (pagado, en cocina,
  listo —según recoger/mesa/domicilio—, en camino, entregado, cancelado;
  cita confirmada o cancelada). `aviso_estado` guarda de qué estado ya se
  avisó, para no ofrecerlo dos veces.
- Con la API de WhatsApp Business configurada (`whatsapp_api` en config)
  sale solo al cambiar el estado (toast "Le avisamos a X"). Sin ella:
  - Detalle del pedido: el aviso se ve **como burbuja de WhatsApp** sobre
    el fondo del chat (`.pq-aviso-detalle`) con "Avisarle por WhatsApp".
  - Tablero y agenda: enlace verde discreto `.pq-aviso-estado`
    ("Avisarle: Listo") bajo la acción principal; no compite con ella.
- La migración da por avisados los pedidos y citas que ya existían.
