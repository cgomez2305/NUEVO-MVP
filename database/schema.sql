-- Veci — esquema MySQL
-- Vende por WhatsApp sin comisión. Y haz que vuelvan.
--
-- Importar con:
--   mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS veci
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE veci;
SET NAMES utf8mb4;

-- Los tres planes de Veci (gratis/barrio/pro). Filas fijas (ver el INSERT
-- más abajo, después de creada la tabla negocios): se editan a mano desde
-- SQL si cambia un precio o un límite, no hay pantalla de administración
-- para esto todavía. NULL en un límite significa "ilimitado".
-- sedes_incluidas/precio_sede_extra son metadata para cuando exista el
-- proyecto de multisede con cobro por sede extra (hoy cualquier negocio
-- puede crear sedes sin límite, ver `sedes` más abajo — incluye_multisede
-- todavía no se hace cumplir en el código, ver src/Models/Sede.php).
CREATE TABLE IF NOT EXISTS planes (
  id                             TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre                         VARCHAR(20)  NOT NULL UNIQUE,
  precio_mensual                 INT UNSIGNED NOT NULL,
  precio_anual                   INT UNSIGNED NOT NULL,
  limite_pedidos_mes             SMALLINT UNSIGNED DEFAULT NULL,
  limite_ia_mes                  TINYINT UNSIGNED DEFAULT NULL,
  incluye_copiloto               TINYINT(1)   NOT NULL DEFAULT 0,
  incluye_estadisticas_completas TINYINT(1)   NOT NULL DEFAULT 0,
  incluye_multisede              TINYINT(1)   NOT NULL DEFAULT 0,
  sedes_incluidas                TINYINT UNSIGNED DEFAULT NULL,
  precio_sede_extra              INT UNSIGNED DEFAULT NULL,
  creado_en                      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Un negocio = una marca. tipo_negocio decide qué flujo usan TODAS sus
-- sedes: catálogo con carrito ('pedidos', comida, tiendas) o servicios con
-- cita previa ('reservas', peluquerías, talleres, consultorios, spas...).
-- Lo que antes vivía aquí (tienda pública, horario, catálogo, Bre-B) ahora
-- vive en `sedes`: un negocio puede tener una sola sede (el caso normal,
-- no se nota que existe el concepto) o varias.
--
-- plan_id arranca en 1 (gratis) para toda cuenta nueva. plan_estado y
-- plan_vence_en solo importan para un plan pago: 'activo' con
-- plan_vence_en en el futuro, o 'degradado_a_gratis' cuando el cron
-- (bin/revisar_planes.php) lo bajó por falta de pago — nunca se bloquea
-- la tienda, solo se vuelve a los límites de Gratis. Ver pagos_plan.
CREATE TABLE IF NOT EXISTS negocios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(120) NOT NULL,
  color_marca   CHAR(7)      DEFAULT '#E8452C',
  tipo_negocio  ENUM('pedidos','reservas') NOT NULL DEFAULT 'pedidos',
  plan_id       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  plan_estado   ENUM('activo','vencido','degradado_a_gratis') NOT NULL DEFAULT 'activo',
  plan_vence_en DATE         DEFAULT NULL,
  plan_ciclo    ENUM('mensual','anual') NOT NULL DEFAULT 'mensual',
  -- El equipo de Veci suspende una cuenta desde el panel interno (mora,
  -- abuso, solicitud del dueño). Suspendida: nadie de ese negocio puede
  -- iniciar sesión y sus tiendas públicas dejan de responder.
  suspendido    TINYINT(1)   NOT NULL DEFAULT 0,
  suspendido_en DATETIME     DEFAULT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plan_id) REFERENCES planes(id)
) ENGINE=InnoDB;

-- Precios actuales (ver docs/precios.html): mismo precio mensual×12 para
-- el anual, sin descuento todavía definido — se ajusta aquí si se decide
-- uno. limite_ia_mes cuenta usos reales de la API de Claude por negocio
-- (ver usos_ia); limite_pedidos_mes cuenta pedidos+citas creados por mes
-- calendario (ver Pedido::contarEsteMesPorNegocio / Cita::ídem).
INSERT INTO planes
  (id, nombre, precio_mensual, precio_anual, limite_pedidos_mes, limite_ia_mes,
   incluye_copiloto, incluye_estadisticas_completas, incluye_multisede, sedes_incluidas, precio_sede_extra)
