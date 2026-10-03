  var btnMensual = document.getElementById('btnMensual');
  var btnAnual = document.getElementById('btnAnual');
  var contPrecios = document.querySelector('.precios');
  var pastilla = document.querySelector('.precio-toggle .pastilla');

  function moverPastilla(btn) {
    pastilla.style.left = btn.offsetLeft + 'px';
    pastilla.style.width = btn.offsetWidth + 'px';
  }
  var ctasPlanPago = Array.prototype.slice.call(contPrecios.querySelectorAll('a[href*="plan=barrio"], a[href*="plan=pro"]'));
  function elegirCiclo(anual) {
    contPrecios.classList.toggle('modo-anual', anual);
    btnMensual.classList.toggle('activo', !anual);
    btnAnual.classList.toggle('activo', anual);
    moverPastilla(anual ? btnAnual : btnMensual);
    ctasPlanPago.forEach(function (a) {
      var href = a.getAttribute('href').replace(/[&?]ciclo=anual/, '');
      a.setAttribute('href', anual ? href + '&ciclo=anual' : href);
    });
  }
  btnMensual.addEventListener('click', function () { elegirCiclo(false); });
  btnAnual.addEventListener('click', function () { elegirCiclo(true); });
  moverPastilla(btnMensual);
  window.addEventListener('resize', function () {
    moverPastilla(btnAnual.classList.contains('activo') ? btnAnual : btnMensual);
  });

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
