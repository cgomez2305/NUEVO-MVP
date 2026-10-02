<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="pq-panel-bg pq-auth-bg">
  <div class="pq-shell pq-auth-shell">
    <div class="pq-content">
      <div class="pq-centro pq-auth-logo-caja">
        <a class="pq-auth-logo-enlace" href="<?= e(base_url('/')) ?>">
          <img class="pq-auth-logo" src="<?= e(base_url('assets/img/logo-veci-lockup-transparente.png')) ?>" alt="Veci">
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
