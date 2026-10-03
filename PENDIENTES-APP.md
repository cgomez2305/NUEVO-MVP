# Pendientes de la app para conectar con el sitio web

Origen: revisión del sitio (`docs/`, rama `work/marketing-web`) contra `PRODUCT.md` del 2026-10-03.
Al terminar cada punto, actualizar `PRODUCT.md` → "Capabilities and Constraints" y subirlo a la rama de la app.

## 1. Leer y guardar los parámetros del registro (prioridad alta)

El sitio ya envía estos parámetros a `/registro`; hoy la app los ignora.

- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`: guardarlos en el negocio al crearlo (columnas nuevas o tabla de atribución) con la fecha. Recortar a 80 caracteres, guardar como texto plano. Deben sobrevivir si el formulario vuelve con error (igual que `ref`).
- `plan` (`gratis`, `barrio`, `pro`) y `ciclo=anual`: el negocio sigue naciendo en Gratis. Al terminar el registro, si llegó con plan pago, llevarlo a `/panel/plan` con ese plan y ciclo preseleccionados. Nunca cobrar automáticamente.
- `modo` (`pedidos`, `reservas`): dejar marcada esa opción en el formulario de registro.
- Seguridad: aceptar solo valores de una lista blanca; cualquier otro valor se ignora.

## 2. Códigos de oferta para los planes de Veci

Necesario para que el sitio vuelva a mostrar la oferta `VECICHAT30` (30% del primer mes de Barrio o Pro, pago mensual). Hoy no existe. Modelo: los `cupones` de negocio que ya existen (uso con fila bloqueada, límite por IP).

- Leer `oferta` en `/registro` y guardarlo con el negocio (misma regla que `ref`).
- Validar en el servidor al pagar el primer plan, no al registrarse.
- Un uso por negocio. Sin OTP, usar al menos el número de WhatsApp y el documento (cédula o NIT), guardados con hash.
- Solo negocios nuevos: rechazar si ese número o documento ya tuvo un negocio en Veci, aunque esté cancelado.
- Solo el primer mes de Barrio o Pro mensual. No acumulable con el pago anual ni con otros códigos.
- Fecha de fin y cupo total configurables desde el admin (p. ej. 300 usos).
- Máximo 5 intentos por hora por IP y por cuenta.
- Pago por Bre-B confirmado a mano: el admin debe ver el monto esperado ya con el descuento.
- Registro de canjes: código, fecha, plan, y si pagó el segundo mes.

## 3. Más adelante: "Sube tu foto sin cuenta"

Endpoint público para que el sitio muestre la lectura del menú con IA sin registro.

- CORS solo para `https://tuveci.co`.
- Límite por IP (p. ej. 2 al día) y de tamaño de imagen.
- No guardar la foto.
- Tope diario de gasto de la API.
- Avisar la URL del endpoint: el sitio tiene que permitirla en su Content-Security-Policy (`connect-src`).
