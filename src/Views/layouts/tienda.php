<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<?php
// El color del negocio manda en su tienda: se expone como variable CSS
// (--marca) junto con el color de texto que se lee encima (--marca-sobre),
// y todo el resto de tonos se derivan de ahí en app.css.
$marcaTienda = color_seguro($negocio['color_marca'] ?? null);
?>
<body class="pq-tienda-bg">
  <div class="pq-shell-tienda" style="--marca: <?= e($marcaTienda) ?>; --marca-sobre: <?= e(color_texto_sobre($marcaTienda)) ?>">
    <?= $contenido ?>
    <?php if (isset($negocio) && ($negocio['plan_nombre'] ?? 'gratis') === 'gratis'): ?>
      <a href="<?= e(base_url('/')) ?>" target="_blank" rel="noopener" class="pq-sello-veci">
        <img src="<?= e(base_url('assets/img/icon-192.png')) ?>" alt="" width="14" height="14">
        Hecho con Veci
      </a>
    <?php endif; ?>
  </div>
  <script src="<?= e(base_url('assets/js/confirmar.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/interacciones.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/tienda.js')) ?>" defer></script>
</body>
</html>
