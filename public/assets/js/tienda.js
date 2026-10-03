/*
  Tienda pública: navegación de secciones del menú (.pq-categorias).
  Sin este script los chips siguen siendo anclas normales (#seccion-N);
  con él:
    - el chip de la sección que se está viendo queda marcado (y se
      desplaza a la vista si la fila de chips tiene scroll horizontal),
    - la barra muestra su sombra solo cuando ya quedó pegada arriba.
*/
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var nav = document.querySelector('[data-categorias]');
    if (!nav) return;

    var chips = Array.prototype.slice.call(nav.querySelectorAll('a[href^="#seccion-"]'));
    var secciones = chips
      .map(function (chip) { return document.getElementById(chip.getAttribute('href').slice(1)); })
      .filter(Boolean);
    var reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function activar(id) {
      chips.forEach(function (chip) {
        var activo = chip.getAttribute('href') === '#' + id;
        chip.classList.toggle('pq-categoria-activa', activo);
        if (activo) {
          chip.setAttribute('aria-current', 'true');
          // Solo mueve la fila de chips (no la página): scrollIntoView
          // desplazaría también la ventana y pelearía con el scroll del cliente.
          var destino = chip.offsetLeft - (nav.clientWidth - chip.offsetWidth) / 2;
          nav.scrollTo({ left: Math.max(0, destino), behavior: reducirMovimiento ? 'auto' : 'smooth' });
        } else {
          chip.removeAttribute('aria-current');
        }
      });
    }

    // La sección activa es la última cuyo comienzo ya pasó por debajo de la
    // barra pegada; al llegar al final de la página, la última sección (si
    // es corta nunca alcanza a subir hasta la barra y quedaría sin marcar).
    var actual = null;
    var pendiente = false;
    function calcular() {
      pendiente = false;
      var linea = nav.getBoundingClientRect().bottom + 24;
      var elegida = secciones[0];
      secciones.forEach(function (seccion) {
        if (seccion.getBoundingClientRect().top <= linea) elegida = seccion;
      });
      var alFondo = window.scrollY > 0 && window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
      if (alFondo) elegida = secciones[secciones.length - 1];
      if (elegida && elegida.id !== actual) {
        actual = elegida.id;
        activar(actual);
      }
    }
    window.addEventListener('scroll', function () {
      if (!pendiente) { pendiente = true; window.requestAnimationFrame(calcular); }
    }, { passive: true });
    calcular();

    // Sombra al quedar pegada: un centinela de 1px justo antes de la barra.
    var centinela = document.createElement('div');
    centinela.setAttribute('aria-hidden', 'true');
    centinela.style.height = '1px';
    nav.parentNode.insertBefore(centinela, nav);
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entradas) {
        nav.classList.toggle('pq-categorias-pegada', !entradas[0].isIntersecting);
      }).observe(centinela);
    }
  });
})();
