# Parroquia

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
   mysql -u root -p parroquia < database/seed.sql
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

   - **Producción** (Apache/LiteSpeed, el típico hosting compartido de
     WordPress): apunta el docroot del dominio o subdominio a `public/`.
     Ahí ya está el `.htaccess` con la reescritura a `index.php`.

5. Entra a `/registro` para crear tu propio negocio, o usa la cuenta de
   demostración si cargaste `seed.sql`:

   - **WhatsApp:** `3001234567`
   - **Contraseña:** `parroquia123`
   - **Tienda pública:** `/t/donamaria`

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
