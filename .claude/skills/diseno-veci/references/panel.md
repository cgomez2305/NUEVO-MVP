# Componentes del panel del dueño

Ruta 2 "Tiquete y papel": fondo `--tiquete`, texto `--carbon`, acción
`--sello` (#3B4CCA), títulos en Instrument Serif, interfaz en Inter, datos
(precios, horas, números de pedido) en JetBrains Mono. CSS: bloque
"PANEL v2" al final de `app.css`. Layout: `src/Views/layouts/panel.php`.

Estado del rediseño:
- **Fase 1 (hecha):** shell, pedidos (kanban + historial), detalle del
  pedido, productos, agenda de citas, avisos push en Mi cuenta.
- **Fase 2 (pendiente):** servicios, horario, fechas bloqueadas, copiloto,
  recordatorios, sedes, colaboradores, empleados, cuenta, plan,
  `producto_form.php` y `productos/_gestor.php` (sigue con la miniatura
  vieja de fondo en línea).

**Detalle firma del panel: la comanda imprimible** (`.pq-comanda-panel`).
No se repite en otras pantallas del panel; ahí el detalle propio es otro.

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
- Navegación lateral y bottomnav en Inter (no en mono): son rótulos, no datos.
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
  mono) / nota entre comillas / tiempo de espera / **un solo** botón con el
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
        DOÑA MARÍA                 ← nombre_publico_sede()
 Pedido #50 · 1 Oct, 7:02 a. m.
 - - - - - - - - - - - - - - - -
 Ana Ruiz · 3009998877
 1× Bandeja paisa       $28.000
 - - - - - - - - - - - - - - - -
 Total                  $28.000
 - - - - - - - - - - - - - - - -
 Entrega  Recoge en el local
 Pago     Efectivo
 \/\/\/\/\/\/\/\/\/\/\/\/\/\/\/\/  ← borde rasgado (conic-gradient + mask)
```

- Blanco, todo en mono, líneas punteadas, `max-width: 420px`.
- Debajo: botón sello con el siguiente paso, luego "WhatsApp" e "Imprimir"
  (texto corto + `aria-label` largo), y `<details class="pq-detalle-mas">`
  con el cambio de estado manual y "Cancelar este pedido"
  (`.pq-boton-peligro`, con `data-confirmar`). Lo destructivo nunca queda a
  la vista.
- `@media print`: `@page { size: 80mm auto }`, todo `visibility: hidden`
  menos la comanda, que se posiciona a 72mm de ancho y sin máscara. Sale
  lista para el rollo térmico. Para probarla:
  `page.emulateMediaType('print')` con viewport de ~302px.
- Mensaje de WhatsApp: el texto va con `rawurlencode()` y **sin** `e()`
  (un "&" del nombre del negocio saldría como `&amp;`).

## `<details>` del panel

`.pq-detalle-mas` y `.pq-agenda-mas`: el `summary` es flex (pierde el
triángulo nativo), así que se dibuja un chevron con `::after` que gira al
abrir. Escape ya cierra cualquier `<details>` del panel.

## Catálogo de productos (`panel/productos.php`)

- `.pq-catalogo-grid { grid-template-columns: minmax(0, 1fr) }`: sin el
  `minmax(0, …)` un nombre largo empujaba la grilla 33px fuera de la
  pantalla.
- Toda la info es un enlace a editar (`.pq-producto-card-info`); el botón
  "Editar" se oculta en celular y queda el menú ⋮.
- **Sin foto, inicial honesta** (`.pq-producto-miniatura-inicial`): letra en
  Instrument Serif sobre el color del producto
  (`--color-producto: color_seguro(...)`). Nunca una foto genérica.

## Agenda de citas (`panel/citas.php`)

```
Hoy  viernes 2 de octubre           ← Instrument Serif + fecha_larga()
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
- Columna de hora de 72px con borde punteado: la hora grande (`g:i`, mono
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
