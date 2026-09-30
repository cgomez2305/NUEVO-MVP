(function () {
  'use strict';

  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    return; // navegador sin soporte (Safari viejo, navegadores sin push, etc.)
  }

  var boton = document.getElementById('push-boton');
  if (!boton) return;
  var estado = document.getElementById('push-estado');

  function mostrar(texto, deshabilitado) {
    if (estado) estado.textContent = texto;
    boton.disabled = !!deshabilitado;
  }

  function urlBase64ToUint8Array(base64url) {
    var pad = '='.repeat((4 - (base64url.length % 4)) % 4);
    var base64 = (base64url + pad).replace(/-/g, '+').replace(/_/g, '/');
    var raw = window.atob(base64);
    var salida = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) {
      salida[i] = raw.charCodeAt(i);
    }
    return salida;
  }

  function enviarSuscripcion(sub) {
    var claves = sub.toJSON().keys;
    var cuerpo = new URLSearchParams({
      _csrf: boton.getAttribute('data-csrf'),
      endpoint: sub.endpoint,
      p256dh: claves.p256dh,
      auth: claves.auth,
    });
    return fetch('/panel/push/suscribir', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: cuerpo,
    });
  }

  var registro = null;
  var clavePublica = null;

  function actualizarBotonSegunSuscripcion() {
    registro.pushManager.getSubscription().then(function (sub) {
      if (sub) {
        mostrar('Notificaciones activadas', false);
        boton.textContent = 'Desactivar notificaciones';
        boton.onclick = function () {
          sub.unsubscribe().then(function () {
            var cuerpo = new URLSearchParams({ _csrf: boton.getAttribute('data-csrf'), endpoint: sub.endpoint });
            fetch('/panel/push/desuscribir', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: cuerpo,
            }).finally(actualizarBotonSegunSuscripcion);
          });
        };
      } else {
        boton.textContent = 'Activar notificaciones';
        mostrar('Recibe un aviso aunque tengas el panel cerrado.', false);
        boton.onclick = function () {
          mostrar('Activando…', true);
          Notification.requestPermission().then(function (permiso) {
            if (permiso !== 'granted') {
              mostrar('Bloqueaste los permisos de notificación en el navegador.', false);
              return;
            }
            registro.pushManager
              .subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(clavePublica) })
              .then(enviarSuscripcion)
              .then(actualizarBotonSegunSuscripcion)
              .catch(function () {
                mostrar('No se pudo activar. Intenta de nuevo.', false);
              });
          });
        };
      }
    });
  }

  navigator.serviceWorker
    .register('/sw.js')
    .then(function (r) {
      registro = r;
      return fetch('/panel/push/clave-publica', { credentials: 'same-origin' });
    })
    .then(function (r) { return r.json(); })
    .then(function (datos) {
      if (!datos.clave) {
        boton.style.display = 'none';
        return;
      }
      clavePublica = datos.clave;
      actualizarBotonSegunSuscripcion();
    })
    .catch(function () {
      boton.style.display = 'none';
    });
})();
