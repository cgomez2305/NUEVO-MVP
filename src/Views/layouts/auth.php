<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="pq-panel-bg">
  <div class="pq-shell" style="justify-content: center">
    <div class="pq-content">
      <div class="pq-centro" style="margin-bottom: 24px">
        <a href="<?= e(base_url('/')) ?>" class="pq-serif" style="font-size: 28px; text-decoration: none; color: var(--carbon)">Veci</a>
      </div>
      <?= $contenido ?>
    </div>
  </div>
</body>
</html>
