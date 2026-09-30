/*
 * Veci — pequeñas interacciones compartidas por todo el panel y la tienda:
 * copiar al portapapeles, estado "enviando…" en los botones (evita doble
 * clic → producto o pedido duplicado) y que las alertas de éxito se cierren
 * solas. Vive aparte de confirmar.js porque ese archivo existe por una
 * razón puntual (el CSP bloquea JS inline); este es funcionalidad nueva.
 */
(function () {
  'use strict';

  var ICONO_COPIAR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="4" width="10" height="14" rx="2"/><path d="M8 8H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-1"/></svg>';
  var ICONO_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>';

  function copiarTexto(texto) {
    if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
      return navigator.clipboard.writeText(texto).catch(function () { copiarConTextarea(texto); });
    }
    copiarConTextarea(texto);
    return Promise.resolve();
  }

  function copiarConTextarea(texto) {
    var campo = document.createElement('textarea');
    campo.value = texto;
    campo.setAttribute('readonly', '');
    campo.style.position = 'fixed';
    campo.style.left = '-9999px';
    document.body.appendChild(campo);
    campo.select();
    try { document.execCommand('copy'); } catch (e) { /* nada que hacer si tampoco esto funciona */ }
    document.body.removeChild(campo);
  }

  // ---------------------------------------------------------------------
  // Botones "copiar enlace" (data-copiar="texto a copiar")
  // ---------------------------------------------------------------------
  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-copiar]');
    if (!boton) return;
    evento.preventDefault();

    var texto = boton.getAttribute('data-copiar');
    copiarTexto(texto).then(function () {
      if (boton.dataset.copiando === '1') return; // ya está mostrando el check
      boton.dataset.copiando = '1';
      boton.dataset.iconoOriginal = boton.innerHTML;
      boton.innerHTML = ICONO_CHECK;
      boton.classList.add('pq-copiado');
      boton.setAttribute('aria-label', 'Enlace copiado');
      window.setTimeout(function () {
        boton.innerHTML = boton.dataset.iconoOriginal || ICONO_COPIAR;
        boton.classList.remove('pq-copiado');
        boton.setAttribute('aria-label', 'Copiar enlace');
        delete boton.dataset.copiando;
      }, 1700);
    });
  });

  // ---------------------------------------------------------------------
  // Estado "enviando…" al mandar cualquier formulario: evita que un doble
  // clic (o una conexión lenta) cree dos pedidos, dos productos, etc.
  // Se salta si otro listener (confirmar.js) ya canceló el envío.
  // ---------------------------------------------------------------------
  document.addEventListener('submit', function (evento) {
    if (evento.defaultPrevented) return;
    var boton = evento.target.querySelector('button[type="submit"]');
    if (!boton || boton.disabled) return;
    boton.classList.add('pq-btn-cargando');
    boton.disabled = true;
  });

  // ---------------------------------------------------------------------
  // Las alertas de éxito se cierran solas a los pocos segundos; las de
  // error/aviso se quedan hasta que la persona navegue (pueden ser
  // importantes y nadie debería tener que leerlas contra el reloj).
  // ---------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pq-alerta-ok').forEach(function (alerta) {
      window.setTimeout(function () {
        alerta.classList.add('pq-alerta-saliendo');
      }, 4500);
    });
  });
})();
