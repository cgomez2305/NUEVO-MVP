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
   (`zip -r veci.zip .` en la raíz del repo, sin incluir
   `config/config.php` si ya lo creaste) y súbelo por el Administrador
   de archivos de cPanel (botón *Upload*, luego *Extract*), o por FTP
   con FileZilla si lo prefieres.

3. **Crea la base de datos** en cPanel → MySQL Databases:
   - Crea una base (cPanel suele anteponer tu usuario, ej.
     `usuario_veci`).
   - Crea un usuario MySQL con una contraseña fuerte.
   - Agrega ese usuario a esa base con **todos los privilegios**.

4. **Importa el esquema.** cPanel → phpMyAdmin → selecciona tu base →
   pestaña *Import* → sube primero `database/schema.sql` y luego,
   opcionalmente, `database/seed.sql` (los datos de demostración).

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

7. **Da permisos de escritura** a `public/uploads/menus/` y
   `public/uploads/logos/` (clic derecho → Permissions → 755, o 775 si
   tu hosting lo exige) para que las fotos de menú se puedan guardar.

8. **Pruébala:** entra a `https://app.tudominio.com/registro`, crea un
   negocio, sube una foto de menú y publica la tienda. Si prefieres
   partir con datos ya cargados, entra con la cuenta de demostración
   (`3001234567` / `veci123`) si importaste `seed.sql`.

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
  php bin/crear_admin.php "Tu nombre" tu@correo.com "una-contraseña-larga"
  ```

- **Legal y hábeas data**: política de privacidad y términos de servicio
  en `docs/privacidad.html` y `docs/terminos.html` (enlazadas desde el
  registro, el checkout público y el footer de todo el sitio). El derecho
  de eliminación de datos (Ley 1581 de 2012) tiene un camino concreto: el
  dueño borra a un cliente y todo su historial con un botón en
  `/panel/copiloto`, y si el negocio no responde, `soporte@tuveci.co` lo
  hace directamente.

## Planes y suscripciones

Tres planes — Gratis ($0), Barrio ($59.000/mes) y Pro ($129.000/mes) — con
límites y funciones que sí se hacen cumplir en el código (`database/schema.sql`
→ tabla `planes`; `negocios.plan_id/plan_estado/plan_vence_en/plan_ciclo`):

- **Gratis**: hasta 50 pedidos o citas por mes calendario (se resetea el
  día 1), hasta 3 análisis con foto con IA por mes, sin copiloto de
  recompra, historial de 30 días, sello "Hecho con Veci" visible en la
  tienda pública.
- **Barrio**: todo ilimitado salvo estadísticas completas (sigue en 30 días
  de historial), incluye el copiloto de recompra.
- **Pro**: historial completo + exportar a CSV, y es el único con
  multisede (3 sedes incluidas en el precio). La sede extra ($30.000/mes)
  todavía no tiene cobro en el código, así que por ahora crear una cuarta
  sede se bloquea en el servidor.

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
  3 cuentas nuevas por IP al día; 20 logins fallidos por IP cada 15 min
  (además del bloqueo de 5 intentos por cuenta, también en `/admin`);
  5 correos de recuperación por IP y 3 por cuenta por hora; 5 pedidos,
  citas o inscripciones a lista de espera por IP y tienda por hora (20 en
  total entre tiendas), para que nadie agote el cupo Gratis de un negocio
  con pedidos falsos.
- **Cuentas**: contraseñas de mínimo 8 caracteres con `password_hash`;
  los tokens de recuperación se guardan como SHA-256 (un backup filtrado no
  sirve para tomar cuentas); la sesión de `/admin` se cierra sola a los
  30 minutos sin actividad.
- **Aplicación**: consultas 100% preparadas (PDO sin emulación), CSRF en
  todo POST, escape de salida con `e()`, todo dato de un negocio filtrado
  por su `negocio_id`/sede, subidas de imagen con lista blanca de tipos y
  nombres aleatorios, CSV de exportación protegido contra inyección de
  fórmulas, errores nunca visibles al visitante (van al log del servidor),
  cabeceras CSP, HSTS (en HTTPS), X-Frame-Options, nosniff y
  Permissions-Policy, cookie de sesión HttpOnly + SameSite + Secure.
- **Servidor**: los scripts de `bin/` solo corren por consola, y el
  `.htaccess` de la raíz bloquea `config/`, `src/`, `database/` y `bin/`
  por si el dominio no apunta a `public/`.

Pendiente fuera del código: HTTPS obligatorio en el hosting, backups
diarios de la base y monitorear el log de errores de PHP.

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
