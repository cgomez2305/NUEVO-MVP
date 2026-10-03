  // ---- Calculadora: slider + recibo + ahorro ----
  var input = document.getElementById('ventaApps');
  var elValorGrande = document.getElementById('calcValorGrande');
  var elVentas = document.getElementById('reciboVentas');
  var elComision = document.getElementById('reciboComision');
  var elAhorro = document.getElementById('calcAhorro');
  var elAhorroFill = document.getElementById('calcAhorroFill');
  var VECI_BARRIO = 29900;

  function formatoCOP(n) {
    return '$' + Math.round(n).toLocaleString('es-CO');
  }

  function calcular() {
    var venta = parseInt(input.value, 10) || 0;
    var baja = venta * 0.25;
    var alta = venta * 0.30;
    var promedio = venta * 0.275;
    var ahorro = Math.max(0, promedio - VECI_BARRIO);

    elValorGrande.textContent = formatoCOP(venta);
    elVentas.textContent = formatoCOP(venta);
    elComision.textContent = venta > 0 ? (formatoCOP(baja) + ' – ' + formatoCOP(alta)) : '$0 – $0';
    elAhorro.textContent = formatoCOP(ahorro) + '/mes';

    var pct = promedio > 0 ? Math.min(100, (ahorro / promedio) * 100) : 0;
    elAhorroFill.style.width = pct + '%';

    var pctSlider = ((venta - input.min) / (input.max - input.min)) * 100;
    input.style.setProperty('--val', pctSlider + '%');

    [elValorGrande, elVentas, elComision, elAhorro].forEach(function (el) {
      el.classList.remove('calc-pulso');
      void el.offsetWidth;
      el.classList.add('calc-pulso');
    });
  }

  input.addEventListener('input', calcular);
  calcular();

  // ---- Precios: toggle mensual / anual, con pastilla deslizante ----
  var btnMensual = document.getElementById('btnMensual');
  var btnAnual = document.getElementById('btnAnual');
  var contPrecios = document.querySelector('.precios');
  var pastilla = document.querySelector('.precio-toggle .pastilla');

  function moverPastilla(btn) {
    pastilla.style.left = btn.offsetLeft + 'px';
    pastilla.style.width = btn.offsetWidth + 'px';
  }

  var ctasPlanPago = Array.prototype.slice.call(contPrecios.querySelectorAll('a[href*="plan=barrio"], a[href*="plan=pro"]'));
  function elegirCiclo(anual) {
    contPrecios.classList.toggle('modo-anual', anual);
    btnMensual.classList.toggle('activo', !anual);
    btnAnual.classList.toggle('activo', anual);
    moverPastilla(anual ? btnAnual : btnMensual);
    ctasPlanPago.forEach(function (a) {
      var href = a.getAttribute('href').replace(/[&?]ciclo=anual/, '');
      a.setAttribute('href', anual ? href + '&ciclo=anual' : href);
    });
  }
  btnMensual.addEventListener('click', function () { elegirCiclo(false); });
  btnAnual.addEventListener('click', function () { elegirCiclo(true); });
  moverPastilla(btnMensual);
  window.addEventListener('resize', function () {
    moverPastilla(btnAnual.classList.contains('activo') ? btnAnual : btnMensual);
  });

  // ---- Animaciones al hacer scroll ----
  // Un simple chequeo de posición (no IntersectionObserver) para que un
  // salto directo a un ancla (#precios desde otra página) también revele
  // todo lo que quedó "atrás" del salto, no solo lo que se cruza al hacer
  // scroll normal.
  var elementosReveal = Array.prototype.slice.call(document.querySelectorAll('.reveal, .reveal-fila, .reveal-der, .reveal-izq'));
  var pendientes = elementosReveal.filter(function (el) { return !el.classList.contains('visto'); });
  var revisando = false;

  function revisarReveal() {
    pendientes = pendientes.filter(function (el) {
      var rect = el.getBoundingClientRect();
      var enVista = rect.top < window.innerHeight * 0.92 && rect.bottom > 0;
      if (enVista) el.classList.add('visto');
      return !enVista;
    });
    revisando = false;
  }

  function pedirRevision() {
    if (!revisando) {
      revisando = true;
      requestAnimationFrame(revisarReveal);
    }
  }

  revisarReveal();
  window.addEventListener('scroll', pedirRevision, { passive: true });
  window.addEventListener('resize', pedirRevision);

  // Red de seguridad: si algo quedó fuera de pantalla (un salto directo a
  // un ancla, un rastreador que no hace scroll), que igual se muestre.
  setTimeout(function () {
    pendientes.forEach(function (el) { el.classList.add('visto'); });
    pendientes = [];
  }, 1500);

  // ---- Luces de fondo del hero y tilt del teléfono: reaccionan al mouse y al scroll ----
  (function () {
    var hero = document.querySelector('.hero');
    var glows = document.querySelector('.hero-glows');
    var telefono = document.querySelector('.telefono');
    if (!hero || !glows) return;
    var reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reducido) return;

    // el teléfono gira en 3D una sola vez al entrar; cuando esa animación
    // termina, se cambia por "listo" para que el tilt por mouse (abajo)
    // pueda mover su transform — una animation con fill-mode "both" le
    // sigue ganando a cualquier transform declarado después, sin importar
    // la especificidad, así que no basta con agregar la clase nueva.
    if (telefono) {
      telefono.addEventListener('animationend', function (e) {
        if (e.animationName === 'telefonoEntrada') {
          telefono.classList.remove('entrando');
          telefono.classList.add('listo');
        }
      });
    }

    hero.addEventListener('mousemove', function (e) {
      var rect = hero.getBoundingClientRect();
      var px = (e.clientX - rect.left) / rect.width - 0.5;
      var py = (e.clientY - rect.top) / rect.height - 0.5;
      glows.style.setProperty('--gx', (px * 46) + 'px');
      glows.style.setProperty('--gy', (py * 36) + 'px');
      if (telefono && telefono.classList.contains('listo')) {
        telefono.style.setProperty('--tilt-y', (px * 14) + 'deg');
        telefono.style.setProperty('--tilt-x', (py * -10) + 'deg');
      }
    });
    hero.addEventListener('mouseleave', function () {
      glows.style.setProperty('--gx', '0px');
      glows.style.setProperty('--gy', '0px');
      if (telefono) {
        telefono.style.setProperty('--tilt-y', '0deg');
        telefono.style.setProperty('--tilt-x', '0deg');
      }
    });

    function actualizarScroll() {
      var rect = hero.getBoundingClientRect();
      var progreso = Math.max(0, Math.min(1, 1 - rect.bottom / (rect.height + window.innerHeight)));
      glows.style.setProperty('--sy', (progreso * 70) + 'px');
    }
    var pidiendo = false;
    window.addEventListener('scroll', function () {
      if (!pidiendo) { pidiendo = true; requestAnimationFrame(function () { actualizarScroll(); pidiendo = false; }); }
    }, { passive: true });
    actualizarScroll();
  })();

  // ---- Frase con revelado de palabras por scroll ----
  (function () {
    var frase = document.querySelector('[data-scroll-frase]');
    if (!frase) return;
    var soportaMix = CSS.supports('color', 'color-mix(in srgb, red, blue)');
    if (!soportaMix) return;

    var texto = frase.textContent.trim();
    frase.innerHTML = texto.split(' ').map(function (palabra) {
      return '<span class="rf-w">' + palabra + '</span>';
    }).join(' ');
    var palabras = Array.prototype.slice.call(frase.querySelectorAll('.rf-w'));
    var total = palabras.length;
    var actualizando = false;

    function actualizar() {
      var rect = frase.getBoundingClientRect();
      var vh = window.innerHeight;
      var progreso = (vh * 0.82 - rect.top) / (rect.height + vh * 0.35);
      progreso = Math.max(0, Math.min(1, progreso));
      palabras.forEach(function (span, i) {
        var local = (progreso - i / total) * total * 1.2;
        local = Math.max(0, Math.min(1, local));
        span.style.color = 'color-mix(in srgb, var(--texto) ' + (local * 100) + '%, var(--texto-tenue))';
      });
      actualizando = false;
    }

    function pedir() {
      if (!actualizando) { actualizando = true; requestAnimationFrame(actualizar); }
    }

    actualizar();
    window.addEventListener('scroll', pedir, { passive: true });
    window.addEventListener('resize', pedir);
  })();

