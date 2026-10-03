# Sitio público (`docs/`: landing, precios, industrias, blog)

HTML estático con una sola hoja, `docs/assets/css/site.css`, y su propio
JS (`docs/assets/js/tema.js` para el modo oscuro, `demos.js` para los demos).
No usa `app.css`: comparte con la app la **marca**, no el código.

## Tipografía (la misma de la app)

- Tokens en `:root`: `--fuente-titulo` (Bricolage Grotesque) y
  `--fuente-texto` (Inter Tight). Alojadas en `docs/assets/fonts/` (copias
  de `public/assets/fonts/`) con `@font-face` al inicio de `site.css` y
  `<link rel="preload">` en cada página. Nada de Google Fonts: más rápido en
  datos móviles, sin pasarle la IP del visitante a un tercero, y funciona
  aunque Google no cargue.
- Títulos (`h1`, `h2`, `.serif`, avatares con inicial): Bricolage 700 con
  `letter-spacing: -.025em`. Ya no hay serif.
- Lo que antes era JetBrains Mono (`.mono`, insignias, números de paso,
  cifras): Inter Tight con `font-variant-numeric: tabular-nums`. La mono es
  fría para un sitio que le habla al dueño de un negocio de barrio.
- Página nueva: copia el `<head>` de otra (los dos `preload`) y no declares
  `font-family` con nombres sueltos: siempre `var(--fuente-…)`.

## Logo

`assets/img/logo-veci-lockup-transparente.png` (claro) y
`logo-veci-lockup-blanco.png` (oscuro, lo cambia `tema.js` con
`data-logo-claro`/`data-logo-oscuro`). El PNG viejo con fondo crema dejaba
un recuadro visible sobre la cápsula blanca del menú.

## Detalles que no hay que romper

- `section { overflow-x: clip }`: las filas `.reveal-der`/`.reveal-izq`
  entran deslizándose desde el costado; sin el clip, en el celular la
  página quedaba 16 px más ancha y el navegador la alejaba. `clip` y no
  `hidden` para no crear un contenedor de scroll.
- El subrayado del `<em>` del hero va en cada `.palabra` (son inline-block
  por la animación de entrada); en el `<em>` solo se pintaba en los
  espacios.
- Honestidad: el sitio promete solo lo que la app hace. Al cambiar algo
  de planes, referidos o límites en la app, revisa `precios.html`, la
  sección de precios de `index.html` y `referidos.html`.

## Verificar

```bash
cd docs && php -S localhost:8001   # en otra terminal
node .claude/skills/diseno-veci/scripts/capturas.js --base http://localhost:8001 \
  --rutas /index.html,/precios.html,/blog/index.html --anchos 390,1280 --out <scratchpad>/sitio
```
Las secciones con `.reveal*` salen en blanco en `--completa` (se revelan al
hacer scroll): para revisarlas, captura el elemento después de
`scrollIntoView`.
