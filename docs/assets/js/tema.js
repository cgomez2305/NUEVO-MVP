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

    // Hamburguesa: abre/cierra el panel de navegación en mobile (una tarjeta
    // flotante con su propio scrim), y el ícono se convierte en una X (las
    // tres líneas del SVG, ver site.css) en vez de quedarse como hamburguesa
    // con el menú ya abierto.
    var scrim = document.querySelector('.nav-backdrop');
    if (!scrim) {
      scrim = document.createElement('div');
      scrim.className = 'nav-backdrop';
      document.body.appendChild(scrim);
    }

    // Cabecera propia del pop-up ("Menú" + botón de cerrar): se inyecta una
    // sola vez por panel para no tocar el HTML de las 20 páginas del sitio.
    document.querySelectorAll('.nav-links').forEach(function (links) {
      if (links.querySelector('.nav-panel-header')) return;
      var header = document.createElement('div');
      header.className = 'nav-panel-header';
      header.innerHTML =
        '<span>Menú</span>' +
        '<button type="button" class="nav-panel-cerrar" aria-label="Cerrar menú">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg>' +
        '</button>';
      links.insertBefore(header, links.firstChild);
    });

    function cerrarMenuMovil() {
      document.querySelectorAll('.nav-links.abierto').forEach(function (links) {
        links.classList.remove('abierto');
        var nav = links.closest('nav');
        var boton = nav ? nav.querySelector('.btn-hamburguesa') : null;
        if (boton) {
          boton.classList.remove('abierto');
          boton.setAttribute('aria-label', 'Abrir menú');
          boton.setAttribute('aria-expanded', 'false');
        }
      });
      document.body.classList.remove('menu-abierto');
      scrim.classList.remove('visible');
    }

    document.querySelectorAll('.btn-hamburguesa').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var nav = boton.closest('nav');
        var links = nav ? nav.querySelector('.nav-links') : null;
        if (!links) return;
        var abierto = links.classList.toggle('abierto');
        boton.classList.toggle('abierto', abierto);
        boton.setAttribute('aria-label', abierto ? 'Cerrar menú' : 'Abrir menú');
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        document.body.classList.toggle('menu-abierto', abierto);
        scrim.classList.toggle('visible', abierto);
      });
    });

    // Cerrar el panel al tocar el scrim, al presionar Escape, o al elegir
    // un enlace (evita quedarse con el panel abierto y el scroll bloqueado
    // después de navegar).
    scrim.addEventListener('click', cerrarMenuMovil);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') cerrarMenuMovil();
    });
    document.querySelectorAll('.nav-panel-cerrar').forEach(function (boton) {
      boton.addEventListener('click', cerrarMenuMovil);
    });
    document.querySelectorAll('.nav-links').forEach(function (links) {
      links.querySelectorAll('a:not(.nav-trigger)').forEach(function (enlace) {
        enlace.addEventListener('click', cerrarMenuMovil);
      });
    });

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
