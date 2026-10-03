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
-- sedes_incluidas es el cupo de sedes del plan; precio_sede_extra (solo
-- Pro) es lo que cuesta al mes cada sede por encima de ese cupo (ver
-- negocios.sedes_extra y pagos_plan.concepto).
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
  -- Reservas en el local o visitas a la casa del cliente (técnicos).
  modalidad     ENUM('local','domicilio') NOT NULL DEFAULT 'local',
  -- 'salud': consultorios (planes de tratamiento, datos sensibles).
  rubro         ENUM('general','salud') NOT NULL DEFAULT 'general',
  plan_id       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  plan_estado   ENUM('activo','vencido','degradado_a_gratis') NOT NULL DEFAULT 'activo',
  plan_vence_en DATE         DEFAULT NULL,
  plan_ciclo    ENUM('mensual','anual') NOT NULL DEFAULT 'mensual',
  -- Sedes pagadas por encima de las incluidas en el plan (Pro: $19.900/mes
  -- cada una). El cupo de sedes es planes.sedes_incluidas + sedes_extra.
  sedes_extra   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- El equipo de Veci suspende una cuenta desde el panel interno (mora,
  -- abuso, solicitud del dueño). Suspendida: nadie de ese negocio puede
  -- iniciar sesión y sus tiendas públicas dejan de responder.
  suspendido    TINYINT(1)   NOT NULL DEFAULT 0,
  suspendido_en DATETIME     DEFAULT NULL,
  -- Código para invitar a otros negocios (ver tabla referidos).
  codigo_referido VARCHAR(16) DEFAULT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_negocios_codigo_referido (codigo_referido),
  FOREIGN KEY (plan_id) REFERENCES planes(id)
) ENGINE=InnoDB;

-- Precios actuales (ver docs/precios.html): el anual es "2 meses gratis"
-- (10 veces el mensual). limite_ia_mes cuenta usos reales de la API de Claude por negocio
-- (ver usos_ia); limite_pedidos_mes cuenta pedidos+citas creados por mes
-- calendario (ver Pedido::contarEsteMesPorNegocio / Cita::ídem).
INSERT INTO planes
  (id, nombre, precio_mensual, precio_anual, limite_pedidos_mes, limite_ia_mes,
   incluye_copiloto, incluye_estadisticas_completas, incluye_multisede, sedes_incluidas, precio_sede_extra)
VALUES
  (1, 'gratis', 0,      0,       50,   3,    0, 0, 0, 1, NULL),
  (2, 'barrio', 29900,  299000,  NULL, NULL, 1, 0, 0, 1, NULL),
  (3, 'pro',    69900,  699000,  NULL, NULL, 1, 1, 1, 3, 19900);

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
  -- Sube al cambiar o restablecer la contraseña: las sesiones con otra versión se cierran.
  sesion_version     INT UNSIGNED NOT NULL DEFAULT 0,
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
  -- Reglas de la agenda (imprevistos): colchón entre citas, minutos que se
  -- espera a quien llega tarde y qué pasa con el anticipo si no llega.
  colchon_min         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  tolerancia_min      TINYINT UNSIGNED NOT NULL DEFAULT 15,
  anticipo_no_asiste  ENUM('se_pierde','se_abona') NOT NULL DEFAULT 'se_pierde',
  -- Fila virtual para clientes sin cita: el dueño la abre y la cierra.
  fila_abierta        TINYINT(1)   NOT NULL DEFAULT 0,
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
  -- Pedido mínimo de la sede (0 = sin mínimo). Cada zona de domicilio
  -- puede pedir uno mayor (ver zonas_domicilio).
  pedido_minimo       INT UNSIGNED NOT NULL DEFAULT 0,
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
  -- Tiendas (fase 4): lo que le cuesta al tendero y cómo se vende. Por
  -- peso, precio y costo son POR KILO y stock va en GRAMOS.
  costo       INT UNSIGNED DEFAULT NULL,
  vende_por   ENUM('unidad','peso') NOT NULL DEFAULT 'unidad',
  categoria   VARCHAR(60)  NOT NULL DEFAULT 'General',
  descripcion VARCHAR(160) DEFAULT NULL,
  -- Código de barras (lector o cámara): único por sede cuando existe.
  codigo_barras VARCHAR(32) DEFAULT NULL,
  imagen      VARCHAR(255) DEFAULT NULL,
  color       CHAR(7)      NOT NULL DEFAULT '#5B7F3A',
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  agotado     TINYINT(1)   NOT NULL DEFAULT 0,
  -- "Agotado por hoy": vuelve solo al día siguiente (ver Producto::SELECT).
  agotado_hasta DATE       DEFAULT NULL,
  -- Inventario opcional: NULL = no se controla; cada pedido descuenta.
  stock       INT          DEFAULT NULL,
  orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_productos_sede (sede_id, activo),
  UNIQUE KEY uniq_producto_codigo_sede (sede_id, codigo_barras),
  INDEX idx_productos_codigo (codigo_barras)
) ENGINE=InnoDB;

