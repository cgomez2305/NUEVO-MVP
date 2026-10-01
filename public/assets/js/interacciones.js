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
  // Validación con mensajes propios bajo cada campo (checkout, reserva y
  // lista de espera), en vez del globo genérico del navegador o de un
  // alert(). Un solo motor reusado por los tres forms de la tienda pública
  // que piden nombre/WhatsApp — ver activarValidacionFormulario() más abajo
  // y sus tres usos en los bloques DOMContentLoaded siguientes.
  // ---------------------------------------------------------------------
  function soloDigitos(valor) { return (valor || '').replace(/\D+/g, ''); }
  function telefonoColombianoValido(valor) {
    var digitos = soloDigitos(valor);
    return digitos.length === 10 && digitos.charAt(0) === '3';
  }

  // El mensaje de error va DESPUÉS del contenedor visual del campo, no
  // después del <input> crudo: para el teléfono eso es el wrapper con el
  // prefijo +57 (si no, el texto quedaría apretado como un tercer item de
  // esa fila en vez de en su propia línea); para el consentimiento, la
  // tarjeta completa del checkbox.
  function contenedorDe(campo) {
    return campo.closest('.pq-input-telefono') || campo.closest('.pq-consentimiento') || campo;
  }

  function limpiarErrorCampo(campo) {
    campo.classList.remove('pq-input-invalido');
    campo.removeAttribute('aria-invalid');
    var siguiente = contenedorDe(campo).nextElementSibling;
    if (siguiente && siguiente.classList.contains('pq-campo-error')) siguiente.remove();
  }

  function mostrarErrorCampo(campo, mensaje, enLabel) {
    limpiarErrorCampo(campo);
    if (!enLabel) campo.classList.add('pq-input-invalido');
    campo.setAttribute('aria-invalid', 'true');
    var error = document.createElement('span');
    error.className = 'pq-campo-error';
    error.textContent = mensaje;
    contenedorDe(campo).insertAdjacentElement('afterend', error);
  }

  function tipoEntregaDe(form) {
    var el = form.querySelector('[name="tipo_entrega"]:checked');
    return el ? el.value : 'domicilio';
  }

  // Reglas reusables: cada una recibe el <form> y devuelve un mensaje de
  // error (string) o null si el campo está bien.
  function errorRequerido(form, selector, mensaje) {
    var campo = form.querySelector(selector);
    return campo && campo.value.trim() === '' ? mensaje : null;
  }
  function errorNombre(form, selector) {
    return errorRequerido(form, selector || '#nombre', 'Escribe tu nombre para continuar.');
  }
  function errorTelefono(form, selector) {
    var campo = form.querySelector(selector || '#telefono');
    if (!campo) return null;
    if (campo.value.trim() === '') return 'Escribe tu WhatsApp para continuar.';
    return telefonoColombianoValido(campo.value) ? null : 'Ingresa un número de WhatsApp válido.';
  }

  // { id, selector, obtenerError(form) => string|null, enLabel }
  function regla(id, selector, obtenerError) {
    return { id: id, selector: selector, obtenerError: obtenerError };
  }
  function reglaConsentimiento(mensaje) {
    return {
      id: 'autorizo_datos', selector: '[name="autorizo_datos"]', enLabel: true,
      obtenerError: function (f) {
        var campo = f.querySelector('[name="autorizo_datos"]');
        return campo && !campo.checked ? mensaje : null;
      },
    };
  }

  /**
   * Conecta un <form> a validación progresiva: nada se marca en rojo al
   * cargar, un campo se valida la primera vez que pierde el foco (queda
   * "tocado") y de ahí en adelante se corrige en vivo mientras se escribe.
   * Al enviar, se marca todo como tocado y se muestran los errores que
   * sigan pendientes, con scroll al primero. novalidate solo lo pone JS:
   * sin JS, el navegador sigue validando con los required/maxlength de
   * siempre — esto es una mejora encima, no la única red de seguridad.
   *
   * opciones.revalidarConCambioDe: nombres de radio/select cuyo cambio
   * puede volver requerido (o no) un campo ya tocado — p. ej. elegir
   * "recoger" en vez de "domicilio" debe soltar el error de Dirección de
   * inmediato, no esperar a que ese campo pierda el foco otra vez.
   */
  function activarValidacionFormulario(form, reglas, opciones) {
    form.setAttribute('novalidate', 'novalidate');
    var tocados = {};

    function revalidar(r) {
      var campo = form.querySelector(r.selector);
      if (!campo) return;
      var mensaje = r.obtenerError(form);
      if (mensaje) mostrarErrorCampo(campo, mensaje, r.enLabel);
      else limpiarErrorCampo(campo);
    }

    reglas.forEach(function (r) {
      var campo = form.querySelector(r.selector);
      if (!campo) return;
      var evento = r.enLabel ? 'change' : 'blur';
      campo.addEventListener(evento, function () { tocados[r.id] = true; revalidar(r); });
      campo.addEventListener('input', function () { if (tocados[r.id]) revalidar(r); });
    });

    var disparadores = (opciones && opciones.revalidarConCambioDe) || [];
    disparadores.forEach(function (nombre) {
      form.querySelectorAll('[name="' + nombre + '"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
          reglas.forEach(function (r) { if (tocados[r.id]) revalidar(r); });
        });
      });
    });

    form.addEventListener('submit', function (evento) {
      var errores = [];
      reglas.forEach(function (r) {
        var campo = form.querySelector(r.selector);
        if (!campo) return;
        var mensaje = r.obtenerError(form);
        if (mensaje) errores.push({ campo: campo, mensaje: mensaje, enLabel: r.enLabel });
      });
      if (errores.length === 0) return;

      evento.preventDefault();
      reglas.forEach(function (r) { tocados[r.id] = true; });
      errores.forEach(function (error, indice) {
        mostrarErrorCampo(error.campo, error.mensaje, error.enLabel);
        if (indice === 0) {
          contenedorDe(error.campo).scrollIntoView({ block: 'center', behavior: 'smooth' });
          error.campo.focus();
        }
      });
    });
  }

  // ---------------------------------------------------------------------
  // Borrador del checkout público (nombre/WhatsApp/dirección): conveniencia
  // del navegador (localStorage), nunca algo que el servidor necesite leer.
  // Se guarda mientras la persona escribe (no solo al enviar con éxito), así
  // un back o un reload accidental a medio llenar no pierde lo ya escrito.
  // Si localStorage falla o está bloqueado, los campos simplemente quedan
  // vacíos como antes — nunca bloquea el pedido.
  // ---------------------------------------------------------------------
  var CAMPOS_BORRADOR = {
    nombre: 'veci_checkout_nombre',
    telefono: 'veci_checkout_telefono',
    direccion: 'veci_checkout_direccion',
    referencia: 'veci_checkout_referencia',
  };

  document.addEventListener('DOMContentLoaded', function () {
    var formPedido = document.getElementById('pq-form-pedido');
    if (!formPedido) return;

    try {
      Object.keys(CAMPOS_BORRADOR).forEach(function (id) {
        var valor = window.localStorage.getItem(CAMPOS_BORRADOR[id]);
        var campo = formPedido.querySelector('#' + id);
        if (valor && campo && !campo.value) campo.value = valor;
      });
    } catch (e) { /* localStorage no disponible: los campos quedan vacíos, como siempre */ }

    function guardarBorrador() {
      try {
        Object.keys(CAMPOS_BORRADOR).forEach(function (id) {
          var campo = formPedido.querySelector('#' + id);
          if (campo && campo.value) window.localStorage.setItem(CAMPOS_BORRADOR[id], campo.value);
        });
      } catch (e) { /* conveniencia opcional: si falla, el pedido sigue su curso igual */ }
    }

    formPedido.addEventListener('input', guardarBorrador);
    formPedido.addEventListener('submit', guardarBorrador);

    activarValidacionFormulario(formPedido, [
      regla('nombre', '#nombre', errorNombre),
      regla('telefono', '#telefono', errorTelefono),
      regla('direccion', '#direccion', function (f) {
        return tipoEntregaDe(f) === 'domicilio' ? errorRequerido(f, '#direccion', 'Necesitamos una dirección para el domicilio.') : null;
      }),
      regla('mesa', '#mesa', function (f) {
        return tipoEntregaDe(f) === 'mesa' ? errorRequerido(f, '#mesa', 'Escribe tu número de mesa.') : null;
      }),
      reglaConsentimiento('Debes aceptar el uso de datos para procesar el pedido.'),
    ], { revalidarConCambioDe: ['tipo_entrega'] });
  });

  // ---------------------------------------------------------------------
  // Validación de los forms de reserva y lista de espera (reservar.php):
  // mismas reglas y mismo comportamiento progresivo que el checkout de
  // arriba, sin duplicar la lógica — ver activarValidacionFormulario().
  // ---------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    var formReserva = document.getElementById('pq-form-reserva');
    if (formReserva) {
      activarValidacionFormulario(formReserva, [
        regla('nombre', '#nombre', errorNombre),
        regla('telefono', '#telefono', errorTelefono),
        reglaConsentimiento('Debes aceptar el uso de datos para continuar.'),
      ]);
    }

    var formListaEspera = document.getElementById('pq-form-lista-espera');
    if (formListaEspera) {
      activarValidacionFormulario(formListaEspera, [
        regla('nombre', '#le-nombre', function (f) { return errorRequerido(f, '#le-nombre', 'Escribe tu nombre para continuar.'); }),
        regla('telefono', '#le-telefono', function (f) { return errorTelefono(f, '#le-telefono'); }),
        reglaConsentimiento('Debes aceptar el uso de datos para continuar.'),
      ]);
    }
  });

  // ---------------------------------------------------------------------
  // Carrusel de fechas (reservar.php / cita_reprogramar.php): con 14 días
  // para elegir, el chip de la fecha activa puede caer fuera de la vista
  // inicial (ej. tras anotarse en la lista de espera para un domingo
  // lejano) y parecer que la pantalla no coincide con la fecha real.
  // Lo centramos al cargar — sin JS el carrusel sigue siendo deslizable
  // a mano, no se pierde información, solo el centrado automático.
  // ---------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pq-dias-scroll .pq-chip-caja').forEach(function (chip) {
      if (typeof chip.scrollIntoView === 'function') {
        chip.scrollIntoView({ inline: 'center', block: 'nearest' });
      }
    });
  });
})();
