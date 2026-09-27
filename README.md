# Veci

Vende por WhatsApp sin pagar comisión. Y haz que vuelvan.

Aplicación real (no maqueta) del producto descrito en el documento de
análisis: un mostrador digital para el negocio colombiano que vive en
WhatsApp, cobra por Bre-B y usa un copiloto de recompra para decirle al
dueño a quién escribirle hoy.

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
   "Doña María" con productos, clientes y un historial de pedidos pensado
   para que el copiloto tenga algo real que mostrar:

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
     la API de Claude. Sin llave, usa un catálogo de ejemplo para que el
     flujo completo se pueda probar sin depender de una API externa.

4. Levanta el servidor:

   - **Desarrollo local**, con el servidor embebido de PHP:

     ```bash
     php -S localhost:8000 serve.php
     ```

   - **Producción**: ver la guía completa para hosting compartido tipo
     cPanel (el mismo donde sueles instalar WordPress) más abajo.

5. Entra a `/registro` para crear tu propio negocio, o usa la cuenta de
   demostración si cargaste `seed.sql`:

   - **WhatsApp:** `3001234567`
   - **Contraseña:** `veci123`
   - **Tienda pública:** `/t/donamaria`

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
