# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Dueños de negocios de barrio en Colombia que hoy venden o agendan por WhatsApp de forma manual. Cuatro líneas de negocio con el mismo peso, ninguna prioritaria sobre las demás: tiendas/minimarkets y restaurantes (modo pedidos), peluquerías y barberías, entrenadores personales, y consultorios odontológicos (modo reservas). Su situación actual: anotan pedidos o citas a mano por chat, confirman horarios uno por uno, pierden ventas por cruces de agenda o pedidos mal anotados, y no tienen forma sistemática de saber qué cliente dejó de comprar o agendar para reactivarlo.

## Product Purpose

Veci le da a ese negocio una tienda o agenda pública propia por WhatsApp, sin pagar comisión por venta, para vender/agendar de forma ordenada y recuperar clientes que dejaron de volver. Éxito = el negocio publica su catálogo o agenda en minutos (no días), deja de perder pedidos/citas por desorganización, y usa el copiloto de recompra para reactivar clientes inactivos.

## Positioning

0% de comisión siempre — plan fijo mensual, nunca un porcentaje de las ventas — frente a Rappi/DiDi (25–30% por pedido). Onboarding con foto + IA real (API de Claude/Anthropic) que arma el catálogo o los servicios desde una foto, con revisión humana antes de publicar. Un mismo producto cubre pedidos y reservas (dos modos). Los colaboradores van en todos los planes; varias sedes, solo en Pro (ver Capabilities and Constraints).

El espacio "vender por WhatsApp sin comisión" ya tiene jugadores establecidos en Colombia (Comerciaya, Whataform, ReservaSimple en reservas). La diferenciación defendible es la ejecución — IA de onboarding real, copiloto de recompra, ambos modos en un mismo producto — no la exclusividad de la categoría. Evitar el mensaje "nadie más hace esto".

## Operating Context

El dueño opera principalmente desde el celular, en modo "atendiendo clientes" (4G, gama media), no desde escritorio. Flujo real: foto del menú o servicios → la IA arma el catálogo → el dueño revisa y publica → el cliente final pide o agenda por el enlace público → el pedido/cita llega al WhatsApp del dueño → cobro por Bre-B (llave, menos de 20 segundos, sin comisión), Nequi (transferencia + comprobante) o efectivo contraentrega → cada mañana el copiloto revisa quién dejó de comprar o agendar y sugiere el mensaje de reactivación. El panel del dueño es la otra mitad del mismo producto (se construye en otra sesión/rama de este mismo repositorio).

## Capabilities and Constraints

Verificado contra el código de la app (rama `claude/new-session-mde7lj`, 2026-10-03), no contra lo que dice la web. Lo que no está construido dice **Próximamente** y no se puede prometer como disponible.

### Registro: qué parámetros lee y qué guarda

| Parámetro | ¿Lo lee `/registro`? | ¿Lo guarda? |
|---|---|---|
| `ref` (código de referido) | Sí. Se valida contra `negocios.codigo_referido` (solo negocios no suspendidos). | Sí: crea la fila en `referidos` al terminar el registro. |
| `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` | Sí. | Sí, en `negocio_origen` junto con la fecha. Van como texto plano: sin etiquetas, sin caracteres de control y recortados a 80 caracteres. |
| `plan` (`gratis`, `barrio`, `pro`) y `ciclo` (`mensual`, `anual`) | Sí, solo esos valores. | Sí (`plan_interes`, `ciclo_interes`). El negocio **siempre nace en Gratis**. Si llegó con Barrio o Pro, al terminar el registro va a `/panel/plan` con ese plan y ciclo ya marcados. **Nunca se cobra automáticamente.** |
| `modo` (`pedidos`, `reservas`) | Sí, solo esos valores. | Deja marcada esa opción en el formulario (el dueño puede cambiarla) y se guarda como `modo_interes`. |
| `oferta` (p. ej. `VECICHAT30`) | Sí: letras y números, de 3 a 20, en mayúsculas. | Sí (`oferta_codigo`). En "Tu plan" el campo del código aparece ya escrito. Se valida al pagar, no al registrarse (ver abajo). |

Todo lo anterior:
- se recuerda en la sesión desde que llega, así que sobrevive si el formulario vuelve con un error, igual que `ref`;
- solo acepta valores de una lista blanca: cualquier otro valor se ignora.

### Planes, límites y cobro

