---
name: diseno-veci
description: Sistema de diseño y proceso de trabajo visual de Veci (tienda pública, panel del dueño, onboarding, admin). Úsala SIEMPRE que se vaya a crear o cambiar cualquier vista PHP de src/Views, estilos de public/assets/css/app.css, componentes de interfaz, animaciones, colores, tipografía o espaciados de la app — aunque el pedido no diga "diseño" (p. ej. "agrega un botón", "muestra el horario", "arregla cómo se ve el carrito", "haz la pantalla de X", "que se vea más pro"). También para revisar o auditar la interfaz con capturas.
---

# Diseño Veci

Veci es la tienda por WhatsApp del negocio de barrio colombiano: la
arepera, el salón de belleza, la panadería. La interfaz tiene que sentirse
**hecha a mano para ese barrio**, no una plantilla SaaS. Esta skill reúne
las decisiones de diseño ya tomadas, para que cada pantalla nueva suba el
nivel en vez de reinventar (o degradar) lo que existe.

## Las dos rutas visuales (no mezclarlas)

| | Ruta 1 · "Letrero de barrio" | Ruta 2 · "Tiquete y papel" |
|---|---|---|
| Dónde | Tienda pública `/t/{slug}`, carrito, reservas, confirmaciones (`layouts/tienda.php`) | Panel del dueño, onboarding, admin (`layouts/panel.php`, `onboarding.php`, `admin.php`) |
| Quién la ve | El cliente del negocio, en su celular, con datos móviles | El dueño, varias veces al día, operando rápido |
| Sensación | Cálida, local, con la marca **del negocio** al frente | Clara, operativa, confiable, con la marca **Veci** |
| Fondo | `--papel` #F6F1E7 | `--tiquete` #FFFDF8 |
| Texto | `--tinta` #1B1A17 | `--carbon` #202124 |
| Acento | `--marca` (color del negocio) | `--sello` #3B4CCA (azul Veci) |
| Tipografía | Bricolage Grotesque (títulos) + Inter Tight (texto) | Instrument Serif (títulos) + Inter (texto) + JetBrains Mono (datos) |

En la tienda manda la marca del negocio: Veci solo aparece en el sello
"Hecho con Veci" del plan Gratis. En el panel, la marca del negocio
aparece solo donde el dueño piensa en *su* tienda (insignia de la sede,
cenefa, escaparate, comanda): ver "El puente con la tienda" en
`references/panel.md`. Detalle completo de tokens, escalas y
componentes en `references/tokens.md` y `references/tienda.md`.

## Principios (el porqué de cada decisión)

1. **Primero el celular de gama media.** 360–390 px de ancho, pulgar,
   sol, 4G lento. Todo se diseña ahí y luego se expande; escritorio es la
   adaptación, no al revés. Áreas táctiles ≥ 44 px.
2. **Honestidad visual.** Nunca inventar contenido para que "se vea
   lleno": sin foto real no hay foto falsa (nada de degradados que parecen
   imágenes rotas); sin horario cargado no hay "Abierto ahora"; un
   estado o número siempre sale del dato real.
3. **Un detalle propio por pantalla, no diez.** Lo que hace única a Veci son
   pocos detalles precisos y con sentido local (el toldo rayado de la
   tienda, la carta con puntos guía, el tiquete). Si un adorno no cuenta
   algo del barrio o no ayuda a usar la pantalla, sobra.
4. **Jerarquía con tipografía y espacio antes que con color.** El color de
   acento se reserva para la acción principal y el estado; repetir el
   mismo botón de color en cada fila es ruido.
5. **Movimiento con propósito.** Animaciones de 150–400 ms que confirman
   algo (se agregó al carrito, se abrió una hoja) o guían la vista; nunca
   en bucle salvo indicadores de estado vivos. Todo respeta
   `prefers-reduced-motion`.
6. **Mejora progresiva.** Todo funciona sin JS (formularios y enlaces
   reales); el JS solo lo hace más fluido. CSP: `script-src 'self'` — no
   hay JS inline, solo archivos en `public/assets/js/`.
7. **Contraste AA siempre.** 4.5:1 para texto normal. El color del negocio
   puede ser cualquiera (amarillo, azul, rosado): el texto encima usa
   `color_texto_sobre()` y los acentos de texto usan `--marca-texto`
   (la marca oscurecida), nunca la marca cruda sobre papel.

## Proceso de trabajo (siempre en este orden)

1. **Mira antes de tocar.** Levanta el servidor y saca capturas reales del
   estado actual:
   ```bash
   service mariadb start; nohup php -S localhost:8000 serve.php >/dev/null 2>&1 &
   node .claude/skills/diseno-veci/scripts/capturas.js \
     --rutas /t/donamaria,/t/salonbonita --anchos 390,1280 --out <scratchpad>/antes
   ```
   Lee las imágenes. Escribe un diagnóstico corto: qué se ve genérico, qué
   jerarquía falla, qué está roto (errores de consola, scroll horizontal,
   404 que el script reporta).
2. **Decide el detalle propio y la jerarquía** de la pantalla antes de
   escribir CSS. Revisa si ya existe un componente en `references/` que
   resuelva lo mismo: reutilízalo o mejóralo en su sitio, no lo dupliques.
