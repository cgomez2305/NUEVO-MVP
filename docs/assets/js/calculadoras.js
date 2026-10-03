// Calculadoras de cierre ([data-calc]): el visitante pone sus números y ve su caso.
(function () {
  function cop(n) { return '$' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

  var CALCULOS = {
    'ahorro-apps': function (v) {
      var bajo = v.ventas * 0.25, alto = v.ventas * 0.30;
      return {
        ventas: cop(v.ventas),
        comision: cop(bajo) + ' – ' + cop(alto),
        ahorro: cop(Math.max(0, v.ventas * 0.275 - 59000)),
        anual: cop(Math.max(0, v.ventas * 0.275 - 59000) * 12)
      };
    },
    'citas-perdidas': function (v) {
      var perdidas = Math.round(v.citas * 4.3 * v.ausencia / 100);
      return {
        citas: String(v.citas),
        ausencia: v.ausencia + '%',
        perdidas: perdidas + (perdidas === 1 ? ' cita' : ' citas'),
        plata: cop(perdidas * v.precio),
        anual: cop(perdidas * v.precio * 12)
      };
    },
    'clientes-perdidos': function (v) {
      var mes = v.clientes * v.compra * v.veces;
      return {
        clientes: String(v.clientes),
        veces: v.veces + (v.veces === 1 ? ' vez' : ' veces'),
        plata: cop(mes),
        anual: cop(mes * 12)
      };
    }
  };

  function leer(input) {
    var n = parseFloat(input.value);
    var min = parseFloat(input.min), max = parseFloat(input.max);
    if (isNaN(n)) n = min || 0;
    if (!isNaN(min) && n < min) n = min;
    if (!isNaN(max) && n > max) n = max;
    return n;
  }

  document.querySelectorAll('[data-calc]').forEach(function (calc) {
    var calcular = CALCULOS[calc.getAttribute('data-calc')];
    if (!calcular) return;
    var entradas = calc.querySelectorAll('[data-entrada]');
    function actualizar() {
      var v = {};
      entradas.forEach(function (i) {
        var n = leer(i);
        v[i.getAttribute('data-entrada')] = n;
        if (i.type === 'range') i.style.setProperty('--val', ((n - i.min) / (i.max - i.min) * 100) + '%');
      });
      var r = calcular(v);
      calc.querySelectorAll('[data-salida]').forEach(function (s) {
        var k = s.getAttribute('data-salida');
        if (r[k] != null) s.textContent = r[k];
      });
    }
    entradas.forEach(function (i) {
      i.addEventListener('input', actualizar);
      i.addEventListener('change', function () { i.value = leer(i); actualizar(); });
    });
    actualizar();
  });
})();
