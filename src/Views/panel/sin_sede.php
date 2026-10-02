<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/../layouts/_head.php'; ?>
</head>
<body class="pq-panel-bg">
  <main class="pq-sin-sede">
    <img src="<?= e(base_url('assets/img/logo-veci-lockup-transparente.png')) ?>" alt="Veci" class="pq-sin-sede-logo">
    <h1 class="pq-h1">Hola, <?= e($usuario['nombre']) ?></h1>
    <p class="pq-lead">Todavía no tienes ninguna sede asignada. Pídele al dueño del negocio que te dé acceso desde <strong>Colaboradores</strong> en su panel.</p>
    <form method="post" action="<?= e(base_url('/logout')) ?>">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-ghost">Cerrar sesión</button>
    </form>
  </main>
</body>
</html>
