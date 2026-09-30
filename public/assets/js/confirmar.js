/*
 * Veci — interacciones que antes vivían como atributos inline (onsubmit,
 * onchange, onclick) y que la Content-Security-Policy del sitio bloquea en
 * silencio (script-src 'self' no permite JS inline, ver src/bootstrap.php).
 * Sin este archivo, un formulario con onsubmit="return confirm(...)" se
 * envía igual —sin preguntar nada— porque el navegador descarta el atributo
 * y no hay error visible para quien lo está usando.
 *
 * Uso en las plantillas:
 *   <form data-confirmar="¿Seguro?">              en vez de onsubmit="return confirm(...)"
 *   <select data-autoenviar>                       en vez de onchange="this.form.submit()"
 *   <input data-seleccionar-al-tocar>              en vez de onclick="this.select()"
 *   <button data-imprimir>                         en vez de onclick="window.print()"
 *   <tr data-href="/panel/pedidos/1">               en vez de onclick="location.href=..."
 */
(function () {
  'use strict';

  document.addEventListener('submit', function (evento) {
    var mensaje = evento.target.getAttribute && evento.target.getAttribute('data-confirmar');
    if (mensaje && !window.confirm(mensaje)) {
      evento.preventDefault();
    }
  });

  document.addEventListener('change', function (evento) {
    if (evento.target.matches && evento.target.matches('[data-autoenviar]')) {
      var formulario = evento.target.closest('form');
      if (formulario) formulario.submit();
    }
  });

  document.addEventListener('click', function (evento) {
    if (evento.target.matches && evento.target.matches('[data-seleccionar-al-tocar]')) {
      evento.target.select();
    }
    if (evento.target.closest && evento.target.closest('[data-imprimir]')) {
      window.print();
    }
    // Fila de tabla clicable (historial de pedidos): ignora el clic si ya
    // cayó sobre un enlace o botón propio de la fila, para no interceptar
    // ese clic ni navegar dos veces.
    var fila = evento.target.closest && evento.target.closest('[data-href]');
    if (fila && !evento.target.closest('a, button, input, select')) {
      window.location.href = fila.getAttribute('data-href');
    }
  });

  // Los desplegables del panel (selector de sede, menús ⋮, "Más" del
  // celular, "+ Nueva sede") son todos <details> nativos: se abren con un
  // clic sin JS, pero el navegador no los cierra solo con Escape como sí
  // hace con un <dialog>. Se agrega ese único comportamiento que falta,
  // para cualquier <details> del panel, sin tocar cuáles existen.
  document.addEventListener('keydown', function (evento) {
    if (evento.key !== 'Escape') return;
    var abierto = document.querySelector('.pq-shell details[open]');
    if (!abierto) return;
    abierto.removeAttribute('open');
    var resumen = abierto.querySelector('summary');
    if (resumen) resumen.focus();
  });
})();