Ya existen y se aplican en el servidor (tabla `planes`; el plan vigente se calcula en cada petición, así que un plan vencido se comporta como Gratis aunque el cron no haya corrido):

| | Gratis | Barrio | Pro |
|---|---|---|---|
| Precio mensual / anual | $0 | $29.900 / $299.000 | $69.900 / $699.000 |
| Pedidos o citas al mes | 50 | Ilimitados | Ilimitados |
| Lecturas de menú con IA al mes | 3 | Ilimitadas | Ilimitadas |
| Copiloto de recompra | No | Sí | Sí |
| Historial en el panel | 30 días | 30 días | Completo |
| Sedes | 1 | 1 | 3 incluidas, +$19.900/mes cada sede extra |
| Sello "Hecho con Veci" en la tienda | Sí | No | No |

- Colaboradores con acceso por sede: en todos los planes.
- Con código de oferta, el primer pago mensual de Barrio o Pro lleva el descuento (ver abajo).
- Exportar a CSV (pedidos, citas, clientes): en todos los planes y con todo el historial, aunque el panel muestre solo 30 días. Es una decisión explícita ("tus datos son tuyos").
- 0% de comisión: confirmado, ningún cobro depende del valor de las ventas.
- Cobro del plan, dos vías:
  1. Transferencia Bre-B que un admin confirma escribiendo el monto recibido. Si no coincide exacto, no se activa nada.
  2. Wompi Web Checkout, si están configuradas las llaves: tarjeta, PSE, Nequi o Bancolombia. Solo lo activa el webhook firmado de Wompi o la consulta directa a su API, con monto exacto en COP.
- Ciclo mensual (30 días) o anual. Si renueva el mismo plan antes del vencimiento, el período nuevo se suma al final.
- Al vencer sin pago, `bin/revisar_planes.php` (cron diario) baja el negocio a Gratis. La tienda nunca se bloquea: solo vuelven los límites de Gratis.
- **Cobro recurrente automático** (débito o tarjeta guardada que se renueva sola): **Próximamente.** Hoy cada período se paga a mano: el dueño entra a "Tu plan" y paga.
- **Precio fundador:** **Próximamente.** Falta decidir si es para los primeros 100 negocios o hasta una fecha.

### Códigos de oferta de los planes de Veci: construido

`VECICHAT30` ya existe: 30% del primer mes de Barrio o Pro con pago mensual, cupo de 300 y sin fecha de fin (se pone desde el admin). El sitio ya puede volver a mostrarlo.

**Cómo se usa.** El dueño aplica el código en "Tu plan" y escribe la cédula o el NIT del titular. Las tarjetas muestran el precio del primer mes con descuento. **La validación definitiva se hace en el servidor al pedir el primer plan**, no al registrarse.

**Reglas:**
- **Un uso por negocio.** No se acumula con el pago anual (el anual va sin descuento y se avisa) ni con otro código.
- **Solo negocios nuevos.** Se rechaza si el WhatsApp del dueño o el documento ya tuvieron otro negocio en Veci, aunque esté borrado o suspendido, o si ya usaron una oferta.
  - El documento se normaliza: sin el dígito de verificación, solo números.
  - Ambos se guardan solo como HMAC-SHA256 con una clave del servidor (`app.clave_hash`), nunca en claro.
  - Con la API de WhatsApp configurada, además se confirma con un código que el WhatsApp de la cuenta es de quien usa la oferta (ver abajo).
- **Solo el primer mes de Barrio o Pro mensual:** se rechaza si el negocio ya pagó un plan. El descuento se calcula sobre el precio del plan; las sedes extra no tienen descuento.
- **Fecha de fin, cupo total y porcentaje** se cambian desde `/admin/ofertas`. Desde ahí también se pausa una oferta o se crea otra.
  - El cupo se cuenta con la oferta bloqueada, así que dos negocios a la vez no pasan del cupo (probado).
  - Una solicitud cancelada o rechazada libera su cupo.
- **Límite de intentos:** 5 por hora por IP y 5 por hora por negocio.
- **Bre-B confirmado a mano:** el pago guarda el monto ya con el descuento. El admin ve "Debe llegar: $20.930" y al lado "VECICHAT30: $29.900 − $8.970". Wompi cobra ese mismo monto.
- **Registro de canjes** en `/admin/ofertas`: código, fecha, negocio, plan, descuento, estado (por pagar, pagado o cancelado) y si el negocio pagó el segundo mes.