VALUES
  (1, 'gratis', 0,      0,       50,   3,    0, 0, 0, 1, NULL),
  (2, 'barrio', 59000,  708000,  NULL, NULL, 1, 0, 0, 1, NULL),
  (3, 'pro',    129000, 1548000, NULL, NULL, 1, 1, 1, 3, 30000);

-- Quién entra al panel. 'dueno' ve y administra TODAS las sedes de su
-- negocio (incluyendo crear sedes y colaboradores). 'colaborador' solo
-- entra a las sedes que se le asignen en usuario_sedes, y no ve ajustes
-- de negocio (sedes, colaboradores, horario, depósitos, exportar, push).
CREATE TABLE IF NOT EXISTS usuarios (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id         INT UNSIGNED NOT NULL,
  nombre             VARCHAR(120) NOT NULL,
  whatsapp           VARCHAR(20)  NOT NULL UNIQUE,
  password_hash      VARCHAR(255) NOT NULL,
  rol                ENUM('dueno','colaborador') NOT NULL DEFAULT 'dueno',
  intentos_fallidos  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta    DATETIME     DEFAULT NULL,
  -- Correo opcional: solo sirve para poder recuperar la contraseña por ese
  -- canal (ver src/Controllers/AuthController.php). Sin correo, la única
  -- salida si se pierde el acceso es que el equipo de Veci genere un
  -- enlace de recuperación desde el panel interno.
  correo             VARCHAR(160) DEFAULT NULL,
  reset_token        CHAR(64)     DEFAULT NULL,
  reset_token_expira DATETIME     DEFAULT NULL,
  creado_en          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_usuarios_correo (correo),
  UNIQUE KEY uniq_usuarios_reset_token (reset_token)
) ENGINE=InnoDB;

