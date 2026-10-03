/* Veci — tema (claro/oscuro) + interacciones del nav, compartido por landing y blog. */
(function () {
  'use strict';

  var LLAVE = 'veci-tema';

  function temaGuardado() {
    try {
      return localStorage.getItem(LLAVE);
    } catch (e) {
      return null;
    }
  }

  function guardarTema(valor) {
    try {
      localStorage.setItem(LLAVE, valor);
    } catch (e) {
      /* almacenamiento no disponible (modo privado, etc.): seguimos sin persistir */
    }
  }

  // El logo claro/oscuro del nav se resuelve en CSS ([data-theme="dark"] .brand
  // .logo-claro/.logo-oscuro, ver site.css) con las dos imágenes ya en el DOM,
  // no cambiando el src por JS — así no depende de que este script corra a
  // tiempo en cualquier entorno donde se publique la página.
  function aplicarTema(tema) {
    if (tema === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
  }

  var inicial = temaGuardado();
  if (!inicial) {
    inicial = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  aplicarTema(inicial);

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-tema').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var actual = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        var nuevo = actual === 'dark' ? 'light' : 'dark';
        aplicarTema(nuevo);
        guardarTema(nuevo);
      });
    });

    // Menú móvil: una hoja de pantalla completa que contesta primero "¿esto
    // sirve para mi negocio?" y deja el registro siempre a la vista. Se arma
    // aquí una sola vez para no repetir el HTML en las 20 páginas.
    var script = document.querySelector('script[src*="assets/js/tema.js"]');
    var base = script ? script.getAttribute('src').replace(/assets\/js\/tema\.js.*$/, '') : '';
    var REGISTRO = 'https://app.tuveci.co/registro';
    function svg(d) {
      return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        d.split('|').map(function (x) { return '<path d="' + x + '"/>'; }).join('') + '</svg>';
    }
    var NEGOCIOS = [
      ['tiendas.html', 'Tiendas y comida', 'caja', 'M4 9V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3|M3 9h18l-1 10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2L3 9Z|M9 13v4M15 13v4'],
      ['peluquerias.html', 'Peluquerías y barberías', 'aji', 'M6 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z|M6 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z|M20 4 8.1 15.9|M14.5 14.5 20 20|M8.1 8.1 12 12'],
      ['odontologos.html', 'Consultorios', 'sello', 'M12 5c-2-2-6-1-6 3 0 3 1 4 1.5 8 .2 1.6 1 2.5 2 2.5s1.5-1.5 1.5-3.5.5-2.5 1-2.5 1 .5 1 2.5.5 3.5 1.5 3.5 1.8-.9 2-2.5c.5-4 1.5-5 1.5-8 0-4-4-5-6-3Z'],
      ['entrenadores.html', 'Entrenadores', 'mostaza', 'M6.5 6.5v11|M17.5 6.5v11|M3 9.5v5|M21 9.5v5|M6.5 12h11']
    ];
    var PRINCIPALES = [
      ['index.html#como-funciona', 'Cómo funciona', 'De la foto a tu tienda en 10 minutos', false],
      ['precios.html', 'Precios', 'Desde $0 · 0% de comisión', false],
      ['demo.html', 'Demos en vivo', 'Pruébalo sin registrarte', true],
      ['funciones.html', 'Funciones', 'Todo lo que trae cada plan', false],
      ['blog/index.html', 'Blog', 'Guías para vender por WhatsApp', false]
    ];
    var actual = location.pathname.replace(/\/$/, '/index.html');
    function esActual(href) {
      var limpio = href.split('#')[0];
      return href.indexOf('#') < 0 && actual.slice(-limpio.length) === limpio;
    }
    var hoja = document.createElement('div');
    hoja.className = 'menu-movil';
    hoja.id = 'menu-movil';
    hoja.hidden = true;
    hoja.setAttribute('role', 'dialog');
    hoja.setAttribute('aria-modal', 'true');
    hoja.setAttribute('aria-label', 'Menú');
    hoja.innerHTML =
      '<div class="menu-movil-cuerpo">' +
        '<p class="menu-movil-titulo">¿Qué tipo de negocio tienes?</p>' +
        '<div class="menu-movil-negocios">' + NEGOCIOS.map(function (n) {
          return '<a href="' + base + n[0] + '" class="menu-movil-negocio"' + (esActual(n[0]) ? ' aria-current="page"' : '') + '>' +
            '<span class="menu-movil-ico" style="background:var(--tinte-' + n[2] + ');color:var(--' + (n[2] === 'sello' ? 'sello' : n[2] + '-texto') + ')">' + svg(n[3]) + '</span>' + n[1] + '</a>';
        }).join('') + '</div>' +
        '<nav class="menu-movil-principal" aria-label="Secciones">' + PRINCIPALES.map(function (l) {
          return '<a href="' + base + l[0] + '"' + (esActual(l[0]) ? ' aria-current="page"' : '') + '><strong>' + l[1] +
            (l[3] ? ' <span class="menu-movil-vivo" aria-hidden="true"></span>' : '') + '</strong><span>' + l[2] + '</span></a>';
        }).join('') + '</nav>' +
        '<p class="menu-movil-secundario"><a href="' + base + 'nosotros.html">Quiénes somos</a><a href="' + base + 'integraciones.html">Integraciones</a><a href="' + base + 'referidos.html">Referidos</a></p>' +
      '</div>' +
      '<div class="menu-movil-pie">' +
        '<a href="' + REGISTRO + '" class="btn btn-sello menu-movil-cta">Crear mi tienda gratis</a>' +
        '<p>Sin tarjeta · lista en 10 minutos · <a href="https://app.tuveci.co/login">Iniciar sesión</a></p>' +
      '</div>';
    document.body.appendChild(hoja);

    var hamburguesas = Array.prototype.slice.call(document.querySelectorAll('.btn-hamburguesa'));
    hamburguesas.forEach(function (b) { b.setAttribute('aria-controls', 'menu-movil'); b.setAttribute('aria-expanded', 'false'); });
    var ultimoBoton = null;

    function abrirMenu(boton) {
      ultimoBoton = boton;
      hoja.hidden = false;
      void hoja.offsetWidth;
      hoja.classList.add('abierto');
      document.body.classList.add('menu-abierto');
      hamburguesas.forEach(function (b) {
        b.classList.add('abierto');
        b.setAttribute('aria-expanded', 'true');
        b.setAttribute('aria-label', 'Cerrar menú');
      });
      var primero = hoja.querySelector('a');
      if (primero) setTimeout(function () { primero.focus({ preventScroll: true }); }, 60);
    }
    function cerrarMenuMovil() {
      if (hoja.hidden) return;
      hoja.classList.remove('abierto');
      document.body.classList.remove('menu-abierto');
      hamburguesas.forEach(function (b) {
        b.classList.remove('abierto');
        b.setAttribute('aria-expanded', 'false');
        b.setAttribute('aria-label', 'Abrir menú');
      });
      setTimeout(function () { if (!hoja.classList.contains('abierto')) hoja.hidden = true; }, 260);
      if (ultimoBoton) ultimoBoton.focus({ preventScroll: true });
    }
    hamburguesas.forEach(function (boton) {
      boton.addEventListener('click', function () {
        if (hoja.hidden) abrirMenu(boton); else cerrarMenuMovil();
      });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarMenuMovil(); });
    hoja.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', cerrarMenuMovil); });
    window.addEventListener('resize', function () { if (window.innerWidth > 860) cerrarMenuMovil(); });

    // Mega menú: en mobile (o teclado) se abre con click/Enter en vez de solo :hover.
    document.querySelectorAll('.nav-trigger').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var item = boton.closest('.nav-item-menu');
        if (item) item.classList.toggle('abierto');
      });
    });

    // Spotlight que sigue el cursor en las tarjetas: centralizado aquí (antes
    // vivía solo en index.html) para que cualquier página con estas clases
    // tenga la misma micro-interacción, no solo la portada.
    document.querySelectorAll('.feature-card, .dolor-card, .plan, .industria-col, .integra-item, .verificable-item, .articulo-card, .demo-teaser-card').forEach(function (card) {
      card.addEventListener('mousemove', function (e) {
        var rect = card.getBoundingClientRect();
        card.style.setProperty('--mx', (e.clientX - rect.left) + 'px');
        card.style.setProperty('--my', (e.clientY - rect.top) + 'px');
      });
    });
  });
})();

