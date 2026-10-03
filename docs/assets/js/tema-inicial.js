// Corre en <head>, antes del primer pintado: evita el destello de tema claro
// y que el navegador restaure el scroll a mitad de una animación de entrada.
try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (e) {}
(function () {
  try {
    var t = localStorage.getItem('veci-tema') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
  } catch (e) {}
})();
