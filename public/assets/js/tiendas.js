/*
 * Veci — tiendas de barrio (fase 4): mostrador con lector de códigos,
 * cámara y báscula; ayudas del formulario de producto (código, costo,
 * margen, venta por peso). Solo se carga en negocios de pedidos.
 *
 * Todo funciona sin este archivo (formularios reales, ver
 * MostradorController): aquí solo se vuelve inmediato. Con el catálogo de
 * la sede ya cargado, escanear no espera al servidor: el tiquete se arma
 * en el navegador, se copia a la sesión en segundo plano (por si se
 * recarga) y al cobrar viaja con el formulario; el servidor vuelve a armar
 * todo con los precios de la base.
 *
 * Los formularios del mostrador se interceptan en fase de captura: así el
 * "enviando…" general (interacciones.js) no apaga botones de una página
 * que no se va a recargar.
 */
(function () {
  'use strict';

  var GRAMOS_POR_KILO = 1000;
  var MAX_UNIDADES = 999;
  var MAX_GRAMOS = 50000;

  function pesos(valor) {
    var signo = valor < 0 ? '-' : '';
    return signo + '$' + String(Math.abs(Math.round(valor))).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }
  function soloDigitos(texto) { return String(texto || '').replace(/\D+/g, ''); }
  function numero(texto) { return parseInt(soloDigitos(texto), 10) || 0; }
  function sinTildes(texto) {
    return String(texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  function normalizarCodigo(texto) {
    var codigo = String(texto || '').replace(/\s+/g, '').toUpperCase();
    return /^[0-9A-Z.\-]{3,32}$/.test(codigo) ? codigo : null;
  }
  function gramosLegibles(gramos) {
    if (Math.abs(gramos) < GRAMOS_POR_KILO) return gramos + ' g';
    return String(Math.round(gramos) / GRAMOS_POR_KILO).replace('.', ',') + ' kg';
  }
  /** Igual que Producto::precioPorGramos: redondeado a $50, nunca menos de $50. */
  function precioPorGramos(precioKilo, gramos) {
    return Math.max(50, Math.round(precioKilo * gramos / GRAMOS_POR_KILO / 50) * 50);
  }
  function crear(etiqueta, clase, texto) {
    var el = document.createElement(etiqueta);
    if (clase) el.className = clase;
    if (texto !== undefined) el.textContent = texto;
    return el;
  }

  // Un pitido corto al escanear (bien) o al no encontrar (mal): quien cobra
  // está mirando al cliente, no la pantalla. Si el navegador no deja, nada.
  var audio = null;
  function pitar(bien) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var osc = audio.createOscillator();
      var vol = audio.createGain();
      osc.frequency.value = bien ? 1320 : 330;
      vol.gain.value = 0.06;
      osc.connect(vol);
      vol.connect(audio.destination);
      osc.start();
      osc.stop(audio.currentTime + (bien ? 0.07 : 0.22));
    } catch (e) { /* sin sonido: no importa */ }
  }

  // ===================================================================
  // Mostrador
  // ===================================================================
  function iniciarMostrador(raiz) {
    var lector = raiz.querySelector('[data-mostrador-lector]');
    var campo = raiz.querySelector('[data-mostrador-codigo]');
    var aviso = raiz.querySelector('[data-mostrador-aviso]');
    var sugerencias = raiz.querySelector('[data-mostrador-sugerencias]');
    var lista = raiz.querySelector('[data-mostrador-lineas]');
    var vacio = raiz.querySelector('[data-mostrador-vacio]');
    var cobro = raiz.querySelector('[data-mostrador-cobro]');
    var items = raiz.querySelector('[data-mostrador-items]');
    var recibido = raiz.querySelector('[data-mostrador-recibido]');
    var botonCobrar = raiz.querySelector('[data-mostrador-cobrar]');
    var csrf = raiz.getAttribute('data-csrf');
    var crearUrl = raiz.getAttribute('data-crear-url');
    var codigosUrl = raiz.getAttribute('data-codigos-url');
    var catalogo = null;
    var porCodigo = {};
    var porId = {};
    // Orden del tiquete: lo último escaneado arriba. cantidad = unidades o kilos.
    var lineas = [];

    Array.prototype.forEach.call(items.querySelectorAll('input[name^="items["]'), function (input) {
      var id = input.name.replace(/^items\[(\d+)\]$/, '$1');
      lineas.push({ id: id, cantidad: parseFloat(input.value) || 0 });
    });

    function decir(texto, tipo, enlace) {
      aviso.textContent = texto;
      aviso.className = 'pq-mostrador-aviso' + (tipo ? ' pq-mostrador-aviso-' + tipo : '');
      if (enlace) {
        aviso.appendChild(document.createTextNode(' '));
        var a = crear('a', 'pq-mostrador-crear', enlace.texto);
        a.href = enlace.href;
        aviso.appendChild(a);
      }
    }

    function enfocar() {
      if (campo && document.activeElement !== campo) campo.focus({ preventScroll: true });
    }

    // ------------------------------------------------------------- datos
    function cargarCatalogo() {
      return fetch(raiz.getAttribute('data-catalogo-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (datos) {
          catalogo = datos.productos || [];
          porId = {};
          porCodigo = {};
          catalogo.forEach(function (p) {
            porId[String(p.id)] = p;
            if (p.codigo) porCodigo[p.codigo] = p;
          });
        });
    }
    cargarCatalogo()
      .then(function () {
        lineas = lineas.filter(function (l) { return porId[l.id]; });
        raiz.classList.add('pq-mostrador-vivo');
        pintar(false);
      })
      .catch(function () { /* sin catálogo, los formularios siguen funcionando como siempre */ });

    // ------------------------------------------------------- el tiquete
    function linea(id) {
      for (var i = 0; i < lineas.length; i++) if (lineas[i].id === String(id)) return lineas[i];
      return null;
    }

    function agregar(producto, cantidad, reemplazar) {
      var id = String(producto.id);
      var actual = linea(id);
      var nueva = reemplazar || !actual ? cantidad : actual.cantidad + cantidad;
      var tope = producto.peso ? MAX_GRAMOS / GRAMOS_POR_KILO : MAX_UNIDADES;
      nueva = Math.min(tope, nueva);
      if (producto.stock !== null) {
        var hay = producto.peso ? producto.stock / GRAMOS_POR_KILO : producto.stock;
        if (nueva > hay) {
          pitar(false);
          if (hay <= 0) {
            decir('Según el inventario no hay ' + producto.nombre + '. Si sí tienes, corrige las unidades en Productos.', 'error');
            return;
          }
          decir('Según el inventario solo quedan ' + (producto.peso ? gramosLegibles(producto.stock) : producto.stock) + ' de ' + producto.nombre + '.', 'error');
          nueva = producto.peso ? hay : Math.floor(hay);
        } else {
          decir('', '');
        }
      } else {
        decir('', '');
      }
      lineas = lineas.filter(function (l) { return l.id !== id; });
      lineas.unshift({ id: id, cantidad: nueva });
      pintar(true, id);
    }

    function pintar(guardar, resaltar) {
      if (!catalogo) return;
      lista.textContent = '';
      var total = 0;
      lineas.forEach(function (l) {
        var p = porId[l.id];
        var subtotal = p.peso ? precioPorGramos(p.precio, Math.round(l.cantidad * GRAMOS_POR_KILO)) : p.precio * l.cantidad;
        total += subtotal;
        var li = crear('li', 'pq-comanda-linea' + (l.id === resaltar ? ' pq-mostrador-linea-nueva' : ''));
        li.setAttribute('data-linea', l.id);
        var fila = crear('div', 'pq-comanda-fila');
        fila.appendChild(crear('span', 'pq-comanda-nombre', p.nombre));
        var guia = crear('span', 'pq-plato-guia');
        guia.setAttribute('aria-hidden', 'true');
        fila.appendChild(guia);
        fila.appendChild(crear('span', 'pq-comanda-subtotal', pesos(subtotal)));
        li.appendChild(fila);
        var controles = crear('div', 'pq-comanda-controles');
        if (p.peso) {
          controles.appendChild(crear('span', 'pq-comanda-unitario', gramosLegibles(Math.round(l.cantidad * GRAMOS_POR_KILO)) + ' · ' + pesos(p.precio) + '/kg'));
          var cambiar = crear('button', 'pq-enlace-boton', 'Cambiar peso');
          cambiar.type = 'button';
          cambiar.setAttribute('data-accion', 'pesar');
          cambiar.setAttribute('data-id', l.id);
          controles.appendChild(cambiar);
        } else {
          controles.appendChild(crear('span', 'pq-comanda-unitario', pesos(p.precio) + ' c/u'));
          var stepper = crear('div', 'pq-stepper');
          stepper.appendChild(botonIcono('menos', l.id, 'Quitar una unidad de ' + p.nombre, '<path d="M5 12h14"/>', 'pq-stepper-boton'));
          stepper.appendChild(crear('span', 'pq-stepper-cantidad', String(l.cantidad)));
          stepper.appendChild(botonIcono('mas', l.id, 'Sumar una unidad de ' + p.nombre, '<path d="M12 5v14M5 12h14"/>', 'pq-stepper-boton'));
          controles.appendChild(stepper);
        }
        var quitar = crear('div', 'pq-comanda-quitar');
        quitar.appendChild(botonIcono('quitar', l.id, 'Quitar ' + p.nombre + ' del tiquete', '<path d="M6 6l12 12M18 6L6 18"/>', ''));
        controles.appendChild(quitar);
        li.appendChild(controles);
        lista.appendChild(li);
      });

      vacio.hidden = lineas.length > 0;
      var cuenta = raiz.querySelector('[data-mostrador-cuenta]');
      if (cuenta) cuenta.textContent = lineas.length === 1 ? '1 producto' : lineas.length + ' productos';
      ['[data-mostrador-total]', '[data-mostrador-total-visor]', '[data-mostrador-total-boton]'].forEach(function (sel) {
        var el = raiz.querySelector(sel);
        if (el) el.textContent = pesos(total);
      });
      raiz.setAttribute('data-total', String(total));
      botonCobrar.disabled = lineas.length === 0;
      var vaciar = document.querySelector('[data-mostrador-vaciar]');
      if (vaciar) vaciar.hidden = lineas.length === 0;

      items.textContent = '';
      lineas.forEach(function (l) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'items[' + l.id + ']';
        input.value = String(Math.round(l.cantidad * 1000) / 1000);
        items.appendChild(input);
      });
      actualizarVueltas();
      if (guardar) sincronizar();
    }

    function botonIcono(accion, id, etiqueta, trazo, clase) {
      var b = crear('button', clase);
      b.type = 'button';
      b.setAttribute('data-accion', accion);
      b.setAttribute('data-id', id);
      b.setAttribute('aria-label', etiqueta);
      b.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">' + trazo + '</svg>';
      return b;
    }

    // Copia del tiquete en la sesión, para que recargar o ir a otra pantalla no lo borre.
    var temporizador = null;
    function sincronizar() {
      window.clearTimeout(temporizador);
      temporizador = window.setTimeout(function () {
        var datos = new FormData();
        datos.append('_csrf', csrf);
        lineas.forEach(function (l) { datos.append('items[' + l.id + ']', String(l.cantidad)); });
        fetch(raiz.getAttribute('data-sincronizar-url'), { method: 'POST', body: datos, credentials: 'same-origin' }).catch(function () {});
      }, 350);
    }

    lista.addEventListener('click', function (evento) {
      var boton = evento.target.closest('[data-accion]');
      if (!boton || !catalogo) return;
      var l = linea(boton.getAttribute('data-id'));
      var p = porId[boton.getAttribute('data-id')];
      if (!l || !p) return;
      var accion = boton.getAttribute('data-accion');
      if (accion === 'mas') { agregar(p, 1, false); }
      else if (accion === 'menos') {
        l.cantidad -= 1;
        if (l.cantidad < 1) lineas = lineas.filter(function (x) { return x !== l; });
        decir('', '');
        pintar(true);
      } else if (accion === 'quitar') {
        lineas = lineas.filter(function (x) { return x !== l; });
        decir('', '');
        pintar(true);
      } else if (accion === 'pesar') {
        abrirBascula(p, true);
        return;
      }
      enfocar();
    });

    // ----------------------------------------------------------- báscula
    var bascula = null;
    function cerrarBascula() {
      if (bascula) bascula.remove();
      bascula = null;
      var delServidor = document.querySelector('[data-bascula-servidor]');
      if (delServidor) delServidor.remove();
    }
    function abrirBascula(p, reemplazar) {
      cerrarBascula();
      bascula = crear('section', 'pq-mostrador-bascula');
      bascula.setAttribute('aria-label', 'Peso de ' + p.nombre);
      bascula.appendChild(crear('h2', 'pq-mostrador-bascula-titulo', '¿Cuánto lleva de ' + p.nombre + '?'));
      bascula.appendChild(crear('p', 'pq-ayuda', pesos(p.precio) + ' el kilo · se redondea a $50' + (p.stock !== null ? ' · quedan ' + gramosLegibles(p.stock) : '')));
      var atajos = crear('div', 'pq-mostrador-pesos');
      [[125, '125 g'], [250, '250 g'], [500, '1 libra'], [1000, '1 kg']].forEach(function (a) {
        var b = crear('button', 'pq-mostrador-peso');
        b.type = 'button';
        b.appendChild(crear('strong', '', a[1]));
        b.appendChild(crear('span', 'pq-mono', pesos(precioPorGramos(p.precio, a[0]))));
        b.addEventListener('click', function () { agregar(p, a[0] / GRAMOS_POR_KILO, reemplazar); cerrarBascula(); enfocar(); });
        atajos.appendChild(b);
      });
      bascula.appendChild(atajos);
      var otro = crear('form', 'pq-mostrador-peso-otro');
      var etiqueta = crear('label', 'pq-label', 'Otro peso');
      etiqueta.htmlFor = 'bascula-gramos-js';
      var envoltura = crear('span', 'pq-campo-sufijo');
      envoltura.setAttribute('data-sufijo', 'g');
      var gramos = crear('input', 'pq-input pq-mono');
      gramos.id = 'bascula-gramos-js';
      gramos.inputMode = 'numeric';
      gramos.maxLength = 5;
      gramos.placeholder = 'Ej.: 380';
      envoltura.appendChild(gramos);
      var listo = crear('button', 'pq-btn pq-btn-sello pq-btn-chico', 'Agregar');
      listo.type = 'submit';
      otro.appendChild(etiqueta);
      otro.appendChild(envoltura);
      otro.appendChild(listo);
      otro.addEventListener('submit', function (evento) {
        evento.preventDefault();
        evento.stopPropagation();
        var g = numero(gramos.value);
        if (g <= 0 || g > MAX_GRAMOS) { decir('Escribe cuántos gramos lleva (entre 1 g y 50 kg).', 'error'); gramos.focus(); return; }
        agregar(p, g / GRAMOS_POR_KILO, reemplazar);
        cerrarBascula();
        enfocar();
      }, true);
      bascula.appendChild(otro);
      var cancelar = crear('button', 'pq-enlace-boton', 'Cancelar');
      cancelar.type = 'button';
      cancelar.addEventListener('click', function () { cerrarBascula(); enfocar(); });
      bascula.appendChild(cancelar);
      lector.insertAdjacentElement('afterend', bascula);
      bascula.querySelector('.pq-mostrador-peso').focus();
    }

    // ------------------------------------------------- lector y búsqueda
    function buscarPorNombre(texto) {
      var t = sinTildes(texto.trim());
      if (t.length < 2) return [];
      var empiezan = [];
      var contienen = [];
      catalogo.forEach(function (p) {
        var n = sinTildes(p.nombre);
        if (n.indexOf(t) === 0) empiezan.push(p);
        else if (n.indexOf(t) > 0) contienen.push(p);
      });
      return empiezan.concat(contienen);
    }

    function elegir(p) {
      campo.value = '';
      ocultarSugerencias();
      if (p.peso) { abrirBascula(p, false); return; }
      pitar(true);
      agregar(p, 1, false);
      enfocar();
    }

    function ocultarSugerencias() { sugerencias.hidden = true; sugerencias.textContent = ''; }

    function mostrarSugerencias(encontrados) {
      sugerencias.textContent = '';
      encontrados.slice(0, 8).forEach(function (p) {
        var li = crear('li');
        var b = crear('button', 'pq-mostrador-sugerencia');
        b.type = 'button';
        b.appendChild(crear('span', 'pq-mostrador-sugerencia-nombre', p.nombre));
        b.appendChild(crear('span', 'pq-mono', pesos(p.precio) + (p.peso ? ' /kg' : '')));
        b.addEventListener('click', function () { elegir(p); });
        li.appendChild(b);
        sugerencias.appendChild(li);
      });
      sugerencias.hidden = encontrados.length === 0;
    }

    campo.addEventListener('input', function () {
      if (!catalogo) return;
      var texto = campo.value.trim();
      // Solo dígitos = un código que viene del lector: no se sugiere nada mientras llega.
      if (/^\d*$/.test(texto)) { ocultarSugerencias(); return; }
      mostrarSugerencias(buscarPorNombre(texto));
    });

    var recargado = false;
    function procesar(texto) {
      texto = String(texto || '').trim();
      if (!texto) return;
      var codigo = normalizarCodigo(texto);
      var producto = codigo ? porCodigo[codigo] : null;
      if (producto) { recargado = false; elegir(producto); return; }
      // ¿Lo crearon hace un momento en otra pestaña? Se baja el catálogo otra
      // vez y se reintenta, una sola vez por escaneo.
      if (codigo && /^\d+$/.test(codigo) && !recargado) {
        recargado = true;
        campo.value = ''; // el lector puede mandar el siguiente código mientras tanto
        cargarCatalogo().then(function () { procesar(texto); }, function () { procesar(texto); });
        return;
      }
      recargado = false;
      var encontrados = buscarPorNombre(texto);
      if (encontrados.length === 1) { elegir(encontrados[0]); return; }
      if (encontrados.length > 1) { mostrarSugerencias(encontrados); decir('Hay ' + encontrados.length + ' productos con «' + texto + '»: elige uno.', ''); return; }
      pitar(false);
      campo.value = '';
      ocultarSugerencias();
      if (codigo && /^\d+$/.test(codigo)) {
        decir('No tienes ningún producto con el código ' + codigo + '.', 'error', { texto: 'Crearlo', href: crearUrl + encodeURIComponent(codigo) });
        // ¿Otra tienda Veci ya lo usa? Solo el nombre.
        fetch(codigosUrl + encodeURIComponent(codigo), { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
            if (d && d.sugerencia) decir('No tienes ningún producto con el código ' + codigo + '. Otras tiendas lo llaman: «' + d.sugerencia + '».', 'error', { texto: 'Crearlo', href: crearUrl + encodeURIComponent(codigo) });
          })
          .catch(function () {});
      } else {
        decir('No encontramos «' + texto + '» en tus productos.', 'error');
      }
      enfocar();
    }

    // Fase de captura: estos formularios no recargan la página con el catálogo cargado.
    document.addEventListener('submit', function (evento) {
      if (!catalogo) return;
      var form = evento.target;
      if (form === lector) {
        evento.preventDefault();
        evento.stopPropagation();
        procesar(campo.value);
        return;
      }
      if (!raiz.contains(form) && !form.matches('[data-mostrador-vaciar]')) return;
      var accion = form.getAttribute('action') || '';
      if (/\/panel\/mostrador\/(linea|agregar)$/.test(accion)) {
        // Los formularios que pintó el servidor (antes de cargar el catálogo).
        evento.preventDefault();
        evento.stopPropagation();
        var id = (form.querySelector('[name="producto_id"]') || {}).value;
        var p = porId[id];
        if (!p) return;
        var tipo = (form.querySelector('[name="accion"]') || {}).value || 'agregar';
        if (tipo === 'quitar') { lineas = lineas.filter(function (l) { return l.id !== String(id); }); pintar(true); }
        else if (tipo === 'menos') { var l = linea(id); if (l) { l.cantidad -= 1; if (l.cantidad < 1 || p.peso) lineas = lineas.filter(function (x) { return x !== l; }); } pintar(true); }
        else if (p.peso) { var gramos = evento.submitter && evento.submitter.name === 'gramos' ? numero(evento.submitter.value) : numero((form.querySelector('[name="gramos_otro"]') || {}).value); if (gramos > 0) { agregar(p, gramos / GRAMOS_POR_KILO, !!form.querySelector('[name="reemplazar"]')); cerrarBascula(); } }
        else { agregar(p, 1, false); }
        enfocar();
        return;
      }
      if (form.matches('[data-mostrador-vaciar]')) {
        evento.preventDefault();
        evento.stopPropagation();
        if (window.confirm(form.getAttribute('data-confirmar') || '¿Borrar el tiquete?')) {
          lineas = [];
          decir('', '');
          pintar(true);
        }
        enfocar();
      }
    }, true);

    // "Pesar" desde los resultados del servidor: abre la báscula aquí mismo.
    document.addEventListener('click', function (evento) {
      if (!catalogo) return;
      var enlace = evento.target.closest('a[href*="/panel/mostrador?pesar="]');
      if (!enlace) return;
      var m = enlace.getAttribute('href').match(/pesar=(\d+)/);
      var p = m ? porId[m[1]] : null;
      if (!p) return;
      evento.preventDefault();
      abrirBascula(p, /reemplazar=1/.test(enlace.getAttribute('href')));
    });

    // Lo que se teclee fuera de un campo va al lector: el lector USB es un
    // teclado, y si alguien tocó la pantalla el código no se pierde.
    document.addEventListener('keydown', function (evento) {
      var activo = document.activeElement;
      var escribible = activo && (activo.isContentEditable || activo.tagName === 'TEXTAREA' || activo.tagName === 'SELECT'
        || (activo.tagName === 'INPUT' && !/^(radio|checkbox|button|submit|hidden)$/.test(activo.type)));
      if (escribible || evento.ctrlKey || evento.metaKey || evento.altKey) return;
      // La barra espaciadora sigue sirviendo para tocar el botón enfocado.
      if (evento.key && evento.key.length === 1 && evento.key !== ' ') campo.focus();
    });

    // ------------------------------------------------------------- cobro
    function metodo() {
      var elegido = cobro.querySelector('[name="metodo"]:checked');
      return elegido ? elegido.value : 'efectivo';
    }

    function actualizarVueltas() {
      var total = parseInt(raiz.getAttribute('data-total'), 10) || 0;
      var fila = raiz.querySelector('[data-mostrador-visor-vueltas]');
      var cifra = raiz.querySelector('[data-mostrador-vueltas]');
      var etiqueta = fila ? fila.querySelector('span') : null;
      if (!fila || !recibido) return;
      var dio = numero(recibido.value);
      if (metodo() !== 'efectivo' || !recibido.value.trim()) { fila.hidden = true; return; }
      fila.hidden = false;
      var resta = dio - total;
      fila.classList.toggle('pq-mostrador-visor-falta', resta < 0);
      etiqueta.textContent = resta < 0 ? 'Faltan' : 'Vueltas';
      cifra.textContent = pesos(Math.abs(resta));
    }

    if (recibido) {
      recibido.addEventListener('input', actualizarVueltas);
      var billetes = cobro.querySelector('[data-mostrador-billetes]');
      if (billetes) {
        billetes.hidden = false;
        // "Exacto" y "Borrar" primero; los billetes se suman ("me dio uno de
        // 20 y uno de 5") en vez de reemplazarse.
        var exacto = crear('button', 'pq-mostrador-billete pq-mostrador-billete-exacto', 'Exacto');
        exacto.type = 'button';
        exacto.setAttribute('data-billete', 'exacto');
        billetes.insertBefore(exacto, billetes.firstChild);
        var borrar = crear('button', 'pq-mostrador-billete pq-mostrador-billete-borrar', 'Borrar');
        borrar.type = 'button';
        borrar.setAttribute('data-billete', 'borrar');
        billetes.appendChild(borrar);
        billetes.addEventListener('click', function (evento) {
          var b = evento.target.closest('[data-billete]');
          if (!b) return;
          var valor = b.getAttribute('data-billete');
          var total = parseInt(raiz.getAttribute('data-total'), 10) || 0;
          var nuevo = valor === 'exacto' ? total : (valor === 'borrar' ? 0 : numero(recibido.value) + parseInt(valor, 10));
          recibido.value = nuevo > 0 ? String(nuevo).replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
          actualizarVueltas();
        });
      }
    }
    // El lector es un teclado: si alguien deja el cursor en "¿Con cuánto
    // paga?" (o en otro campo del cobro) y escanea, el código no puede
    // cobrarse como plata. Una ráfaga de teclas a velocidad de lector que
    // termina en Enter se saca del campo y va al tiquete. Un Enter normal
    // en el cobro no cobra: lleva al botón "Cobrar" (un segundo Enter cobra).
    var rafaga = { campo: null, antes: '', teclas: 0, ultima: 0 };
    cobro.addEventListener('keydown', function (evento) {
      var t = evento.target;
      if (!t.matches || !t.matches('input[type="text"], input[type="tel"], input[type="search"], input:not([type])')) return;
      var ahora = Date.now();
      if (evento.key === 'Enter') {
        evento.preventDefault();
        if (rafaga.campo === t && rafaga.teclas >= 6) {
          var antes = soloDigitos(rafaga.antes);
          var codigo = t.matches('[data-precio]') ? soloDigitos(t.value).slice(antes.length) : t.value.slice(rafaga.antes.length);
          t.value = rafaga.antes;
          if (t === recibido) actualizarVueltas();
          rafaga = { campo: null, antes: '', teclas: 0, ultima: 0 };
          procesar(codigo);
          return;
        }
        rafaga = { campo: null, antes: '', teclas: 0, ultima: 0 };
        botonCobrar.focus();
        return;
      }
      if (!evento.key || evento.key.length !== 1) return;
      if (rafaga.campo !== t || ahora - rafaga.ultima > 50) {
        rafaga = { campo: t, antes: t.value, teclas: 0, ultima: ahora };
      }
      rafaga.teclas += 1;
      rafaga.ultima = ahora;
    });

    // Buscar entre muchos clientes al fiar: filtra las opciones del select.
    var filtro = cobro.querySelector('[data-filtro-clientes]');
    var selectClientes = filtro ? cobro.querySelector(filtro.getAttribute('data-filtro-clientes')) : null;
    if (filtro && selectClientes) {
      filtro.hidden = false;
      filtro.addEventListener('input', function () {
        var t = sinTildes(filtro.value.trim());
        var primero = null;
        Array.prototype.forEach.call(selectClientes.options, function (op) {
          if (!op.hasAttribute('data-buscar')) return; // "Elige…" y "+ Cliente nuevo" siempre
          var ve = t === '' || sinTildes(op.getAttribute('data-buscar')).indexOf(t) !== -1;
          op.hidden = !ve;
          op.disabled = !ve;
          if (ve && !primero) primero = op;
        });
        var actual = selectClientes.options[selectClientes.selectedIndex];
        if (t !== '' && primero && (!actual || actual.disabled || actual.value === '')) {
          selectClientes.value = primero.value;
          selectClientes.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    }

    cobro.addEventListener('change', function (evento) {
      if (evento.target.name === 'metodo') actualizarVueltas();
    });
    raiz.setAttribute('data-total', String(numero((raiz.querySelector('[data-mostrador-total]') || {}).textContent)));
    actualizarVueltas();

    // Antes de mandar el cobro, lo que el servidor rechazaría igual, dicho aquí.
    cobro.addEventListener('submit', function (evento) {
      var problema = null;
      var total = parseInt(raiz.getAttribute('data-total'), 10) || 0;
      if (catalogo && lineas.length === 0) problema = 'La venta está vacía: agrega al menos un producto.';
      else if (metodo() === 'efectivo' && recibido && recibido.value.trim() && numero(recibido.value) < total) problema = 'Con ' + pesos(numero(recibido.value)) + ' no alcanza: faltan ' + pesos(total - numero(recibido.value)) + '.';
      else if (metodo() === 'efectivo' && recibido && numero(recibido.value) > total + 200000) problema = '"¿Con cuánto paga?" dice ' + pesos(numero(recibido.value)) + ': parece un código escaneado en ese campo. Bórralo y escribe con cuánto paga.';
      else if (metodo() === 'fiado') {
        var cliente = cobro.querySelector('[name="cliente_id"]');
        if (!cliente.value) problema = 'Para fiar, elige a quién (o crea el cliente).';
        else if (cliente.value === 'nuevo') {
          var nombre = cobro.querySelector('[name="cliente_nombre"]');
          var tel = soloDigitos(cobro.querySelector('[name="cliente_telefono"]').value).replace(/^57(?=\d{10}$)/, '');
          if (!nombre.value.trim()) problema = 'Escribe el nombre del cliente.';
          else if (!/^3\d{9}$/.test(tel)) problema = 'Escribe un WhatsApp de 10 dígitos que empiece por 3.';
          else if (!cobro.querySelector('[name="cliente_autorizo"]').checked && !(cobro.querySelector('[name="cliente_confirmado"]') || {}).checked) problema = 'Marca que el cliente autorizó guardar su nombre y número (Ley 1581).';
        }
      }
      if (problema) {
        evento.preventDefault();
        evento.stopImmediatePropagation();
        decir(problema, 'error');
        pitar(false);
        aviso.scrollIntoView({ block: 'center', behavior: 'smooth' });
        return;
      }
      window.clearTimeout(temporizador); // el formulario ya lleva las líneas
    }, true);

    // ------------------------------------------------------------ cámara
    var botonCamara = raiz.querySelector('[data-mostrador-camara]');
    var caja = raiz.querySelector('[data-mostrador-video]');
    var video = caja ? caja.querySelector('video') : null;
    var flujo = null;
    var buscando = false;
    if (botonCamara) botonCamara.hidden = false;

    function apagarCamara() {
      buscando = false;
      if (flujo) flujo.getTracks().forEach(function (t) { t.stop(); });
      flujo = null;
      if (caja) caja.hidden = true;
    }

    if (botonCamara) botonCamara.addEventListener('click', function () {
      if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        decir('Este navegador no puede leer códigos con la cámara (pasa en iPhone y en algunos computadores). Usa un lector de códigos USB o Bluetooth, o escribe los números que salen debajo de las barras.', 'info');
        enfocar();
        return;
      }
      var detector;
      try {
        detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf'] });
      } catch (e) {
        decir('No se pudo usar el lector de la cámara en este navegador. Usa un lector de códigos o escribe el número.', 'info');
        return;
      }
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
        .then(function (s) {
          flujo = s;
          video.srcObject = s;
          caja.hidden = false;
          return video.play();
        })
        .then(function () {
          buscando = true;
          decir('Apunta la cámara al código de barras.', 'info');
          (function mirar() {
            if (!buscando) return;
            detector.detect(video).then(function (codigos) {
              if (codigos && codigos.length && buscando) {
                apagarCamara();
                procesar(codigos[0].rawValue);
                return;
              }
              window.setTimeout(mirar, 180);
            }).catch(function () { window.setTimeout(mirar, 400); });
          })();
        })
        .catch(function () {
          apagarCamara();
          decir('No pudimos abrir la cámara. Revisa que le diste permiso a Veci en el navegador, o usa un lector de códigos.', 'error');
        });
    });
    var cerrar = raiz.querySelector('[data-mostrador-camara-cerrar]');
    if (cerrar) cerrar.addEventListener('click', function () { apagarCamara(); decir('', ''); enfocar(); });
    window.addEventListener('pagehide', apagarCamara);
  }

  // ===================================================================
  // Formulario de producto (código, costo y margen, venta por peso) y
  // cualquier campo de código fuera del mostrador (compras).
  // ===================================================================
  function iniciarCamposLector() {
    document.querySelectorAll('[data-campo-lector]').forEach(function (campoCodigo) {
      var form = campoCodigo.form;
      var nota = form ? form.querySelector('[data-codigo-sugerencia]') : null;
      var url = campoCodigo.getAttribute('data-consulta-url');
      var propio = campoCodigo.getAttribute('data-producto-id');
      var ultimo = '';

      function consultar() {
        var codigo = normalizarCodigo(campoCodigo.value);
        if (!nota || !url || !codigo || codigo === ultimo) return;
        ultimo = codigo;
        fetch(url + encodeURIComponent(codigo), { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
            if (!d) return;
            nota.textContent = '';
            nota.hidden = true;
            if (d.producto && String(d.producto.id) !== propio) {
              nota.textContent = 'Ojo: ese código ya es de «' + d.producto.nombre + '».';
              nota.hidden = false;
              return;
            }
            if (d.sugerencia) {
              var nombre = form.querySelector('[name="nombre"]');
              nota.appendChild(document.createTextNode('Otras tiendas Veci lo llaman: «' + d.sugerencia + '». '));
              if (nombre && nombre.value.trim() === '') {
                var usar = crear('button', 'pq-enlace-boton', 'Usar este nombre');
                usar.type = 'button';
                usar.addEventListener('click', function () { nombre.value = d.sugerencia; nombre.focus(); });
                nota.appendChild(usar);
              }
              nota.hidden = false;
            }
          })
          .catch(function () {});
      }

      // El lector manda Enter al final del código: aquí no debe enviar el formulario.
      campoCodigo.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter') {
          evento.preventDefault();
          consultar();
          var siguiente = form && form.querySelector('[name="nombre"]');
          if (siguiente && siguiente.value.trim() === '') siguiente.focus();
        }
      });
      campoCodigo.addEventListener('change', consultar);
      if (campoCodigo.value) consultar();
    });

    // Etiquetas que cambian si el producto va por peso: "Precio" → "Precio del kilo".
    function etiquetasPorPeso() {
      var elegido = document.querySelector('[data-vende-por]:checked');
      if (!elegido) return;
      var peso = elegido.value === 'peso';
      // Las existencias estaban en la otra unidad (kilos ↔ unidades): se piden de nuevo.
      var stock = document.querySelector('[data-stock-original]');
      var avisoStock = document.querySelector('[data-stock-aviso]');
      if (stock && stock.getAttribute('data-stock-original') !== '') {
        var cambio = elegido.value !== stock.getAttribute('data-vende-por-original');
        stock.value = cambio ? '' : stock.getAttribute('data-stock-original');
        stock.placeholder = cambio ? (peso ? 'Kilos que hay' : 'Unidades que hay') : 'Sin contar';
        stock.required = cambio;
        if (avisoStock) avisoStock.hidden = !cambio;
      }
      document.querySelectorAll('[data-etiqueta-peso]').forEach(function (el) {
        el.textContent = el.getAttribute(peso ? 'data-etiqueta-peso' : 'data-etiqueta-unidad');
      });
      actualizarMargen();
    }
    function actualizarMargen() {
      var salida = document.querySelector('[data-margen-producto]');
      var precio = document.getElementById('precio');
      var costo = document.getElementById('costo');
      if (!salida || !precio || !costo) return;
      if (!salida.hasAttribute('data-texto-base')) salida.setAttribute('data-texto-base', salida.textContent);
      var p = numero(precio.value);
      var c = numero(costo.value);
      var peso = (document.querySelector('[data-vende-por]:checked') || {}).value === 'peso';
      salida.classList.remove('pq-producto-margen-perdida');
      if (!costo.value.trim() || p <= 0) { salida.textContent = salida.getAttribute('data-texto-base'); return; }
      if (c > p) {
        salida.textContent = 'Ojo: lo vendes por debajo de lo que te cuesta (pierdes ' + pesos(c - p) + (peso ? ' por kilo).' : ' por unidad).');
        salida.classList.add('pq-producto-margen-perdida');
        return;
      }
      salida.textContent = 'Te deja ' + pesos(p - c) + (peso ? ' por kilo' : ' por unidad') + ' (' + Math.floor((p - c) * 100 / p) + ' % del precio).';
    }
    document.addEventListener('change', function (evento) {
      if (evento.target.matches && evento.target.matches('[data-vende-por]')) etiquetasPorPeso();
    });
    document.addEventListener('input', function (evento) {
      if (evento.target.id === 'precio' || evento.target.id === 'costo') actualizarMargen();
    });
    actualizarMargen();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var mostrador = document.querySelector('[data-mostrador]');
    if (mostrador) iniciarMostrador(mostrador);
    iniciarCamposLector();
  });
})();