// Animaciones al hacer scroll (.reveal, .reveal-fila, .reveal-der, .reveal-izq):
// centralizado aquí para que CUALQUIER página que cargue tema.js tenga el
// mismo comportamiento, sin depender de que cada página copie este bloque
// a mano — eso fue exactamente lo que dejó el footer invisible (clase
// .reveal sin nadie que le agregue .visto) en demo.html, privacidad.html,
// terminos.html y las páginas del blog.
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var elementosReveal = Array.prototype.slice.call(
      document.querySelectorAll('.reveal, .reveal-fila, .reveal-der, .reveal-izq')
    );
    var pendientes = elementosReveal.filter(function (el) { return !el.classList.contains('visto'); });
    if (!pendientes.length) return;
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
      if (!revisando) { revisando = true; requestAnimationFrame(revisarReveal); }
    }

    revisarReveal();
    window.addEventListener('scroll', pedirRevision, { passive: true });
    window.addEventListener('resize', pedirRevision);
    window.setTimeout(function () {
      pendientes.forEach(function (el) { el.classList.add('visto'); });
      pendientes = [];
    }, 1500);
  });
})();

// Atribución: cada enlace a registro lleva de dónde vino el visitante y desde
// qué bloque hizo clic. Si llegó con UTM (un anuncio, Instagram), se respetan
// los suyos; si no, se marca como tráfico del sitio. Los enlaces que ya traen
// utm_source (los del asistente) no se tocan.
(function () {
  var CLAVES = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  var entrada = {};
  try {
    var q = new URLSearchParams(location.search);
    CLAVES.forEach(function (k) { var v = q.get(k); if (v) entrada[k] = v.slice(0, 80); });
    if (entrada.utm_source) sessionStorage.setItem('veci-utm', JSON.stringify(entrada));
    else entrada = JSON.parse(sessionStorage.getItem('veci-utm') || '{}') || {};
  } catch (e) { entrada = {}; }

  var pagina = (location.pathname.replace(/\/$/, '/index.html').split('/').pop() || 'index.html').replace('.html', '');
  if (/\/blog\//.test(location.pathname)) pagina = 'blog-' + pagina;

  function bloque(a) {
    if (a.closest('.menu-movil')) return 'menu';
    if (a.closest('.nav-fija, .nav-panel')) return 'nav';
    if (a.closest('.hero, .demo-hero')) return 'hero';
    if (a.closest('.cierre')) return 'cierre';
    if (a.closest('[data-calc]')) return 'calculadora';
    if (a.closest('.demo-mi-tienda')) return 'demo';
    if (a.closest('.plan')) return 'plan';
    if (a.closest('.articulo-cta, .articulo')) return 'articulo';
    if (a.closest('footer')) return 'footer';
    return 'pagina';
  }

  function decorar() {
    document.querySelectorAll('a[href^="https://app.tuveci.co/registro"]').forEach(function (a) {
      var url;
      try { url = new URL(a.href); } catch (e) { return; }
      if (url.searchParams.get('utm_source')) return;
      url.searchParams.set('utm_source', entrada.utm_source || 'web');
      url.searchParams.set('utm_medium', entrada.utm_medium || 'sitio');
      url.searchParams.set('utm_campaign', entrada.utm_campaign || pagina);
      if (entrada.utm_term) url.searchParams.set('utm_term', entrada.utm_term);
      url.searchParams.set('utm_content', (entrada.utm_source ? pagina + '-' : '') + bloque(a));
      a.href = url.toString();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', decorar);
  else decorar();
})();

// Comanda del cierre: se "imprime" línea por línea cuando entra en pantalla
// y al final cae el sello. Sin JS o con movimiento reducido se ve completa.
(function () {
  var comandas = document.querySelectorAll('[data-comanda]');
  if (!comandas.length || !('IntersectionObserver' in window)) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.documentElement.classList.add('js-comanda');
  var io = new IntersectionObserver(function (entradas) {
    entradas.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('impreso'); io.unobserve(e.target); }
    });
  }, { threshold: 0.35 });
  comandas.forEach(function (c) { io.observe(c); });
})();
