<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/../layouts/_head.php'; ?>
</head>
<body class="pq-panel-bg">
  <div class="pq-shell">
    <div class="pq-content" style="display: flex; flex-direction: column; min-height: 100vh; justify-content: center; gap: 28px">

      <div>
        <span class="pq-eyebrow">Veci</span>
        <h1 class="pq-h1" style="font-size: 40px">Vende por WhatsApp sin pagar comisión. Y haz que vuelvan.</h1>
        <p class="pq-lead">Publica tu tienda con una foto de tu menú, cobra por Bre-B y deja que el copiloto te diga a quién escribirle hoy.</p>
      </div>

      <div class="pq-card">
        <div class="pq-paso"><span class="pq-paso-num">001</span><span>Tomas foto de tu menú</span></div>
        <div class="pq-paso"><span class="pq-paso-num">002</span><span>La IA arma tu tienda y tú la revisas</span></div>
        <div class="pq-paso"><span class="pq-paso-num">003</span><span>Cobras por Bre-B, Nequi o efectivo</span></div>
      </div>

      <div class="pq-stack" style="gap: 10px">
        <?php if ($negocio): ?>
          <a href="<?= e(base_url('/panel')) ?>" class="pq-btn pq-btn-sello">Ir a mi panel →</a>
        <?php else: ?>
          <a href="<?= e(base_url('/registro')) ?>" class="pq-btn pq-btn-sello">Crear mi tienda gratis →</a>
          <a href="<?= e(base_url('/login')) ?>" class="pq-btn pq-btn-ghost">Ya tengo cuenta</a>
        <?php endif; ?>
        <a href="<?= e(base_url('/t/donamaria')) ?>" class="pq-ayuda pq-centro" style="margin-top: 6px">Ver una tienda de ejemplo →</a>
      </div>

    </div>
  </div>
</body>
</html>
