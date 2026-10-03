  var elementosReveal = Array.prototype.slice.call(document.querySelectorAll('.reveal, .reveal-fila'));
  var pendientes = elementosReveal.filter(function (el) { return !el.classList.contains('visto'); });
  var revisando = false;
  function revisarReveal() {
    pendientes = pendientes.filter(function (el) {
      var rect = el.getBoundingClientRect();
      var enVista = rect.top < window.innerHeight * 0.92 && rect.bottom > 0;
      if (enVista) el.classList.add('visto');
      return !enVista;
    });
    revisando = false;
  }
  function pedirRevision() {
    if (!revisando) { revisando = true; requestAnimationFrame(revisarReveal); }
  }
  revisarReveal();
  window.addEventListener('scroll', pedirRevision, { passive: true });
  window.addEventListener('resize', pedirRevision);
  setTimeout(function () {
    pendientes.forEach(function (el) { el.classList.add('visto'); });
    pendientes = [];
  }, 1500);