Los `cupones` de cada negocio para sus propios clientes son aparte y siguen igual.

**Código por WhatsApp (OTP) para usar una oferta: construido, se enciende al configurar la API de WhatsApp.**
- Con `whatsapp_api.token`, `phone_number_id` y una plantilla de autenticación aprobada por Meta (`whatsapp_api.plantilla_codigo`), aplicar un código de oferta manda un código de 6 dígitos al WhatsApp de la cuenta. La oferta solo queda aplicada al escribirlo bien.
- El código vence a los 10 minutos y admite 5 intentos; en la base solo queda su HMAC. Un código nuevo anula el anterior. Máximo 3 códigos por hora por cuenta y 6 por IP.
- Una vez confirmado, vale 30 minutos en esa sesión (quitar y volver a aplicar no pide otro). Al confirmar se vuelve a evaluar la oferta (si se pausó o se agotó mientras tanto, no se aplica).
- **Hoy, sin la API de WhatsApp configurada, no se pide el código** y la protección es la de arriba (huellas del WhatsApp y del documento). No se puede prometer "verificamos tu WhatsApp" hasta configurarla.

### Referidos, pasarela, Excel y webhooks

- **Referidos: construido.**
  - Cada negocio tiene su enlace `/registro?ref=CODIGO` en `/panel/referidos` (solo el dueño), con el estado de cada invitado.
  - Cuando el invitado paga su primer plan, quien invitó recibe 30 días: extiende su plan pago o, si está en Gratis, recibe Barrio esos días. Es una sola vez por invitado.
  - El premio nunca vale más que lo que pagó el invitado.
  - La página `referidos.html` de la web que dice "Próximamente" ya se puede actualizar.
- **Pasarela de tarjeta:**
  - **Para pagar el plan de Veci: construida** con Wompi. Queda activa solo si se configuran las llaves; sin ellas el cobro es Bre-B con confirmación manual.
  - **Para que los clientes del negocio paguen con tarjeta en la tienda: no existe.** El cliente final paga por Bre-B (llave), Nequi o efectivo/contraentrega. **Próximamente.**
- **Exportar a Excel:**
  - Se exporta en **CSV** (UTF-8 con BOM, abre bien en Excel con tildes), protegido contra inyección de fórmulas.
  - Pide la contraseña otra vez y queda en la bitácora.
  - Archivo `.xlsx` nativo: **Próximamente.** En la web hay que decir "exportar a Excel (CSV)", no "archivo de Excel".
- **Webhooks entrantes:** construidos.
  - `/webhooks/breb`: firma HMAC-SHA256. Marca pagado un pedido o anticipo solo si sigue pendiente y el monto cubre el total.
  - `/webhooks/wompi`: checksum con el secreto de eventos. Activa planes.
  - El de Bre-B necesita un PSP o participante real que lo llame; sin eso, el pago Bre-B lo confirma el negocio a mano.
- **Webhooks salientes** (avisar a un sistema del negocio cuando entra un pedido): **Próximamente.** Hoy los avisos son notificaciones push al celular del dueño y WhatsApp.
- **WhatsApp automático:**
  - Recordatorios y avisos de estado salen solos solo si se configura la API de WhatsApp Cloud (Meta).
  - Sin ella, el panel arma el mensaje y el dueño lo envía con un toque (enlace `wa.me`).
  - No prometer "WhatsApp automático" sin esa condición.

### Otras capacidades confirmadas

- **Modos de negocio:** pedidos (catálogo y carrito) y reservas (agenda, profesionales, duración, anticipo), con variantes de visitas a domicilio, consultorio de salud y servicios profesionales (abogados, contadores, arquitectos, consultores). Un negocio usa un modo, nunca ambos a la vez: es un límite de diseño.
- **Página pública con estilo según la línea de negocio (fachadas): construido para tres.**
  - **Barrio:** toldo de colores y carta. Comida, tiendas, salones y talleres; todos los negocios de pedidos.
  - **Consultorio:** membrete clínico limpio, paleta fría y "Agenda tu cita". Se asigna sola a los consultorios de salud.
  - **Despacho:** membrete de papelería con letra serif, consultas numeradas y "Agenda una consulta". Se asigna a "Servicios profesionales".
  - El dueño la cambia en "Editar sede" y puede agregar su **credencial** (p. ej. tarjeta profesional) y un párrafo **"Quiénes somos"**. **Veci no verifica la credencial:** la escribe el dueño.
  - Cambian la cabecera, la letra, los colores base y las palabras (turno / cita / consulta) en todas las pantallas de la tienda; los datos y el flujo de reserva son los mismos.
  - Estudio (belleza y bienestar), Oficio (técnicos a domicilio) y Marca (emprendimientos con catálogo de fotos): **Próximamente.**
  - Para un abogado o arquitecto, la agenda sigue siendo por citas de duración fija. Portafolio de proyectos y precio "a convenir": **Próximamente.**