-- Una sede = un punto de atención físico con su propia tienda pública
-- (/t/slug), catálogo, horario y agenda. Un negocio nuevo arranca con una
-- sola sede creada junto con la cuenta; agregar más es opcional y solo
-- lo hace el dueño. horario_atencion guarda la disponibilidad semanal
-- como JSON: {"1":["09:00","18:00"], ...} con 1=lunes..7=domingo.
CREATE TABLE IF NOT EXISTS sedes (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id          INT UNSIGNED NOT NULL,
  slug                VARCHAR(60)  NOT NULL UNIQUE,
  nombre              VARCHAR(120) NOT NULL,
  descripcion         VARCHAR(180) DEFAULT NULL,
  whatsapp            VARCHAR(20)  NOT NULL,
  inicial             CHAR(2)      DEFAULT NULL,
  menu_foto           VARCHAR(255) DEFAULT NULL,
  llave_breb_tipo     ENUM('celular','cedula','correo') DEFAULT 'celular',
  llave_breb_valor    VARCHAR(120) DEFAULT NULL,
  horario_atencion    JSON         DEFAULT NULL,
  intervalo_citas_min SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  -- Si el checkout público ofrece "Comer aquí" como forma de entrega. Por
  -- defecto activo (no cambia el comportamiento de sedes ya creadas); un
  -- negocio sin consumo en el local (tienda, panadería solo para llevar...)
  -- lo desactiva desde "Editar sede" y esa opción deja de aparecer.
  acepta_mesa         TINYINT(1)   NOT NULL DEFAULT 1,
  -- Dirección física de la sede, en texto libre ("Cra 15 #8-20, Barrio
  -- Centro"). Opcional: muchos negocios solo venden por domicilio/WhatsApp
  -- y no tienen local al que invitar al cliente. Cuando está presente se
  -- muestra en la tienda pública ("Dónde estamos") y en el checkout
  -- ("Recoges en") con un enlace de búsqueda en Google Maps.
  direccion           VARCHAR(200) DEFAULT NULL,
  publicada           TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A qué sedes puede entrar cada colaborador. Un 'dueno' no necesita filas
-- aquí: se le asume acceso a todas las sedes de su propio negocio.
CREATE TABLE IF NOT EXISTS usuario_sedes (
  usuario_id  INT UNSIGNED NOT NULL,
  sede_id     INT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, sede_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Catálogo de una sede, extraído por la IA o cargado a mano.
CREATE TABLE IF NOT EXISTS productos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id     INT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  precio      INT UNSIGNED NOT NULL,
  categoria   VARCHAR(60)  NOT NULL DEFAULT 'General',
  descripcion VARCHAR(160) DEFAULT NULL,
  imagen      VARCHAR(255) DEFAULT NULL,
  color       CHAR(7)      NOT NULL DEFAULT '#5B7F3A',
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  agotado     TINYINT(1)   NOT NULL DEFAULT 0,
  orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_productos_sede (sede_id, activo)
) ENGINE=InnoDB;

-- La base de clientes del NEGOCIO (no de una sede sola): si un cliente
-- compra en dos sedes de la misma marca, es el mismo cliente para el
-- copiloto y para el historial. autorizo_datos guarda el check explícito
-- del checkout (Ley 1581 de 2012).
CREATE TABLE IF NOT EXISTS clientes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id      INT UNSIGNED NOT NULL,
  nombre          VARCHAR(120) NOT NULL,
  telefono        VARCHAR(20)  NOT NULL,
  autorizo_datos  TINYINT(1)   NOT NULL DEFAULT 0,
  autorizado_en   DATETIME     DEFAULT NULL,
  -- Opt-in SEPARADO del anterior: ese autoriza a guardar los datos para
  -- procesar el pedido (requerido); este es, además, querer recibir
  -- promociones (opcional). El checkout público los pide por separado.
  acepta_marketing TINYINT(1)  NOT NULL DEFAULT 0,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_cliente_por_negocio (negocio_id, telefono)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id       INT UNSIGNED NOT NULL,
  cliente_id    INT UNSIGNED NOT NULL,
  total         INT UNSIGNED NOT NULL,
  descuento     INT UNSIGNED NOT NULL DEFAULT 0,
  cupon_codigo  VARCHAR(20)  DEFAULT NULL,
  metodo_pago   ENUM('breb','nequi','efectivo') NOT NULL DEFAULT 'breb',
  tipo_entrega  ENUM('domicilio','recoger','mesa') NOT NULL DEFAULT 'domicilio',
  direccion     VARCHAR(255) DEFAULT NULL,
  mesa          VARCHAR(20)  DEFAULT NULL,
  notas         VARCHAR(255) DEFAULT NULL,
  estado        ENUM('pendiente','pagado','en_cocina','listo','en_camino','entregado','cancelado')
                NOT NULL DEFAULT 'pendiente',
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  INDEX idx_pedidos_sede_fecha (sede_id, creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedido_items (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id         INT UNSIGNED NOT NULL,
  producto_id       INT UNSIGNED DEFAULT NULL,
  nombre_producto   VARCHAR(120) NOT NULL,
  precio_unitario   INT UNSIGNED NOT NULL,
  cantidad          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Historial de mensajes que el copiloto sugirió y el dueño mandó,
-- para no repetir el mismo cliente todos los días. Vive a nivel de
-- negocio, igual que clientes.
CREATE TABLE IF NOT EXISTS mensajes_copiloto (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  cliente_id  INT UNSIGNED NOT NULL,
  mensaje     TEXT NOT NULL,
  enviado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Catálogo de servicios de una sede tipo 'reservas' (equivalente a
-- productos, pero con duración: cada servicio ocupa un bloque de agenda).
CREATE TABLE IF NOT EXISTS servicios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id       INT UNSIGNED NOT NULL,
  nombre        VARCHAR(120) NOT NULL,
  precio        INT UNSIGNED NOT NULL,
  duracion_min  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  color         CHAR(7)      NOT NULL DEFAULT '#5B7F3A',
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  agotado       TINYINT(1)   NOT NULL DEFAULT 0,
  deposito_tipo ENUM('ninguno','porcentaje','monto_fijo') NOT NULL DEFAULT 'ninguno',
  deposito_valor INT UNSIGNED NOT NULL DEFAULT 0,
  orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_servicios_sede (sede_id, activo)
) ENGINE=InnoDB;

-- Empleados/recursos de una sede de reservas (estilistas, sillas...). Si
-- una sede no tiene ninguno, las citas se agendan igual que antes (recurso
-- único implícito). Si tiene al menos uno, el cliente elige con quién
-- agenda y la disponibilidad se calcula por empleado, no por sede.
CREATE TABLE IF NOT EXISTS empleados (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id     INT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_empleados_sede (sede_id, activo)
) ENGINE=InnoDB;

-- Días en que una sede no atiende aunque su horario semanal lo permita
-- (vacaciones, festivos). La disponibilidad de citas los excluye.
CREATE TABLE IF NOT EXISTS fechas_bloqueadas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id     INT UNSIGNED NOT NULL,
  fecha       DATE         NOT NULL,
  motivo      VARCHAR(120) DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_fecha_bloqueada (sede_id, fecha)
) ENGINE=InnoDB;

-- Una cita = una reserva de un cliente para un servicio de una sede, en
-- una fecha y hora. duracion_min queda copiado del servicio al momento de
-- reservar, así si el dueño cambia la duración después no descuadra las
-- citas ya agendadas.
CREATE TABLE IF NOT EXISTS citas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id       INT UNSIGNED NOT NULL,
  cliente_id    INT UNSIGNED NOT NULL,
  servicio_id   INT UNSIGNED DEFAULT NULL,
  empleado_id   INT UNSIGNED DEFAULT NULL,
  nombre_servicio VARCHAR(120) NOT NULL,
  precio        INT UNSIGNED NOT NULL,
  descuento     INT UNSIGNED NOT NULL DEFAULT 0,
  cupon_codigo  VARCHAR(20)  DEFAULT NULL,
  fecha_hora    DATETIME     NOT NULL,
  duracion_min  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  estado        ENUM('pendiente','confirmada','completada','cancelada')
                NOT NULL DEFAULT 'pendiente',
  notas         VARCHAR(255) DEFAULT NULL,
  token_gestion CHAR(32)     DEFAULT NULL,
  recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
  anticipo_monto  INT UNSIGNED NOT NULL DEFAULT 0,
  anticipo_estado ENUM('no_requerido','pendiente','pagado') NOT NULL DEFAULT 'no_requerido',
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL,
  INDEX idx_citas_sede_fecha (sede_id, fecha_hora),
  UNIQUE KEY uniq_citas_token (token_gestion)
) ENGINE=InnoDB;

-- Cuando un día no tiene horarios libres, el cliente puede anotarse aquí en
-- vez de irse sin más: el dueño ve quién quiere ese día y le puede avisar a
-- mano si se libera un cupo (alguien cancela, se agrega un horario, etc.).
CREATE TABLE IF NOT EXISTS lista_espera (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id         INT UNSIGNED NOT NULL,
  cliente_id      INT UNSIGNED NOT NULL,
  servicio_id     INT UNSIGNED DEFAULT NULL,
  nombre_servicio VARCHAR(120) NOT NULL,
  fecha           DATE NOT NULL,
  estado          ENUM('pendiente','contactado') NOT NULL DEFAULT 'pendiente',
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  INDEX idx_lista_espera_sede_fecha (sede_id, fecha)
) ENGINE=InnoDB;

-- Quién del equipo de Veci puede entrar al panel interno (/admin): ver
-- todos los negocios, suspenderlos y generar enlaces de recuperación de
-- contraseña para soporte. Completamente aparte de `usuarios`: no hay
-- registro público, solo se crea con bin/crear_admin.php.
-- intentos_fallidos/bloqueado_hasta: mismo mecanismo de fuerza bruta que
-- usuarios (5 intentos → 15 min bloqueado, ver Admin::MAX_INTENTOS_LOGIN).
-- Un admin puede confirmar pagos y suspender cuentas, así que esta
-- contraseña necesita el mismo blindaje que cualquier login de negocio.
CREATE TABLE IF NOT EXISTS admins (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre             VARCHAR(120) NOT NULL,
  correo             VARCHAR(160) NOT NULL UNIQUE,
  password_hash      VARCHAR(255) NOT NULL,
  intentos_fallidos  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta    DATETIME     DEFAULT NULL,
  creado_en          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Limitador de tasa genérico (ver src/Models/LimiteTasa.php): una fila por
-- cada vez que una clave (IP, o IP+sede) hizo una acción pública que se
-- puede abusar repitiéndola — registro, pedido, cita, lista de espera,
-- login, recuperación de contraseña. Se limpia sola: nunca guarda más de
-- 24 horas. No es un log de tráfico.
CREATE TABLE IF NOT EXISTS limites_tasa (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  accion      VARCHAR(30)  NOT NULL,
  clave       VARCHAR(80)  NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_limites_tasa (accion, clave, creado_en),
  INDEX idx_limites_tasa_fecha (creado_en)
) ENGINE=InnoDB;

-- Suscripciones de Web Push de cada USUARIO del panel (no por sede: un
-- colaborador que entra a varias sedes recibe todo en el mismo celular).
-- endpoint/p256dh/auth vienen tal cual de PushSubscription.toJSON().
CREATE TABLE IF NOT EXISTS push_subscripciones (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  endpoint    VARCHAR(600) NOT NULL,
  p256dh      VARCHAR(255) NOT NULL,
  auth        VARCHAR(255) NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_push_endpoint (endpoint)
) ENGINE=InnoDB;

-- Cobro manual verificado de un plan pago (decisión híbrida: Bre-B a mano
-- ahora, posible pasarela recurrente más adelante — metodo_pago queda
-- libre como texto para no tener que migrar el esquema si eso cambia).
-- El dueño pide el cambio de plan desde /panel/plan (queda una fila sin
-- confirmar); un admin la confirma desde /admin/negocios/{id} después de
-- ver el comprobante por WhatsApp, lo que extiende negocios.plan_vence_en.
CREATE TABLE IF NOT EXISTS pagos_plan (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id     INT UNSIGNED NOT NULL,
  plan_id        TINYINT UNSIGNED NOT NULL,
  monto          INT UNSIGNED NOT NULL,
  metodo_pago    VARCHAR(30)  NOT NULL DEFAULT 'breb_manual',
  ciclo          ENUM('mensual','anual') NOT NULL DEFAULT 'mensual',
  periodo_inicio DATE         NOT NULL,
  periodo_fin    DATE         NOT NULL,
  -- NULL en confirmado_en = todavía esperando que un admin lo revise.
  confirmado_por INT UNSIGNED DEFAULT NULL,
  confirmado_en  DATETIME     DEFAULT NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES planes(id),
  FOREIGN KEY (confirmado_por) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_pagos_plan_pendientes (confirmado_en)
) ENGINE=InnoDB;

-- Un uso real de la API de Claude para "la IA arma tu catálogo" (foto →
-- productos/servicios), para hacer cumplir planes.limite_ia_mes del plan
-- Gratis. Solo se inserta cuando sí se intentó la llamada real a la API
-- (ver ExtractorMenu + OnboardingController::analizar) — no cuando ya se
-- usó el catálogo de ejemplo, sea por falta de llave o por límite alcanzado.
CREATE TABLE IF NOT EXISTS usos_ia (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  sede_id     INT UNSIGNED NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_usos_ia_negocio_fecha (negocio_id, creado_en)
) ENGINE=InnoDB;

-- Cupones de descuento del negocio.
--
-- Dos orígenes:
--   'panel'    el dueño crea un código para todos (VECI10, DIADELAMADRE…)
--              y lo comparte en sus estados o redes.
--   'copiloto' cupón personal que nace cuando el dueño le manda a un
--              cliente un mensaje con descuento desde el copiloto: solo
--              sirve para ese cliente (cliente_id) y una vez.
--
-- valor: porcentaje (1–90) si tipo = 'porcentaje'; pesos si tipo = 'monto'.
-- usos_maximos NULL = sin tope. vence_en NULL = no vence.
CREATE TABLE IF NOT EXISTS cupones (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id           INT UNSIGNED NOT NULL,
  codigo               VARCHAR(20)  NOT NULL,
  tipo                 ENUM('porcentaje','monto') NOT NULL DEFAULT 'porcentaje',
  valor                INT UNSIGNED NOT NULL,
  minimo_compra        INT UNSIGNED NOT NULL DEFAULT 0,
  vence_en             DATE         DEFAULT NULL,
  usos_maximos         INT UNSIGNED DEFAULT NULL,
  una_vez_por_cliente  TINYINT(1)   NOT NULL DEFAULT 1,
  cliente_id           INT UNSIGNED DEFAULT NULL,
  origen               ENUM('panel','copiloto') NOT NULL DEFAULT 'panel',
  activo               TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_cupon_codigo (negocio_id, codigo)
) ENGINE=InnoDB;

-- Cada vez que un cupón se usó, en un pedido o en una cita.
CREATE TABLE IF NOT EXISTS cupon_usos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cupon_id    INT UNSIGNED NOT NULL,
  cliente_id  INT UNSIGNED NOT NULL,
  pedido_id   INT UNSIGNED DEFAULT NULL,
  cita_id     INT UNSIGNED DEFAULT NULL,
  descuento   INT UNSIGNED NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cupon_id) REFERENCES cupones(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  INDEX idx_cupon_usos_cupon (cupon_id, cliente_id)
) ENGINE=InnoDB;

-- Migraciones ya incluidas en este esquema (ver bin/migrar.php): una
-- instalación nueva nace al día y el migrador no intenta repetirlas.
CREATE TABLE IF NOT EXISTS migraciones (
  nombre      VARCHAR(190) PRIMARY KEY,
  aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO migraciones (nombre) VALUES
  ('2026-10-01_acepta_mesa_acepta_marketing.sql'),
  ('2026-10-01_blindaje_planes.sql'),
  ('2026-10-01_planes_suscripciones.sql'),
  ('2026-10-01_sedes_direccion.sql'),
  ('2026-10-03_01_cupones.sql');
