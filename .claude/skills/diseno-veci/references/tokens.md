# Tokens de diseño Veci

Todos viven en `:root` de `public/assets/css/app.css` (los de la marca de
cada tienda, en `.pq-shell-tienda`). Si necesitas un valor que no está
aquí, agrégalo como token con un comentario del porqué; no lo escribas
suelto en una regla.

## Color

### Ruta 2 · Panel ("Tiquete y papel")
| Token | Valor | Uso |
|---|---|---|
| `--tiquete` | #FFFDF8 | Fondo del panel, tarjetas |
| `--carbon` | #202124 | Texto principal |
| `--sello` | #3B4CCA | Acento Veci: acción principal, activo, enlaces |
| `--recibo` | #E9E4D8 | Bordes suaves, hover de filas |
| `--caja` | #16A36A | Éxito, dinero recibido, "abierto" |
| `--gris-texto` | #54565f | Texto secundario |
| `--gris-suave` | #8a8d97 | Texto terciario, iconos inactivos (no para texto largo) |
| `--borde` | #D8D3C6 | Bordes de inputs y botones fantasma |
| `--tinte-sello` / `--tinte-caja` / `--tinte-aji` | rgba 8–10 % | Fondos de estado (activo, éxito, error) |

### Ruta 1 · Tienda ("Letrero de barrio")
| Token | Valor | Uso | Contraste |
|---|---|---|---|
| `--papel` | #F6F1E7 | Fondo de la tienda | — |
| `--tinta` | #1B1A17 | Texto principal, barra del pedido | 16:1 |
| `--texto-calido` | #5F5848 | Texto secundario (descripciones, etiquetas) | 6.3:1 papel |
| `--texto-tenue` | #736C5B | Texto terciario (descripción de plato, meta) | 4.6:1 papel |
| `--papel-hondo` | #EFE8D8 | Fondos hundidos (chips neutros, hover) | — |
| `--linea-tienda` | #E3DBC8 | Bordes y líneas punteadas | — |
| `--aji` / `--mostaza` / `--aguacate` | #E8452C / #F2B632 / #5B7F3A | Paleta de producto y colores por defecto de marca | — |
| Verde de estado | #0f7a4f sobre `--tinte-caja` | "Abierto", "Hoy 3:00 p. m." | 4.8:1 |
| Rojo de estado | #9c2c17 sobre `--tinte-aji` | "Agotado", "No disponible" | 6.7:1 |

### Marca del negocio (solo tienda)
Se inyectan en `layouts/tienda.php` sobre `.pq-shell-tienda`:
- `--marca`: `color_seguro($negocio['color_marca'])` (respaldo #F2B632).
- `--marca-sobre`: `color_texto_sobre($marca)`: tinta o papel, el que dé
  más contraste. **Todo texto o icono sobre `--marca` usa este.**

Derivados en CSS (no hace falta calcularlos en PHP):
- `--marca-claro`: raya clara del toldo.
- `--marca-suave`: fondo de resaltado (fila "hoy" del horario).
- `--marca-texto`: la marca oscurecida con tinta. **Único uso permitido de
  la marca como color de texto** (enlaces, etiqueta "HOY"), porque la marca
  cruda puede ser amarilla y no leerse sobre papel.

## Tipografía

Fuentes alojadas en `public/assets/fonts/` (variables, subconjunto latino),
declaradas en `public/assets/css/fuentes.css`. No volver a Google Fonts:
la CSP (`font-src 'self'`) lo bloquea a propósito.

| Familia | Ruta | Uso |
|---|---|---|
| Bricolage Grotesque 600–800 (`--fuente-titulo`) | Todo | Títulos (`.pq-h1` 700, `-.015em`), nombre del negocio, insignias |
| Inter Tight 400–700 (`--fuente-texto`) | Todo | Texto, interfaz y datos (#pedido, horas, precios con `tabular-nums`) |

Una sola familia para tienda, panel, onboarding y admin (antes el panel
usaba Instrument Serif + Inter + JetBrains Mono y se leía como otro
producto). Las rutas se distinguen por papel y color, no por letra. No
agregar familias ni volver a una monoespaciada: `.pq-mono` ahora es solo
"cifras tabulares".

Escala de la tienda (celular → escritorio ≥ 860 px):
| Rol | Tamaño | Peso | Notas |
|---|---|---|---|
| Nombre del negocio | clamp(26px, 7.6vw, 34px) → 42px | 700 | `letter-spacing: -.02em`, `text-wrap: balance` |
| Título de sección | 19px → 22px | 700 | con barrita de `--marca` delante |
| Nombre de plato o servicio | 15.5px | 600 | |
| Precio | 15px | 650 | `font-variant-numeric: tabular-nums` siempre |
| Texto secundario | 13.5–14.5px | 400 | `--texto-calido` o `--texto-tenue` |
| Etiqueta en mayúsculas | 10.5–11px | 700 | `letter-spacing: .06em`; solo para "HOY" o rótulos de 1 palabra |

## Espaciado, radios y elevación

Escala de 4 px: `--esp-1` 4 · `--esp-2` 8 · `--esp-3` 12 · `--esp-4` 16 ·
`--esp-5` 20 (margen lateral en celular) · `--esp-6` 24 · `--esp-8` 32
(margen lateral en escritorio) · `--esp-10` 40 · `--esp-12` 48.

Radios: `--radio-s` 8 (chips, filas resaltadas) · `--radio-m` 12 (fotos,
botones cuadrados) · `--radio-l` 18 (hojas de la carta, tarjetas de
información, barra del pedido) · 999px (pastillas).

Elevación de la tienda (de menos a más):
1. Hoja de la carta: `0 1px 2px rgba(27,26,23,.04), 0 14px 30px -22px rgba(27,26,23,.3)`.
2. Barra fija del pedido: `0 16px 32px -12px rgba(27,26,23,.55), 0 2px 6px rgba(27,26,23,.2)`.
Las tarjetas de información no llevan sombra (solo borde): son secundarias.

## Movimiento

`--curva: cubic-bezier(.2,.8,.2,1)` · `--rapido` 150ms (hover, toque) ·
`--medio` 250ms (cambios de estado) · `--lento` 400ms (entradas).

Animaciones con nombre (reutilizar, no crear variantes):
- `pqAparece`: entrada de filas (8px hacia arriba + opacidad), escalonada
  con `animation-delay: calc(var(--i) * 35ms)`, con `--i` de 0 a 7 como máximo.
- `pqSube`: entrada de la barra del pedido.
- `pqPop`: confirmación de un toque (agregar al carrito).
- `pqToldo`: el toldo se despliega una vez al cargar.
- `pqLatido`: punto de "abierto ahora" (único bucle permitido).

`@media (prefers-reduced-motion: reduce)` global ya anula todo; no hace
falta repetirlo por componente.

## Capas (z-index)
`.pq-categorias` 15 · `.pq-barra-carrito` 20 · hojas y modales 30+.

## Iconos
SVG en línea, `viewBox="0 0 24 24"`, trazo `stroke-width="1.8"` (2 a 2.2
para iconos de 18 px o menos), `stroke-linecap/linejoin="round"`,
`fill="none"`, color `currentColor`, siempre `aria-hidden="true"` con
texto o `aria-label` al lado. Excepción: el logo de WhatsApp (relleno).