- **Onboarding:** foto con IA real (API de Claude). Si no hay credenciales, usa un catálogo de ejemplo.
- **Copiloto de recompra:**
  - Segmenta clientes en inactivo, VIP, nuevo y recurrente.
  - Sugiere el mensaje y "llena huecos" de la agenda.
  - Calcula el **dinero recuperado** con regla explicable: primera compra o reserva dentro de 14 días después del mensaje, contada una sola vez.
- **Tiendas:** mostrador con escáner, fiado, compras, inventario y venta por peso.
- **Más funciones:** fidelidad con sellos, cupones, bonos de sesiones, reseñas, fila virtual, cierre de caja, PWA con notificaciones push e inicio "Hoy".

### Conexión con el sitio

- **Reporte de campañas (atribución): construido** en el panel interno, `/admin/origenes` ("Campañas").
  - Embudo del período (7, 30, 90 días o desde siempre): se registraron, publicaron su tienda, pagaron un plan, usaron un código de oferta.
  - Una fila por fuente / medio / campaña (`utm_source`, `utm_medium`, `utm_campaign`) con esas mismas cifras y el plan que miraban; los que llegaron sin utm salen juntos como "Sin campaña".
  - Cuenta **negocios registrados**, no visitas ni clics: lo de antes del registro lo mide el sitio.
  - También muestra cuántas lecturas y tokens gastó la demo de la foto en el período.
- **"Sube tu foto sin cuenta": construido.** `POST https://<dominio de la app>/api/menu-demo` (el mismo dominio de `app.url`). **El sitio debe permitir esa URL en su CSP (`connect-src`).**
  - Envío `multipart/form-data`: `foto` (JPG, PNG o WebP, hasta 4 MB, entre 100×100 y 30 megapíxeles) y `tipo` opcional (`pedidos` o `reservas`; por defecto `pedidos`).
  - Respuesta JSON `{estado, mensaje, tipo, items}`. `estado`: `ok` (con ítems), `vacio` (la foto no tenía precios), `foto_invalida` (400), `foto_grande` (413), `limite_ip` y `muchos_intentos` (429), `tope_diario` y `no_disponible` (503), `fallo` (502), `origen_no_permitido` (403). El `mensaje` ya viene en español para mostrarlo tal cual.
  - Ítems de `pedidos`: `nombre`, `precio`, `categoria`, `descripcion`. De `reservas`: `nombre`, `precio`, `duracion_min`. Como máximo 15 ítems (los primeros de la carta), sin etiquetas HTML; el sitio los pinta como texto.
  - CORS solo para `https://tuveci.co` y `https://www.tuveci.co` (`demo_ia.origenes`). Otro origen o sin `Origin`: 403.
  - **Frenos:** 2 lecturas al día por IP, 20 intentos por hora por IP, y un tope diario para toda la demo de 150 lecturas y 1.500.000 tokens (configurables en `demo_ia`). Al llegar al tope responde "vuelve mañana". El `Origin` lo puede falsear quien no use un navegador: lo que limita el gasto son los topes.
  - **No se guarda la foto** (se lee del archivo temporal y se borra) ni la IP (solo su huella). No abre sesión ni deja cookies.
  - Sin `anthropic_api_key` en el servidor responde `no_disponible`: el sitio debe tener un estado para eso.

### Datos, seguridad y Ley 1581: qué se puede prometer

Resultado de dos auditorías de seguridad y de pruebas automáticas (`tests/aislamiento.php`, `tests/estatico.php`).

**Se puede prometer:**

