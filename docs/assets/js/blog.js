// Índice del blog: filtro por tema. Sin JS se ven todas las guías.
(function () {
  var botones = document.querySelectorAll('.blog-filtro');
  if (!botones.length) return;
  var piezas = document.querySelectorAll('.blog-filas li, .blog-portada');
  var vacio = document.querySelector('.blog-vacio');
  botones.forEach(function (b) {
    b.addEventListener('click', function () {
      var tema = b.getAttribute('data-tema');
      botones.forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
      var visibles = 0;
      piezas.forEach(function (p) {
        var ver = tema === 'todos' || p.getAttribute('data-tema') === tema;
        p.hidden = !ver;
        if (ver) visibles++;
      });
      if (vacio) vacio.hidden = visibles > 0;
    });
  });
})();

// Artículo: barra de progreso de lectura bajo la barra de navegación.
(function () {
  var barra = document.querySelector('.lectura-barra span');
  var articulo = document.querySelector('.articulo-cuerpo');
  if (!barra || !articulo) return;
  var pendiente = false;
  function medir() {
    var r = articulo.getBoundingClientRect();
    var total = r.height - window.innerHeight * 0.6;
    var avance = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 1;
    barra.style.transform = 'scaleX(' + avance.toFixed(4) + ')';
    pendiente = false;
  }
  function pedir() { if (!pendiente) { pendiente = true; requestAnimationFrame(medir); } }
  window.addEventListener('scroll', pedir, { passive: true });
  window.addEventListener('resize', pedir);
  medir();
})();
