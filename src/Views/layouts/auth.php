<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="pq-panel-bg pq-auth-bg">
  <div class="pq-shell" style="justify-content: center; background: transparent">
    <div class="pq-content">
      <div class="pq-centro" style="margin-bottom: 36px">
        <a href="<?= e(base_url('/')) ?>" style="display: inline-flex; text-decoration: none">
          <img src="<?= e(base_url('assets/img/logo-veci-lockup.png')) ?>" alt="Veci" style="height: 30px; width: auto">
        </a>
      </div>
      <div class="pq-auth-card">
        <?= $contenido ?>
      </div>
    </div>
  </div>
  <script src="<?= e(base_url('assets/js/interacciones.js')) ?>" defer></script>
</body>
</html>
