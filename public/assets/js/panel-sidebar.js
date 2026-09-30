/*
 * Veci — botón para colapsar/expandir el sidebar de escritorio a mano.
 * El estado por defecto (expandido ≥1280px, icon-only entre 960-1279px) ya
 * lo resuelve el CSS solo, sin JS: esto únicamente permite override manual,
 * guardado en localStorage. El script que evita el parpadeo al cargar la
 * página vive inline en panel.php (tiene que correr antes del primer
 * pintado, no puede esperar a que este archivo cargue con defer).
 */
(function () {
  'use strict';

  var boton = document.getElementById('pq-sidebar-toggle');
  if (!boton) return;

  function estadoActual() {
    return document.documentElement.getAttribute('data-sidebar') === 'colapsado' ? 'colapsado' : 'expandido';
  }

  function actualizarBoton() {
    var colapsado = estadoActual() === 'colapsado';
    boton.setAttribute('aria-pressed', colapsado ? 'true' : 'false');
    boton.setAttribute('aria-label', colapsado ? 'Expandir menú' : 'Colapsar menú');
    boton.setAttribute('title', colapsado ? 'Expandir menú' : 'Colapsar menú');
  }

  boton.addEventListener('click', function () {
    var nuevoEstado = estadoActual() === 'colapsado' ? 'expandido' : 'colapsado';
    document.documentElement.setAttribute('data-sidebar', nuevoEstado);
    try { localStorage.setItem('veci_sidebar', nuevoEstado); } catch (e) { /* modo privado: solo pierde la persistencia, no rompe el toggle */ }
    actualizarBoton();
  });

  actualizarBoton();
})();