-- La base de clientes del NEGOCIO (no de una sede sola): si un cliente
-- compra en dos sedes de la misma marca, es el mismo cliente para el
-- copiloto y para el historial. autorizo_datos guarda el check explícito
-- del checkout (Ley 1581 de 2012).
CREATE TABLE IF NOT EXISTS clientes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id      INT UNSIGNED NOT NULL,
  nombre          VARCHAR(120) NOT NULL,
  telefono        VARCHAR(20)  DEFAULT NULL, -- NULL: cliente del fiado sin WhatsApp
  autorizo_datos  TINYINT(1)   NOT NULL DEFAULT 0,
  autorizado_en   DATETIME     DEFAULT NULL,
  -- Opt-in SEPARADO del anterior: ese autoriza a guardar los datos para
  -- procesar el pedido (requerido); este es, además, querer recibir
  -- promociones (opcional). El checkout público los pide por separado.
  acepta_marketing TINYINT(1)  NOT NULL DEFAULT 0,
  -- Ver consentimientos (migración 32): cuándo cambió el permiso de
  -- promociones, el enlace sin login para que el cliente lo active o lo
  -- retire, y cuándo el negocio le pidió permiso (una sola vez).
  marketing_actualizado_en DATETIME DEFAULT NULL,
  token_preferencias CHAR(32)  DEFAULT NULL,
  permiso_pedido_en DATETIME   DEFAULT NULL,
  -- Tope de lo que se le fía (NULL = sin tope). Ver fiado_movimientos.
  fiado_limite    INT UNSIGNED DEFAULT NULL,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_cliente_por_negocio (negocio_id, telefono),
  UNIQUE KEY uniq_cliente_token_preferencias (token_preferencias)
) ENGINE=InnoDB;

