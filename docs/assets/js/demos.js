/* Veci — demo interactiva de las dos tiendas (pedidos y reservas). 100% en el navegador, sin backend: reproduce el flujo real de la app para que se pueda probar sin instalar nada. */
(function () {
  'use strict';

  function formatoCOP(n) {
    return '$' + Math.round(n).toLocaleString('es-CO');
  }

  // ---------------------------------------------------------------------
  // Tabs: Pedidos / Reservas
  // ---------------------------------------------------------------------
  function initTabs() {
    var botones = document.querySelectorAll('.demo-tab');
    var paneles = document.querySelectorAll('.demo-panel');
    botones.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var destino = btn.getAttribute('data-demo-tab');
        botones.forEach(function (b) { b.classList.toggle('activo', b === btn); });
        paneles.forEach(function (p) { p.classList.toggle('activo', p.id === destino); });
        history.replaceState(null, '', '#' + destino);
      });
    });
    var hash = (location.hash || '').replace('#', '');
    if (hash) {
      var btn = document.querySelector('.demo-tab[data-demo-tab="' + hash + '"]');
      if (btn) btn.click();
    }
  }

  // ---------------------------------------------------------------------
  // Vista cliente / vista administrador, dentro de cada demo
  // ---------------------------------------------------------------------
  function initVistaToggle() {
    document.querySelectorAll('.demo-vista-toggle').forEach(function (toggle) {
      var panel = toggle.closest('.demo-panel');
      if (!panel) return;
      toggle.querySelectorAll('button').forEach(function (btn) {
        btn.addEventListener('click', function () {
          panel.setAttribute('data-vista', btn.getAttribute('data-vista-btn'));
          toggle.querySelectorAll('button').forEach(function (b) { b.classList.toggle('activo', b === btn); });
        });
      });
    });
  }

  // ---------------------------------------------------------------------
  // Demo de pedidos (carrito real, cliente elige productos)
  // Se inicializa una vez por cada .demo-panel[data-tipo="pedidos"], así
  // que un mismo código sirve para Doña María, el minimarket, etc.
  // ---------------------------------------------------------------------
  function initPedidos(raiz) {
    if (!raiz) return;

    var carrito = {}; // id -> cantidad
    var botonesAgregar = raiz.querySelectorAll('.demo-producto [data-add]');
    var listaCarrito = raiz.querySelector('.demo-carrito-lista');
    var totalEl = raiz.querySelector('.demo-carrito-total');
    var barraEl = raiz.querySelector('.demo-carrito-barra');
    var btnConfirmar = raiz.querySelector('.demo-confirmar');
    var vistaExito = raiz.querySelector('.demo-exito');
    var mensajeExito = raiz.querySelector('.demo-exito-mensaje');

    function productos() {
      var mapa = {};
      raiz.querySelectorAll('.demo-producto').forEach(function (el) {
        mapa[el.getAttribute('data-id')] = {
          nombre: el.getAttribute('data-nombre'),
          precio: parseInt(el.getAttribute('data-precio'), 10),
        };
      });
      return mapa;
    }
    var catalogo = productos();

    function render() {
      var ids = Object.keys(carrito).filter(function (id) { return carrito[id] > 0; });
      listaCarrito.innerHTML = '';
      var total = 0;
      var cantidad = 0;

      if (ids.length === 0) {
        listaCarrito.innerHTML = '<p class="demo-ayuda">Agrega productos para armar el pedido.</p>';
      }

      ids.forEach(function (id) {
        var prod = catalogo[id];
        var sub = prod.precio * carrito[id];
        total += sub;
        cantidad += carrito[id];
        var fila = document.createElement('div');
        fila.className = 'demo-carrito-fila';
        fila.innerHTML =
          '<span>' + carrito[id] + ' × ' + prod.nombre + '</span>' +
          '<span class="mono">' + formatoCOP(sub) + '</span>';
        listaCarrito.appendChild(fila);
      });

      totalEl.textContent = formatoCOP(total);
      barraEl.textContent = cantidad > 0 ? (cantidad + ' producto' + (cantidad === 1 ? '' : 's') + ' · ' + formatoCOP(total)) : '';
      btnConfirmar.disabled = cantidad === 0;
    }

    botonesAgregar.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.closest('.demo-producto').getAttribute('data-id');
        carrito[id] = (carrito[id] || 0) + 1;
        render();
        btn.classList.remove('pulso');
        void btn.offsetWidth;
        btn.classList.add('pulso');
      });
    });

    btnConfirmar.addEventListener('click', function () {
      var ids = Object.keys(carrito).filter(function (id) { return carrito[id] > 0; });
      if (ids.length === 0) return;
      var texto = 'Pedido nuevo de Cliente Demo:\n';
      var total = 0;
      ids.forEach(function (id) {
        var prod = catalogo[id];
        texto += '- ' + carrito[id] + ' x ' + prod.nombre + '\n';
        total += prod.precio * carrito[id];
      });
      texto += 'Total: ' + formatoCOP(total);
      mensajeExito.textContent = texto;
      vistaExito.classList.add('visible');
    });

    raiz.querySelectorAll('.demo-exito-cerrar').forEach(function (btn) {
      btn.addEventListener('click', function () {
        vistaExito.classList.remove('visible');
        carrito = {};
        render();
      });
    });

    render();
  }

  // ---------------------------------------------------------------------
  // Demo de reservas (elegir servicio, día, hora disponible)
  // Igual que initPedidos: una instancia por cada .demo-panel[data-tipo="reservas"].
  // ---------------------------------------------------------------------
  function initReservas(raiz) {
    if (!raiz) return;

    var servicioActivo = null;
    var horaElegida = null;
    var diasCorto = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    // Ocupados de ejemplo: por índice de día (0 = hoy .. 4) y hora "HH:MM".
    var ocupados = {
      0: ['10:00', '10:30', '15:00'],
      1: ['09:30', '11:00', '16:30'],
      2: [],
      3: ['09:00', '09:30', '10:00', '10:30'],
      4: ['14:00'],
    };

    var contDias = raiz.querySelector('.demo-dias');
    var contSlots = raiz.querySelector('.demo-slots');
    var resumen = raiz.querySelector('.demo-resumen-reserva');
    var btnConfirmar = raiz.querySelector('.demo-confirmar-reserva');
    var vistaExito = raiz.querySelector('.demo-exito');
    var mensajeExito = raiz.querySelector('.demo-exito-mensaje');
    var diaActivo = 0;

    function generarSlots(inicioMin, finMin, intervalo) {
      var slots = [];
      for (var m = inicioMin; m < finMin; m += intervalo) {
        slots.push((Math.floor(m / 60) + '').padStart(2, '0') + ':' + ((m % 60) + '').padStart(2, '0'));
      }
      return slots;
    }

    function pintarDias() {
      contDias.innerHTML = '';
      for (var i = 0; i < 5; i++) {
        var fecha = new Date();
        fecha.setDate(fecha.getDate() + i);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'demo-dia' + (i === diaActivo ? ' activo' : '');
        btn.textContent = i === 0 ? 'Hoy' : diasCorto[fecha.getDay()] + ' ' + fecha.getDate();
        btn.addEventListener('click', function (idx) {
          return function () {
            diaActivo = idx;
            horaElegida = null;
            pintarDias();
            pintarSlots();
            actualizarResumen();
          };
        }(i));
        contDias.appendChild(btn);
      }
    }

    function pintarSlots() {
      contSlots.innerHTML = '';
      if (!servicioActivo) {
        contSlots.innerHTML = '<p class="demo-ayuda">Elige un servicio arriba para ver horarios.</p>';
        return;
      }
      var slots = generarSlots(9 * 60, 18 * 60, servicioActivo.duracion);
      var ocupadosDia = ocupados[diaActivo] || [];
      slots.forEach(function (hora) {
        var btn = document.createElement('button');
        btn.type = 'button';
        var ocupado = ocupadosDia.indexOf(hora) !== -1;
        btn.className = 'demo-slot mono' + (ocupado ? ' ocupado' : '') + (hora === horaElegida ? ' activo' : '');
        btn.textContent = hora;
        btn.disabled = ocupado;
        if (!ocupado) {
          btn.addEventListener('click', function () {
            horaElegida = hora;
            pintarSlots();
            actualizarResumen();
          });
        }
        contSlots.appendChild(btn);
      });
    }

    function actualizarResumen() {
      if (servicioActivo && horaElegida) {
        var fecha = new Date();
        fecha.setDate(fecha.getDate() + diaActivo);
        var etiquetaDia = diaActivo === 0 ? 'hoy' : diasCorto[fecha.getDay()] + ' ' + fecha.getDate();
        resumen.innerHTML = '<strong>' + servicioActivo.nombre + '</strong> · ' + etiquetaDia + ' a las ' + horaElegida + ' · <span class="mono">' + formatoCOP(servicioActivo.precio) + '</span>';
        btnConfirmar.disabled = false;
      } else {
        resumen.textContent = 'Elige un servicio y un horario disponible.';
        btnConfirmar.disabled = true;
      }
    }

    raiz.querySelectorAll('.demo-servicio').forEach(function (el) {
      el.addEventListener('click', function () {
        servicioActivo = {
          nombre: el.getAttribute('data-nombre'),
          precio: parseInt(el.getAttribute('data-precio'), 10),
          duracion: parseInt(el.getAttribute('data-duracion'), 10),
        };
        horaElegida = null;
        raiz.querySelectorAll('.demo-servicio').forEach(function (s) { s.classList.toggle('activo', s === el); });
        pintarSlots();
        actualizarResumen();
        var picker = raiz.querySelector('.demo-picker');
        if (picker) picker.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    });

    btnConfirmar.addEventListener('click', function () {
      if (!servicioActivo || !horaElegida) return;
      var fecha = new Date();
      fecha.setDate(fecha.getDate() + diaActivo);
      var etiquetaDia = diaActivo === 0 ? 'hoy' : diasCorto[fecha.getDay()] + ' ' + fecha.getDate();
      mensajeExito.textContent = 'Reserva nueva de Cliente Demo:\n- ' + servicioActivo.nombre + ' el ' + etiquetaDia + ' a las ' + horaElegida + '\nValor: ' + formatoCOP(servicioActivo.precio);
      vistaExito.classList.add('visible');
    });

    raiz.querySelectorAll('.demo-exito-cerrar').forEach(function (btn) {
      btn.addEventListener('click', function () {
        vistaExito.classList.remove('visible');
        servicioActivo = null;
        horaElegida = null;
        raiz.querySelectorAll('.demo-servicio').forEach(function (s) { s.classList.remove('activo'); });
        pintarSlots();
        actualizarResumen();
      });
    });

    pintarDias();
    pintarSlots();
    actualizarResumen();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initVistaToggle();
    document.querySelectorAll('.demo-panel[data-tipo="pedidos"]').forEach(initPedidos);
    document.querySelectorAll('.demo-panel[data-tipo="reservas"]').forEach(initReservas);
  });
})();