- **Cada negocio solo ve sus datos.** Está probado con una prueba automática que intenta leer y modificar datos ajenos por todas las rutas del panel. Un colaborador solo ve los clientes de las sedes que tiene asignadas.
- **Consentimiento de verdad (Ley 1581):**
  - La autorización de datos y la de promociones son dos casillas separadas.
  - Cada alta o retiro queda registrado con origen, versión de la política, fecha e IP.
  - Cada promoción del copiloto lleva un enlace para dejar de recibirlas.
  - El copiloto no propone escribirle con ofertas a quien no autorizó.
- **Derecho de supresión:** el dueño puede borrar a un cliente y todo su historial.
- **Datos de salud:** el motivo de consulta pide una autorización aparte para datos sensibles, y los WhatsApp al paciente dicen "tu cita", sin nombrar el procedimiento.
- **Contraseñas y enlaces:** las contraseñas se guardan con hash. Los tokens de recuperación y los de celulares de confianza se guardan como hash, así que un backup filtrado no sirve para entrar.
- **Protección de la cuenta del dueño:**
  - Freno de intentos que un tercero no puede usar para bloquear la cuenta.
  - Se pide la contraseña otra vez para cambiar la llave Bre-B, el correo de recuperación, crear colaboradores o exportar clientes.
  - Bitácora de seguridad visible para el dueño, que incluye lo que hace el equipo de Veci sobre su cuenta.
  - Opción de cerrar sesión en todos los dispositivos.
- **Panel interno de Veci:** contraseña más código de app autenticadora (2FA obligatorio).
- **Los clientes no pueden usar lo de otro:** escribir el número de otra persona no da su historial, ni gasta su bono, ni muestra sus sellos.

**No se puede prometer todavía:**

- **"Datos cifrados" o "cifrado de extremo a extremo":** la base no está cifrada a nivel de aplicación. El cifrado en tránsito (HTTPS) depende del hosting. Decir "conexión segura (HTTPS)" solo si el hosting lo tiene activo.
- **Backups, disponibilidad, certificaciones** (ISO, SOC 2, PCI) **o "nivel bancario":** no hay nada de eso en el código. Backups diarios: tarea del hosting, **Próximamente** como compromiso escrito.
- **Verificación del número del cliente por código de WhatsApp (OTP): Próximamente.** Hoy el número es lo que la persona escribe. (El código por WhatsApp existe solo para el dueño que usa una oferta de plan, y solo con la API de WhatsApp configurada.) La identidad se protege con el celular reconocido y los enlaces privados, no con verificación.
- **"Cumplimos la Ley 1581" como certificación:** se puede decir que la app tiene las herramientas (autorizaciones separadas, registro de consentimiento, retiro, supresión). El cumplimiento de cada negocio como responsable del tratamiento depende de cómo los use.

## Brand Commitments

Nombre: Veci. Identidad visual ya compartida con la app del producto (mismo panel/tienda, no una marca distinta para marketing): paleta de color (`--sello` #3B4CCA, `--carbon`, `--tiquete`, `--caja`) y tipografía (Bricolage Grotesque + Inter Tight) idénticas entre la web de marketing y la app — mantener esa unificación, no reintroducir fuentes o colores propios de marketing. Tono honesto y directo, sin lenguaje de "producto en construcción"; una función no construida se marca explícitamente "Próximamente", nunca como disponible.

## Evidence on Hand

Ninguna prueba social real todavía: sin testimonios, sin clientes piloto, sin cifras de uso reales (confirmado con el usuario). El sitio no debe insinuar prueba social — cifras de negocios activos, citas con nombre y negocio, capturas de clientes reales — hasta que exista de verdad.

## Product Principles

1. Honestidad verificable: toda función que se promete en la web existe de verdad en el código. Ya se corrigieron varias promesas falsas (Daviplata como método de pago, "pedidos y reservas en la misma cuenta", multisede marcado como futuro cuando ya está construido) — no repetir el patrón.
2. 0% de comisión, siempre: el precio fijo es la posición central frente a Rappi/DiDi/Bold, nunca un porcentaje de las ventas.
3. Hecho para el barrio colombiano, no una plantilla genérica adaptada: Bre-B, pesos colombianos, Ley 1581 de datos personales, WhatsApp como canal nativo desde el primer commit.
4. El dueño opera desde el celular: todo se diseña primero para gama media y 4G, la versión de escritorio es la adaptación.
5. Las cuatro líneas de negocio pesan igual: ninguna (tiendas, peluquerías, entrenadores, odontólogos) es el foco por encima de las demás.