-- Registro de consentimientos (Ley 1581): cada cambio con origen, versión
-- de la política e IP si lo hizo el cliente. Solo de agregar.
CREATE TABLE IF NOT EXISTS consentimientos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id       INT UNSIGNED NOT NULL,
  cliente_id       INT UNSIGNED NOT NULL,
  finalidad        ENUM('datos', 'marketing') NOT NULL,
  otorgado         TINYINT(1)   NOT NULL,
  origen           VARCHAR(20)  NOT NULL,
  politica_version VARCHAR(20)  NOT NULL,
  usuario_id       INT UNSIGNED DEFAULT NULL,
  ip               VARCHAR(45)  DEFAULT NULL,
  creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  INDEX idx_consentimientos_cliente (cliente_id, finalidad, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id       INT UNSIGNED NOT NULL,
  cliente_id    INT UNSIGNED NOT NULL,
  total         INT UNSIGNED NOT NULL,
  descuento     INT UNSIGNED NOT NULL DEFAULT 0,
  cupon_codigo  VARCHAR(20)  DEFAULT NULL,
  -- Lo cobrado de domicilio y la zona, copiados al pedir (ver zonas_domicilio).
  costo_domicilio INT UNSIGNED NOT NULL DEFAULT 0,
  zona_domicilio  VARCHAR(80)  DEFAULT NULL,
  -- Último estado del que se le avisó al cliente por WhatsApp.
  aviso_estado    VARCHAR(20)  DEFAULT NULL,
  metodo_pago   ENUM('breb','nequi','efectivo') NOT NULL DEFAULT 'breb',
  tipo_entrega  ENUM('domicilio','recoger','mesa') NOT NULL DEFAULT 'domicilio',
  direccion     VARCHAR(255) DEFAULT NULL,
  mesa          VARCHAR(20)  DEFAULT NULL,
  notas         VARCHAR(255) DEFAULT NULL,
  estado        ENUM('pendiente','pagado','en_cocina','listo','en_camino','entregado','cancelado')
                NOT NULL DEFAULT 'pendiente',
  -- Lo que descontó del inventario ({producto_id: unidades|gramos}); se devuelve eso al cancelar/anular.
  inventario_movido TEXT DEFAULT NULL,
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
  -- Si el producto iba por peso al pedirlo (1 = 1 kg): cancelar devuelve en esa unidad.
  por_peso          TINYINT(1)   NOT NULL DEFAULT 0,
  -- Pedido en línea por medias libras: los gramos pedidos (cantidad = 1 y
  -- precio_unitario = el precio de ese peso). NULL = por unidad o kilos.
  gramos            INT UNSIGNED DEFAULT NULL,
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
  -- 'fijo' ($20.000), 'desde' (desde $20.000) o 'rango' (hasta precio_max):
  -- muchos trabajos no tienen un precio exacto hasta verlos.
  precio_tipo   ENUM('fijo','desde','rango') NOT NULL DEFAULT 'fijo',
  precio_max    INT UNSIGNED DEFAULT NULL,
  duracion_min  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  color         CHAR(7)      NOT NULL DEFAULT '#5B7F3A',
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  agotado       TINYINT(1)   NOT NULL DEFAULT 0,
  deposito_tipo ENUM('ninguno','porcentaje','monto_fijo') NOT NULL DEFAULT 'ninguno',
  deposito_valor INT UNSIGNED NOT NULL DEFAULT 0,
  orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Servicio que se repite (mantenimiento cada N meses); NULL = no.
  repetir_cada_meses TINYINT UNSIGNED DEFAULT NULL,
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
  -- Lo que el cliente ve para elegir a "su" profesional.
  foto        VARCHAR(255) DEFAULT NULL,
  especialidad VARCHAR(80) DEFAULT NULL,
  bio         VARCHAR(240) DEFAULT NULL,
  -- % de comisión (NULL = no trabaja por comisión) y horario propio (mismo
  -- JSON que sedes.horario_atencion; NULL = el de la sede).
  comision_pct TINYINT UNSIGNED DEFAULT NULL,
  horario_atencion TEXT DEFAULT NULL,
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

-- "Se me complicó el día" de UNA persona del equipo: ese día no recibe
-- reservas ni reprogramaciones (antes solo se cerraba el día de todo el
-- negocio, y a la persona enferma la seguían agendando).
CREATE TABLE IF NOT EXISTS empleado_dias_libres (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empleado_id  INT UNSIGNED NOT NULL,
  fecha        DATE         NOT NULL,
  motivo       VARCHAR(120) DEFAULT NULL,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_dia_libre (empleado_id, fecha)
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
  -- Copiados del servicio: 'fijo', 'desde' o 'rango' (hasta precio_max).
  precio_tipo   ENUM('fijo','desde','rango') NOT NULL DEFAULT 'fijo',
  precio_max    INT UNSIGNED DEFAULT NULL,
  -- Lo que de verdad se cobró (al terminar o al aprobar un ajuste). NULL = precio.
  precio_final  INT UNSIGNED DEFAULT NULL,
  descuento     INT UNSIGNED NOT NULL DEFAULT 0,
  cupon_codigo  VARCHAR(20)  DEFAULT NULL,
  fecha_hora    DATETIME     NOT NULL,
  duracion_min  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  -- Duración real: de "Empezar" a "Terminar" en la agenda.
  iniciada_en   DATETIME     DEFAULT NULL,
  terminada_en  DATETIME     DEFAULT NULL,
  estado        ENUM('pendiente','confirmada','en_curso','completada','no_asistio','cancelada')
                NOT NULL DEFAULT 'pendiente',
  notas         VARCHAR(255) DEFAULT NULL,
  token_gestion CHAR(32)     DEFAULT NULL,
  recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
  aviso_estado  VARCHAR(20)  DEFAULT NULL,
  anticipo_monto  INT UNSIGNED NOT NULL DEFAULT 0,
  anticipo_estado ENUM('no_requerido','pendiente','pagado') NOT NULL DEFAULT 'no_requerido',
  -- Imprevistos: retraso avisado por el negocio o por el cliente (y si el
  -- cliente dijo que espera), cita que el negocio pidió mover por un
  -- imprevisto (se limpia al reprogramar), aviso de imprevisto pendiente de
  -- mandar ('retraso'|'reprogramar') y ajuste de precio que el cliente
  -- aprueba o no desde su enlace.
  retraso_negocio_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  retraso_cliente_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cliente_espera      TINYINT(1)   NOT NULL DEFAULT 0,
  imprevisto_motivo   VARCHAR(120) DEFAULT NULL,
  aviso_imprevisto    VARCHAR(20)  DEFAULT NULL,
  ajuste_precio       INT UNSIGNED DEFAULT NULL,
  ajuste_motivo       VARCHAR(200) DEFAULT NULL,
  ajuste_estado       ENUM('pendiente','aprobado','rechazado') DEFAULT NULL,
  -- "No vino" con regla "se abona": el cupón que recibió el cliente y el
  -- bono al que volvió la sesión (para mostrarlo y para poder deshacerlo).
  cupon_abono_id      INT UNSIGNED DEFAULT NULL,
  bono_devuelto_id    INT UNSIGNED DEFAULT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Visitas a domicilio (ver migrations/2026-10-03_16_visitas.sql).
  direccion            VARCHAR(200) DEFAULT NULL,
  direccion_referencia VARCHAR(160) DEFAULT NULL,
  zona_nombre          VARCHAR(80)  DEFAULT NULL,
  recargo_zona         INT UNSIGNED NOT NULL DEFAULT 0,
  problema             TEXT         DEFAULT NULL,
  franja_inicio        TIME         DEFAULT NULL,
  franja_fin           TIME         DEFAULT NULL,
  en_camino_en         DATETIME     DEFAULT NULL,
  llegada_estimada     DATETIME     DEFAULT NULL,
  recordar_repetir     TINYINT(1)   NOT NULL DEFAULT 0,
  repetir_avisado_en   DATETIME     DEFAULT NULL,
  -- Salud (ver migrations/2026-10-03_18_salud.sql): motivo con permiso de datos sensibles y plan de tratamiento.
  motivo_consulta       VARCHAR(500) DEFAULT NULL,
  autorizo_sensibles_en DATETIME     DEFAULT NULL,
  plan_id               INT UNSIGNED DEFAULT NULL,
  plan_fase_id          INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL,
  INDEX idx_citas_sede_fecha (sede_id, fecha_hora),
  INDEX idx_citas_plan (plan_id),
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
  -- 'plan' = pago o renovación del plan (con sus sedes extra);
  -- 'sede_extra' = agregar sedes a mitad de período (prorrateado).
  concepto       ENUM('plan','sede_extra') NOT NULL DEFAULT 'plan',
  sedes_extra    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  monto          INT UNSIGNED NOT NULL,
  metodo_pago    VARCHAR(30)  NOT NULL DEFAULT 'breb_manual',
  ciclo          ENUM('mensual','anual') NOT NULL DEFAULT 'mensual',
  periodo_inicio DATE         NOT NULL,
  periodo_fin    DATE         NOT NULL,
  -- NULL en confirmado_en = todavía esperando que un admin lo revise.
  confirmado_por INT UNSIGNED DEFAULT NULL,
  -- Transacción de la pasarela (Wompi) que pagó este plan, si fue así.
  transaccion_pasarela VARCHAR(64) DEFAULT NULL,
  confirmado_en  DATETIME     DEFAULT NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES planes(id),
  FOREIGN KEY (confirmado_por) REFERENCES admins(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_pago_transaccion (transaccion_pasarela),
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

-- Tarjeta de sellos (ver migrations/2026-10-03_02_fidelidad.sql).
-- Los sellos no se guardan: se cuentan de pedidos y citas desde que el

CREATE TABLE IF NOT EXISTS fidelidad (
  negocio_id     INT UNSIGNED PRIMARY KEY,
  activa         TINYINT(1)       NOT NULL DEFAULT 1,
  meta           TINYINT UNSIGNED NOT NULL DEFAULT 8,
  premio         VARCHAR(120)     NOT NULL,
  minimo_compra  INT UNSIGNED     NOT NULL DEFAULT 0,
  desde          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fidelidad_premios (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id   INT UNSIGNED     NOT NULL,
  cliente_id   INT UNSIGNED     NOT NULL,
  sellos       TINYINT UNSIGNED NOT NULL,
  premio       VARCHAR(120)     NOT NULL,
  usuario_id   INT UNSIGNED     DEFAULT NULL,
  entregado_en DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  INDEX idx_fidelidad_premios_cliente (negocio_id, cliente_id)
) ENGINE=InnoDB;

-- Zonas de domicilio por sede (ver migrations/2026-10-03_03_zonas_domicilio.sql).
CREATE TABLE IF NOT EXISTS zonas_domicilio (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id        INT UNSIGNED     NOT NULL,
  nombre         VARCHAR(80)      NOT NULL,
  costo          INT UNSIGNED     NOT NULL DEFAULT 0,
  minimo_pedido  INT UNSIGNED     NOT NULL DEFAULT 0,
  activa         TINYINT(1)       NOT NULL DEFAULT 1,
  creado_en      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_zonas_sede (sede_id, activa)
) ENGINE=InnoDB;

-- Combos (ver migrations/2026-10-03_05_combos.sql).
CREATE TABLE IF NOT EXISTS combo_items (
  combo_id    INT UNSIGNED     NOT NULL,
  producto_id INT UNSIGNED     NOT NULL,
  cantidad    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (combo_id, producto_id),
  FOREIGN KEY (combo_id) REFERENCES productos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cierre de caja diario (ver migrations/2026-10-03_06_cierres_caja.sql).
CREATE TABLE IF NOT EXISTS cierres_caja (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id           INT UNSIGNED NOT NULL,
  fecha             DATE         NOT NULL,
  ventas_total      INT          NOT NULL DEFAULT 0,
  base              INT          NOT NULL DEFAULT 0,
  efectivo_esperado INT          NOT NULL DEFAULT 0,
  efectivo_contado  INT          NOT NULL DEFAULT 0,
  diferencia        INT          NOT NULL DEFAULT 0,
  resumen           TEXT         NOT NULL,
  notas             VARCHAR(255) DEFAULT NULL,
  usuario_id        INT UNSIGNED DEFAULT NULL,
  creado_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_cierre_sede_fecha (sede_id, fecha)
) ENGINE=InnoDB;

-- Reseñas (ver migrations/2026-10-03_07_resenas.sql).
CREATE TABLE IF NOT EXISTS resenas (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id         INT UNSIGNED     NOT NULL,
  sede_id            INT UNSIGNED     NOT NULL,
  cliente_id         INT UNSIGNED     NOT NULL,
  pedido_id          INT UNSIGNED     DEFAULT NULL,
  cita_id            INT UNSIGNED     DEFAULT NULL,
  token              CHAR(32)         NOT NULL,
  estrellas          TINYINT UNSIGNED DEFAULT NULL,
  comentario         VARCHAR(400)     DEFAULT NULL,
  comentario_oculto  TINYINT(1)       NOT NULL DEFAULT 0,
  pedida_en          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  respondida_en      DATETIME         DEFAULT NULL,
  UNIQUE KEY uniq_resena_token (token),
  UNIQUE KEY uniq_resena_pedido (pedido_id),
  UNIQUE KEY uniq_resena_cita (cita_id),
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  INDEX idx_resenas_negocio (negocio_id, respondida_en)
) ENGINE=InnoDB;

-- Paquetes y bonos de sesiones (ver migrations/2026-10-03_08_paquetes_bonos.sql).
CREATE TABLE IF NOT EXISTS paquetes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id        INT UNSIGNED      NOT NULL,
  servicio_id    INT UNSIGNED      NOT NULL,
  sesiones       TINYINT UNSIGNED  NOT NULL,
  precio         INT UNSIGNED      NOT NULL,
  vigencia_dias  SMALLINT UNSIGNED DEFAULT NULL,
  activo         TINYINT(1)        NOT NULL DEFAULT 1,
  creado_en      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bonos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id         INT UNSIGNED     NOT NULL,
  paquete_id      INT UNSIGNED     DEFAULT NULL,
  -- Garantía o retoque: bono de 1 sesión a $0 ligado a la cita original.
  garantia_de     INT UNSIGNED     DEFAULT NULL,
  cliente_id      INT UNSIGNED     NOT NULL,
  servicio_id     INT UNSIGNED     DEFAULT NULL,
  nombre_servicio VARCHAR(120)     NOT NULL,
  sesiones_total  TINYINT UNSIGNED NOT NULL,
  precio_pagado   INT UNSIGNED     NOT NULL,
  vence_en        DATE             DEFAULT NULL,
  token           CHAR(32)         NOT NULL,
  usuario_id      INT UNSIGNED     DEFAULT NULL,
  creado_en       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_bono_token (token),
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (paquete_id) REFERENCES paquetes(id) ON DELETE SET NULL,
  CONSTRAINT fk_bonos_garantia FOREIGN KEY (garantia_de) REFERENCES citas(id) ON DELETE SET NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  INDEX idx_bonos_cliente (sede_id, cliente_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bono_usos (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bono_id   INT UNSIGNED NOT NULL,
  cita_id   INT UNSIGNED NOT NULL,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_bono_uso_cita (cita_id),
  FOREIGN KEY (bono_id) REFERENCES bonos(id) ON DELETE CASCADE,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Referidos entre negocios (ver migrations/2026-10-03_11_referidos.sql).
CREATE TABLE IF NOT EXISTS referidos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referidor_id  INT UNSIGNED NOT NULL,
  referido_id   INT UNSIGNED NOT NULL,
  dias_premio   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  premiado_en   DATETIME     DEFAULT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_referido (referido_id),
  FOREIGN KEY (referidor_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (referido_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Profesionales, adicionales y fila virtual (ver migrations/2026-10-03_14_profesionales.sql).
CREATE TABLE IF NOT EXISTS empleado_fotos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empleado_id INT UNSIGNED NOT NULL,
  ruta        VARCHAR(255) NOT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
  INDEX idx_empleado_fotos (empleado_id)
) ENGINE=InnoDB;

-- Qué servicios hace cada profesional, con precio y duración propios si
-- difieren (NULL = los del servicio). Un profesional SIN filas aquí hace
-- todos los servicios al precio normal.
CREATE TABLE IF NOT EXISTS empleado_servicios (
  empleado_id  INT UNSIGNED NOT NULL,
  servicio_id  INT UNSIGNED NOT NULL,
  precio       INT UNSIGNED DEFAULT NULL,
  duracion_min SMALLINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (empleado_id, servicio_id),
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Adicionales que se suman al reservar (barba, cejas, mascarilla): precio y
-- minutos extra. servicio_id NULL = se ofrece con todos los servicios.
CREATE TABLE IF NOT EXISTS adicionales (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id      INT UNSIGNED NOT NULL,
  servicio_id  INT UNSIGNED DEFAULT NULL,
  nombre       VARCHAR(80)  NOT NULL,
  precio       INT UNSIGNED NOT NULL,
  duracion_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
  INDEX idx_adicionales_sede (sede_id)
) ENGINE=InnoDB;

-- Lo que se sumó a cada cita (copiado: si el adicional cambia o se borra, la
-- cita guarda lo que se reservó). citas.precio y duracion_min ya los incluyen.
CREATE TABLE IF NOT EXISTS cita_adicionales (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cita_id      INT UNSIGNED NOT NULL,
  adicional_id INT UNSIGNED DEFAULT NULL,
  nombre       VARCHAR(80)  NOT NULL,
  precio       INT UNSIGNED NOT NULL,
  duracion_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  FOREIGN KEY (adicional_id) REFERENCES adicionales(id) ON DELETE SET NULL,
  INDEX idx_cita_adicionales (cita_id)
) ENGINE=InnoDB;

-- Fila virtual para clientes sin cita (la barbería del barrio vive de quien
-- llega sin avisar). sedes.fila_abierta: el dueño la abre y la cierra.

CREATE TABLE IF NOT EXISTS turnos_fila (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id      INT UNSIGNED NOT NULL,
  cliente_id   INT UNSIGNED NOT NULL,
  servicio_id  INT UNSIGNED DEFAULT NULL,
  empleado_id  INT UNSIGNED DEFAULT NULL,
  estado       ENUM('esperando','llamado','atendido','se_fue') NOT NULL DEFAULT 'esperando',
  token        CHAR(32)     NOT NULL,
  cita_id      INT UNSIGNED DEFAULT NULL,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  llamado_en   DATETIME     DEFAULT NULL,
  cerrado_en   DATETIME     DEFAULT NULL,
  UNIQUE KEY uniq_turno_token (token),
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE SET NULL,
  INDEX idx_turnos_sede (sede_id, estado, creado_en)
) ENGINE=InnoDB;

-- Tiendas (fase 4), ver migrations/2026-10-03_21_ventas_mostrador.sql.
-- Venta de mostrador: lo que se vende en el local (con lector de códigos o
-- buscando por nombre), sin pasar por la tienda en línea. Descuenta el
-- mismo inventario que los pedidos y entra al cierre de caja por método.
--
-- recibido/cambio solo para efectivo ("¿con cuánto paga?"); cliente_id
-- solo si es fiado. token: el del formulario que la creó, único, para que
-- un doble toque en "Cobrar" no registre la venta dos veces.
-- Anular (solo el dueño, el mismo día) devuelve el inventario y, si fue
-- fiado, anula el cargo; la venta queda en el historial marcada.

CREATE TABLE IF NOT EXISTS ventas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id     INT UNSIGNED NOT NULL,
  total       INT UNSIGNED NOT NULL,
  metodo      ENUM('efectivo','nequi','breb','fiado') NOT NULL,
  recibido    INT UNSIGNED DEFAULT NULL,
  cambio      INT UNSIGNED DEFAULT NULL,
  cliente_id  INT UNSIGNED DEFAULT NULL,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  token       CHAR(32)     DEFAULT NULL,
  anulada     TINYINT(1)   NOT NULL DEFAULT 0,
  anulada_en  DATETIME     DEFAULT NULL,
  anulada_por INT UNSIGNED DEFAULT NULL,
  -- Lo que descontó del inventario ({producto_id: unidades|gramos}); se devuelve eso al cancelar/anular.
  inventario_movido TEXT DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (anulada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_ventas_token (token),
  INDEX idx_ventas_sede_fecha (sede_id, creado_en)
) ENGINE=InnoDB;

-- cantidad: unidades, o KILOS si el producto se vende por peso (0,250 =
-- 250 g). precio_unitario es el de la unidad o el del kilo; costo_unitario
-- es el costo que tenía el producto al vender (para el margen real, aunque
-- el costo cambie después). nombre y por_peso se copian: borrar o editar el
-- producto no cambia el tiquete.
CREATE TABLE IF NOT EXISTS venta_items (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venta_id        INT UNSIGNED  NOT NULL,
  producto_id     INT UNSIGNED  DEFAULT NULL,
  nombre          VARCHAR(120)  NOT NULL,
  por_peso        TINYINT(1)    NOT NULL DEFAULT 0,
  cantidad        DECIMAL(10,3) NOT NULL,
  precio_unitario INT UNSIGNED  NOT NULL,
  costo_unitario  INT UNSIGNED  DEFAULT NULL,
  subtotal        INT UNSIGNED  NOT NULL,
  FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tiendas (fase 4), ver migrations/2026-10-03_22_fiado.sql.
-- Fiado (la cuenta del cliente en la tienda). Es del NEGOCIO, como el
-- cliente: lo que se fió en una sede se puede abonar en otra.
-- Saldo = cargos − abonos (sin contar los anulados). Un cargo nace de una
-- venta de mostrador fiada (venta_id) o a mano, con nota. sede_id dice en
-- qué local entró el abono: un abono en efectivo suma a la caja de ESA sede.
-- fiado_limite: tope opcional de lo que se le fía a un cliente (lo pone el
-- dueño; la venta de mostrador no deja pasarlo).

CREATE TABLE IF NOT EXISTS fiado_movimientos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  sede_id     INT UNSIGNED DEFAULT NULL,
  cliente_id  INT UNSIGNED NOT NULL,
  tipo        ENUM('cargo','abono') NOT NULL,
  monto       INT UNSIGNED NOT NULL,
  venta_id    INT UNSIGNED DEFAULT NULL,
  metodo      ENUM('efectivo','nequi','breb') DEFAULT NULL,
  nota        VARCHAR(160) DEFAULT NULL,
  anulado     TINYINT(1)   NOT NULL DEFAULT 0,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE SET NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE SET NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_fiado_cliente (negocio_id, cliente_id, creado_en),
  INDEX idx_fiado_sede_fecha (sede_id, creado_en)
) ENGINE=InnoDB;

-- Cada recordatorio de pago que se abrió por WhatsApp. Ley 2300 de 2023:
-- como mucho uno por cliente a la semana (se revisa aquí) y solo en el
-- horario permitido (ver App\Services\HorarioCobro).
CREATE TABLE IF NOT EXISTS fiado_recordatorios (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT UNSIGNED NOT NULL,
  cliente_id  INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  saldo       INT UNSIGNED NOT NULL,
  enviado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_recordatorio_cliente (cliente_id, enviado_en)
) ENGINE=InnoDB;

-- Tiendas (fase 4), ver migrations/2026-10-03_23_compras.sql.
-- Compras a proveedor: la llegada del pedido del distribuidor. Suma al
-- inventario y deja como costo del producto el último costo pagado.
-- cantidad: unidades, o KILOS si el producto se vende por peso.
-- stock_antes: lo que había antes de sumar (NULL = el producto no llevaba
-- inventario y empezó a llevarlo con esta compra).

CREATE TABLE IF NOT EXISTS compras (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id     INT UNSIGNED NOT NULL,
  proveedor   VARCHAR(120) NOT NULL,
  total       INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  token       CHAR(32)     DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_compras_token (token),
  INDEX idx_compras_sede_fecha (sede_id, creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS compra_items (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compra_id       INT UNSIGNED  NOT NULL,
  producto_id     INT UNSIGNED  DEFAULT NULL,
  nombre          VARCHAR(120)  NOT NULL,
  por_peso        TINYINT(1)    NOT NULL DEFAULT 0,
  cantidad        DECIMAL(10,3) NOT NULL,
  costo_unitario  INT UNSIGNED  NOT NULL,
  subtotal        INT UNSIGNED  NOT NULL,
  stock_antes     INT           DEFAULT NULL,
  FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tiendas (fase 4), ver migrations/2026-10-03_24_codigos_barras.sql.
-- Catálogo compartido de códigos de barras entre todas las tiendas Veci:
-- solo el código y cómo se llama el producto (nunca precios, costos ni
-- nada de la tienda que lo registró). Cuando una tienda escanea un código
-- que no tiene, se le sugiere el nombre ("Otras tiendas lo llaman: …").
-- Solo entran códigos de producto reales (GTIN con dígito de control
-- válido), no los internos de la balanza ni los inventados por la tienda.

CREATE TABLE IF NOT EXISTS codigos_barras (
  codigo          VARCHAR(32)  NOT NULL PRIMARY KEY,
  nombre          VARCHAR(120) NOT NULL,
  veces_usado     INT UNSIGNED NOT NULL DEFAULT 1,
  actualizado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Fotos de visitas a domicilio (ver migrations/2026-10-03_16_visitas.sql), guardadas fuera de public/.
CREATE TABLE IF NOT EXISTS cita_fotos (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cita_id   INT UNSIGNED NOT NULL,
  archivo   VARCHAR(80)  NOT NULL,
  momento   ENUM('cliente','antes','despues') NOT NULL,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  INDEX idx_cita_fotos (cita_id, momento)
) ENGINE=InnoDB;

-- Cotizaciones por ítems (ver migrations/2026-10-03_17_cotizaciones.sql).
CREATE TABLE IF NOT EXISTS cotizaciones (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id         INT UNSIGNED NOT NULL,
  cita_id         INT UNSIGNED NOT NULL,
  token           CHAR(32)     NOT NULL,
  estado          ENUM('enviada','aprobada','rechazada','reemplazada') NOT NULL DEFAULT 'enviada',
  total           INT UNSIGNED NOT NULL DEFAULT 0,
  anticipo        INT UNSIGNED NOT NULL DEFAULT 0,
  anticipo_pagado TINYINT(1)   NOT NULL DEFAULT 0,
  garantia_dias   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  validez_dias    TINYINT UNSIGNED NOT NULL DEFAULT 8,
  nota            VARCHAR(500) DEFAULT NULL,
  respondida_en   DATETIME     DEFAULT NULL,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cotizacion_token (token),
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE,
  INDEX idx_cotizaciones_cita (cita_id, estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cotizacion_items (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cotizacion_id  INT UNSIGNED NOT NULL,
  tipo           ENUM('mano_obra','material','otro','descuento') NOT NULL,
  descripcion    VARCHAR(160) NOT NULL,
  cantidad       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  valor_unitario INT UNSIGNED NOT NULL,
  FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Planes de tratamiento (ver migrations/2026-10-03_18_salud.sql).
CREATE TABLE IF NOT EXISTS planes_tratamiento (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sede_id       INT UNSIGNED NOT NULL,
  cliente_id    INT UNSIGNED NOT NULL,
  token         CHAR(32)     NOT NULL,
  titulo        VARCHAR(120) NOT NULL,
  estado        ENUM('propuesto','aprobado','rechazado','terminado','cancelado') NOT NULL DEFAULT 'propuesto',
  total         INT UNSIGNED NOT NULL DEFAULT 0,
  validez_dias  TINYINT UNSIGNED NOT NULL DEFAULT 30,
  nota          VARCHAR(500) DEFAULT NULL,
  saldo_recordado_en DATETIME DEFAULT NULL,
  respondido_en DATETIME     DEFAULT NULL,
  -- Por dónde se aprobó: el enlace del paciente o en el consultorio (y quién lo marcó).
  aprobado_canal ENUM('enlace','consultorio') DEFAULT NULL,
  aprobado_por  INT UNSIGNED DEFAULT NULL,
  -- Plan del que nació (rehecho de uno vencido, rechazado o cancelado).
  rehecho_de    INT UNSIGNED DEFAULT NULL,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_plan_token (token),
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_planes_aprobado_por FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_planes_rehecho_de FOREIGN KEY (rehecho_de) REFERENCES planes_tratamiento(id) ON DELETE SET NULL,
  INDEX idx_planes_sede (sede_id, estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plan_fases (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id     INT UNSIGNED NOT NULL,
  orden       TINYINT UNSIGNED NOT NULL,
  nombre      VARCHAR(120) NOT NULL,
  sesiones    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  valor       INT UNSIGNED NOT NULL,
  FOREIGN KEY (plan_id) REFERENCES planes_tratamiento(id) ON DELETE CASCADE,
  INDEX idx_plan_fases (plan_id, orden)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plan_abonos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id     INT UNSIGNED NOT NULL,
  sede_id     INT UNSIGNED NOT NULL,
  monto       INT UNSIGNED NOT NULL,
  metodo      ENUM('efectivo','nequi','breb','tarjeta') NOT NULL DEFAULT 'efectivo',
  nota        VARCHAR(160) DEFAULT NULL,
  anulado     TINYINT(1)   NOT NULL DEFAULT 0,
  usuario_id  INT UNSIGNED DEFAULT NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plan_id) REFERENCES planes_tratamiento(id) ON DELETE CASCADE,
  FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,
  INDEX idx_plan_abonos_sede (sede_id, creado_en)
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
  ('2026-10-03_01_cupones.sql'),
  ('2026-10-03_02_fidelidad.sql'),
  ('2026-10-03_03_zonas_domicilio.sql'),
  ('2026-10-03_04_inventario.sql'),
  ('2026-10-03_05_combos.sql'),
  ('2026-10-03_06_cierres_caja.sql'),
  ('2026-10-03_07_resenas.sql'),
  ('2026-10-03_08_paquetes_bonos.sql'),
  ('2026-10-03_09_avisos_estado.sql'),
  ('2026-10-03_10_pasarela_wompi.sql'),
  ('2026-10-03_11_referidos.sql'),
  ('2026-10-03_12_sedes_extra.sql'),
  ('2026-10-03_13_imprevistos.sql'),
  ('2026-10-03_14_profesionales.sql'),
  ('2026-10-03_15_no_asistio_abono.sql'),
  ('2026-10-03_20_productos_tienda.sql'),
  ('2026-10-03_21_ventas_mostrador.sql'),
  ('2026-10-03_22_fiado.sql'),
  ('2026-10-03_23_compras.sql'),
  ('2026-10-03_24_codigos_barras.sql'),
  ('2026-10-03_16_visitas.sql'),
  ('2026-10-03_17_cotizaciones.sql'),
  ('2026-10-03_25_pedido_items_por_peso.sql'),
  ('2026-10-03_18_salud.sql'),
  ('2026-10-03_26_precios_asequibles.sql'),
  ('2026-10-03_27_tienda_libras_fiado.sql'),
  ('2026-10-03_28_planes_presencial.sql'),
  ('2026-10-03_29_sesion_version.sql'),
  ('2026-10-03_30_inventario_movido.sql'),
  ('2026-10-03_31_dias_libres_empleado.sql'),
  ('2026-10-03_32_consentimientos.sql');
