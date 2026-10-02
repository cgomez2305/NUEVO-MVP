# Panel interno (admin) · equipo de Veci

Ruta 2 como el panel del dueño (papel `--tiquete`, azul `--sello`, la
misma tipografía). Layout: `src/Views/layouts/admin.php`. CSS: bloque
"PANEL INTERNO (admin)" al final de `app.css`. Vistas en `src/Views/admin/`.

## Lo propio (y nada más)

- **Sello "Interno"** (`.pq-admin-sello`) en la cabecera: rótulo de caucho
  torcido, borde doble azul. Dice "estás en el panel de la casa" sin
  cambiar de sistema visual.
- **Consignación** (`admin/_consignacion.php`, `.pq-consignacion`): cada
  pago de plan por confirmar es el desprendible de una consignación.
  - Arriba, impreso: negocio, plan, cuándo se pidió y lo que **debe llegar**.
  - Un corte perforado con muescas (`.pq-consignacion-corte`).
  - Abajo, el campo "¿Cuánto llegó a la cuenta de Veci?".
  - Igual → sello verde "Coincide" (`.pq-consignacion-sello`, animado
    con `pqSello`) y "Confirmar y activar plan" se habilita. Distinto →
    "Faltan/Sobran $X". JS en `interacciones.js` (`form[data-consignacion]`).
  - El servidor (`AdminController::confirmarPago`) verifica lo mismo, así
    que sin JS nunca se activa un plan con otro monto.
  - Se usa en la lista (con negocio, `volver=/admin`) y en la ficha.

## Reutilizado del panel

- `.pq-caja-dia` para las cifras: negocios, con tienda abierta, pagan plan,
  nuevos en 7 días.
- `.pq-segmentos` como filtros (`Negocio::FILTROS_ADMIN`, con conteo de
  `Negocio::resumenAdmin()`). En el admin el sangrado es de 16 px.
- `.pq-letrero-insignia-chica` con el `color_marca` de **cada** negocio
  (inline `--marca`/`--marca-sobre` en la insignia): el equipo reconoce el
  negocio por el mismo color que ve su cliente.
- `.pq-chip` solo para el estado que pide atención (Suspendido / Pago por
  confirmar / Sin abrir), no para todo.

## Ficha del negocio

- Orden: enlace de contraseña recién generado (si hay) → pago por
  confirmar → plan e historial → sedes (abierta con enlace, o en qué paso
  del alta se quedó) → quiénes entran (rol legible, WhatsApp con espacios).
- El enlace de recuperación se manda con **"Mandar por WhatsApp"** directo
  al número de esa persona (`wa.me/57…`) o se copia; se muestra una vez.
- **Zona delicada** al final (`.pq-admin-delicado`): suspender ya no es el
  primer botón de la pantalla, y explica cuándo usarlo (fraude o abuso,
  no falta de pago: eso ya baja a Gratis solo).
- Fechas relativas con `dias_desde()` + `hace_dias()` (días de calendario:
  ayer a las 11 p. m. es "ayer", no "hoy").

## Escritorio

`.pq-admin-hoja`: la misma hoja flotante con sombra del panel (máx.
1120 px); contenido a 760 px; las consignaciones en dos columnas desde
700 px.
