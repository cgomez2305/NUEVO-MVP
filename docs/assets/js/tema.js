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

    // Hamburguesa: abre/cierra el panel de navegación en mobile.
    document.querySelectorAll('.btn-hamburguesa').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var nav = boton.closest('nav');
        var links = nav ? nav.querySelector('.nav-links') : null;
        if (links) links.classList.toggle('abierto');
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
