/* Asistente de Veci: responde dudas y guía hacia el registro.
   Corre 100% en el navegador: no llama a ninguna API, no envía ni guarda en
   servidores lo que escribe el visitante. Todo el texto del usuario entra al
   DOM con textContent, nunca con innerHTML. */
(function () {
  'use strict';

  var REGISTRO = 'https://app.tuveci.co/registro';
  var SOPORTE = 'soporte@tuveci.co';

  // La app es quien valida el código (un uso por negocio nuevo, vigencia,
  // cupo total). Lo de aquí es solo fricción: quien borre su navegador ve la
  // oferta otra vez, pero el backend no debe aceptarla dos veces.
  var OFERTA = {
    activa: true,
    codigo: 'VECICHAT30',
    titulo: '30% de descuento en tu primer mes',
    detalle: 'Aplica al plan Barrio o Pro, pago mensual. Solo para negocios nuevos, un uso por negocio.',
    horasVigencia: 72,
    minMensajes: 3,
    minPuntos: 5,
    minSegundos: 40
  };

  var MAX_MENSAJES = 40;
  var MAX_LARGO = 300;
  var PAUSA_MIN_MS = 700;

  var CLAVE_SESION = 'veci-chat-v1';
  var CLAVE_OFERTA = 'veci-chat-oferta';
  var CLAVE_TEASER = 'veci-chat-teaser';

  var script = document.currentScript;
  var BASE = script ? script.getAttribute('src').replace(/assets\/js\/veci-chat\.js.*$/, '') : '';
  function ruta(p) { return /^https?:|^mailto:/.test(p) ? p : BASE + p; }

  function leer(store, clave) {
    try { var v = window[store].getItem(clave); return v ? JSON.parse(v) : null; } catch (e) { return null; }
  }
  function guardar(store, clave, valor) {
    try { window[store].setItem(clave, JSON.stringify(valor)); } catch (e) {}
  }

  function evento(nombre, datos) {
    var detalle = { evento: nombre };
    for (var k in datos || {}) detalle[k] = datos[k];
    try { window.dispatchEvent(new CustomEvent('veci:chat', { detail: detalle })); } catch (e) {}
    if (Array.isArray(window.dataLayer)) {
      var capa = { event: 'veci_chat_' + nombre };
      for (var j in datos || {}) capa[j] = datos[j];
      window.dataLayer.push(capa);
    }
  }

  function normalizar(t) {
    return t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9$.,%\s]/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function cop(n) {
    return '$' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  // ---------------------------------------------------------------------
  // Base de conocimiento. Solo hechos publicados en el sitio o en PRODUCT.md;
  // lo que no está aquí se responde con "no sé" + contacto, nunca inventado.
  // peso 2 = intención comercial alta (cuenta doble hacia la oferta).
  // ---------------------------------------------------------------------
  var C = {
    precios: { t: 'Precios y planes', v: 'precios' },
    gratis: { t: '¿Qué trae el plan Gratis?', v: 'gratis' },
    recomendar: { t: '¿Qué plan me sirve?', v: '#plan' },
    ahorro: { t: 'Calcular mi ahorro vs. Rappi', v: '#ahorro' },
    breb: { t: '¿Cómo cobro por Bre-B?', v: 'breb' },
    ia: { t: '¿Cómo funciona lo de la foto?', v: 'ia' },
    copiloto: { t: 'El copiloto de recompra', v: 'copiloto' },
    reservas: { t: 'Agendar citas', v: 'reservas' },
    demo: { t: 'Ver una demo', v: 'demo' },
    empezar: { t: 'Quiero empezar', v: 'empezar' },
    sedes: { t: 'Tengo varios locales', v: 'sedes' },
    permanencia: { t: '¿Hay contrato?', v: 'permanencia' },
    comision: { t: '¿Cobran comisión?', v: 'comision' }
  };

  var KB = [
    { id: 'saludo', keys: ['hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches', 'hey', 'que tal', 'saludos'],
      r: ['¡Hola! Soy el asistente de Veci. Te respondo sobre precios, cobros, la IA que arma tu catálogo, citas y más.', '¿Por dónde quieres empezar?'],
      chips: [C.recomendar, C.ahorro, C.precios, C.demo] },
    { id: 'gracias', keys: ['gracias', 'muchas gracias', 'listo gracias', 'perfecto', 'genial', 'excelente', 'chevere', 'bacano'],
      r: ['Con gusto. Si te queda otra duda, aquí sigo.'],
      chips: [C.empezar, C.demo] },
    { id: 'adios', keys: ['chao', 'adios', 'hasta luego', 'nos vemos', 'bye'],
      r: ['¡Que te vaya muy bien con el negocio! Cuando quieras, empiezas gratis y sin tarjeta.'],
      links: [{ t: 'Empezar gratis', h: REGISTRO, cta: true }] },
    { id: 'que-es', keys: ['que es veci', 'que hace veci', 'que es esto', 'como funciona veci', 'de que se trata', 'para que sirve', 'que ofrecen', 'veci'],
      r: ['Veci es el mostrador digital de tu negocio: una tienda o agenda con tu propio enlace, donde tu cliente pide o agenda y el pedido te llega listo a WhatsApp.',
          'Cobras por Bre-B, Nequi o efectivo con **0% de comisión**, y cada mañana el copiloto te dice a qué cliente escribirle para que vuelva.'],
      chips: [C.ia, C.precios, C.demo] },
    { id: 'comision', peso: 2, keys: ['comision', 'porcentaje', 'cobran por venta', 'cobran por pedido', 'cuanto me quitan', 'se quedan con', 'cobran algo por cada', 'de comision'],
      r: ['**0% de comisión, siempre.** Pagas un plan fijo (desde $0) y nunca un porcentaje de lo que vendes. Lo que cobras por Bre-B, Nequi o efectivo te llega completo a ti.',
          'Para comparar: las apps de domicilio cobran entre 25% y 30% de cada pedido.'],
      chips: [C.ahorro, C.precios] },
    { id: 'precios', peso: 2, keys: ['precio', 'precios', 'cuanto cuesta', 'cuanto vale', 'cuanto sale', 'cuanto cobran', 'tarifa', 'planes', 'plan', 'mensualidad', 'valor', 'costo', 'cuesta'],
      r: ['Tres planes, todos con 0% de comisión:',
          '**Gratis — $0.** Hasta 50 pedidos o citas al mes y 3 fotos leídas con IA. No vence.',
          '**Barrio — $59.000/mes.** Sin límite de pedidos, IA sin límite, copiloto de recompra y sin la marca "Hecho con Veci".',
          '**Pro — $129.000/mes.** Todo lo de Barrio, hasta 3 sedes, estadísticas completas y soporte prioritario.',
          'Pagando anual te ahorras 2 meses: Barrio queda en $49.000/mes y Pro en $108.000/mes.'],
      links: [{ t: 'Comparar planes en detalle', h: 'precios.html' }],
      chips: [C.recomendar, C.ahorro, C.permanencia] },
    { id: 'gratis', peso: 2, keys: ['gratis', 'plan gratis', 'free', 'sin pagar', 'prueba gratis', 'periodo de prueba', 'trial', 'gratuito'],
      r: ['El plan Gratis es gratis para siempre, no es una prueba que vence. Incluye:',
          'Hasta 50 pedidos o citas al mes, hasta 3 fotos leídas con IA al mes, tu tienda con enlace propio, cobro por Bre-B, Nequi o efectivo, y las herramientas de crecimiento (cupones, tarjeta de sellos, reseñas, combos y más).',
          'Tu tienda muestra la marca "Hecho con Veci". No pedimos tarjeta para registrarte.'],
      links: [{ t: 'Empezar gratis', h: REGISTRO, cta: true }],
      chips: [C.recomendar, { t: '¿Y si paso de 50 pedidos?', v: 'limite' }] },
    { id: 'limite', keys: ['paso de 50', 'mas de 50', 'supero', 'limite', 'se acaba', 'me cortan', 'llego al tope', 'tope'],
      r: ['Te avisamos para que subas de plan. No cortamos tu tienda de un momento a otro ni escondemos los pedidos que ya recibiste.',
          'Subes desde tu panel cuando lo necesites, sin perder tu historial.'],
      chips: [C.precios, C.recomendar] },
    { id: 'barrio', peso: 2, keys: ['plan barrio', 'barrio'],
      r: ['**Barrio — $59.000/mes** ($49.000/mes si pagas anual). Es el más elegido.',
          'Incluye todo lo de Gratis sin el límite de 50 pedidos, fotos leídas con IA sin límite, el copiloto de recompra con mensajes listos para WhatsApp y tu tienda sin la marca "Hecho con Veci".'],
      chips: [{ t: '¿Y el plan Pro?', v: 'pro' }, C.copiloto, C.empezar] },
    { id: 'pro', peso: 2, keys: ['plan pro', 'pro'],
      r: ['**Pro — $129.000/mes** ($108.000/mes si pagas anual).',
          'Todo lo de Barrio, más: hasta 3 sedes incluidas ($30.000/mes cada sede extra), historial completo con panel de estadísticas y soporte prioritario por WhatsApp.'],
      chips: [C.sedes, C.empezar] },
    { id: 'anual', peso: 2, keys: ['anual', 'pago anual', 'por ano', 'al ano', 'descuento anual', 'meses gratis'],
      r: ['Pagando anual te regalamos 2 meses: Barrio queda en $49.000/mes y Pro en $108.000/mes, facturado una vez al año.'],
      chips: [C.precios, C.permanencia] },
    { id: 'permanencia', keys: ['permanencia', 'contrato', 'cancelar', 'cancelo', 'clausula', 'amarrado', 'me puedo salir', 'darme de baja', 'retirarme', 'exportar'],
      r: ['Sin permanencia ni contrato. Cancelas cuando quieras, y cambiar o bajar de plan no tiene costo ni penalidad.',
          'Tus datos son tuyos: los puedes exportar en cualquier momento, en cualquier plan.'],
      chips: [C.precios, C.empezar] },
    { id: 'breb', keys: ['bre b', 'breb', 'bre-b', 'llave', 'cobrar', 'cobro', 'como me pagan', 'como cobro', 'transferencia', 'pagos', 'como pagan', 'metodos de pago', 'medios de pago'],
      r: ['Tu cliente te paga con tu llave Bre-B (celular, cédula o correo) desde cualquier banco o billetera participante, en menos de 20 segundos y sin comisión de tarjeta.',
          'Hoy la confirmación es manual: ves el pago en tu banco y marcas el pedido como pagado. También puedes aceptar Nequi (con comprobante) o efectivo contraentrega.'],
      links: [{ t: 'Qué es Bre-B, explicado', h: 'blog/que-es-bre-b.html' }],
      chips: [{ t: '¿Aceptan tarjeta?', v: 'tarjeta' }, C.comision] },
    { id: 'nequi', keys: ['nequi', 'efectivo', 'contraentrega', 'contra entrega', 'comprobante'],
      r: ['Sí. Además de Bre-B aceptas Nequi (tu cliente transfiere, te manda el comprobante y confirmas el pedido) y efectivo contraentrega. Tú decides qué métodos ofrecer.'],
      chips: [C.breb, C.precios] },
    { id: 'daviplata', keys: ['daviplata', 'davivienda', 'bancolombia'],
      r: ['No tenemos integraciones con bancos o billeteras específicas. Lo que sí funciona es Bre-B: si la app de tu cliente permite pagar a una llave Bre-B, te puede pagar desde ahí.', 'Además aceptas Nequi con comprobante y efectivo.'],
      chips: [C.breb] },
    { id: 'tarjeta', keys: ['tarjeta', 'credito', 'debito', 'pasarela', 'datafono', 'pse', 'wompi', 'mercado pago', 'bold', 'pago automatico', 'automatico'],
      r: ['La pasarela de tarjeta de crédito y débito está en camino (próximamente). Hoy cobras por Bre-B, Nequi o efectivo, que no tienen comisión de tarjeta.'],
      links: [{ t: 'Ver integraciones', h: 'integraciones.html' }],
      chips: [C.breb] },
    { id: 'ia', keys: ['foto', 'fotos', 'ia', 'inteligencia artificial', 'menu', 'carta', 'catalogo', 'subir productos', 'cargar productos', 'armar la tienda', 'claude', 'escanear'],
      r: ['Le tomas una foto a tu menú, tu carta, tu pizarra o tu lista de servicios, y la IA lee productos y precios y arma tu catálogo.',
          'Tú revisas, corriges lo que haga falta y publicas. Toma menos de 10 minutos. En el plan Gratis tienes 3 fotos al mes; en Barrio y Pro, sin límite.'],
      links: [{ t: 'Cómo funciona la foto + IA', h: 'blog/foto-menu-ia.html' }],
      chips: [C.demo, C.empezar] },
    { id: 'tiempo', keys: ['cuanto tarda', 'cuanto se demora', 'cuanto tiempo', 'que tan rapido', 'demora', 'configurar', 'montar la tienda', 'dificil', 'complicado', 'facil'],
      r: ['Menos de 10 minutos: foto de tu catálogo, revisas lo que armó la IA, eliges cómo cobrar y publicas. Sin curso de tecnología, todo desde el celular.'],
      chips: [C.ia, C.empezar] },
    { id: 'modos', keys: ['pedidos y reservas', 'los dos modos', 'ambos', 'pedidos o reservas', 'modo pedidos', 'modo reservas', 'modos'],
      r: ['Veci tiene dos modos: **pedidos** (catálogo con carrito, para comida y tiendas) y **reservas** (agenda con horario, para negocios que atienden por cita).',
          'Cada negocio usa uno de los dos. Si tienes ambos tipos de negocio, cada uno va en su propia tienda.'],
      chips: [C.reservas, C.recomendar] },
    { id: 'reservas', keys: ['cita', 'citas', 'agenda', 'agendar', 'reserva', 'reservas', 'turno', 'turnos', 'horario', 'peluqueria', 'barberia', 'spa', 'consultorio', 'odontologo', 'entrenador', 'anticipo'],
      r: ['En modo reservas tu cliente elige servicio, día y hora en tu enlace, y la cita te llega a WhatsApp.',
          'Configuras la duración de cada servicio, tus empleados y un anticipo si lo quieres. Si tienes varios profesionales, el cliente puede escoger con quién agendar. También vendes paquetes de sesiones que se descuentan solos.'],
      links: [{ t: 'Probar la demo de reservas', h: 'demo.html#reservas' }],
      chips: [C.recomendar, C.precios] },
    { id: 'copiloto', peso: 2, keys: ['copiloto', 'recompra', 'clientes que no vuelven', 'reactivar', 'fidelizar', 'que vuelvan', 'dejo de comprar', 'clientes perdidos', 'retener', 'no vuelven', 'no vuelve', 'volver a comprar'],
      r: ['Cada mañana el copiloto revisa quién lleva más tiempo del habitual sin pedir o agendar, comparando a cada cliente con su propio ritmo, no con un promedio.',
          'Te sugiere el mensaje y con un clic se abre WhatsApp con el texto listo. Está en los planes Barrio y Pro.'],
      links: [{ t: 'Cómo evitar que tus clientes no vuelvan', h: 'blog/como-evitar-que-tus-clientes-no-vuelvan.html' }],
      chips: [C.precios, C.empezar] },
    { id: 'sedes', peso: 2, keys: ['sedes', 'sede', 'sucursal', 'sucursales', 'varios locales', 'otro local', 'dos locales', 'multisede'],
      r: ['El plan Pro incluye hasta 3 sedes, y cada sede adicional cuesta $30.000/mes. Cada sede tiene su propia tienda, catálogo y horario, en una sola cuenta.'],
      chips: [{ t: '¿Y mi equipo?', v: 'equipo' }, { t: '¿Y el plan Pro?', v: 'pro' }] },
    { id: 'equipo', keys: ['colaborador', 'colaboradores', 'empleado', 'empleados', 'equipo', 'usuarios', 'accesos', 'meseros', 'cajero'],
      r: ['Puedes invitar colaboradores con su propio acceso al panel en todos los planes, incluido Gratis, y darles acceso solo a la sede que atienden. Como dueño, tú ves todo.'],
      chips: [C.sedes, C.precios] },
    { id: 'domicilios', keys: ['domicilio', 'domicilios', 'envio', 'envios', 'zona', 'zonas', 'pedido minimo', 'reparto', 'repartidor', 'mensajero'],
      r: ['Defines una tarifa de domicilio por barrio y un pedido mínimo; tu cliente ve el costo exacto antes de confirmar.',
          'El reparto lo haces tú o tu mensajero: Veci no tiene flota de domiciliarios propia.'],
      chips: [C.precios, C.demo] },
    { id: 'herramientas', keys: ['cupon', 'cupones', 'descuentos a mis clientes', 'tarjeta de sellos', 'sellos', 'resenas', 'combos', 'inventario', 'agotado', 'cierre de caja', 'promociones', 'herramientas'],
      r: ['Incluidas en los tres planes, desde Gratis: cupones de descuento, tarjeta de sellos, reseñas por WhatsApp, combos y "lo más pedido", domicilios por zona, inventario y "agotado por hoy", paquetes de sesiones, cierre de caja diario y avisos automáticos de estado por WhatsApp.'],
      links: [{ t: 'Ver todas las funciones', h: 'funciones.html' }],
      chips: [C.precios, C.demo] },
    { id: 'avisos', keys: ['notificacion', 'notificaciones', 'me avisa', 'aviso', 'avisos', 'como me entero', 'alerta'],
      r: ['Cuando llega un pedido o una cita nueva te llega una notificación al celular, aunque tengas el panel cerrado. Y tu cliente recibe avisos por WhatsApp cuando cambia el estado de su pedido o cita.'],
      chips: [C.empezar] },
    { id: 'whatsapp', keys: ['whatsapp', 'whatsapp business', 'wsp', 'wpp', 'chat'],
      r: ['Tu cliente arma el pedido o elige su cita en tu enlace y le llega listo a tu WhatsApp, con productos y cantidades. Tu cliente no tiene que instalar nada nuevo.'],
      chips: [C.demo, C.precios] },
    { id: 'app', keys: ['descargar', 'app store', 'play store', 'instalar', 'aplicacion', 'computador', 'celular', 'android', 'iphone'],
      r: ['No tienes que descargar nada de una tienda de apps: tu panel funciona desde el navegador del celular (pensado para un Android de gama media) o del computador. Tu cliente tampoco instala nada.'],
      chips: [C.empezar, C.demo] },
    { id: 'rappi', peso: 2, keys: ['rappi', 'didi', 'ifood', 'apps de domicilio', 'plataformas', 'diferencia', 'comparacion', 'versus', 'vs', 'mejor que'],
      r: ['La diferencia de fondo: las apps de domicilio cobran entre 25% y 30% de cada pedido y el cliente queda siendo "de la app". Con Veci pagas un plan fijo, el cliente es tuyo (sabes quién es y cuándo dejó de pedir) y el pedido pasa por WhatsApp.',
          'Puedes usar los dos a la vez: muchas tiendas mueven a sus clientes frecuentes a su propio enlace.'],
      links: [{ t: 'Alternativas a Rappi para tu negocio', h: 'blog/alternativas-a-rappi.html' }],
      chips: [C.ahorro, C.precios] },
    { id: 'datos', keys: ['datos', 'privacidad', 'seguridad', 'seguro', 'ley 1581', 'habeas data', 'datos personales'],
      r: ['Cumplimos la Ley 1581 de datos personales. La información de tus clientes es tuya: no la vendemos ni la compartimos, y la puedes exportar cuando quieras.',
          'Este chat tampoco guarda lo que escribes en ningún servidor.'],
      links: [{ t: 'Política de privacidad', h: 'privacidad.html' }] },
    { id: 'demo', keys: ['demo', 'demos', 'ejemplo', 'ver como se ve', 'probar', 'muestra', 'como se ve'],
      r: ['Tenemos cinco negocios de ejemplo funcionando en tu navegador: un restaurante, una peluquería, un minimarket, un entrenador y un consultorio odontológico. Ves la tienda como tu cliente y el panel como tú.'],
      links: [{ t: 'Abrir las demos', h: 'demo.html' }],
      chips: [C.empezar, C.precios] },
    { id: 'negocios', keys: ['para que negocios', 'mi negocio', 'restaurante', 'tienda de barrio', 'minimarket', 'panaderia', 'floristeria', 'papeleria', 'ropa', 'sirve para'],
      r: ['Sirve para cualquier negocio que venda productos (restaurantes, tiendas de barrio, panaderías, floristerías, ropa) o que atienda por cita (peluquerías, spas, consultorios, entrenadores, talleres).'],
      links: [{ t: 'Tiendas', h: 'tiendas.html' }, { t: 'Peluquerías', h: 'peluquerias.html' }, { t: 'Entrenadores', h: 'entrenadores.html' }, { t: 'Odontólogos', h: 'odontologos.html' }],
      chips: [C.recomendar] },
    { id: 'referidos', keys: ['referido', 'referidos', 'recomendar a otro', 'invitar', 'ganar'],
      r: ['El programa de referidos está en camino (próximamente). Cuando abra, te contamos en tu panel.'],
      chips: [C.precios] },
    { id: 'excel', keys: ['excel', 'sheets', 'google sheets', 'contabilidad', 'reporte', 'reportes', 'estadisticas', 'webhook', 'webhooks', 'api'],
      r: ['El panel de estadísticas completo viene en el plan Pro. Exportar a Excel o Google Sheets y los webhooks están en camino (próximamente). Mientras tanto, tus datos en bruto son tuyos y los puedes pedir en cualquier plan.'],
      links: [{ t: 'Ver integraciones', h: 'integraciones.html' }] },
    { id: 'soporte', keys: ['soporte', 'ayuda', 'humano', 'persona', 'asesor', 'hablar con alguien', 'contacto', 'contactar', 'correo', 'email', 'telefono', 'llamar'],
      r: ['Escríbenos a ' + SOPORTE + ' y te responde una persona que conoce el producto. En el plan Barrio el soporte es por WhatsApp, y en Pro es prioritario.'],
      links: [{ t: 'Escribir a soporte', h: 'mailto:' + SOPORTE }] },
    { id: 'nosotros', keys: ['quienes son', 'quien esta detras', 'empresa', 'colombianos', 'de donde son', 'confiable', 'confiar'],
      r: ['Veci está hecho en Colombia para negocios de barrio: pesos colombianos, Bre-B nativo y Ley 1581 desde el primer día.'],
      links: [{ t: 'Quiénes somos', h: 'nosotros.html' }] },
    { id: 'empezar', peso: 2, keys: ['empezar', 'registrarme', 'registro', 'crear cuenta', 'abrir cuenta', 'quiero empezar', 'como empiezo', 'inscribirme', 'me interesa', 'lo quiero', 'activar'],
      r: ['¡Listo! Te registras gratis, sin tarjeta, y en menos de 10 minutos tienes tu tienda o agenda publicada.'],
      links: [{ t: 'Crear mi cuenta gratis', h: REGISTRO, cta: true }] }
  ];

  var PALABRAS_OFERTA = ['descuento', 'promo', 'promocion', 'oferta', 'cupon para mi', 'codigo', 'rebaja', 'mas barato', 'precio especial'];

  function puntuar(texto) {
    var n = normalizar(texto).replace(/[$.,%]/g, ' ').replace(/\s+/g, ' ').trim();
    var palabras = n.split(' ');
    var mejor = null, mejorPuntos = 0;
    KB.forEach(function (e) {
      var p = 0;
      e.keys.forEach(function (k) {
        if (k.indexOf(' ') > -1) { if (n.indexOf(k) > -1) p += 3; return; }
        for (var i = 0; i < palabras.length; i++) {
          var w = palabras[i];
          if (w === k) { p += 2; break; }
          if (k.length >= 5 && w.length >= 5 && (w.indexOf(k) === 0 || k.indexOf(w) === 0)) { p += 1.5; break; }
        }
      });
      if (p > mejorPuntos) { mejorPuntos = p; mejor = e; }
    });
    return mejorPuntos >= 1.5 ? mejor : null;
  }

  function pideOferta(texto) {
    var n = normalizar(texto);
    return PALABRAS_OFERTA.some(function (k) { return n.indexOf(k) > -1; });
  }

  // "vendo 5 millones", "800 mil", "$3.500.000", "2 palos"
  function leerMonto(texto) {
    var n = normalizar(texto).replace(/\$/g, '');
    var m = n.match(/(\d+(?:[.,]\d+)*)\s*(millones|millon|mill|palos|palo|m\b|mil|k\b)?/);
    if (!m) return null;
    var crudo = m[1], unidad = m[2] || '';
    var valor;
    if (/^\d{1,3}([.,]\d{3})+$/.test(crudo)) valor = parseFloat(crudo.replace(/[.,]/g, ''));
    else valor = parseFloat(crudo.replace(',', '.'));
    if (isNaN(valor)) return null;
    if (/^(millones|millon|mill|palos|palo|m)$/.test(unidad)) valor *= 1e6;
    else if (/^(mil|k)$/.test(unidad)) valor *= 1e3;
    if (valor < 50000 || valor > 2e9) return null;
    return valor;
  }

  // ---------------------------------------------------------------------
  // Estado
  // ---------------------------------------------------------------------
  var estado = leer('sessionStorage', CLAVE_SESION) || {
    mensajes: [], puntos: 0, enviados: 0, abiertoEn: 0, flujo: null, tipo: null, ofertaVista: false
  };
  function persistir() {
    if (estado.mensajes.length > 60) estado.mensajes = estado.mensajes.slice(-60);
    guardar('sessionStorage', CLAVE_SESION, estado);
  }

  function ofertaGuardada() {
    var o = leer('localStorage', CLAVE_OFERTA);
    return o && o.codigo === OFERTA.codigo ? o : null;
  }
  function ofertaDisponible() {
    if (!OFERTA.activa) return false;
    var o = ofertaGuardada();
    return !o || Date.now() < o.vence;
  }
  function cumpleCompuertas() {
    var seg = estado.abiertoEn ? (Date.now() - estado.abiertoEn) / 1000 : 0;
    return estado.enviados >= OFERTA.minMensajes && estado.puntos >= OFERTA.minPuntos && seg >= OFERTA.minSegundos;
  }

  // ---------------------------------------------------------------------
  // Interfaz
  // ---------------------------------------------------------------------
  var reducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function el(tag, clase, texto) {
    var n = document.createElement(tag);
    if (clase) n.className = clase;
    if (texto != null) n.textContent = texto;
    return n;
  }
  function svg(path, size) {
    var ns = 'http://www.w3.org/2000/svg';
    var s = document.createElementNS(ns, 'svg');
    s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('width', size || 18); s.setAttribute('height', size || 18);
    s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '2');
    s.setAttribute('stroke-linecap', 'round'); s.setAttribute('stroke-linejoin', 'round'); s.setAttribute('aria-hidden', 'true');
    path.split('|').forEach(function (d) { var p = document.createElementNS(ns, 'path'); p.setAttribute('d', d); s.appendChild(p); });
    return s;
  }
  // **negrita** → <strong>, sin interpretar HTML
  function conFormato(parrafo, texto) {
    texto.split('**').forEach(function (trozo, i) {
      if (!trozo) return;
      parrafo.appendChild(i % 2 ? el('strong', null, trozo) : document.createTextNode(trozo));
    });
    return parrafo;
  }

  var lanzador = el('button', 'vchat-lanzador');
  lanzador.type = 'button';
  lanzador.setAttribute('aria-expanded', 'false');
  lanzador.setAttribute('aria-controls', 'vchat-panel');
  lanzador.appendChild(svg('M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-5A8 8 0 1 1 21 12Z|M8.5 11.5h.01|M12 11.5h.01|M15.5 11.5h.01', 20));
  lanzador.appendChild(el('span', 'vchat-lanzador-texto', 'Pregúntale a Veci'));

  var panel = el('div', 'vchat-panel');
  panel.id = 'vchat-panel';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', 'Asistente de Veci');
  panel.hidden = true;

  var cabecera = el('div', 'vchat-cabecera');
  var avatar = el('span', 'vchat-avatar');
  var img = el('img');
  img.alt = ''; img.width = 22; img.height = 22; img.dataset.src = BASE + 'assets/img/favicon-32.png';
  avatar.appendChild(img);
  var titulos = el('div', 'vchat-titulos');
  titulos.appendChild(el('strong', null, 'Asistente Veci'));
  titulos.appendChild(el('span', null, 'Respuestas al instante · no guarda tus datos'));
  var cerrar = el('button', 'vchat-cerrar');
  cerrar.type = 'button';
  cerrar.setAttribute('aria-label', 'Cerrar el asistente');
  cerrar.appendChild(svg('M6 6l12 12|M18 6 6 18'));
  cabecera.appendChild(avatar); cabecera.appendChild(titulos); cabecera.appendChild(cerrar);

  var registro = el('div', 'vchat-registro');
  registro.setAttribute('role', 'log');
  registro.setAttribute('aria-live', 'polite');

  var formulario = el('form', 'vchat-form');
  formulario.setAttribute('novalidate', '');
  var entrada = el('input', 'vchat-input');
  entrada.type = 'text';
  entrada.maxLength = MAX_LARGO;
  entrada.autocomplete = 'off';
  entrada.placeholder = 'Escribe tu pregunta…';
  entrada.setAttribute('aria-label', 'Escribe tu pregunta');
  var enviar = el('button', 'vchat-enviar');
  enviar.type = 'submit';
  enviar.setAttribute('aria-label', 'Enviar');
  enviar.appendChild(svg('M5 12h14|m13 6 6 6-6 6'));
  formulario.appendChild(entrada); formulario.appendChild(enviar);

  var pie = el('p', 'vchat-pie');
  pie.appendChild(document.createTextNode('¿Prefieres una persona? '));
  var mail = el('a', null, SOPORTE); mail.href = 'mailto:' + SOPORTE;
  pie.appendChild(mail);

  panel.appendChild(cabecera); panel.appendChild(registro); panel.appendChild(formulario); panel.appendChild(pie);

  var teaser = null;

  function montar() {
    document.body.appendChild(panel);
    document.body.appendChild(lanzador);
  }

  // ---------------------------------------------------------------------
  // Render de mensajes (los mensajes se guardan como datos, no como HTML)
  // ---------------------------------------------------------------------
  function quitarChips() {
    Array.prototype.forEach.call(registro.querySelectorAll('.vchat-chips'), function (c) { c.remove(); });
  }

  function pintar(m, conChips) {
    if (m.de === 'yo') {
      registro.appendChild(el('div', 'vchat-msg vchat-yo', m.texto));
    } else if (m.tipo === 'oferta') {
      registro.appendChild(tarjetaOferta(m));
    } else {
      var b = el('div', 'vchat-msg vchat-bot');
      (m.r || []).forEach(function (t) { b.appendChild(conFormato(el('p'), t)); });
      if (m.links && m.links.length) {
        var ls = el('div', 'vchat-links');
        m.links.forEach(function (l) {
          var a = el('a', l.cta ? 'vchat-link vchat-link-cta' : 'vchat-link', l.t + (l.cta ? ' →' : ''));
          a.href = l.cta && l.h === REGISTRO ? enlaceRegistro(false) : ruta(l.h);
          if (/^https?:/.test(l.h)) a.rel = 'noopener';
          a.addEventListener('click', function () { evento(l.cta ? 'cta' : 'enlace', { destino: l.h }); });
          ls.appendChild(a);
        });
        b.appendChild(ls);
      }
      registro.appendChild(b);
    }
    if (conChips && m.chips && m.chips.length) {
      var cs = el('div', 'vchat-chips');
      m.chips.forEach(function (c) {
        var bt = el('button', 'vchat-chip', c.t);
        bt.type = 'button';
        bt.addEventListener('click', function () { recibir(c.t, c.v); });
        cs.appendChild(bt);
      });
      registro.appendChild(cs);
    }
  }

  function enlaceRegistro(conOferta) {
    var u = REGISTRO + '?utm_source=web&utm_medium=chatbot&utm_campaign=' + (conOferta ? 'oferta-chat' : 'asistente');
    if (conOferta) u += '&oferta=' + encodeURIComponent(OFERTA.codigo);
    return u;
  }

  function tarjetaOferta(m) {
    var o = ofertaGuardada();
    var finOferta = o ? Math.min(o.vence, m.vence) : m.vence;
    var vigente = OFERTA.activa && Date.now() < finOferta;
    var t = el('div', 'vchat-oferta' + (vigente ? '' : ' vencida'));
    var sello = el('span', 'vchat-oferta-sello');
    sello.appendChild(el('span', null, '−30%'));
    var cuerpo = el('div', 'vchat-oferta-cuerpo');
    cuerpo.appendChild(el('span', 'vchat-oferta-eyebrow', 'SOLO EN ESTE CHAT'));
    cuerpo.appendChild(el('strong', null, OFERTA.titulo));
    cuerpo.appendChild(el('p', null, OFERTA.detalle));
    var codigo = el('div', 'vchat-oferta-codigo');
    codigo.appendChild(el('span', 'mono', OFERTA.codigo));
    var copiar = el('button', 'vchat-copiar', 'Copiar');
    copiar.type = 'button';
    copiar.addEventListener('click', function () {
      var hecho = function () { copiar.textContent = 'Copiado'; setTimeout(function () { copiar.textContent = 'Copiar'; }, 1800); };
      try { navigator.clipboard.writeText(OFERTA.codigo).then(hecho, function () {}); } catch (e) {}
      evento('oferta_copiada', {});
    });
    codigo.appendChild(copiar);
    cuerpo.appendChild(codigo);
    var vence = new Date(finOferta);
    cuerpo.appendChild(el('span', 'vchat-oferta-vence', vigente
      ? 'Vence el ' + vence.toLocaleDateString('es-CO', { day: 'numeric', month: 'long' }) + ' a las ' + vence.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' })
      : 'Esta oferta ya venció'));
    if (vigente) {
      var cta = el('a', 'vchat-link vchat-link-cta', 'Activar mi descuento →');
      cta.href = enlaceRegistro(true);
      cta.rel = 'noopener';
      cta.addEventListener('click', function () { evento('oferta_cta', {}); });
      cuerpo.appendChild(cta);
    }
    t.appendChild(sello);
    t.appendChild(el('span', 'vchat-oferta-divisor'));
    t.appendChild(cuerpo);
    return t;
  }

  function bajar() { registro.scrollTop = registro.scrollHeight; }

  function agregar(m) {
    quitarChips();
    estado.mensajes.push(m);
    persistir();
    pintar(m, true);
    bajar();
  }

  var escribiendo = false;
  function responder(m, despues) {
    escribiendo = true;
    enviar.disabled = true;
    var puntos = el('div', 'vchat-msg vchat-bot vchat-escribiendo');
    puntos.setAttribute('aria-label', 'Escribiendo');
    puntos.appendChild(el('span')); puntos.appendChild(el('span')); puntos.appendChild(el('span'));
    registro.appendChild(puntos);
    bajar();
    var largo = (m.r || []).join(' ').length;
    var espera = reducido ? 150 : Math.min(1100, 380 + largo * 2.2);
    setTimeout(function () {
      puntos.remove();
      agregar(m);
      escribiendo = false;
      enviar.disabled = false;
      if (despues) despues();
    }, espera);
  }

  // ---------------------------------------------------------------------
  // Oferta
  // ---------------------------------------------------------------------
  function mostrarOferta() {
    if (escribiendo) { setTimeout(mostrarOferta, 600); return; }
    var o = ofertaGuardada();
    if (!o) {
      o = { codigo: OFERTA.codigo, vistaEn: Date.now(), vence: Date.now() + OFERTA.horasVigencia * 3600 * 1000 };
      guardar('localStorage', CLAVE_OFERTA, o);
      evento('oferta_mostrada', { codigo: OFERTA.codigo });
    }
    estado.ofertaVista = true;
    responder({ de: 'bot', r: ['Como estás evaluando Veci en serio, te dejo esto. Es para usar una sola vez:'] }, function () {
      agregar({ de: 'bot', tipo: 'oferta', vence: o.vence, chips: [C.precios, C.demo] });
    });
  }

  // Se ofrece una sola vez por sesión, y solo tras una respuesta de alta intención.
  function quizasOfrecer(intencionAlta) {
    if (estado.ofertaVista || !intencionAlta || !ofertaDisponible() || !cumpleCompuertas()) return false;
    setTimeout(mostrarOferta, reducido ? 100 : 900);
    return true;
  }

  function responderPedidoDeOferta() {
    var o = ofertaGuardada();
    if (!OFERTA.activa) {
      responder({ de: 'bot', r: ['Hoy no tenemos promociones activas, pero el plan Gratis no vence y pagando anual te ahorras 2 meses.'], chips: [C.gratis, C.precios] });
    } else if (o && Date.now() >= o.vence) {
      responder({ de: 'bot', r: ['La oferta del chat ya venció en este navegador y es de un solo uso. Igual puedes empezar con el plan Gratis, que no vence, o pagar anual y ahorrarte 2 meses.'], chips: [C.gratis, C.precios] });
    } else if (o || cumpleCompuertas()) {
      mostrarOferta();
    } else {
      estado.flujo = 'plan';
      responder({ de: 'bot', r: ['Sí hay algo para negocios que están evaluando Veci en serio. Primero cuéntame un poco: ¿qué tipo de negocio tienes?'], chips: chipsTipo() });
    }
  }

  // ---------------------------------------------------------------------
  // Flujos guiados
  // ---------------------------------------------------------------------
  function chipsTipo() {
    return [
      { t: 'Restaurante o comida', v: '#tipo:comida' },
      { t: 'Tienda o minimarket', v: '#tipo:tienda' },
      { t: 'Peluquería o spa', v: '#tipo:belleza' },
      { t: 'Entrenador o clases', v: '#tipo:entrenador' },
      { t: 'Consultorio', v: '#tipo:salud' }
    ];
  }
  var MODO = { comida: 'pedidos', tienda: 'pedidos', belleza: 'reservas', entrenador: 'reservas', salud: 'reservas' };

  function iniciarPlan() {
    estado.flujo = 'plan';
    responder({ de: 'bot', r: ['Te recomiendo uno en dos preguntas. ¿Qué tipo de negocio tienes?'], chips: chipsTipo() });
  }

  function elegirTipo(tipo) {
    estado.tipo = tipo;
    estado.flujo = 'volumen';
    var pregunta = MODO[tipo] === 'reservas' ? '¿Cuántas citas atiendes más o menos al mes?' : '¿Cuántos pedidos recibes más o menos al mes?';
    responder({ de: 'bot', r: [pregunta], chips: [
      { t: 'Menos de 50', v: '#vol:bajo' },
      { t: 'Entre 50 y 300', v: '#vol:medio' },
      { t: 'Más de 300', v: '#vol:alto' },
      { t: 'Tengo varios locales', v: '#vol:sedes' }
    ] });
  }

  function recomendar(vol) {
    estado.flujo = null;
    estado.puntos += 2;
    var modo = MODO[estado.tipo] || 'pedidos';
    var modoTxt = modo === 'reservas' ? 'el **modo reservas** (agenda con horario)' : 'el **modo pedidos** (catálogo con carrito)';
    var r;
    if (vol === 'bajo') {
      r = ['Empieza con el plan **Gratis**: te alcanza para 50 ' + (modo === 'reservas' ? 'citas' : 'pedidos') + ' al mes, sin pagar nada y sin fecha de vencimiento. Usarías ' + modoTxt + '.',
           'Cuando crezcas, pasas a Barrio desde tu panel sin perder nada.'];
    } else if (vol === 'medio' || vol === 'alto') {
      r = ['Para ese volumen te sirve el plan **Barrio ($59.000/mes)**: sin límite de ' + (modo === 'reservas' ? 'citas' : 'pedidos') + ' y con el copiloto de recompra, que es lo que más rinde cuando ya tienes clientela. Usarías ' + modoTxt + '.',
           'Si pagas anual queda en $49.000/mes.'];
    } else {
      r = ['Con varios locales, el plan **Pro ($129.000/mes)** es el tuyo: hasta 3 sedes incluidas, cada una con su tienda y horario, estadísticas completas y soporte prioritario. Usarías ' + modoTxt + '.',
           'Cada sede adicional cuesta $30.000/mes.'];
    }
    evento('plan_recomendado', { tipo: estado.tipo, volumen: vol });
    var links = [{ t: 'Crear mi cuenta gratis', h: REGISTRO, cta: true }];
    if (modo === 'reservas') links.push({ t: 'Ver la demo de reservas', h: 'demo.html#reservas' });
    else links.push({ t: 'Ver la demo de pedidos', h: 'demo.html#pedidos' });
    responder({ de: 'bot', r: r, links: links, chips: [C.ahorro, C.copiloto] }, function () { quizasOfrecer(true); });
  }

  function iniciarAhorro() {
    estado.flujo = 'ahorro';
    responder({ de: 'bot', r: ['¿Cuánto vendes al mes por apps de domicilio como Rappi o DiDi? Escríbelo como quieras, por ejemplo "5 millones" o "800 mil".'], chips: [
      { t: '$2 millones', v: '#monto:2000000' },
      { t: '$5 millones', v: '#monto:5000000' },
      { t: '$10 millones', v: '#monto:10000000' }
    ] });
  }

  function calcularAhorro(monto) {
    estado.flujo = null;
    estado.puntos += 2;
    var bajo = monto * 0.25, alto = monto * 0.30, medio = monto * 0.275;
    var plan = 'Barrio', cuota = 59000;
    var ahorro = Math.max(0, medio - cuota);
    evento('ahorro_calculado', { monto: monto });
    responder({ de: 'bot', r: [
      'Si vendes ' + cop(monto) + ' al mes por apps, la comisión se lleva entre **' + cop(bajo) + ' y ' + cop(alto) + '** cada mes.',
      'Con Veci (plan ' + plan + ') pagas ' + cop(cuota) + ' fijos. Si esos pedidos pasaran por tu propio enlace, te quedarían cerca de **' + cop(ahorro) + ' más al mes** (' + cop(ahorro * 12) + ' al año).',
      'Es un cálculo con la comisión promedio de 27,5%; no incluye lo que pagas por domicilios.'
    ], links: [{ t: 'Cuánto le dejas a Rappi, explicado', h: 'blog/cuanto-le-dejas-a-rappi.html' }], chips: [C.recomendar, C.empezar] }, function () { quizasOfrecer(true); });
  }

  // ---------------------------------------------------------------------
  // Entrada del usuario
  // ---------------------------------------------------------------------
  var ultimoEnvio = 0;

  function recibir(texto, valor) {
    var ahora = Date.now();
    if (escribiendo || ahora - ultimoEnvio < PAUSA_MIN_MS) return;
    texto = String(texto || '').replace(/[\u0000-\u001F\u007F]/g, ' ').trim().slice(0, MAX_LARGO);
    if (!texto) return;
    ultimoEnvio = ahora;

    if (estado.enviados >= MAX_MENSAJES) {
      responder({ de: 'bot', r: ['Hemos hablado bastante por aquí. Para seguir, escríbenos a ' + SOPORTE + ' y te responde una persona.'], links: [{ t: 'Escribir a soporte', h: 'mailto:' + SOPORTE }] });
      return;
    }
    estado.enviados++;
    agregar({ de: 'yo', texto: texto });
    evento('mensaje', { origen: valor ? 'chip' : 'texto' });

    var v = valor || '';
    if (v === '#plan') return iniciarPlan();
    if (v === '#ahorro') return iniciarAhorro();
    if (v.indexOf('#tipo:') === 0) return elegirTipo(v.slice(6));
    if (v.indexOf('#vol:') === 0) return recomendar(v.slice(5));
    if (v.indexOf('#monto:') === 0) return calcularAhorro(parseInt(v.slice(7), 10));

    var nt = normalizar(texto);
    if (estado.flujo === 'plan') {
      var tipo = /restaur|comida|almuerz|panader|cafe|pizz|hambur|corrientazo/.test(nt) ? 'comida'
        : /tienda|minimarket|mercado|ropa|floris|papeler|ferreter|drogu/.test(nt) ? 'tienda'
        : /pelu|barber|spa|unas|estetic|manicur/.test(nt) ? 'belleza'
        : /entrena|gym|gimnas|clases|yoga|profe/.test(nt) ? 'entrenador'
        : /consult|odont|medic|psicolog|dent|fisio/.test(nt) ? 'salud' : null;
      if (tipo) return elegirTipo(tipo);
    }
    if (estado.flujo === 'volumen') {
      if (/local|sede|sucursal/.test(nt)) return recomendar('sedes');
      var cifra = nt.match(/\d+/);
      if (cifra) { var c = parseInt(cifra[0], 10); return recomendar(c < 50 ? 'bajo' : c <= 300 ? 'medio' : 'alto'); }
    }

    if (estado.flujo === 'ahorro' || /rappi|didi|\bapps?\b|vendo|ventas|vendemos/.test(nt)) {
      var monto = leerMonto(texto);
      if (monto) return calcularAhorro(monto);
    }

    if (pideOferta(texto)) return responderPedidoDeOferta();

    if (/recomiend|que plan|cual plan|cual me sirve|cual me conviene/.test(nt)) return iniciarPlan();
    if (/ahorr|calcul/.test(nt)) return iniciarAhorro();

    var e = v ? KB.filter(function (k) { return k.id === v; })[0] : puntuar(texto);
    if (e) {
      estado.flujo = null;
      estado.puntos += e.peso || 1;
      evento('respuesta', { tema: e.id });
      return responder({ de: 'bot', r: e.r, links: e.links, chips: e.chips }, function () { quizasOfrecer(e.peso === 2); });
    }

    evento('sin_respuesta', {});
    responder({ de: 'bot', r: ['No tengo una respuesta segura para eso y prefiero no inventarla. Escríbenos a ' + SOPORTE + ' y te responde una persona.', 'Mientras tanto, esto es lo que más me preguntan:'],
      chips: [C.precios, C.recomendar, C.breb, C.ia] });
  }

  formulario.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var t = entrada.value;
    if (!t.trim() || escribiendo) return;
    entrada.value = '';
    recibir(t);
  });

  // ---------------------------------------------------------------------
  // Abrir / cerrar
  // ---------------------------------------------------------------------
  var pintado = false;
  function abrir(accionInicial) {
    if (img.dataset.src) { img.src = img.dataset.src; delete img.dataset.src; }
    panel.hidden = false;
    requestAnimationFrame(function () { panel.classList.add('abierto'); });
    lanzador.setAttribute('aria-expanded', 'true');
    lanzador.classList.add('activo');
    document.documentElement.classList.add('vchat-abierto');
    quitarTeaser(true);
    if (!estado.abiertoEn) estado.abiertoEn = Date.now();
    if (!pintado) {
      pintado = true;
      estado.mensajes.forEach(function (m, i) { pintar(m, i === estado.mensajes.length - 1); });
      bajar();
    }
    if (accionInicial === 'ahorro' && !escribiendo) recibir('Calcular mi ahorro vs. Rappi', '#ahorro');
    else if (!estado.mensajes.length) {
      responder({ de: 'bot', r: ['¡Hola! Soy el asistente de Veci. Te ayudo a ver si Veci le sirve a tu negocio y cuánto te ahorrarías.', '¿Qué quieres saber?'],
        chips: [C.recomendar, C.ahorro, C.precios, C.ia, C.breb, C.demo] });
    }
    persistir();
    evento('abierto', {});
    if (window.matchMedia('(min-width: 561px)').matches) setTimeout(function () { entrada.focus(); }, 60);
  }
  function cerrarPanel() {
    panel.classList.remove('abierto');
    lanzador.setAttribute('aria-expanded', 'false');
    lanzador.classList.remove('activo');
    document.documentElement.classList.remove('vchat-abierto');
    setTimeout(function () { if (!panel.classList.contains('abierto')) panel.hidden = true; }, reducido ? 0 : 220);
    lanzador.focus();
  }
  lanzador.addEventListener('click', function () { panel.hidden ? abrir() : cerrarPanel(); });
  cerrar.addEventListener('click', cerrarPanel);
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !panel.hidden) cerrarPanel(); });

  // ---------------------------------------------------------------------
  // Invitación: una vez cada 7 días, a los 20 s, solo si no ha abierto el chat
  // ---------------------------------------------------------------------
  function quitarTeaser(guardarlo) {
    if (teaser) { teaser.remove(); teaser = null; }
    if (guardarlo) guardar('localStorage', CLAVE_TEASER, Date.now());
  }
  function programarTeaser() {
    var ultimo = leer('localStorage', CLAVE_TEASER);
    if (ultimo && Date.now() - ultimo < 7 * 864e5) return;
    if (estado.mensajes.length) return;
    setTimeout(function () {
      if (!panel.hidden || estado.mensajes.length || document.body.classList.contains('menu-abierto')) return;
      teaser = el('div', 'vchat-teaser');
      var ir = el('button', 'vchat-teaser-ir');
      ir.type = 'button';
      ir.appendChild(el('strong', null, '¿Cuánto le dejas a Rappi al mes?'));
      ir.appendChild(el('span', null, 'Te lo calculo en 10 segundos'));
      ir.addEventListener('click', function () { evento('teaser_click', {}); abrir('ahorro'); });
      var x = el('button', 'vchat-teaser-x');
      x.type = 'button';
      x.setAttribute('aria-label', 'Cerrar invitación');
      x.appendChild(svg('M6 6l12 12|M18 6 6 18', 14));
      x.addEventListener('click', function () { quitarTeaser(true); });
      teaser.appendChild(ir); teaser.appendChild(x);
      document.body.appendChild(teaser);
      evento('teaser_mostrado', {});
    }, 20000);
  }

  function iniciar() {
    montar();
    programarTeaser();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();
})();
