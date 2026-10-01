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
  // Botones "copiar" (data-copiar="texto fijo" o data-copiar-de="#selector"
  // para copiar el valor actual de un campo, p. ej. un mensaje que la
  // persona acaba de editar).
  // ---------------------------------------------------------------------
  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-copiar], [data-copiar-de]');
    if (!boton) return;
    evento.preventDefault();

    var referencia = boton.getAttribute('data-copiar-de');
    var campoOrigen = referencia ? document.querySelector(referencia) : null;
    var texto = campoOrigen ? campoOrigen.value : boton.getAttribute('data-copiar');
    if (texto === null || texto === undefined) return;

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

  // ---------------------------------------------------------------------
  // Campos que solo aparecen según lo que la persona eligió en un radio o
  // select (p. ej. la dirección del carrito solo se pide si el cliente
  // marcó "domicilio"). El campo lleva data-mostrar-si="nombre=valor"; su
  // input/textarea con data-requerido-si-visible se vuelve required solo
  // mientras está visible.
  // ---------------------------------------------------------------------
  function actualizarCamposCondicionales() {
    document.querySelectorAll('[data-mostrar-si]').forEach(function (campo) {
      var partes = campo.getAttribute('data-mostrar-si').split('=');
      var nombre = partes[0];
      var valorEsperado = partes[1];
      var elegido = document.querySelector('[name="' + nombre + '"]:checked') || document.querySelector('[name="' + nombre + '"]');
      var mostrar = !!elegido && elegido.value === valorEsperado;
      campo.style.display = mostrar ? '' : 'none';
      var entrada = campo.querySelector('[data-requerido-si-visible]');
      if (entrada) entrada.required = mostrar;
    });
  }
  document.addEventListener('change', actualizarCamposCondicionales);
  document.addEventListener('DOMContentLoaded', actualizarCamposCondicionales);

  // ---------------------------------------------------------------------
  // Precio con puntos de miles mientras se escribe (input data-precio). Es
  // puramente visual: el servidor ya limpia cualquier caracter que no sea
  // dígito (ver dinero_desde_texto() en helpers.php), así que si el
  // navegador no corre este script el formulario sigue funcionando igual,
  // solo sin el separador en pantalla.
  // ---------------------------------------------------------------------
  document.addEventListener('input', function (evento) {
    if (!evento.target.matches || !evento.target.matches('[data-precio]')) return;
    var campo = evento.target;
    var soloDigitos = campo.value.replace(/\D+/g, '');
    var cursorDesdeElFinal = campo.value.length - campo.selectionStart;
    campo.value = soloDigitos === '' ? '' : soloDigitos.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    var nuevaPosicion = Math.max(0, campo.value.length - cursorDesdeElFinal);
    campo.setSelectionRange(nuevaPosicion, nuevaPosicion);
  });

  // ---------------------------------------------------------------------
  // Contador "123/500" bajo un textarea marcado con data-contador (p. ej.
  // el mensaje del copiloto). Puramente informativo: el maxlength del HTML
  // ya impide pasarse aunque este script no corra.
  // ---------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea[data-contador][maxlength]').forEach(function (campo) {
      var contador = document.createElement('span');
      contador.className = 'pq-contador-caracteres';
      var actualizar = function () {
        contador.textContent = campo.value.length + '/' + campo.getAttribute('maxlength');
      };
      actualizar();
      campo.insertAdjacentElement('afterend', contador);
      campo.addEventListener('input', actualizar);
    });
  });

  // ---------------------------------------------------------------------
  // Carrito de la tienda pública (data-carrito-form="agregar"/"quitar"):
  // el form sigue siendo uno normal con su action real, así que sin este
  // script (o si el fetch falla) el envío normal recarga la página y
  // funciona exactamente igual que siempre. Con JS, se manda lo mismo por
  // fetch y se actualiza la barra de carrito / el total en el sitio, sin
  // recargar toda la página solo por sumar un producto.
  // ---------------------------------------------------------------------
  function formatearPesos(valor) {
    return '$' + String(Math.round(valor)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function actualizarCarritoEnPagina(carrito, tipo, fila, productoId) {
    var barra = document.getElementById('pq-barra-carrito');
    if (barra) {
      if (carrito.cantidad > 0) {
        var resumen = document.getElementById('pq-barra-carrito-resumen');
        if (resumen) {
          resumen.textContent = carrito.cantidad + (carrito.cantidad === 1 ? ' producto · ' : ' productos · ') + formatearPesos(carrito.total);
        }
        barra.classList.remove('pq-barra-carrito-oculta');
        barra.classList.add('pq-barra-carrito-pulso');
        window.setTimeout(function () { barra.classList.remove('pq-barra-carrito-pulso'); }, 220);
      } else {
        barra.classList.add('pq-barra-carrito-oculta');
      }
    }

    // Nada más que hacer fuera de la página del carrito (p. ej. el "+" del
    // catálogo): la barra ya quedó al día arriba.
    var total = document.getElementById('pq-carrito-total');
    if (!total) return;

    // Carrito vacío: la página de carrito tiene un estado vacío propio
    // (mensaje + botón "Ver el menú") que solo el servidor sabe armar.
    if (carrito.cantidad === 0) {
      window.location.reload();
      return;
    }

    total.textContent = formatearPesos(carrito.total);
    var stickyTotal = document.getElementById('pq-checkout-sticky-total');
    if (stickyTotal) stickyTotal.textContent = formatearPesos(carrito.total);

    if (tipo === 'quitar') {
      if (fila) fila.remove();
      return;
    }

    // agregar/restar: busca la línea de ESTE producto en la respuesta. Si ya
    // no aparece es que restar la dejó en 0 (se comporta como "quitar").
    var lineaActual = null;
    (carrito.lineas || []).forEach(function (linea) {
      if (String(linea.producto_id) === String(productoId)) lineaActual = linea;
    });

    if (lineaActual === null) {
      if (fila) fila.remove();
      return;
    }

    var cantidadEl = document.getElementById('pq-cantidad-' + productoId);
    if (cantidadEl) cantidadEl.textContent = lineaActual.cantidad;
    var subtotalEl = document.getElementById('pq-subtotal-' + productoId);
    if (subtotalEl) subtotalEl.textContent = formatearPesos(lineaActual.subtotal);
  }

  document.addEventListener('submit', function (evento) {
    var form = evento.target;
    if (!form.matches || !form.matches('[data-carrito-form]')) return;
    if (!window.fetch || !window.FormData) return; // sin soporte: que siga el envío normal

    evento.preventDefault();
    var tipo = form.getAttribute('data-carrito-form');
    var fila = form.closest('.pq-fila-carrito');
    var campoProducto = form.querySelector('[name="producto_id"]');
    var productoId = campoProducto ? campoProducto.value : null;
    // El listener de "enviando…" de arriba ya deshabilitó este botón; como
    // aquí la página no recarga, hay que volver a habilitarlo o el cliente
    // no podría agregar una segunda unidad del mismo producto.
    var boton = form.querySelector('button[type="submit"]');

    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (resp) { return resp.ok ? resp.json() : Promise.reject(); })
      .then(function (carrito) {
        actualizarCarritoEnPagina(carrito, tipo, fila, productoId);
        if (boton) { boton.disabled = false; boton.classList.remove('pq-btn-cargando'); }
      })
      .catch(function () { form.submit(); }); // algo falló: no se pierde la acción, solo recarga
  });

  // ---------------------------------------------------------------------
  // Autocompletar nombre/WhatsApp en el checkout público: conveniencia del
  // navegador (localStorage), nunca algo que el servidor necesite leer — si
  // falla o está bloqueado, los campos simplemente quedan vacíos como antes.
  // ---------------------------------------------------------------------
  var LLAVE_NOMBRE = 'veci_checkout_nombre';
  var LLAVE_TELEFONO = 'veci_checkout_telefono';

  document.addEventListener('DOMContentLoaded', function () {
    var formPedido = document.getElementById('pq-form-pedido');
    if (!formPedido) return;
    try {
      var nombreGuardado = window.localStorage.getItem(LLAVE_NOMBRE);
      var telefonoGuardado = window.localStorage.getItem(LLAVE_TELEFONO);
      var campoNombre = formPedido.querySelector('#nombre');
      var campoTelefono = formPedido.querySelector('#telefono');
      if (nombreGuardado && campoNombre && !campoNombre.value) campoNombre.value = nombreGuardado;
      if (telefonoGuardado && campoTelefono && !campoTelefono.value) campoTelefono.value = telefonoGuardado;
    } catch (e) { /* localStorage no disponible: los campos quedan vacíos, como siempre */ }
  });

  document.addEventListener('submit', function (evento) {
    var form = evento.target;
    if (!form.id || form.id !== 'pq-form-pedido') return;
    try {
      var nombre = form.querySelector('#nombre');
      var telefono = form.querySelector('#telefono');
      if (nombre && nombre.value) window.localStorage.setItem(LLAVE_NOMBRE, nombre.value);
      if (telefono && telefono.value) window.localStorage.setItem(LLAVE_TELEFONO, telefono.value);
    } catch (e) { /* conveniencia opcional: si falla, el pedido sigue su curso igual */ }
  });
})();
