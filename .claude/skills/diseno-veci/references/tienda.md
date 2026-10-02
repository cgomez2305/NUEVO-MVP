# Componentes de la tienda pública

Vistas: `src/Views/tienda/mostrar.php` (pedidos), `servicios.php`
(reservas), y los parciales `_cabecera.php` y `_informacion.php` que
comparten. CSS: sección "tienda pública v2" de `app.css`. JS:
`assets/js/tienda.js` (navegación de secciones) e `interacciones.js`
(carrito sin recargar).

Pendientes de llevar a este lenguaje: `carrito.php`, `reservar.php`,
`pedido_confirmado.php`, `cita_*.php` (todavía usan mono y cuadros de color).

---

## Fachada (`_cabecera.php`)

```
┌───────────────────────────────┐
│▓▓░░▓▓░░▓▓░░▓▓░░▓▓░░▓▓░░▓▓░░▓▓│ ← cenefa (--marca-texto, 7px)
│▓▓░░▓▓░░▓▓░░▓▓░░▓▓░░▓▓░░▓▓░░▓▓│ ← toldo: rayas --marca / --marca-claro
│◖◗◖◗◖◗◖◗◖◗◖◗◖◗◖◗◖◗◖◗◖◗◖◗◖◗◖◗◖◗│ ← festones (máscara radial)
 (M)                               ← insignia 64px, se monta sobre el toldo
 Doña María · Sede Norte           ← h1 Bricolage
 Arepas y jugos · Bucaramanga
 ● Abierto · hasta las 6 p. m.     ← solo si hay horario cargado
 (Escribir) (Cómo llegar) (Horario) ← pastillas de 44px, scroll horizontal
```

- **El toldo es el detalle propio de la tienda.** No agregar más adornos a
  la cabecera; si hay que destacar algo, se hace con jerarquía.
- Rayas y festones miden lo mismo (`2 × --feston`): cada festón cae en una
  raya. Si cambias `--feston`, todo se recalcula.
- La sombra del toldo va en `.pq-toldo` y el dibujo en `::before`, porque
  la máscara recortaría un `drop-shadow` puesto en el mismo elemento.
- Cada acceso rápido aparece solo si existe el dato: WhatsApp, dirección
  u horario.
- El estado (abierto/cerrado) viene de `negocio_abierto_ahora()` y
  `negocio_proxima_apertura()`; el texto va en un único `<span>` para que
  el `gap` del flex no lo separe.

## Navegación de secciones (`.pq-categorias`)

- Solo aparece con 2 o más categorías. Cada chip muestra el número de
  productos de la sección.
- Va pegada arriba (`sticky`) con fondo translúcido y desenfoque. La sombra
  aparece solo cuando ya quedó pegada (centinela + IntersectionObserver).
- Sección activa: la última cuyo comienzo ya pasó bajo la barra; al llegar
  al fondo de la página, la última. No usar "la que más se ve": con
  secciones cortas falla (bug real que se encontró probando).
- El chip activo usa `--marca` y `--marca-sobre`, y se centra en la fila
  moviendo **solo** la fila (`nav.scrollTo`), nunca con `scrollIntoView`.

## Carta (`.pq-carta` > `.pq-carta-lista` > `.pq-plato`)

```
 ▌Comidas
 ┌─────────────────────────────────┐
 │ Bandeja paisa ········ $28.000 (+)│
 │ - - - - - - - - - - - - - - - - - │
 │ Sancocho trifásico de gallina,   ┌──┐
 │ res y cerdo con arroz            │📷│
 │ ··················· $1.250.000   └(+)
 │ Para compartir entre 4…           │
 └─────────────────────────────────┘
```

- **Puntos guía** (`.pq-plato-guia`) entre el nombre y el precio, como en
  la carta impresa. Con `flex-wrap`: si el nombre no cabe, ocupa su línea
  y los puntos y el precio bajan juntos. Nunca dejar que el nombre se
  parta en una palabra por renglón.
- **Sin foto real no hay imagen.** No se dibujan cuadros ni degradados de
  relleno. Con foto: miniatura de 88px a la derecha y el "+" montado sobre
  su esquina.
- Separación entre platos: línea punteada (`1px dashed --linea-tienda`).
  En escritorio la lista pasa a 2 columnas y se quita la línea superior de
  los dos primeros.
- Agotado: nombre y precio en `--texto-tenue`, precio tachado, foto en gris,
  etiqueta "Agotado por hoy" y sin botón "+".

### Botón agregar (`.pq-agregar`)
- 40px visibles, 48px de área táctil (`::before` con `inset: -4px`).
- En reposo: contorno sobrio. Al pasar el cursor: tinta. Si el producto ya
  va en el pedido: relleno de `--marca` (`.pq-agregar-lleva`) con un
  contador en tinta (`[data-cuenta-producto]`).
- `interacciones.js` actualiza todos los contadores con la respuesta JSON y
  hace `pqPop` en el botón tocado. Sin JS el formulario se envía normal.

## Servicio (`.pq-servicio`)

- Toda la fila es el enlace (`<a>`). Si el servicio está agotado, la fila
  es un `<div>` con la etiqueta "No disponible".
- A la derecha: chip verde "HOY · 3:00 p. m." **solo si hay turno real hoy**,
  y luego el chevron. Si hoy no queda ningún turno, se muestra un único
  aviso sobre la lista (`.pq-carta-nota`), nunca repetido en cada fila.

## Información (`_informacion.php`)

- `.pq-info`: tarjetas con solo borde (sin sombra) para horario y "Dónde
  estamos". En escritorio, 2 columnas.
- Horario: `<dl>` con la fila de hoy resaltada (`'hoy' => true` de
  `horario_resumen()`), fondo `--marca-suave` y etiqueta "HOY" en
  `--marca-texto`. Las horas usan guion largo: "9 a. m. – 6 p. m.".
- Ancla `#horario`, enlazada desde el acceso rápido de la cabecera.

## Barra del pedido (`.pq-barra-carrito`)

```
 ┌──────────────────────────────────────┐
 │ [🛍³]  Ver mi pedido       $61.000 → │
 │        3 productos                   │
 └──────────────────────────────────────┘
```
- Es `sticky` con `bottom` que suma `env(safe-area-inset-bottom)`, y va
  después de `</main>`, así que no tapa el final del contenido.
- IDs que usa el JS: `#pq-barra-carrito`, `#pq-barra-carrito-cuenta`,
  `#pq-barra-carrito-resumen` y `#pq-barra-carrito-total`. Con 0 productos
  lleva `.pq-barra-carrito-oculta`.
- En escritorio: 400px de ancho, alineada a la derecha.

## Estados vacíos (`.pq-vacio-tienda`)
Borde punteado, icono de 36px, una frase en negrita que dice qué pasa y
una que dice qué hacer ("puedes escribirle al negocio por WhatsApp").
Nunca un "No hay datos".

## Datos para probar (casos límite)
Antes de dar por buena una pantalla de la tienda, crea temporalmente y
luego borra:
- Un producto con nombre de más de 60 caracteres, precio de 7 cifras,
  descripción larga y foto.
- Un producto agotado.
- Un horario que deje la tienda **abierta** ahora y con turnos hoy (para
  ver el punto que late y los chips verdes). Guarda y restaura
  `sedes.horario_atencion`.
- Un carrito con productos, usando un cookie jar de curl y `--cookies` en
  `capturas.js`.
Inserta texto con tildes usando `mysql --default-character-set=utf8mb4`;
si no, quedan caracteres dañados ("trifÃ¡sico") que no son un bug de la app.
