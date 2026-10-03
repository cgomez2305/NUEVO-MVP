# Plan: features por línea de negocio (desde 2026-10-03)

Pedido del dueño: features reales por línea (belleza, salud, técnicos a
domicilio, tiendas) pensadas para Colombia, y una respuesta a "los servicios
no son exactos, pasan imprevistos". Orden acordado: imprevistos primero
(sirve a todas las líneas), luego belleza, visitas (técnicos), tiendas y salud.
La fase 4 (tiendas) la construye un subagente en un worktree aislado
(migraciones 20-29, base `veci_tiendas`, puerto 8010) y se fusiona revisada.

Migraciones: 13 imprevistos, 14 profesionales, 15 ajustes de "no vino", 16-17 visitas, 18 salud (repo principal); 20-29 fase 4 (subagente).

## Fase 1 · Imprevistos (todas las líneas con citas) — migración 13
- Precio `fijo` / `desde` / `rango` por servicio (copiado en la cita) y
  **cobrado real** (`citas.precio_final`) al terminar. El valor de una cita en
  todas las sumas es `GREATEST(COALESCE(precio_final, precio) - descuento, 0)`
  (`Cita::sqlValor()`), y no cuentan `cancelada` ni `no_asistio`.
- Estados nuevos: `en_curso` (Empezar) y `no_asistio` (No vino).
- **Duración real**: `iniciada_en`/`terminada_en`; con ≥ 5 citas medidas,
  el panel de servicios sugiere ajustar la duración.
- **Colchón** entre citas (`sedes.colchon_min`) en la disponibilidad.
- **Voy retrasado** (negocio): marca las citas que faltan hoy y las avisa
  (API o wa.me); el cliente ve el retraso en su página y puede mover la cita.
- **Se me complicó el día**: reprogramación en bloque con motivo; bloquea el
  día (opcional) y cada cliente elige otra hora desde su enlace.
- **Llego tarde** (cliente) con la tolerancia del negocio (`sedes.tolerancia_min`).
- **No vino**: política del negocio para el anticipo (`se_pierde` o
  `se_abona` → cupón personal por el valor; con bono, la sesión vuelve solo
  si la política es abonar).
- **Ajuste de cotización**: el negocio propone un nuevo valor con motivo y el
  cliente lo aprueba o no desde su enlace.
- **Garantía / retoque**: bono de 1 sesión a $0 ligado a la cita original
  (`bonos.garantia_de`), con vencimiento.

## Fase 2 · Belleza — migración 14
Profesional con foto, especialidad y portafolio; servicios por profesional
(precio/duración propios); horario por profesional; adicionales al reservar;
fila virtual para clientes sin cita; liquidación de comisiones.

## Fase 3 · Visitas (técnicos a domicilio) — migraciones 16-17 ✓
Decisión: no es un tercer `tipo_negocio` (habría partido 66 condiciones y
dejado a los técnicos sin agenda, equipo, imprevistos, anticipos, garantía)
sino `negocios.modalidad = 'domicilio'` sobre reservas. Hecho: franja de
llegada (mañana/tarde) con técnico asignado al primero libre, dirección +
zona con transporte + qué pasa + fotos privadas (storage/visitas/), "voy en
camino" con hora y foto del técnico, cotización por ítems aprobable (pasa a
ser el valor de la visita) con garantía y anticipo de materiales, evidencia
antes/después, zonas de cobertura, "toca repetir" con permiso específico.

## Fase 4 · Tiendas (subagente) — migraciones 20-29
Mostrador con escáner (lector o cámara), código de barras/costo/peso en
productos, compras a proveedor, catálogo compartido de códigos, margen real,
fiado con recordatorio que respeta la Ley 2300 de 2023, cierre de caja.

## Fase 5 · Salud — migración 18 ✓
`negocios.rubro = 'salud'` sobre reservas. Plan de tratamiento por fases que
el paciente aprueba desde su enlace y paga con abonos (las citas del plan
valen $0; la plata entra por los abonos, que van a la caja); avance por
sesiones; recordatorio de saldo con la Ley 2300; controles recurrentes con
"Toca repetir"; motivo de consulta opcional con autorización de datos
sensibles. Sin historia clínica (Resolución 1995 de 1999).

## Decisiones pendientes del dueño
- ¿Plan propio más barato para tiendas?
- Diferencias sitio↔app de planes (anual, IA, exportar, pedidos+reservas).
