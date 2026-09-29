<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="pq-panel-bg" data-negocio-id="<?= (int) $negocio['id'] ?>" data-es-reservas="<?= ($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas' ? '1' : '0' ?>">
  <div class="pq-shell">
    <div class="pq-topbar">
      <a href="<?= e(base_url('/panel')) ?>" class="pq-topbar-brand">Veci</a>
      <nav class="pq-topbar-links">
        <a href="<?= e(base_url('/panel')) ?>" class="<?= ($activo ?? '') === 'panel' ? 'activo' : '' ?>">Panel</a>
        <?php if (($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas'): ?>
          <a href="<?= e(base_url('/panel/citas')) ?>" class="<?= ($activo ?? '') === 'citas' ? 'activo' : '' ?>">Agenda</a>
          <a href="<?= e(base_url('/panel/recordatorios')) ?>" class="<?= ($activo ?? '') === 'recordatorios' ? 'activo' : '' ?>">Recordatorios</a>
          <a href="<?= e(base_url('/panel/copiloto')) ?>" class="<?= ($activo ?? '') === 'copiloto' ? 'activo' : '' ?>">Copiloto</a>
          <a href="<?= e(base_url('/panel/servicios')) ?>" class="<?= ($activo ?? '') === 'servicios' ? 'activo' : '' ?>">Servicios</a>
          <a href="<?= e(base_url('/panel/empleados')) ?>" class="<?= ($activo ?? '') === 'empleados' ? 'activo' : '' ?>">Empleados</a>
          <a href="<?= e(base_url('/panel/horario')) ?>" class="<?= ($activo ?? '') === 'horario' ? 'activo' : '' ?>">Horario</a>
        <?php else: ?>
          <a href="<?= e(base_url('/panel/pedidos')) ?>" class="<?= ($activo ?? '') === 'pedidos' ? 'activo' : '' ?>">Pedidos</a>
          <a href="<?= e(base_url('/panel/copiloto')) ?>" class="<?= ($activo ?? '') === 'copiloto' ? 'activo' : '' ?>">Copiloto</a>
          <a href="<?= e(base_url('/panel/productos')) ?>" class="<?= ($activo ?? '') === 'productos' ? 'activo' : '' ?>">Menú</a>
        <?php endif; ?>
      </nav>
    </div>

    <div class="pq-content">
      <?= $contenido ?>
    </div>

    <form method="post" action="<?= e(base_url('/logout')) ?>" style="padding: 0 20px 24px">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Cerrar sesión</button>
    </form>
  </div>
  <script src="<?= e(base_url('assets/js/panel-notificaciones.js')) ?>" defer></script>
</body>
</html>
