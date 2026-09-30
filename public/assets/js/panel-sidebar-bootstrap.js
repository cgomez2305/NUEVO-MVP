/*
 * Veci — aplica la preferencia de sidebar guardada ANTES de que el sidebar
 * se pinte, para que no haya un parpadeo expandido→colapsado (o viceversa)
 * al cargar. Por eso vive en un archivo aparte cargado SIN defer, justo al
 * abrir <body>: el CSP del sitio (script-src 'self', ver bootstrap.php)
 * bloquea en silencio el JS inline, así que no puede ir como <script> suelto
 * en panel.php — y con defer llegaría tarde, después del primer pintado.
 */
(function () {
  try {
    var pref = localStorage.getItem('veci_sidebar');
    if (pref === 'colapsado' || pref === 'expandido') {
      document.documentElement.setAttribute('data-sidebar', pref);
    }
  } catch (e) {}
})();
