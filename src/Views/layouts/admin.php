<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="pq-panel-bg">
  <div class="pq-shell">
    <div class="pq-topbar">
      <a href="<?= e(base_url('/admin')) ?>" class="pq-topbar-brand">Veci · interno</a>
      <nav class="pq-topbar-links">
        <a href="<?= e(base_url('/admin')) ?>">Negocios</a>
      </nav>
    </div>

    <div class="pq-content">
      <?= $contenido ?>
    </div>

    <form method="post" action="<?= e(base_url('/admin/logout')) ?>" style="padding: 0 20px 24px">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Cerrar sesión (<?= e($admin['nombre']) ?>)</button>
    </form>
  </div>
  <script src="<?= e(base_url('assets/js/confirmar.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/interacciones.js')) ?>" defer></script>
</body>
</html>
