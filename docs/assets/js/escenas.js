/* Escenas: demostraciones paso a paso dentro de un teléfono.
   Cada escena es un [data-escena="id"] con pantallas ([data-vista]) y una
   lista de pasos (.escena-guion). La línea de tiempo de cada id vive abajo,
   en ESCENAS: [milisegundo dentro del paso, acción, ...argumentos].
   Corre solo mientras se ve; se pausa con el mouse encima; con "reducir
   movimiento" no avanza sola y cada paso se ve con los botones de la lista. */
(function () {
  'use strict';

  var reducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var FUNDIDO = 600;

  function cop(n) { return '$' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

  // ---------------------------------------------------------------------
  // Líneas de tiempo (los mismos tiempos de los prompts de animación)
  // ---------------------------------------------------------------------
  var ESCENAS = {
    // Prompt 1 — del pedido al pago (hero)
    pedido: [
      { dur: 3500, a: [[0, 'ver', 'tienda'], [150, 'clase', '.ui-toldo', 'on'], [700, 'clase', '.ui-carta', 'on']] },
      { dur: 5000, a: [
        [300, 'toca', '[data-plato="bandeja"] .ui-mas'],
        [800, 'clase', '[data-plato="bandeja"] .ui-mas', 'lleva'], [800, 'texto', '[data-plato="bandeja"] .ui-mas-cuenta', '1'],
        [850, 'clase', '.ui-barra-pedido', 'on'],
        [2300, 'toca', '[data-plato="arepa"] .ui-mas'],
        [2800, 'clase', '[data-plato="arepa"] .ui-mas', 'lleva'], [2800, 'texto', '[data-plato="arepa"] .ui-mas-cuenta', '1'],
        [2850, 'texto', '.ui-barra-cuenta', '2'], [2850, 'texto', '.ui-barra-detalle', '2 productos'], [2850, 'cuenta', '.ui-barra-total', 28000, 34000, 450]
      ] },
      { dur: 4000, a: [[200, 'toca', '.ui-barra-pedido'], [750, 'dedo-fuera'], [700, 'ver', 'comanda']] },
      { dur: 4500, a: [
        [200, 'toca', '[data-vista="comanda"] .ui-btn-wa'], [750, 'dedo-fuera'], [700, 'ver', 'chat'],
        [1100, 'escribe', '[data-vista="chat"] .ui-escribe', 'Hola Doña María, quiero: 1× Bandeja paisa, 1× Arepa con queso. Total $34.000 · Pedido #128', 26],
        [4000, 'clase', '[data-vista="chat"] .ui-burbuja-out', 'leido'], [3900, 'clase', '.chip-pedido', 'on']
      ] },
      { dur: 4500, a: [
        [0, 'ver', 'pago'], [600, 'quita', '.chip-pedido', 'on'], [900, 'toca', '[data-vista="pago"] .ui-copiar'], [1400, 'clase', '[data-vista="pago"] .ui-copiable-llave', 'copiado'],
        [1400, 'texto', '[data-vista="pago"] .ui-copiar', 'Copiado ✓'], [1900, 'dedo-fuera'], [2400, 'clase', '[data-vista="pago"] .ui-notif-banco', 'on']
      ] },
      { dur: 5500, a: [
        [0, 'ver', 'panel'], [1000, 'toca', '[data-vista="panel"] .ui-btn-sello'], [1500, 'dedo-fuera'],
        [1500, 'clase', '[data-vista="panel"] .ui-sello', 'cae'], [1500, 'texto', '[data-vista="panel"] .ui-btn-sello', 'Pagado'],
        [1500, 'clase', '[data-vista="panel"] .ui-btn-sello', 'hecho'], [1500, 'texto', '[data-vista="panel"] .ui-estado-pago', 'Pago Bre-B · confirmado'], [2000, 'clase', '[data-vista="panel"] .ui-comision', 'on'], [1700, 'clase', '.chip-pago', 'on']
      ] }
    ],

    // Prompt 2, primera mitad — foto, lectura y revisión
    foto: [
      { dur: 4000, a: [[0, 'ver', 'camara'], [1300, 'toca', '.ui-obturador'], [1800, 'clase', '.ui-camara', 'flash'], [1800, 'dedo-fuera'], [1950, 'clase', '.ui-camara', 'tomada']] },
      { dur: 5000, a: [[0, 'ver', 'leer'], [500, 'toca', '[data-vista="leer"] .ui-btn-sello'], [1000, 'dedo-fuera'], [1000, 'clase', '.ui-foto-leida', 'leyendo'], [1000, 'clase', '.ui-leyendo', 'on']] },
      { dur: 6500, a: [
        [0, 'ver', 'catalogo'], [200, 'clase', '.ui-onb-lista', 'on'],
        [2600, 'toca', '.ui-item-falta .ui-campo'], [3100, 'dedo-fuera'],
        [3100, 'escribe', '.ui-item-falta .ui-campo', '$16.000', 10], [4000, 'clase', '.ui-item-falta', 'completo']
      ] }
    ],

    // Prompt 2, segunda mitad — color del toldo y tienda abierta
    abrir: [
      { dur: 5000, a: [[0, 'ver', 'color'], [1500, 'toca', '[data-color="mostaza"]'], [2000, 'dedo-fuera'],
        [2000, 'clase', '[data-color="mostaza"]', 'activo'], [2000, 'quita', '[data-color="anil"]', 'activo'], [2000, 'clase', '.ui-escaparate', 'mostaza']] },
      { dur: 5500, a: [[0, 'ver', 'abierta'], [200, 'clase', '[data-vista="abierta"] .ui-toldo', 'on'], [800, 'clase', '.ui-letrero', 'on'], [1500, 'clase', '.ui-enlace-tienda', 'on']] },
      { dur: 4500, a: [[300, 'toca', '[data-vista="abierta"] .ui-btn-wa'], [800, 'dedo-fuera'], [800, 'ver', 'compartir'],
        [1100, 'escribe', '[data-vista="compartir"] .ui-escribe', '¡Ya tenemos tienda! Pide aquí: tuveci.co/t/donamaria', 26], [3400, 'clase', '[data-vista="compartir"] .ui-burbuja-out', 'leido']] }
    ],

    // Prompt 3 — cobro por Bre-B
    cobro: [
      { dur: 4500, a: [[0, 'ver', 'tiquete'], [700, 'clase', '[data-vista="tiquete"] .ui-sello', 'cae']] },
      { dur: 4000, a: [[0, 'ver', 'pagar'], [800, 'toca', '[data-vista="pagar"] .ui-copiar'], [1300, 'clase', '[data-vista="pagar"] .ui-copiable-llave', 'copiado'], [1300, 'texto', '[data-vista="pagar"] .ui-copiar', 'Copiado ✓'], [1800, 'dedo-fuera']] },
      { dur: 5500, a: [
        [0, 'ver', 'banco'], [600, 'clase', '.ui-campo-llave', 'pegado'], [1100, 'escribe', '.ui-campo-monto', '$34.000', 10],
        [2300, 'toca', '[data-vista="banco"] .ui-btn-banco'], [2800, 'dedo-fuera'], [2800, 'clase', '.ui-banco-ok', 'on']
      ] },
      { dur: 6000, a: [
        [0, 'ver', 'confirma'], [400, 'clase', '[data-vista="confirma"] .ui-notif', 'on'],
        [1900, 'toca', '[data-vista="confirma"] .ui-btn-sello'], [2400, 'dedo-fuera'],
        [2400, 'clase', '[data-vista="confirma"] .ui-sello', 'cae'], [2400, 'texto', '[data-vista="confirma"] .ui-btn-sello', 'Pagado'],
        [2400, 'clase', '[data-vista="confirma"] .ui-btn-sello', 'hecho'], [2400, 'texto', '[data-vista="confirma"] .ui-estado-pago', 'Pago Bre-B · confirmado'], [2900, 'clase', '[data-vista="confirma"] .ui-comision', 'on']
      ] }
    ],

    // Prompt 4 — copiloto de recompra
    copiloto: [
      { dur: 4500, a: [[0, 'ver', 'lista'], [300, 'clase', '.ui-clientes', 'on']] },
      { dur: 4500, a: [[200, 'toca', '[data-cliente="mj"]'], [700, 'dedo-fuera'], [700, 'ver', 'ficha']] },
      { dur: 6000, a: [[0, 'ver', 'mensaje'], [500, 'escribe', '[data-vista="mensaje"] .ui-escribe', '¡Hola María José! Hoy hay sancocho recién hecho. ¿Te separo una porción?', 22], [4400, 'clase', '.ui-nota-baja', 'on']] },
      { dur: 5000, a: [[200, 'toca', '[data-vista="mensaje"] .ui-btn-wa'], [700, 'dedo-fuera'], [700, 'ver', 'chat'],
        [1300, 'clase', '[data-vista="chat"] .ui-burbuja-out', 'leido'], [1900, 'clase', '.ui-escribiendo', 'on'], [3200, 'quita', '.ui-escribiendo', 'on'], [3200, 'clase', '.ui-burbuja-in', 'on']] },
      { dur: 5000, a: [[0, 'ver', 'lista'], [0, 'clase', '.ui-clientes', 'on'], [300, 'clase', '.ui-recuperado', 'on'], [600, 'cuenta', '.ui-recuperado-cifra', 0, 32000, 800], [900, 'clase', '[data-cliente="mj"]', 'respondio']] }
    ],

    // Prompt 5 — reserva de una cita
    reserva: [
      { dur: 4000, a: [[0, 'ver', 'servicios'], [150, 'clase', '[data-vista="servicios"] .ui-toldo', 'on'], [700, 'clase', '.ui-servicios', 'on']] },
      { dur: 4500, a: [[200, 'toca', '.ui-servicio-1'], [700, 'dedo-fuera'], [700, 'ver', 'quien'], [2100, 'toca', '[data-persona="1"]'], [2600, 'dedo-fuera'], [2600, 'clase', '[data-persona="1"]', 'activa']] },
      { dur: 4500, a: [[0, 'ver', 'dia'], [1300, 'toca', '[data-dia="mar"]'], [1800, 'dedo-fuera'], [1800, 'clase', '[data-dia="mar"]', 'activa']] },
      { dur: 4500, a: [[0, 'ver', 'hora'], [1400, 'toca', '[data-hora="1030"]'], [1900, 'dedo-fuera'], [1900, 'clase', '[data-hora="1030"]', 'activa']] },
      { dur: 4500, a: [[0, 'ver', 'turno'], [2000, 'toca', '[data-vista="turno"] .ui-btn-wa'], [2500, 'dedo-fuera']] },
      { dur: 5500, a: [[0, 'ver', 'confirmada'], [800, 'clase', '[data-vista="confirmada"] .ui-sello', 'cae']] }
    ],

    // Prompt 6 — mostrador de la tienda: escáner, báscula y fiado
    mostrador: [
      { dur: 5500, a: [
        [0, 'ver', 'mostrador'],
        [700, 'escribe', '.ui-lector-txt', '7702004003508', 40], [1150, 'texto', '.ui-lector-txt', ''],
        [1150, 'clase', '[data-linea="gaseosa"]', 'on'], [1150, 'clase', '[data-linea="gaseosa"]', 'brilla'], [1150, 'texto', '.ui-venta-cuenta', '1 producto'], [1150, 'cuenta', '.ui-visor-total', 0, 2500, 300],
        [2500, 'escribe', '.ui-lector-txt', '7702004003508', 40], [2950, 'texto', '.ui-lector-txt', ''],
        [2950, 'texto', '[data-linea="gaseosa"] .ui-venta-cant', '×2'], [2950, 'texto', '[data-linea="gaseosa"] .ui-venta-sub', '$5.000'], [2950, 'cuenta', '.ui-visor-total', 2500, 5000, 300],
        [4000, 'escribe', '.ui-lector-txt', '7702004003508', 40], [4450, 'texto', '.ui-lector-txt', ''],
        [4450, 'texto', '[data-linea="gaseosa"] .ui-venta-cant', '×3'], [4450, 'texto', '[data-linea="gaseosa"] .ui-venta-sub', '$7.500'], [4450, 'cuenta', '.ui-visor-total', 5000, 7500, 300]
      ] },
      { dur: 6000, a: [
        [300, 'escribe', '.ui-lector-txt', 'queso', 8], [1000, 'clase', '.ui-sugerencia', 'on'],
        [1600, 'toca', '.ui-sugerencia'], [2100, 'dedo-fuera'], [2100, 'quita', '.ui-sugerencia', 'on'], [2100, 'texto', '.ui-lector-txt', ''],
        [2150, 'clase', '.ui-bascula', 'on'], [3300, 'toca', '[data-peso="500"]'], [3800, 'dedo-fuera'], [3800, 'clase', '[data-peso="500"]', 'activo'],
        [4300, 'quita', '.ui-bascula', 'on'], [4500, 'clase', '[data-linea="queso"]', 'on'], [4500, 'clase', '[data-linea="queso"]', 'brilla'],
        [4500, 'texto', '.ui-venta-cuenta', '2 productos'], [4500, 'cuenta', '.ui-visor-total', 7500, 16500, 450]
      ] },
      { dur: 5500, a: [
        [0, 'ver', 'cobrar'], [900, 'toca', '[data-tecla="fiado"]'], [1400, 'dedo-fuera'],
        [1400, 'clase', '[data-tecla="fiado"]', 'activa'], [1400, 'quita', '[data-tecla="efectivo"]', 'activa'], [1500, 'clase', '.ui-fiar', 'on'],
        [2700, 'toca', '[data-fiar="jairo"]'], [3200, 'clase', '[data-fiar="jairo"]', 'activa'], [3200, 'texto', '.ui-btn-cobrar', 'Fiar $16.500 a Don Jairo'],
        [4100, 'toca', '.ui-btn-cobrar'], [4600, 'dedo-fuera']
      ] },
      { dur: 5000, a: [
        [0, 'ver', 'cuaderno'], [600, 'cuenta', '.ui-debe', 23000, 39500, 800], [600, 'clase', '.ui-limite', 'sube'],
        [900, 'clase', '.ui-renglon-nuevo', 'on'], [1100, 'escribe', '.ui-renglon-nuevo .ui-renglon-concepto', 'Gaseosa ×3, queso', 16]
      ] },
      { dur: 6000, a: [
        [0, 'ver', 'recordar'], [500, 'clase', '[data-vista="recordar"] .ui-nota-baja', 'on'],
        [2400, 'toca', '[data-vista="recordar"] .ui-btn-wa'], [2900, 'dedo-fuera'], [2900, 'ver', 'enviado'],
        [3600, 'clase', '[data-vista="enviado"] .ui-burbuja-out', 'leido']
      ] }
    ]
  };

  // ---------------------------------------------------------------------
  // Motor
  // ---------------------------------------------------------------------
  function Escena(raiz, pasos) {
    this.raiz = raiz;
    this.pasos = pasos;
    this.telefono = raiz.querySelector('.telefono');
    this.zonas = Array.prototype.map.call(raiz.querySelectorAll('[data-escena-reset]'), function (el) {
      return { el: el, html: el.innerHTML, clase: el.className };
    });
    this.botones = Array.prototype.slice.call(raiz.querySelectorAll('.escena-guion button'));
    this.texto = raiz.querySelector('.escena-texto');
    this.paso = 0;
    this.t = 0;
    this.visible = false;
    this.encima = false;
    this.fundiendo = -1;
    this.animaciones = [];
    this.iniciada = false;
    this.primera = true;
    var self = this;
    this.botones.forEach(function (b, i) {
      b.addEventListener('click', function () {
        self.irA(i);
        if (reducido) self.aplicarHasta(i);
      });
    });
    if (this.telefono && window.matchMedia('(hover: hover)').matches) {
      this.telefono.addEventListener('mouseenter', function () { self.encima = true; self.raiz.classList.add('escena-pausada'); });
      this.telefono.addEventListener('mouseleave', function () { self.encima = false; self.raiz.classList.remove('escena-pausada'); });
    }
  }

  Escena.prototype.q = function (sel) { return this.raiz.querySelectorAll(sel); };

  Escena.prototype.restaurar = function () {
    this.zonas.forEach(function (z) { z.el.innerHTML = z.html; z.el.className = z.clase; });
    this.ui = this.raiz.querySelector('.ui');
    this.dedo = this.raiz.querySelector('.dedo');
    this.animaciones = [];
  };

  Escena.prototype.irA = function (i) {
    this.restaurar();
    for (var j = 0; j < i; j++) this.pasos[j].a.forEach(function (a) { this.ejecutar(a, true); }, this);
    this.paso = i;
    this.t = 0;
    this.hechas = 0;
    this.fundiendo = -1;
    this.pintarGuion(0);
  };

  // Con "reducir movimiento": el paso elegido se muestra ya terminado.
  Escena.prototype.aplicarHasta = function (i) {
    this.pasos[i].a.forEach(function (a) { this.ejecutar(a, true); }, this);
    this.hechas = this.pasos[i].a.length;
    this.pintarGuion(1);
  };

  Escena.prototype.pintarGuion = function (p) {
    var paso = this.paso;
    this.botones.forEach(function (b, i) {
      b.classList.toggle('activo', i === paso);
      b.classList.toggle('hecho', i < paso);
      b.setAttribute('aria-current', i === paso ? 'step' : 'false');
      b.style.setProperty('--p', i < paso ? 1 : i === paso ? p : 0);
    });
    if (this.texto && this.botones[paso]) this.texto.textContent = this.botones[paso].getAttribute('data-texto') || this.botones[paso].textContent;
  };

  Escena.prototype.posicion = function (el) {
    var x = 0, y = 0, n = el;
    while (n && n !== this.ui) { x += n.offsetLeft; y += n.offsetTop; n = n.offsetParent; }
    return { x: x + el.offsetWidth / 2, y: y + el.offsetHeight / 2 };
  };

  Escena.prototype.ejecutar = function (a, instante) {
    var self = this, tipo = a[1], sel = a[2];
    var todos = sel ? this.q(sel) : [];
    var el = todos[0];
    switch (tipo) {
      case 'ver':
        var vistas = this.ui.querySelectorAll('[data-vista]');
        Array.prototype.forEach.call(vistas, function (v) {
          var esta = v.getAttribute('data-vista') === sel;
          if (instante) v.classList.add('sin-transicion');
          if (!esta && v.classList.contains('activa')) v.classList.add('sale');
          if (esta) v.classList.remove('sale');
          v.classList.toggle('activa', esta);
          if (esta && self.telefono) self.telefono.setAttribute('data-barra', v.getAttribute('data-barra') || 'papel');
        });
        if (instante) setTimeout(function () { Array.prototype.forEach.call(vistas, function (v) { v.classList.remove('sin-transicion', 'sale'); }); }, 30);
        break;
      case 'toca':
        if (instante || !el || !this.dedo) return;
        var p = this.posicion(el);
        if (!this.dedo.classList.contains('visible')) {
          // entra desde abajo, como un pulgar, no cruzando la pantalla
          this.dedo.style.transition = 'none';
          this.dedo.style.transform = 'translate(' + (p.x + 26) + 'px,' + (p.y + 90) + 'px)';
          void this.dedo.offsetWidth;
          this.dedo.style.transition = '';
        }
        this.dedo.style.transform = 'translate(' + p.x + 'px,' + p.y + 'px)';
        this.dedo.classList.add('visible');
        setTimeout(function () {
          if (!self.dedo) return;
          self.dedo.classList.add('pulsa');
          el.classList.add('tocado');
          setTimeout(function () { if (self.dedo) self.dedo.classList.remove('pulsa'); el.classList.remove('tocado'); }, 260);
        }, 480);
        break;
      case 'dedo-fuera':
        if (this.dedo) this.dedo.classList.remove('visible');
        break;
      case 'clase':
        Array.prototype.forEach.call(todos, function (n) { n.classList.add(a[3]); if (instante) n.classList.add('sin-transicion'); });
        if (instante) setTimeout(function () { Array.prototype.forEach.call(todos, function (n) { n.classList.remove('sin-transicion'); }); }, 30);
        break;
      case 'quita':
        Array.prototype.forEach.call(todos, function (n) { n.classList.remove(a[3]); });
        break;
      case 'texto':
        Array.prototype.forEach.call(todos, function (n) {
          n.textContent = a[3];
          if (!instante) { n.classList.remove('cambia'); void n.offsetWidth; n.classList.add('cambia'); }
        });
        break;
      case 'escribe':
        if (!el) return;
        var texto = a[3], cps = a[4] || 18;
        if (instante) { el.textContent = texto; return; }
        el.classList.add('escribiendo');
        var inicio = this.t;
        this.animaciones.push(function (t) {
          var n = Math.min(texto.length, Math.floor((t - inicio) * cps / 1000));
          el.textContent = texto.slice(0, n);
          if (n >= texto.length) { el.classList.remove('escribiendo'); return true; }
        });
        break;
      case 'cuenta':
        if (!el) return;
        var desde = a[3], hasta = a[4], ms = a[5] || 600;
        if (instante) { el.textContent = cop(hasta); return; }
        var t0 = this.t;
        el.classList.add('cambia');
        this.animaciones.push(function (t) {
          var k = Math.min(1, (t - t0) / ms);
          var e = 1 - Math.pow(1 - k, 3);
          el.textContent = cop(desde + (hasta - desde) * e);
          return k >= 1;
        });
        break;
    }
  };

  Escena.prototype.avanzar = function (dt) {
    if (this.fundiendo >= 0) {
      this.fundiendo += dt;
      if (this.fundiendo >= FUNDIDO) {
        this.irA(0);
        if (this.ui) this.ui.classList.remove('fundido');
      }
      return;
    }
    var paso = this.pasos[this.paso];
    this.t += dt;
    while (this.hechas < paso.a.length && paso.a[this.hechas][0] <= this.t) {
      this.ejecutar(paso.a[this.hechas], false);
      this.hechas++;
    }
    var t = this.t;
    this.animaciones = this.animaciones.filter(function (f) { return !f(t); });
    this.pintarGuion(Math.min(1, this.t / paso.dur));
    if (this.t >= paso.dur) {
      if (this.paso < this.pasos.length - 1) {
        this.paso++;
        this.t = 0;
        this.hechas = 0;
        this.pintarGuion(0);
      } else {
        this.fundiendo = 0;
        if (this.dedo) this.dedo.classList.remove('visible');
        if (this.ui) this.ui.classList.add('fundido');
      }
    }
  };

  Escena.prototype.corre = function () {
    var corre = this.visible && !this.encima && !document.hidden && !reducido;
    if (corre && this.primera) { this.primera = false; this.irA(0); }
    return corre;
  };

  // ---------------------------------------------------------------------
  // Arranque
  // ---------------------------------------------------------------------
  var escenas = [];
  Array.prototype.forEach.call(document.querySelectorAll('[data-escena]'), function (raiz) {
    var pasos = ESCENAS[raiz.getAttribute('data-escena')];
    if (!pasos) return;
    var e = new Escena(raiz, pasos);
    e.irA(0);
    e.aplicarHasta(0);
    if (reducido) raiz.classList.add('escena-quieta');
    escenas.push(e);
  });
  if (!escenas.length) return;

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (en) {
        escenas.forEach(function (e) { if (e.raiz === en.target) e.visible = en.isIntersecting; });
      });
    }, { threshold: 0.3 });
    escenas.forEach(function (e) { io.observe(e.raiz); });
  } else {
    escenas.forEach(function (e) { e.visible = true; });
  }

  if (reducido) return;
  var ultimo = 0;
  function cuadro(ahora) {
    var dt = ultimo ? Math.min(100, ahora - ultimo) : 16;
    ultimo = ahora;
    escenas.forEach(function (e) { if (e.corre()) e.avanzar(dt); });
    requestAnimationFrame(cuadro);
  }
  requestAnimationFrame(cuadro);
})();
