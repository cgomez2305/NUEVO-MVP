<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="pq-panel-bg">
  <div class="pq-shell" style="justify-content: center">
    <div class="pq-content">
      <div class="pq-centro" style="margin-bottom: 24px">
        <a href="<?= e(base_url('/')) ?>" style="display: inline-flex; text-decoration: none">
          <img src="<?= e(base_url('assets/img/logo-veci-lockup.png')) ?>" alt="Veci" style="height: 30px; width: auto">
        </a>
      </div>
      <?= $contenido ?>
    </div>
  </div>
</body>
</html>
