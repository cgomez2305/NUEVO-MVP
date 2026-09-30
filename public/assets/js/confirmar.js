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
  });
})();