// Hero: "¿Qué tienes?" ajusta el botón principal y el enlace de demo.
// Conserva los UTM que tema.js ya puso en el enlace de registro.
(function () {
  var ops = document.querySelectorAll('[data-hero-modo]');
  var cta = document.querySelector('[data-hero-cta]');
  var ver = document.querySelector('[data-hero-ver]');
  if (!ops.length || !cta) return;
  var TEXTOS = {
    pedidos: { cta: 'Crear mi tienda gratis', ver: 'Ver una tienda funcionando', demo: 'demo.html' },
    reservas: { cta: 'Abrir mi agenda gratis', ver: 'Ver una agenda funcionando', demo: 'demo.html#reservas' }
  };
  ops.forEach(function (op) {
    op.addEventListener('click', function () {
      var modo = op.getAttribute('data-hero-modo');
      var t = TEXTOS[modo];
      if (!t) return;
      ops.forEach(function (o) { o.setAttribute('aria-pressed', o === op ? 'true' : 'false'); });
      try { var url = new URL(cta.href); url.searchParams.set('modo', modo); cta.href = url.toString(); } catch (e) {}
      cta.firstChild.nodeValue = t.cta;
      if (ver) { ver.firstChild.nodeValue = t.ver + ' '; ver.setAttribute('href', t.demo); }
    });
  });
})();

// "¿Te suena familiar?": interruptor Hoy / Con Veci. La primera vez que la
// cuenta entra en pantalla, pasa sola a "Con Veci" para mostrar el cambio.
(function () {
  var sec = document.querySelector('[data-cuaderno]');
  if (!sec) return;
  var botones = sec.querySelectorAll('[data-cuaderno-modo]');
  var estado = sec.querySelector('.cuaderno-estado');
  var tocado = false;
  function poner(modo) {
    sec.classList.toggle('con-veci', modo === 'veci');
    botones.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-cuaderno-modo') === modo ? 'true' : 'false'); });
    if (estado) estado.textContent = modo === 'veci' ? 'Con Veci' : 'Hoy';
  }
  botones.forEach(function (b) {
    b.addEventListener('click', function () { tocado = true; poner(b.getAttribute('data-cuaderno-modo')); });
  });
  if (!('IntersectionObserver' in window)) return;
  var io = new IntersectionObserver(function (e) {
    if (!e[0].isIntersecting) return;
    io.disconnect();
    setTimeout(function () { if (!tocado) poner('veci'); }, 1600);
  }, { threshold: 0.45 });
  io.observe(sec.querySelector('.cuaderno'));
})();

// Rutas del cliente: la ruta Veci se enciende nodo a nodo al entrar en pantalla.
(function () {
  var sec = document.querySelector('[data-rutas]');
  if (!sec || !('IntersectionObserver' in window)) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.documentElement.classList.add('js-rutas');
  var io = new IntersectionObserver(function (e) {
    if (e[0].isIntersecting) { sec.classList.add('encendida'); io.disconnect(); }
  }, { threshold: 0.3 });
  io.observe(sec.querySelector('.ruta-veci'));
})();
