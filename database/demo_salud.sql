-- Demo de salud: "Sonrisa Dental", consultorio odontológico en Bucaramanga.
-- Se puede correr sobre una base que ya existe (no usa ids fijos y no hace
-- nada si la tienda ya está):
--   mysql veci < database/demo_salud.sql
-- Acceso: WhatsApp 3009990011 · contraseña veci123 · tienda /t/sonrisadental

SET NAMES utf8mb4;
SET @ya := (SELECT COUNT(*) FROM sedes WHERE slug = 'sonrisadental');

INSERT INTO negocios (nombre, color_marca, tipo_negocio, modalidad, rubro)
SELECT 'Sonrisa Dental', '#1F9AA6', 'reservas', 'local', 'salud' FROM DUAL WHERE @ya = 0;
SET @n := IF(@ya = 0, LAST_INSERT_ID(), NULL);

INSERT INTO usuarios (negocio_id, nombre, whatsapp, password_hash, rol)
SELECT @n, 'Sonrisa Dental', '3009990011', '$2y$12$Rp/Uc3/Mg/f0A7wwOH/7w.OrB97It.nmx2n1sExSPVsAdf6Q/cQFW', 'dueno' FROM DUAL WHERE @n IS NOT NULL;

INSERT INTO sedes
  (negocio_id, slug, nombre, descripcion, whatsapp, inicial, llave_breb_tipo, llave_breb_valor, direccion,
   horario_atencion, intervalo_citas_min, colchon_min, publicada)
SELECT @n, 'sonrisadental', 'Sonrisa Dental', 'Odontología general y ortodoncia · Cabecera, Bucaramanga',
   '3009990011', 'S', 'celular', '3009990011', 'Carrera 33 # 48-20, consultorio 302, Bucaramanga',
   '{"1":[["08:00","12:00"],["14:00","18:00"]],"2":[["08:00","12:00"],["14:00","18:00"]],"3":[["08:00","12:00"],["14:00","18:00"]],"4":[["08:00","12:00"],["14:00","18:00"]],"5":[["08:00","12:00"],["14:00","18:00"]],"6":[["08:00","12:00"]]}',
   20, 10, 1
FROM DUAL WHERE @n IS NOT NULL;
SET @s := IF(@n IS NOT NULL, LAST_INSERT_ID(), NULL);

INSERT INTO servicios (sede_id, nombre, precio, precio_tipo, precio_max, duracion_min, color, orden, repetir_cada_meses)
SELECT @s, 'Valoración', 60000, 'fijo', NULL, 40, '#1F9AA6', 1, NULL FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Limpieza y profilaxis', 120000, 'fijo', NULL, 60, '#3F8F4E', 2, 6 FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Control de ortodoncia', 80000, 'fijo', NULL, 20, '#3B4CCA', 3, NULL FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Resina', 150000, 'desde', NULL, 60, '#F28C28', 4, NULL FROM DUAL WHERE @s IS NOT NULL;

INSERT INTO empleados (sede_id, nombre, especialidad)
SELECT @s, 'Dra. Paula Méndez', 'Ortodoncia' FROM DUAL WHERE @s IS NOT NULL
UNION ALL SELECT @s, 'Dr. Camilo Ardila', 'Odontología general' FROM DUAL WHERE @s IS NOT NULL;
