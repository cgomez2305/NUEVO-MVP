# Veci

Vende por WhatsApp sin pagar comisión. Y haz que vuelvan.

Aplicación real (no maqueta) del producto descrito en el documento de
análisis: un mostrador digital para el negocio colombiano que vive en
WhatsApp, cobra por Bre-B y usa un copiloto de recompra para decirle al
dueño a quién escribirle hoy.

Veci soporta dos tipos de negocio, elegidos al registrarse:

- **Pedidos** (comida, tiendas, panaderías): catálogo con carrito, el
  cliente arma su pedido y paga por Bre-B, Nequi o efectivo.
- **Reservas** (peluquerías, spas, talleres, consultorios): catálogo de
  servicios con duración, el cliente elige un horario disponible según
  el horario de atención del negocio y confirma por WhatsApp. El panel
  cambia "Pedidos" por "Agenda" y agrega "Horario de atención"; el
  copiloto de recompra funciona igual, calculando la frecuencia sobre
  las citas en vez de los pedidos.

## Stack

PHP puro + MySQL, sin framework:

- **PHP 8.1+** con PDO (`pdo_mysql`). Enrutador y autoload propios (ver
  `src/Router.php` y `src/bootstrap.php`), sin Composer ni dependencias.
- **MySQL / MariaDB** vía consultas preparadas (`src/Database.php`).
- Vistas en PHP plano (`src/Views/*.php`), una sola hoja de estilos
  (`public/assets/css/app.css`) con la paleta y tipografías del documento
  de diseño: **Ruta 2 "Tiquete y papel"** para el panel del dueño y
  **Ruta 1 "Letrero de barrio"** para la tienda del cliente.
- Sin JavaScript de por medio: todo el flujo (carrito, onboarding,
  copiloto) funciona con formularios y enlaces normales, para que cargue
  rápido en un Android de gama media con datos limitados.

## Instalación

1. Crea la base de datos e importa el esquema:

   ```bash
   mysql -u root -p < database/schema.sql
   ```

2. (Opcional pero recomendado) Carga datos de demostración — el negocio
   "Doña María" (tipo pedidos) y "Salón Bonita" (tipo reservas), cada uno
   con catálogo, clientes y un historial pensado para que el copiloto
   tenga algo real que mostrar:

   ```bash
   mysql -u root -p veci < database/seed.sql
   ```

3. Copia la configuración y ajusta tus credenciales de MySQL:

   ```bash
   cp config/config.example.php config/config.php
   ```

   Edita `config/config.php`:

   - `db`: host, nombre, usuario y contraseña de tu MySQL.
   - `app.url`: el dominio público real de tu instalación (sin barra al
     final). Se usa **solo** para mostrar el enlace que el dueño comparte
     de su tienda — la navegación interna del sitio es siempre relativa,
     así que nunca rompe la sesión sin importar con qué host entres.
   - `anthropic_api_key` (opcional): con una llave, el paso "La IA arma tu
     tienda" lee productos y precios reales de la foto del menú llamando a
     la API de Claude (salida JSON estructurada y respaldo automático de
     modelo si la petición es rechazada). Sin llave, ofrece un catálogo de
     ejemplo —avisándole al dueño que es de ejemplo— para que el flujo
     completo se pueda probar sin depender de una API externa.

4. Levanta el servidor:

   - **Desarrollo local**, con el servidor embebido de PHP:

     ```bash
     php -d upload_max_filesize=12M -d post_max_size=14M -S localhost:8000 serve.php
     ```

     (Los `-d` dejan subir fotos de menú tal como salen del celular; en
     hosting eso lo ponen `public/.user.ini` y `public/.htaccess`.)

   - **Producción**: ver la guía completa para hosting compartido tipo
     cPanel (el mismo donde sueles instalar WordPress) más abajo.

5. Entra a `/registro` para crear tu propio negocio, o usa las cuentas de
   demostración si cargaste `seed.sql`:

   | Negocio | Tipo | WhatsApp | Contraseña | Tienda pública |
   |---|---|---|---|---|
   | Doña María | Pedidos | `3001234567` | `veci123` | `/t/donamaria` |
   | Salón Bonita | Reservas | `3005556677` | `veci123` | `/t/salonbonita` |
   | Frío Express | Visitas a domicilio | `3007778899` | `veci123` | `/t/frioexpress` |
   | Sonrisa Dental | Salud | `3009990011` | `veci123` | `/t/sonrisadental` |

   Frío Express (técnicos de aires) y Sonrisa Dental (consultorio) no están en
   `seed.sql`: se cargan aparte, también sobre una base que ya existe, con
   `mysql veci < database/demo_visitas.sql` y `mysql veci < database/demo_salud.sql`.

