---
name: Veci
description: El mostrador digital por WhatsApp para negocios de barrio colombianos
colors:
  sello: "#3B4CCA"
  sello-claro: "#7A85DB"
  caja: "#16A36A"
  aji: "#E8452C"
  mostaza: "#F2B632"
  carbon: "#202124"
  tiquete: "#FFFDF8"
  recibo: "#E9E4D8"
  gris: "#54565f"
  gris-suave: "#8a8d97"
typography:
  display:
    fontFamily: "Bricolage Grotesque, sans-serif"
    fontSize: "clamp(2.25rem, 1.2rem + 3.8vw, 4.25rem)"
    fontWeight: 700
    lineHeight: 1.05
    letterSpacing: "-0.015em"
  headline:
    fontFamily: "Bricolage Grotesque, sans-serif"
    fontSize: "clamp(1.875rem, 1.4rem + 2.4vw, 2.875rem)"
    fontWeight: 700
    lineHeight: 1.08
    letterSpacing: "-0.015em"
  body:
    fontFamily: "Inter Tight, sans-serif"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "Inter Tight, sans-serif"
    fontSize: "12px"
    fontWeight: 600
    letterSpacing: "0.14em"
rounded:
  badge: "14px"
  tarjeta: "20px"
  pill: "999px"
spacing:
  gutter-movil: "20px"
  gutter-escritorio: "48px"
  gap-tarjetas: "18px"
components:
  button-primary:
    backgroundColor: "{colors.sello}"
    textColor: "{colors.tiquete}"
    rounded: "{rounded.pill}"
    padding: "14px 24px"
  button-primary-hover:
    backgroundColor: "{colors.sello}"
    textColor: "{colors.tiquete}"
  button-success:
    backgroundColor: "{colors.caja}"
    textColor: "{colors.tiquete}"
    rounded: "{rounded.pill}"
    padding: "14px 24px"
  card:
    backgroundColor: "superficie (#FFFFFF claro / #191A20 oscuro)"
    rounded: "{rounded.tarjeta}"
---

# Design System: Veci

## Overview

**Creative North Star: "El Mostrador del Barrio"**

Veci se diseña como el mostrador de la tienda de la esquina, no como el panel de un SaaS corporativo: directo, sin intermediarios, cercano al dueño que atiende con el celular en la mano. Cada pantalla debería sentirse como si alguien de confianza te estuviera mostrando cómo funciona, no como un folleto de ventas genérico. La tipografía (Bricolage Grotesque, bold y con carácter propio) le da peso y seguridad a los títulos; el cuerpo en Inter Tight mantiene todo legible y funcional. El sistema es el mismo que usa la app del producto (panel del dueño, tienda pública): la web de marketing y el producto real deben sentirse como una sola cosa, nunca como dos marcas distintas.

Rechazos confirmados: nada de gradientes decorativos que simulan fotos, nada de iconografía genérica de stock, nada de lenguaje de "MVP en construcción" — lo que no existe en el producto no se promete en la web.

**Key Characteristics:**
- Un solo acento de color (`--sello`, azul) usado con moderación; el resto del color vive en estados semánticos (verde éxito, ámbar, rojo de alerta).
- Tipografía con carácter (Bricolage Grotesque en pesos 700) en vez de un sans-serif neutro genérico.
- Profundidad que aparece como respuesta a la interacción (hover/tap), no de entrada — en reposo el sistema es casi plano.
- Pills y esquinas generosas (999px en botones y badges, 20px en tarjetas) — nada de esquinas vivas.

## Colors

La paleta es la misma que usa la app del producto: un azul de marca, un verde de éxito/dinero, y un fondo cálido tipo papel de recibo — nunca gris frío de SaaS genérico.

### Primary
- **Azul Sello** (`#3B4CCA`): acento de marca Veci — enlaces activos, botón principal, foco. Se usa en ≤10% de cualquier pantalla; su escasez es la señal de que algo es la acción principal.

### Secondary
- **Verde Caja** (`#16A36A`): dinero recibido, confirmaciones, estado "disponible". El color de "la venta ya está hecha".

### Tertiary
- **Ají** (`#E8452C`) y **Mostaza** (`#F2B632`): acentos de producto/categoría (usados en íconos de features, nunca como color de acción).

### Neutral
- **Tiquete** (`#FFFDF8`): fondo base en modo claro — papel cálido, no blanco de hospital.
- **Carbón** (`#202124`): texto principal.
- **Gris** (`#54565f`) / **Gris suave** (`#8a8d97`): texto secundario y terciario.
- **Recibo** (`#E9E4D8`): bordes suaves, divisores.

### Named Rules
**La Regla del Acento Escaso.** El azul Sello se reserva para la acción principal y el estado activo. Repetir el mismo botón azul en cada fila de una pantalla es ruido, no jerarquía.

## Typography

**Display Font:** Bricolage Grotesque (con sans-serif de respaldo)
**Body Font:** Inter Tight (con sans-serif de respaldo)

**Character:** Un grotesco con carácter propio para títulos — curvas distintivas, pensado para pesos 600–800, nunca 400 (a ese peso pierde su personalidad y se ve genérico) — emparejado con un sans-serif funcional y neutro para todo lo demás, incluidas las cifras (`font-variant-numeric: tabular-nums` en vez de una fuente monoespaciada).

