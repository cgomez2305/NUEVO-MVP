# Componentes de la tienda pública

Vistas: `src/Views/tienda/mostrar.php` (pedidos), `servicios.php`
(reservas), y los parciales `_cabecera.php` y `_informacion.php` que
comparten. CSS: sección "tienda pública v2" de `app.css`. JS:
`assets/js/tienda.js` (navegación de secciones) e `interacciones.js`
(carrito sin recargar).

Pantallas internas (carrito, reservar, confirmaciones, gestión y
reprogramación de cita) usan `_cabecera_corta.php` y las secciones "tienda
v2: pantallas internas" y "tienda v2: confirmaciones" de `app.css`. **Toda
la tienda pública ya está en este lenguaje**; no queda ninguna vista con el
topbar viejo, monoespaciada ni cuadros de color.

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

---

## Cabecera corta (`_cabecera_corta.php`)

Para toda pantalla interna de la tienda. Recibe `$volverUrl` y
`$volverTexto`. Lleva una franja de toldo de 30px (sin animación, que se
reserva para la portada) y **un solo enlace** con flecha, insignia de 36px,
nombre del negocio y "Volver a…". Los selectores llevan doble clase
(`.pq-toldo.pq-toldo-corto`) para ganarle a la regla de escritorio del
toldo grande; ese fue un bug real.

## Etapas numeradas (`.pq-etapa`)

`<h2 class="pq-etapa"><span class="pq-etapa-numero">1</span>Tus datos</h2>`.
El número va en un círculo con `--marca` y `--marca-sobre`. Con un dato a la
derecha (mes, "35 libres"), envuélvelo en `.pq-etapa-fila`. En la reserva la
numeración es dinámica (`++$paso`): "¿Con quién?" solo existe si hay
empleados. (No usar `.pq-paso`: ya existe para otra pantalla.)

## Comanda (carrito)

```
 ┌─────────────────────────────┐
 │ COMANDA          3 PRODUCTOS │
 │ - - - - - - - - - - - - - - -│
 │ Bandeja paisa ······ $56.000 │
 │ $28.000 c/u      (− 2 +)  🗑 │
 │ ═════════════════════════════│ ← doble línea
 │ Total             $61.000    │
 └/\/\/\/\/\/\/\/\/\/\/\/\/\/\/┘ ← borde rasgado
```
- Borde rasgado: máscara `conic-gradient(from -45deg at bottom, …)` con
  `--diente`. La sombra va en `.pq-comanda` (contenedor) por la máscara.
- Sin imágenes: es un papelito. Mismos puntos guía que la carta.
- IDs que actualiza el JS: `#pq-comanda-cuenta`, `#pq-cantidad-{id}`,
  `#pq-subtotal-{id}`, `#pq-carrito-total`, `#pq-checkout-sticky-total`;
  la fila es `[data-fila-producto]` (de ahí la saca el JS al quitar).
- En escritorio: formulario a la izquierda y comanda pegada a la derecha
  (340px).

## Hojitas de almanaque (reserva)

`$hojaDia($fecha)` en `reservar.php` arma cada fecha: día de la semana
("hoy" si aplica), número grande en Bricolage y mes. El "lomo" de arriba es
`box-shadow: inset 0 4px 0 var(--marca-claro)`; la hoja activa se llena con
la marca. **Sin sombra exterior:** el carrusel tiene overflow y la recorta.
La misma hoja se usa en "Ver todas las fechas" (grilla `auto-fill`).
`interacciones.js` centra `.pq-dia-activo` al cargar.

## Turno elegido (`.pq-turno`)

Antes del formulario: la hora en grande, la fecha y "servicio · con
persona", sobre `--marca-suave`. Primero se confirma qué y cuándo; después
se piden los datos.

## Formularios en la tienda
Overrides bajo `.pq-shell-tienda`: campos de 16px (evita el zoom del
iPhone), foco y opción elegida con la marca, prefijo +57 y teléfono con
`tabular-nums`, casillas con `accent-color: var(--marca-texto)`. El botón
final sigue siendo el verde de WhatsApp: la acción ocurre allá.

## Sello de caucho (`.pq-sello`)

El detalle propio de las confirmaciones y de la gestión de cita: un sello
girado (-9°), con borde de 2,5px y tinta "gastada" (máscara de puntitos),
que "cae" una vez al cargar (`pqSellar`). Dice el estado: "Por enviar"
(tono marca), "Confirmada"/"Atendida" (`.pq-sello-ok`, verde), "Cancelada"
(`.pq-sello-no`, rojo). Va **abajo a la derecha** del tiquete, sobre
`.pq-comanda-datos`, que le reserva 112px a la derecha: arriba tapaba el
precio de la primera línea (bug real que se encontró en las capturas).

## Tiquete final (`.pq-comanda-final`)

La misma comanda del carrito, sin controles: cabeza con "Pedido #N" y la
fecha, líneas "1× producto ···· $precio", total y un `<dl
class="pq-comanda-datos">` con entrega, pago y nota. Para citas,
`_tiquete_cita.php` cambia las líneas por la hora en grande
(`.pq-tiquete-turno`) y agrega duración, persona y anticipo.

## Pasos de pago (`_pasos_pago.php`)

Lo usan ambas confirmaciones. Variables: `$pagoTitulo`, `$pagoMetodo`
("Bre-B"/"Nequi", nunca "BREB"), `$pagoLlave`, `$pagoReferencia` (solo
Bre-B), `$pagoMonto`, `$pagoPara`. Cada dato copiable va en una caja
punteada con su botón `[data-copiar]` y su propio `aria-label` ("Copiar la
llave"); el JS restaura esa etiqueta después de mostrar "Copiado".

## Helpers de fecha de la tienda
- `hora_completa('09:30')` → "9:30 a. m." (en grillas, siempre con minutos).
- `hoja_almanaque($fecha, $activa, $href)` → la hojita de almanaque;
  reservar y reprogramar la comparten.
- Reprogramar abre en el **día que ya tiene la cita** (no en "hoy") y marca
  el turno actual con la etiqueta "actual" y deshabilitado.


## Imprevistos del lado del cliente (`/cita/{token}`, reservar)

- El aviso del negocio es un **recado pegado con cinta** (`.pq-imprevisto-aviso`):
  papel `--marca-suave`, cinta arriba, levemente torcido. Va antes del tiquete
  porque es lo que el cliente vino a ver: nuevo valor por aprobar (Apruebo /
  No lo apruebo), "Tenemos que mover tu cita" (motivo + Elegir otra hora) o
  "Vamos con N minutos de retraso" (Espero / Mejor otra hora).
- "¿Se te hizo tarde?" (`.pq-llego-tarde`, plegado, solo el día de la cita):
  chips de minutos; los que pasan la tolerancia del negocio van punteados.
- Reservar dice las reglas antes de confirmar (`.pq-turno-reglas`): cuánto se
  espera y qué pasa con el anticipo si no llega. Precio "desde"/rango lleva
  la nota "El valor final se confirma al ver el trabajo".
- El tiquete muestra el precio por tipo, "Valor estimado" mientras no haya
  cobro, y "Valor aprobado/final" cuando lo hay.