## Publicar en hosting compartido (cPanel)

Si ya tienes hosting para sitios de WordPress, casi seguro soporta PHP 8+
y MySQL — es todo lo que Veci necesita. Pasos:

1. **Crea un subdominio** (por ejemplo `app.tudominio.com`) desde
   cPanel → Dominios/Subdominios. Cuando te pida la carpeta de destino
   ("Document Root"), apúntala a una ruta que **no** esté dentro de
   `public_html` visible al público — por ejemplo
   `veci-app/public` (cPanel crea `veci-app/` en tu directorio
   raíz, tú luego subes el proyecto ahí). Así `config/`, `src/` y
   `database/` quedan fuera del alcance del navegador, no solo
   protegidos por `.htaccess`.

   Si tu panel *no* te deja elegir una carpeta fuera de `public_html`,
   sube igual todo el proyecto (por ejemplo a `public_html/veci/`)
   y usa `public_html/veci/public` como Document Root si te lo
   permite; si ni eso, el `.htaccess` de la raíz del proyecto bloquea el
   acceso directo a `config/`, `src/` y `database/` como red de
   seguridad — pero apuntar el dominio a `public/` sigue siendo lo
   correcto.

2. **Sube los archivos.** Comprime el proyecto en tu computador
   (`git archive -o veci.zip HEAD` en la raíz del repo: solo lo que
   está en git, sin `.git/`, sin tu `config/config.php` ni tus fotos;
   después borra del servidor `.claude/`, `capturas-diseno/` y `tests/`,
   que no hacen falta para funcionar) y súbelo por el Administrador
   de archivos de cPanel (botón *Upload*, luego *Extract*), o por FTP
   con FileZilla si lo prefieres.

3. **Crea la base de datos** en cPanel → MySQL Databases:
   - Crea una base (cPanel suele anteponer tu usuario, ej.
     `usuario_veci`).
   - Crea un usuario MySQL con una contraseña fuerte.
   - Agrega ese usuario a esa base con **todos los privilegios**.

4. **Importa el esquema.** cPanel → phpMyAdmin → selecciona tu base →
   pestaña *Import* → sube **solo** `database/schema.sql`.
   ⚠️ **Nunca importes `database/seed.sql` ni los `demo_*.sql` en el
   servidor de verdad:** traen cuentas de demostración con la contraseña
   `veci123`, que está publicada en este repositorio. Son solo para tu
   computador.

5. **Configura la app.** En el Administrador de archivos, dentro de
   `config/`, duplica `config.example.php` como `config.php` y edítalo
   con el editor de cPanel:
   - `db.host`: normalmente `localhost` en hosting compartido.
   - `db.name`, `db.user`, `db.pass`: los que creaste en el paso 3
     (con el prefijo que cPanel les haya puesto).
   - `app.url`: `https://app.tudominio.com` (tu subdominio real, sin
     barra al final).

6. **Revisa la versión de PHP.** cPanel → *Select PHP Version* /
   *MultiPHP Manager*: elige 8.1 o superior para tu subdominio, y
   confirma que la extensión `pdo_mysql` esté activada (casi siempre lo
   está por defecto).

7. **Da permisos de escritura** a `public/uploads/menus/`, `public/uploads/productos/`, `public/uploads/equipo/`,
   `public/uploads/logos/` y `storage/visitas/` (fotos privadas de las visitas a
   domicilio, fuera de la web; clic derecho → Permissions → 755, o 775 si
   tu hosting lo exige) para que las fotos de menú se puedan guardar.

8. **Pruébala:** entra a `https://app.tudominio.com/registro`, crea un
   negocio, sube una foto de menú y publica la tienda. Para el panel
   interno crea tu admin por consola con una contraseña larga y única
   (`php bin/crear_admin.php "Tu nombre" tu@correo`; la pide sin que
   quede en el historial) y activa su segundo factor con
   `php bin/admin_2fa.php tu@correo` (te da una clave para Google
   Authenticator o similar; sin ella no se entra a `/admin`).

### Cobro de planes con Wompi (opcional)

Sin configurar nada, el negocio pide su plan y transfiere por Bre-B; un
admin lo confirma en `/admin`. Para que paguen con tarjeta, PSE o Nequi y el
plan se active solo:

