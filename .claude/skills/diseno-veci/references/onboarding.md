# Onboarding: del registro a "¡Ya abriste!"

Vistas: `src/Views/onboarding/*.php` (layout `layouts/onboarding.php`),
login/registro/recuperar en `src/Views/auth/` (layout `layouts/auth.php`).
Controlador: `OnboardingController`. CSS: bloque "ONBOARDING v2" de
`app.css`. Ruta visual del panel (tiquete y papel) hasta el último paso,
donde entra la marca del negocio.

## Narrativa (el detalle propio del flujo)

El dueño ve su tienda armarse: foto → Veci la lee → revisa la carta →
**elige el color de su toldo con vista previa** → **la tienda real se abre**
(el toldo se despliega con la misma animación que ve el cliente). No hay
otro adorno: cada paso es sobrio y el clímax es el final.

## Pasos

| Pedidos | Reservas | Vista |
|---|---|---|
| 1 Foto | 1 Foto | `foto.php` |
| 2 Catálogo | 2 Servicios | `productos.php` / `servicios.php` |
| — | 3 Horario | `horario.php` (usa `panel/_semana.php`) |
| 3 Abrir | 4 Abrir | `pago.php` (color + Bre-B) → `publicada.php` |

`_pasos.php` es la cabecera de todos: logo o "Atrás", "Paso X de Y" y los
pasos **con nombre** (`.pq-onb-pasos`). Incluye `$pasoActual` y opcional
`$volverUrl`; muestra los flashes `ok` y `aviso`.

## Componentes

- **Leer la foto** (`.pq-onb-escaner`): la foto con una línea que la
  recorre mientras el formulario está enviándose (`.pq-enviando`, la pone
  `data-enviando` en interacciones.js). Con reduced-motion, la línea queda
  quieta en el medio.
- **Revisar el catálogo** (`.pq-onb-lista` / `.pq-onb-item`): UN formulario
  vacío `#pq-catalogo` y todos los campos de las filas lo apuntan con
  `form="pq-catalogo"`, con nombres `items[ID][campo]`. Así "Guardar y
  continuar" guarda todo (`POST /panel/onboarding/catalogo`) y los menús ⋮
  de cada fila pueden tener sus propios formularios sin anidarlos.
  `data-guardia-cambios="pq-catalogo"` en el `<main>` pregunta antes de que
  un ⋮ o "agregar" recargue la página con cambios sin guardar.
- **Agregar un producto que faltó**: manda `disponible=1` y `visible=1`
  ocultos; `crearProducto()` los lee y sin ellos el producto nacía agotado
  y oculto (pasó: bug corregido).
- **Horario**: `panel/_semana.php` con `$sugerirSiVacio = true` propone
  lunes a sábado de 8 a 6 si no hay nada guardado. El controlador exige al
  menos un día (antes volvía a la misma pantalla sin explicar).
- **Color del toldo** (`.pq-onb-colores` / `.pq-onb-color`): paleta
  cerrada de `paleta_marca()` (Ají, Mango, Mostaza, Hoja de plátano,
  Turquesa, Añil, Mora, Guayaba, Café). La vista previa es un
  `.pq-escaparate` con `data-vista-marca`; al elegir, JS le cambia
  `--marca`/`--marca-sobre` (cada radio trae `data-sobre`). El contenedor
  redeclara `--marca-claro/-suave/-texto` porque las variables derivadas
  se calculan donde se declaran, no donde se usan.
  `Negocio::actualizarColor()` solo acepta colores de la paleta. También se
  cambia después en Sedes → Editar datos.
- **¡Ya abriste!** (`.pq-onb-abierta`): `.pq-toldo` completo (animado) +
  `.pq-letrero` con insignia y nombre en Bricolage sobre `--papel`, enlace
  en recuadro punteado del color de la marca, WhatsApp / ver tienda / panel
  y lo que quedó listo.

## Reglas aprendidas aquí

- Vista previa de una foto elegida: `URL.createObjectURL` da `blob:`; la
  CSP necesita `img-src 'self' data: blob:` o la miniatura sale vacía.
- Tamaños de archivo: KB por debajo de 1 MB ("0.0 MB" no dice nada).
- Formularios que tardan (IA, publicar): `data-enviando="Texto…"`.
- El logo de la app es `logo-veci-lockup-transparente.png`: el PNG
  original trae fondo blanco y pintaba un recuadro sobre el papel crema
  (`mix-blend-mode` no sirve contra el fondo raíz de la página).
