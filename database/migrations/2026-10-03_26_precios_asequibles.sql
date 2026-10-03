-- Precios para competir en el barrio (ver docs/precios.html):
-- Barrio $29.900/mes, Pro $69.900/mes, sede extra de Pro $19.900/mes.
-- El anual es "2 meses gratis": 10 veces el mensual (también para las
-- sedes extra, ver Plan::precio). Los negocios con un período ya pagado lo
-- conservan; el precio nuevo aplica al renovar.
UPDATE planes SET precio_mensual = 29900, precio_anual = 299000 WHERE nombre = 'barrio';
UPDATE planes SET precio_mensual = 69900, precio_anual = 699000, precio_sede_extra = 19900 WHERE nombre = 'pro';