1. En el panel de Wompi → Desarrolladores copia la llave pública, el
   secreto de integridad y el secreto de eventos a `config/config.php` →
   `wompi` (con llaves `pub_test_…` se usa el sandbox).
2. En Wompi configura la URL de eventos: `https://TU-DOMINIO/webhooks/wompi`.

El plan se activa solo con un evento firmado (checksum) o consultando la
transacción a la API de Wompi al volver del pago; se exige estado
`APPROVED` y el monto exacto. Nunca se confía en lo que diga la URL de
regreso.

### Actualizar una instalación que ya está en producción

`database/schema.sql` es el esquema completo para una base **nueva**; no
lo vuelvas a importar sobre una base que ya tiene datos. Cuando una
actualización del código agrega columnas o tablas, el cambio queda
también como un archivo en `database/migrations/` (nombrado por fecha)
que solo tiene el `ALTER TABLE`/`CREATE TABLE` necesario. Para aplicarlo:
con un backup reciente a mano, corre:

```bash
php bin/migrar.php
```

Aplica en orden las migraciones que falten y las anota en la tabla
`migraciones`; correrlo otra vez no hace nada. Sin consola (hosting solo
con cPanel), sube cada archivo pendiente en phpMyAdmin → tu base →
*Import*, en orden de nombre, una sola vez.

## Recuperación de contraseña, panel interno y legal

Tres piezas pensadas para operar Veci en producción, no solo para que un
negocio use la app:

- **Recuperación de contraseña**: desde `/olvide-password`, si el dueño
  guardó un correo en "Mi cuenta" y configuraste `smtp` en
  `config/config.php`, se le manda un enlace de un solo uso (vence en 1
  hora). Sin SMTP configurado o sin correo guardado, la cuenta se recupera
  a mano desde el panel interno (ver abajo). El cliente SMTP
  (`src/Services/Correo.php`) habla STARTTLS + AUTH LOGIN por sockets,
  sin dependencias — sirve cualquier proveedor (Gmail con contraseña de
  aplicación, SendGrid, Zoho...).
- **Panel interno (`/admin`)**: para el equipo de Veci, no para un negocio.
  Lista todos los negocios, deja suspender/reactivar una cuenta (bloquea
  login y tienda pública) y generar un enlace de recuperación de
  contraseña a mano para cualquier usuario. No hay registro público: se
  crea la primera cuenta con:

  ```bash
  php bin/crear_admin.php "Tu nombre" tu@correo.com   # pide la contraseña (mín. 12)
  php bin/admin_2fa.php tu@correo.com                 # segundo factor, obligatorio
  ```

- **Legal y hábeas data**: política de privacidad y términos de servicio
  en `docs/privacidad.html` y `docs/terminos.html` (enlazadas desde el
  registro, el checkout público y el footer de todo el sitio). El derecho
  de eliminación de datos (Ley 1581 de 2012) tiene un camino concreto: el
  dueño borra a un cliente y todo su historial con un botón en
  `/panel/copiloto`, y si el negocio no responde, `soporte@tuveci.co` lo
  hace directamente.

## Planes y suscripciones

Tres planes — Gratis ($0), Barrio ($29.900/mes) y Pro ($69.900/mes); el
anual es "2 meses gratis" (10 veces el mensual) — con
límites y funciones que sí se hacen cumplir en el código (`database/schema.sql`
→ tabla `planes`; `negocios.plan_id/plan_estado/plan_vence_en/plan_ciclo`):

- **Gratis**: hasta 50 pedidos o citas por mes calendario (se resetea el
  día 1), hasta 3 análisis con foto con IA por mes, sin copiloto de
  recompra, historial de 30 días, sello "Hecho con Veci" visible en la
  tienda pública. Las ventas de mostrador (tiendas) no cuentan en el límite.
  Exportar a CSV va en todos los planes (los datos son del negocio).
- **Barrio**: todo ilimitado salvo estadísticas completas (sigue en 30 días
  de historial), incluye el copiloto de recompra.
- **Pro**: historial completo y estadísticas, y es el único con
  multisede (3 sedes incluidas en el precio). Cada sede extra cuesta
  $19.900/mes (`planes.precio_sede_extra`): desde `/panel/sedes` el dueño
  la pide y paga solo los días que le quedan al plan (prorrateo); al
  confirmarse el pago sube `negocios.sedes_extra` y puede crearla. Las
  renovaciones de Pro ya cobran las sedes extra que el negocio tenga en ese
  momento (si borró una, deja de cobrarse). Si el plan vence o baja a
  Gratis, las sedes siguen funcionando pero no se pueden crear más.

