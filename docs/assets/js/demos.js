/* Veci — demo interactiva de las tiendas (pedidos y reservas). 100% en el
   navegador, sin backend: reproduce el flujo real de la app para que se
   pueda probar sin instalar nada. Los toques "de verdad" (avatares por
   iniciales, iconos del panel, la burbuja de WhatsApp con "escribiendo…")
   se arman aquí en vez de repetirse a mano en las cinco industrias del HTML. */
(function () {
  'use strict';

  function formatoCOP(n) {
    return '$' + Math.round(n).toLocaleString('es-CO');
  }

  function icono(pathInterior) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + pathInterior + '</svg>';
  }

  // ---------------------------------------------------------------------
  // Tabs: cambia de industria (Doña María / El Ahorro / Salón Bonita / ...)
  // ---------------------------------------------------------------------
  function initTabs() {
    var botones = document.querySelectorAll('.demo-tab');
    var paneles = document.querySelectorAll('.demo-panel');
    botones.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var destino = btn.getAttribute('data-demo-tab');
        botones.forEach(function (b) { b.classList.toggle('activo', b === btn); });
        paneles.forEach(function (p) { p.classList.toggle('activo', p.id === destino); });
        if (btn.scrollIntoView) btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
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
  // Pantalla de "éxito": una burbuja de WhatsApp real, con "escribiendo…"
  // antes de que aparezca el mensaje — no una tarjeta plana de "listo".
  // Compartida por pedidos y reservas.
  // ---------------------------------------------------------------------
  function mostrarExito(raiz, texto) {
    var vistaExito = raiz.querySelector('.demo-exito');
    var cuerpo = raiz.querySelector('.demo-chat-cuerpo');
    if (!vistaExito || !cuerpo) return;

    var avatarTienda = raiz.querySelector('.demo-tienda-header .avatar');
    var nombreTienda = raiz.querySelector('.demo-tienda-header strong');
    var avatarChat = raiz.querySelector('.demo-chat-avatar');
    var infoChat = raiz.querySelector('.demo-chat-info strong');

    if (avatarChat && avatarTienda) {
      avatarChat.textContent = avatarTienda.textContent;
      avatarChat.style.background = avatarTienda.style.background || 'var(--sello)';
    }
    if (infoChat && nombreTienda) infoChat.textContent = nombreTienda.textContent;

    cuerpo.innerHTML = '<div class="demo-chat-escribiendo"><span></span><span></span><span></span></div>';
    vistaExito.classList.add('visible');

    window.setTimeout(function () {
      if (!vistaExito.classList.contains('visible')) return; // se cerró antes de que "llegara"
      var ahora = new Date();
      var hora12 = ahora.getHours() % 12 || 12;
      var minutos = ('0' + ahora.getMinutes()).slice(-2);
      var ampm = ahora.getHours() >= 12 ? 'p.m.' : 'a.m.';
      cuerpo.innerHTML =
        '<div class="demo-chat-burbuja">' +
          '<div class="demo-chat-texto"></div>' +
          '<span class="demo-chat-hora">' + hora12 + ':' + minutos + ' ' + ampm + ' ' +
            icono('<path d="M1 8l3.5 3.5L11 4.5"/><path d="M5.5 8l3.5 3.5L16 4.5"/>').replace('viewBox="0 0 24 24"', 'viewBox="0 0 17 13"').replace('stroke-width="2"', 'stroke-width="1.6"') +
          '</span>' +
        '</div>';
      cuerpo.querySelector('.demo-chat-texto').textContent = texto;
    }, 850);
  }

  // ---------------------------------------------------------------------
  // Demo de pedidos (carrito real, con contador +/- por línea)
  // Una instancia por cada .demo-panel[data-tipo="pedidos"].
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

    raiz.querySelectorAll('.demo-producto').forEach(function (el, i) { el.style.setProperty('--i', i); });

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

    function cambiarCantidad(id, delta) {
      carrito[id] = Math.max(0, (carrito[id] || 0) + delta);
      if (carrito[id] === 0) delete carrito[id];
      render();
    }

    function render(totalAnterior) {
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
          '<span class="demo-carrito-nombre">' + prod.nombre + '</span>' +
          '<span class="demo-carrito-stepper">' +
            '<button type="button" data-menos aria-label="Quitar uno">−</button>' +
            '<span>' + carrito[id] + '</span>' +
            '<button type="button" data-mas aria-label="Agregar uno">+</button>' +
          '</span>' +
          '<span class="mono demo-carrito-sub">' + formatoCOP(sub) + '</span>';
        fila.querySelector('[data-menos]').addEventListener('click', function () { cambiarCantidad(id, -1); });
        fila.querySelector('[data-mas]').addEventListener('click', function () { cambiarCantidad(id, 1); });
        listaCarrito.appendChild(fila);
      });

      totalEl.textContent = formatoCOP(total);
      if (typeof totalAnterior === 'number' && totalAnterior !== total) {
        totalEl.classList.remove('bump');
        void totalEl.offsetWidth;
        totalEl.classList.add('bump');
      }
      barraEl.textContent = cantidad > 0 ? (cantidad + ' producto' + (cantidad === 1 ? '' : 's') + ' · ' + formatoCOP(total)) : '';
      btnConfirmar.disabled = cantidad === 0;
    }

    botonesAgregar.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tarjeta = btn.closest('.demo-producto');
        var id = tarjeta.getAttribute('data-id');
        var totalPrevio = Object.keys(carrito).reduce(function (acc, k) { return acc + catalogo[k].precio * carrito[k]; }, 0);
        carrito[id] = (carrito[id] || 0) + 1;
        render(totalPrevio);
        btn.classList.remove('pulso');
        void btn.offsetWidth;
        btn.classList.add('pulso');

        var flotante = document.createElement('span');
        flotante.className = 'demo-flotante';
        flotante.textContent = '+1';
        btn.appendChild(flotante);
        window.setTimeout(function () { flotante.remove(); }, 700);
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
      mostrarExito(raiz, texto);
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
  // Una instancia por cada .demo-panel[data-tipo="reservas"].
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
    var diaActivo = 0;

    // Pasos guía ("1 · Elige un servicio" / "2 · Elige día y hora") y el
    // círculo de check dentro de cada botón de servicio: se inyectan aquí
    // para no repetir el mismo marcado cinco veces en el HTML.
    var contServicios = raiz.querySelector('.demo-servicios');
    if (contServicios && contServicios.parentNode) {
      var paso1 = document.createElement('div');
      paso1.className = 'demo-paso';
      paso1.innerHTML = '<b>1</b> Elige un servicio';
      contServicios.parentNode.insertBefore(paso1, contServicios);
    }
    var contPicker = raiz.querySelector('.demo-picker');
    if (contPicker && contPicker.parentNode) {
      var paso2 = document.createElement('div');
      paso2.className = 'demo-paso';
      paso2.innerHTML = '<b>2</b> Elige día y hora';
      contPicker.parentNode.insertBefore(paso2, contPicker);
    }
    raiz.querySelectorAll('.demo-servicio').forEach(function (el) {
      var precio = el.querySelector('.precio');
      if (!precio || el.querySelector('.check')) return;
      var envoltura = document.createElement('span');
      envoltura.style.display = 'flex';
      envoltura.style.alignItems = 'center';
      envoltura.style.gap = '10px';
      var check = document.createElement('span');
      check.className = 'check';
      check.innerHTML = icono('<path d="M5 13l4 4L19 7"/>').replace('stroke-width="2"', 'stroke-width="3"');
      el.appendChild(envoltura);
      envoltura.appendChild(precio);
      envoltura.appendChild(check);
    });

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
      var texto = 'Reserva nueva de Cliente Demo:\n- ' + servicioActivo.nombre + ' el ' + etiquetaDia + ' a las ' + horaElegida + '\nValor: ' + formatoCOP(servicioActivo.precio);
      mostrarExito(raiz, texto);
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

  // ---------------------------------------------------------------------
  // Vista administrador: iconos en el nav, iconos por stat y avatares con
  // iniciales por fila — todo inyectado una vez, para que las cinco
  // industrias no tengan que repetir el mismo SVG en el HTML.
  // ---------------------------------------------------------------------
  var ICONOS_NAV = {
    panel: icono('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'),
    pedidos: icono('<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>'),
    agenda: icono('<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>'),
    copiloto: icono('<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/>'),
    menú: icono('<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>'),
    servicios: icono('<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 16l9 5 9-5M3 12l9 5 9-5"/>'),
    horario: icono('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'),
  };
  var COLORES_AVATAR = ['#3B4CCA', '#E8452C', '#16A36A', '#B8860B', '#7A5FE0', '#C0447A'];

  function iniciales(nombreCompleto) {
    var soloNombre = nombreCompleto.split('·')[0].trim();
    var partes = soloNombre.split(' ').filter(function (p) { return p.length > 0; });
    if (partes.length === 0) return '?';
    if (partes.length === 1) return partes[0].substring(0, 2).toUpperCase();
    return (partes[0].charAt(0) + partes[1].charAt(0)).toUpperCase();
  }

  function iconoStat(etiqueta) {
    if (etiqueta.indexOf('recompra') !== -1) {
      return { html: icono('<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>'), bg: 'rgba(59,76,202,.14)', color: '#3B4CCA' };
    }
    if (etiqueta.indexOf('reactivar') !== -1) {
      return { html: icono('<path d="M12 8v5"/><path d="M12 16.5h.01"/><circle cx="12" cy="12" r="9"/>'), bg: 'rgba(232,69,44,.13)', color: '#E8452C' };
    }
    return { html: icono('<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/>'), bg: 'rgba(22,163,106,.14)', color: '#16A36A' };
  }

  function initAdminExtras(admin) {
    admin.querySelectorAll('.demo-admin-nav span').forEach(function (span) {
      if (span.querySelector('svg')) return;
      var clave = span.textContent.trim().toLowerCase();
      if (ICONOS_NAV[clave]) span.insertAdjacentHTML('afterbegin', ICONOS_NAV[clave]);
    });

    admin.querySelectorAll('.demo-admin-stat').forEach(function (stat, i) {
      if (stat.querySelector('.demo-admin-stat-icono')) return;
      var etiqueta = (stat.querySelector('span') || {}).textContent || '';
      var datos = iconoStat(etiqueta);
      var icono2 = document.createElement('div');
      icono2.className = 'demo-admin-stat-icono';
      icono2.style.background = datos.bg;
      icono2.style.color = datos.color;
      icono2.innerHTML = datos.html;
      stat.insertBefore(icono2, stat.firstChild);
      stat.style.setProperty('--i', i);
    });

    admin.querySelectorAll('.demo-admin-fila').forEach(function (fila, i) {
      fila.style.setProperty('--i', i);
      if (fila.querySelector('.demo-admin-avatar')) return;
      var nombreEl = fila.querySelector('.nombre');
      var infoDiv = nombreEl ? nombreEl.parentElement : null;
      if (!infoDiv) return;
      infoDiv.classList.add('info');
      var avatar = document.createElement('div');
      avatar.className = 'demo-admin-avatar';
      avatar.textContent = iniciales(nombreEl.textContent);
      avatar.style.background = COLORES_AVATAR[i % COLORES_AVATAR.length];
      fila.insertBefore(avatar, infoDiv);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initVistaToggle();
    document.querySelectorAll('.demo-panel[data-tipo="pedidos"]').forEach(initPedidos);
    document.querySelectorAll('.demo-panel[data-tipo="reservas"]').forEach(initReservas);
    document.querySelectorAll('.demo-admin').forEach(initAdminExtras);
  });
})();