### Hierarchy
- **Display** (700, clamp(36px, …, 68px), line-height 1.05): headline del hero, una por página.
- **Headline** (700, clamp(30px, …, 46px), line-height 1.08): `h2` de cada sección.
- **Title** (700, clamp(22px, 2.6vw, 28px)): subtítulos de paso/feature.
- **Body** (400, 15–17px, line-height 1.55): párrafos; ancho de línea cómodo, nunca más de ~70ch.
- **Label** (600, 12px, letter-spacing 0.14em, mayúsculas): eyebrows ("CÓMO FUNCIONA"), etiquetas de categoría en tablas.

### Named Rules
**La Regla de los Pesos Vivos.** Bricolage Grotesque nunca se usa en peso 400 — mínimo 700 en cualquier headline, o se pierde el carácter que justifica usarlo.

## Layout

Contenedor centrado (`max-width: 1240px`, `1400px` en los anchos de ecran completos) con gutter fluido (`clamp(20px, 4vw, 48px)`). Tarjetas en grid de 3 o 4 columnas en escritorio, colapsando a 2 y luego a 1 columna en móvil (quiebres en 860px y 560px). Todo se diseña primero para 390px de ancho (el dueño opera desde el celular) y escritorio es la expansión, nunca al revés.

## Elevation & Depth

Sistema mayormente plano en reposo — las tarjetas usan una sombra muy suave de dos capas (difusa + de contacto) que apenas se nota, y bordes de 1px para separar superficies. **La profundidad es una respuesta, no un estado de reposo**: al hacer hover o tap, una tarjeta o botón gana sombra, se eleva (`translateY(-2px)` a `-6px` según el componente) y a veces escala ligeramente (`scale(1.01–1.03)`). Esta pasada de mejora debe acentuar esa respuesta — más presencia en el momento de interactuar — sin añadir sombra permanente de entrada.

### Shadow Vocabulary
- **Suave** (`0 1px 2px rgba(32,33,36,.04), 0 10px 28px -12px rgba(32,33,36,.10)`): reposo de tarjetas.
- **Media** (`0 4px 10px rgba(32,33,36,.05), 0 20px 40px -16px rgba(32,33,36,.16)`): hover de tarjetas y elementos destacados.

### Named Rules
**La Regla de la Profundidad Ganada.** Ninguna superficie nace elevada; se eleva porque el usuario la tocó o la enfocó.

## Shapes

Esquinas generosas y consistentes: `999px` (pill) en todo botón, badge y chip — nunca una esquina recta en un control interactivo. Tarjetas a `20px`, íconos contenedores a `14px`. Sin bordes duros de color; los bordes son siempre `--borde` o `--recibo`, sutiles.

## Components

### Buttons
- **Shape:** pill completo (`border-radius: 999px`).
- **Primary (`.btn-sello`):** fondo `--sello`, texto `--tiquete`; hover sube con `translateY(-2px)` y gana sombra de color (`0 10px 24px -6px rgba(59,76,202,.55)`).
- **Success (`.btn-caja`):** mismo patrón, verde `--caja`.
- **Ghost:** transparente, borde 1.5px, mismo hover de elevación.
- Carácter objetivo de esta pasada: **táctil y seguro de sí mismo** — la respuesta al tap/hover debe sentirse inmediata y con algo de peso, no un cambio de opacidad tibio.

### Cards (`.feature-card`, `.plan`, `.dolor-card`)
- **Corner Style:** 20px.
- **Background:** superficie (blanco en claro, `#191A20` en oscuro).
- **Shadow Strategy:** suave en reposo → media + elevación en hover (ver Elevation & Depth).
- **Border:** 1px `--borde`, casi invisible hasta que la tarjeta se activa.

### Badges / Chips
- **Style:** pill, fondo de tinte semántico (`--tinte-sello`, `--tinte-caja`, etc.) al 8–16% de opacidad, texto del color sólido correspondiente.

### Navigation
- Barra flotante con fondo `--superficie`, mega-menú desplegable por sección; toggle de tema con rotación sutil al hover (`rotate(-12deg) scale(1.06)`) — ejemplo del tipo de micro-interacción con personalidad que el sistema ya practica y que esta pasada debe extender a más controles.

## Do's and Don'ts

### Do:
- **Do** reservar el azul Sello para una sola acción por pantalla.
- **Do** usar Bricolage Grotesque en 700 o más para cualquier título; nunca en 400.
- **Do** dejar que la profundidad (sombra, elevación, escala) aparezca al interactuar, no de entrada.
- **Do** diseñar primero en 390px de ancho.

### Don't:
- **Don't** mezclar otra familia tipográfica con Bricolage Grotesque / Inter Tight — ya se unificó con la app, no reintroducir fuentes propias de marketing.
- **Don't** prometer en copy una función que no está construida — marcarla "Próximamente" en vez de "Disponible".
- **Don't** usar sombra fuerte o elevación permanente en reposo; la quietud es parte del sistema.
- **Don't** inventar prueba social (testimonios, cifras de clientes) — hoy no existe ninguna real.