**Cómo se cobra**: Bre-B y Nequi no tienen cobro automático recurrente, así
que el cobro es manual verificado. El dueño pide el cambio de plan desde
`/panel/plan` (queda una fila sin confirmar en `pagos_plan` con lo que se
espera que transfiera), transfiere por Bre-B a la llave de Veci
(`config/config.php` → `cobro_planes.llave_breb`), y un admin confirma el
pago desde `/admin/negocios/{id}` escribiendo el monto que vio llegar
(si no coincide exacto con lo esperado, no se activa nada) — eso activa o
extiende el plan. Bajar a Gratis es la excepción: es instantáneo, no hay
nada que cobrar.

**Si un plan pago vence** sin que se confirme un pago nuevo, el negocio
vuelve a Gratis automáticamente — nunca se bloquea la tienda. Lo hace un
cron diario:

```bash
0 3 * * * php /ruta/al/proyecto/bin/revisar_planes.php
```

## Registro desde el sitio y ofertas de planes

- **Parámetros de `/registro`** (los manda el sitio web): `ref`, `utm_source`,
  `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `plan`
  (`gratis|barrio|pro`), `ciclo` (`mensual|anual`), `modo`
  (`pedidos|reservas`) y `oferta`. Se recuerdan en la sesión (sobreviven a un
  error del formulario) y se guardan en `negocio_origen`. Los `utm_*` van como
  texto plano de hasta 80 caracteres; plan, ciclo y modo solo de una lista
  blanca; cualquier otro valor se ignora. `modo` deja marcada esa opción. Con
  plan pago el negocio igual nace en Gratis y va a `/panel/plan` con ese plan
  y ciclo marcados: nunca se cobra solo.
- **Códigos de oferta** (`ofertas_plan`, p. ej. `VECICHAT30`): se aplican en
  "Tu plan" con la cédula o el NIT del titular y se validan otra vez en el
  servidor al pedir el primer plan. Solo el primer mes de Barrio o Pro con
  pago mensual; un uso por negocio; no acumulable con el anual ni con otro
  código; solo negocios nuevos (el WhatsApp y el documento no tuvieron otro
  negocio, comparados como HMAC con `app.clave_hash`); fecha de fin y cupo
  desde `/admin/ofertas` (el cupo se cuenta con la oferta bloqueada); 5
  intentos por hora por IP y por negocio. El pago guarda el monto ya con el
  descuento (es lo que el admin ve como "Debe llegar" y lo que cobra Wompi) y
  `/admin/ofertas` lleva el registro de canjes y si pagaron el segundo mes.
- Prueba de punta a punta (base de prueba y servidor local):
  `php tests/registro_ofertas.php http://localhost:8000`.

## Seguridad y anti-abuso

Lo que ya está blindado en el código (para que nadie lo desactive sin
querer en un cambio futuro):

- **Planes**: el plan vigente se calcula en cada petición (un plan pago
  vencido ya se comporta como Gratis aunque el cron no haya corrido); el
  límite de sedes por plan se valida en el servidor; confirmar un pago
  exige escribir el monto recibido y es atómico (no se puede activar dos
  veces); el webhook Bre-B verifica firma HMAC **y** que el monto cubra el
  total.
- **Límites de tasa** (`limites_tasa`, ver `App\Models\LimiteTasa`):
  3 cuentas nuevas por IP al día; 20 logins fallidos por IP cada 15 min;
  5 fallos por número + lugar (IP, o celular de confianza) frenan 15 min
  solo ese lugar, así nadie deja por fuera a un dueño desde afuera; con 30
  fallos en una hora desde lugares desconocidos la cuenta solo acepta
  celulares donde ya se entró (o recuperar la contraseña);
  5 correos de recuperación por IP y 3 por cuenta por hora; 5 pedidos,
  citas o inscripciones a lista de espera por IP y tienda por hora (20 en
  total entre tiendas), para que nadie agote el cupo Gratis de un negocio
  con pedidos falsos.
