(function () {
  'use strict';

  var negocioId = parseInt(document.body.getAttribute('data-negocio-id') || '0', 10);
  if (!negocioId) return;

  var claveP = 'veci-notif-pedido-' + negocioId;
  var claveC = 'veci-notif-cita-' + negocioId;

  function leer(clave) {
    try {
      return parseInt(localStorage.getItem(clave) || '0', 10) || 0;
    } catch (e) {
      return 0;
    }
  }

  function guardar(clave, valor) {
    try {
      localStorage.setItem(clave, String(valor));
    } catch (e) {
      /* almacenamiento no disponible: la notificación igual suena en esta sesión */
    }
  }

  var desdePedido = leer(claveP);
  var desdeCita = leer(claveC);
  var primeraConsulta = true;

  function sonar() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      var ctx = new Ctx();
      var osc = ctx.createOscillator();
      var vol = ctx.createGain();
      osc.connect(vol);
      vol.connect(ctx.destination);
      osc.frequency.value = 880;
      vol.gain.setValueAtTime(0.15, ctx.currentTime);
      vol.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
      osc.start();
      osc.stop(ctx.currentTime + 0.4);
    } catch (e) {
      /* si el navegador bloquea audio sin interacción previa, no pasa nada grave */
    }
  }

  function notificar(titulo, cuerpo) {
    sonar();
    if (window.Notification && Notification.permission === 'granted') {
      try {
        new Notification(titulo, { body: cuerpo });
      } catch (e) {
        /* ignorar: el sonido ya avisó */
      }
    }
  }

  function consultar() {
    var url = '/panel/notificaciones/nuevas?desde_pedido=' + desdePedido + '&desde_cita=' + desdeCita;
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (datos) {
        if (!datos) return;

        (datos.pedidos || []).forEach(function (p) {
          if (p.id > desdePedido) desdePedido = p.id;
          if (!primeraConsulta) notificar('Pedido nuevo', p.texto);
        });
        (datos.citas || []).forEach(function (c) {
          if (c.id > desdeCita) desdeCita = c.id;
          if (!primeraConsulta) notificar('Cita nueva', c.texto);
        });

        guardar(claveP, desdePedido);
        guardar(claveC, desdeCita);
        primeraConsulta = false;
      })
      .catch(function () { /* siguiente intento en el próximo ciclo */ });
  }

  if (window.Notification && Notification.permission === 'default') {
    document.addEventListener('click', function pedirPermiso() {
      Notification.requestPermission();
      document.removeEventListener('click', pedirPermiso);
    }, { once: true });
  }

  consultar();
  setInterval(consultar, 15000);
})();