3. **Implementa** siguiendo las convenciones del repo:
   - CSS en `public/assets/css/app.css`, en la sección de su ruta, con
     clases `pq-*` y un comentario corto que explique el **porqué** de las
     decisiones no obvias (así está escrito todo el archivo).
   - Tokens, no valores sueltos: colores con `var(--...)`, espaciados de la
     escala de 4 px. Si hace falta un valor nuevo, conviértelo en token.
   - Estilos inline en vistas solo para valores que vienen de la base de
     datos (p. ej. `style="--marca: ..."`). Nada de `style="font-size..."`
     nuevo en vistas.
   - **Antes de crear una clase nueva, búscala** (`grep -n "\.pq-nombre\b"
     public/assets/css/app.css`). El archivo tiene más de 2.500 líneas y
     nombres genéricos como `.pq-paso` ya existían para otra pantalla: una
     colisión mezcla estilos sin avisar. Si existe, usa otro nombre.
   - Para ajustar un componente compartido solo en la tienda, sobrescríbelo
     bajo `.pq-shell-tienda` en vez de cambiar la regla base (el panel la usa).
   - Partes repetidas entre vistas → parcial PHP (`src/Views/tienda/_*.php`).
   - Escapar todo con `e()`; precios con `pesos()`.
   - No romper los ganchos de JS: `#pq-barra-carrito`,
     `#pq-barra-carrito-resumen`, `[data-carrito-form]`,
     `#pq-cantidad-{id}`, `#pq-subtotal-{id}` (ver `interacciones.js`).
4. **Verifica con capturas** en 360, 390, 768 y 1280 px (y modo oscuro si la
   pantalla lo soporta). El script marca ✗ si hay errores de consola,
   peticiones fallidas o scroll horizontal: resuélvelos todos. Mira cada
   imagen de verdad — alineaciones, cortes de texto, solapes con la barra
   fija inferior, estados vacíos, agotados, nombres largos.
5. **Revisa la lista de control** (abajo) y prueba el flujo real con
   datos (agregar al carrito, reservar) sin JS y con JS.
6. **Muestra antes/después** al usuario con las capturas, explica las
   decisiones en lenguaje de diseño (no de código) y haz commit.

## Lista de control antes de dar algo por terminado

- [ ] 360 px sin scroll horizontal ni textos cortados; nombres largos
      (40+ caracteres) y precios de 7 cifras se ven bien.
- [ ] Áreas táctiles ≥ 44 px; foco visible (`:focus-visible`) en todo lo
      interactivo; `aria-label` en botones de solo icono.
- [ ] Contraste AA con el color de negocio más claro (amarillo #F2B632) y
      el más oscuro.
- [ ] Estados: vacío, agotado, cerrado, cargando, error — diseñados, no
      olvidados.
- [ ] `prefers-reduced-motion` desactiva todo movimiento no esencial.
- [ ] Funciona sin JS; con JS no hay errores en consola.
- [ ] Nada tapado por la barra fija inferior (carrito/CTA) ni por el
      `safe-area-inset-bottom` del iPhone.
- [ ] Cero colores o tamaños sueltos nuevos fuera de los tokens.
- [ ] `php -l` limpio en las vistas tocadas.
- [ ] Flujo completo probado en navegador (no solo capturas): agregar,
      sumar/restar/quitar, enviar el formulario y llegar a la confirmación.
      En scripts de prueba, las tarjetas de opción se tocan por su `<label>`:
      el radio está oculto y no recibe clics.

## Señales de diseño genérico (evitar)

- Tarjetas idénticas en rejilla con sombra para todo.
- Un botón de color saturado repetido en cada fila.
- Degradados de relleno que fingen imágenes.
- Texto en mayúsculas espaciadas + monoespaciada para todo dato "técnico"
  en la tienda (la mono es del panel; en la tienda se ve frío).
- Iconos de distintas familias o grosores en la misma pantalla.
- Espaciados a ojo (13 px, 22 px) en vez de la escala.
- Animaciones de entrada en todo, en bucle o de más de 400 ms.

## Archivos de referencia

- `references/tokens.md` — paletas, escala tipográfica y de espaciado,
  radios, sombras, movimiento, z-index. Léelo antes de escribir CSS.
- `references/tienda.md` — componentes de la tienda pública (cabecera con
  toldo, navegación de categorías, carta con puntos guía, fila de servicio,
  barra del carrito, tarjetas de información) con su anatomía y estados.
- `references/panel.md` — el panel del dueño completo: el puente con la
  tienda (insignia, cenefa, escaparate, comanda unificada), shell, kanban,
  agenda, servicios, horario, copiloto, formularios e interruptores.
- `references/onboarding.md` — registro y alta del negocio: pasos con
  nombre, lectura de la foto, catálogo en un solo formulario, elegir el
  color del toldo con vista previa y la tienda que se abre al final.
- `scripts/capturas.js` — capturas por ruta y ancho, con detección de
  errores; `--cookies` para pantallas con sesión del panel.

Cuando se rediseñe una zona nueva (panel, onboarding), agrega su
`references/<zona>.md` con los componentes que salgan de ese trabajo, para
que la siguiente pantalla los reutilice.