- **Cuentas**: contraseñas de mínimo 8 caracteres con `password_hash`, y
  se rechazan las fáciles (12345678, "contraseña", el propio WhatsApp o el
  nombre del negocio); los tokens de recuperación se guardan como SHA-256
  (un backup filtrado no sirve para tomar cuentas). Cambiar la llave Bre-B,
  el correo de recuperación, crear un colaborador o exportar a CSV pide la
  contraseña otra vez (vale 10 minutos). En **Mi cuenta** cada usuario ve
  dónde ha entrado y puede cerrar la sesión en los demás dispositivos; el
  dueño ve la **bitácora de seguridad** del negocio (inicios de sesión,
  celular nuevo, llave, correo, colaboradores, exportaciones, anticipos
  marcados a mano y lo que haga el equipo de Veci sobre su cuenta).
- **Panel interno**: entra con contraseña **y** código de una app
  autenticadora (TOTP). Actívalo con `php bin/admin_2fa.php correo` (sin
  él no se entra); la sesión se cierra a los 30 minutos sin actividad y a
  las 10 horas en todo caso. Cada acción del equipo queda en "Actividad
  del equipo" y en la bitácora del negocio afectado.
- **Dinero**: la última sesión de un bono y los usos de un cupón se toman
  con la fila bloqueada (dos pedidos a la vez no pasan el límite); un bono
  solo se aplica si quien reserva demuestra ser el cliente (su celular o
  el enlace de su bono); los sellos solo cuentan pedidos entregados y
  citas completadas; cancelar o reactivar no duplica saldos ni sesiones;
  los pagos de plan cancelados no se borran (si Wompi aprueba tarde, el
  plan igual se activa).
- **Aplicación**: consultas 100% preparadas (PDO sin emulación), CSRF en
  todo POST verificado en el router (aunque un controlador nuevo lo olvide)
  y con rechazo de peticiones de otro origen, escape de salida con `e()`, todo dato de un negocio filtrado
  por su `negocio_id`/sede, subidas de imagen con lista blanca de tipos y
  nombres aleatorios, CSV de exportación protegido contra inyección de
  fórmulas, errores nunca visibles al visitante (van al log del servidor),
  cabeceras CSP, HSTS (en HTTPS), X-Frame-Options, nosniff y
  Permissions-Policy, cookie de sesión HttpOnly + SameSite + Secure.
- **Servidor**: los scripts de `bin/` solo corren por consola, y el
  `.htaccess` de la raíz bloquea `config/`, `src/`, `database/` y `bin/`
  por si el dominio no apunta a `public/`.

- **Datos de clientes y promociones** (Ley 1581): el permiso de
  promociones es aparte del de datos; cada alta o retiro queda en
  `consentimientos` (origen, versión de la política e IP si lo hizo el
  cliente). El copiloto no propone escribirle con ofertas a quien no
  autorizó; a ese cliente solo se le puede pedir permiso una vez. Cada
  promoción lleva el enlace `/preferencias/{token}` para retirarlo. La
  tienda reconoce al cliente para "volver a pedir" solo por una cookie en
  el celular donde ya pidió, nunca por el número que alguien escriba.
- **Aislamiento entre negocios**: `tests/aislamiento.php` entra como el
  dueño (o colaborador) de un negocio y prueba todas las rutas del panel
  que llevan un id con ids de OTROS negocios; falla si alguna respuesta
  muestra nombres o teléfonos ajenos o si alguna fila de otro negocio
  cambia. Correrlo contra una base de prueba antes de cada entrega:

  ```bash
  php tests/aislamiento.php http://localhost:8000 3001234567 veci123
  php tests/aislamiento.php http://localhost:8000 3001110000 veci123   # colaborador: también entre sedes
  ```

  Con un colaborador, las sedes de su negocio que no tiene asignadas
  cuentan como ajenas; también revisa las pantallas de lista (fiado,
  mostrador). Y `php tests/estatico.php` (sin base ni servidor) revisa que
  toda ruta del panel exija sesión, las del admin sesión de admin, y que
  ninguna vista imprima texto de la base sin `e()`.

Pendiente fuera del código: HTTPS obligatorio en el hosting, backups
diarios de la base y monitorear el log de errores de PHP.

## Tiendas de barrio: mostrador, fiado y compras

Para negocios de **pedidos** que también venden en el local (la tienda de
la esquina, la panadería, el minimercado):

