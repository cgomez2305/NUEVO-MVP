<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="pq-panel-bg pq-admin-bg">
  <?php
  // El panel interno usa el mismo papel y la misma letra que el del dueño;
  // lo distingue el sello "Interno" de la cabecera (como el de caucho que
  // se le pone a un documento de la casa), para que nadie confunda en qué
  // panel está operando.
  ?>
  <div class="pq-admin-hoja">
  <header class="pq-admin-cabeza">
    <a href="<?= e(base_url('/admin')) ?>" class="pq-admin-marca" aria-label="Veci · panel interno">
      <img src="<?= e(base_url('assets/img/logo-veci-lockup-transparente.png')) ?>" alt="" class="pq-admin-logo">
      <span class="pq-admin-sello" aria-hidden="true">Interno</span>
    </a>
    <nav class="pq-admin-nav" aria-label="Panel interno">
      <a href="<?= e(base_url('/admin')) ?>">Negocios</a>
      <a href="<?= e(base_url('/admin/ofertas')) ?>">Ofertas</a>
    </nav>
    <div class="pq-admin-sesion">
      <span class="pq-admin-quien"><?= e($admin['nombre']) ?></span>
      <form method="post" action="<?= e(base_url('/admin/logout')) ?>">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Salir</button>
      </form>
    </div>
  </header>

  <main class="pq-admin-cuerpo">
    <?= $contenido ?>
  </main>
  </div>
  <script src="<?= e(base_url('assets/js/confirmar.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/interacciones.js')) ?>" defer></script>
</body>
</html>
