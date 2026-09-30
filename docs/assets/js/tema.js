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

  function aplicarTema(tema) {
    if (tema === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    document.querySelectorAll('.brand[data-logo-claro][data-logo-oscuro]').forEach(function (marca) {
      var img = marca.querySelector('img');
      if (!img) return;
      img.src = tema === 'dark' ? marca.getAttribute('data-logo-oscuro') : marca.getAttribute('data-logo-claro');
    });
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

    // Hamburguesa: abre/cierra el panel de navegación en mobile, y el ícono
    // se convierte en una X (las tres líneas del SVG, ver site.css) en vez
    // de quedarse como hamburguesa con el menú ya abierto.
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
      });
    });

    // Cerrar el menú mobile al elegir un enlace (evita quedarse con el
    // panel abierto y el scroll bloqueado después de navegar).
    document.querySelectorAll('.nav-links').forEach(function (links) {
      links.querySelectorAll('a:not(.nav-trigger)').forEach(function (enlace) {
        enlace.addEventListener('click', function () {
          links.classList.remove('abierto');
          document.body.classList.remove('menu-abierto');
          var boton = links.closest('nav').querySelector('.btn-hamburguesa');
          if (boton) {
            boton.classList.remove('abierto');
            boton.setAttribute('aria-label', 'Abrir menú');
            boton.setAttribute('aria-expanded', 'false');
          }
        });
      });
    });

    // Mega menú: en mobile (o teclado) se abre con click/Enter en vez de solo :hover.
    document.querySelectorAll('.nav-trigger').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var item = boton.closest('.nav-item-menu');
        if (item) item.classList.toggle('abierto');
      });
    });
  });
})();