- **Mostrador** (`/panel/mostrador`, todo el equipo): se cobra con un lector
  de códigos USB/Bluetooth (escribe el código y Enter), con la cámara del
  celular donde el navegador tiene `BarcodeDetector` (Chrome Android; en
  iPhone se explica que use un lector o escriba el número) o buscando por
  nombre. Productos por peso con atajos (125 g, 250 g, 1 libra, 1 kg),
  efectivo con "¿con cuánto paga?" y las vueltas, Nequi, Bre-B o fiado.
  Descuenta el mismo inventario que la tienda en línea (si no alcanza, no se
  vende nada), un doble toque no cobra dos veces, tiquete imprimible y
  anulación (solo el dueño, el mismo día). Funciona sin JavaScript.
- **Productos**: código de barras (único por sede), costo y venta por
  unidad o por peso (precio y costo por kilo; inventario en gramos). Margen
  por producto y "Lo que más te deja" con ventas reales (solo si hay datos
  suficientes).
- **Fiado** (`/panel/fiado`): saldo por cliente, abonos, cargos a mano,
  límite por cliente que el mostrador respeta, y recordatorio de pago por
  WhatsApp solo cuando la Ley 2300 de 2023 lo permite (lunes a viernes
  7 a. m.–7 p. m., sábados 8 a. m.–3 p. m., nunca domingos ni festivos de
  Colombia, uno por semana por cliente). Al crear un cliente para fiarle se
  pide su autorización (Ley 1581).
- **Compras a proveedor** (`/panel/compras`, solo el dueño): lo que llegó
  del distribuidor, por lector o búsqueda; suma inventario y deja el último
  costo. Un código que la tienda no tiene se crea ahí mismo.
- **Catálogo compartido de códigos**: cuando una tienda escanea un código
  que no tiene, Veci le sugiere cómo lo llaman otras tiendas (solo el nombre,
  nunca precios ni datos de otra tienda).
- **Cierre de caja**: suma el mostrador por forma de pago y los abonos de
  fiado; lo fiado se muestra aparte ("Fiado hoy") porque no es plata que
  entró.

Las tablas nuevas vienen en `database/migrations/2026-10-03_20_*.sql` a
`..._24_*.sql` (`php bin/migrar.php`). Detalle de pantallas y reglas en
`.claude/skills/diseno-veci/references/tiendas.md`.

## Estructura

```
public/            Front controller (index.php), assets y uploads
src/
  bootstrap.php     Autoload + sesión + helpers
  Router.php        Enrutador propio (patrones tipo /t/{slug})
  Database.php       Conexión PDO
  Auth.php            Sesión del negocio
  Models/             Negocio, Producto, Cliente, Pedido, Copiloto
  Services/           ExtractorMenu (IA real u catálogo de ejemplo)
  Controllers/        Home, Auth, Onboarding, Panel, Tienda
  Views/              Plantillas PHP, por área (auth, onboarding, panel, tienda)
database/
  schema.sql          Esquema completo
  seed.sql            Datos de demostración
```

## Los dos flujos

**1. Alta del comerciante** (`/registro` → `/panel/onboarding/...`)
Foto del menú → la IA arma la tienda (el dueño revisa y corrige) → elige
cómo cobra por Bre-B → tienda publicada en su propio enlace (`/t/{slug}`).

**2. Cliente pide + copiloto** (`/t/{slug}` → `/panel/copiloto`)
El cliente arma su carrito, autoriza el tratamiento de sus datos (Ley 1581
de 2012) y pide por WhatsApp con un enlace `wa.me` que ya trae el pedido
escrito. El dueño confirma el pago (Bre-B, Nequi o efectivo) al ver el
comprobante. El copiloto revisa la frecuencia de compra de cada cliente y,
cuando alguien lleva más tiempo del habitual sin pedir, sugiere un mensaje
de reactivación con un botón real de WhatsApp para enviarlo.

## Decisiones de alcance (MVP)

Siguiendo la priorización MoSCoW del documento de producto:

- **Bre-B sin conciliación automática**: se muestra la llave y el cliente
  envía el comprobante por WhatsApp; el dueño marca el pedido como pagado
  desde `/panel/pedidos`. Una integración por API queda para la fase de
  tracción, cuando algún proveedor la exponga.
- **WhatsApp vía enlaces `wa.me`**, no la Cloud API de Meta: funciona sin
  costo ni credenciales, que es como hoy operan la mayoría de estos
  negocios. Migrar a la Cloud API (para bots y plantillas automáticas) es
  un cambio aislado a `TiendaController` y `PanelController`.
- **Facturación DIAN y domiciliarios propios**: fuera del MVP, como marca
  la columna "Won't" del documento.
