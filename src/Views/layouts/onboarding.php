<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<?php
// Como en el panel: el color del negocio en el <body>, para la vista previa
// del toldo en el último paso y la tienda que se abre al publicar.
$marcaNegocio = color_seguro($negocio['color_marca'] ?? null);
?>
<body class="pq-panel-bg" style="--marca: <?= e($marcaNegocio) ?>; --marca-sobre: <?= e(color_texto_sobre($marcaNegocio)) ?>">
  <div class="pq-shell pq-onb-shell">
    <?= $contenido ?>
  </div>
  <script src="<?= e(base_url('assets/js/confirmar.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/interacciones.js')) ?>" defer></script>
</body>
</html>
