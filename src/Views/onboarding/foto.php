<?php $esReservas = $negocio['tipo_negocio'] === 'reservas'; $pasoActual = 1; $totalPasos = $esReservas ? 4 : 3; ?>
<div class="pq-topbar pq-onboarding-cabecera" style="border-bottom: none">
  <a href="<?= e(base_url('/')) ?>" class="pq-topbar-brand">Veci</a>
  <?php require __DIR__ . '/_pasos.php'; ?>
</div>

<div class="pq-content">
  <h1 class="pq-h1" style="font-size: 28px"><?= $esReservas ? 'Toma foto de tu lista de servicios' : 'Toma foto de tu menú' ?></h1>
  <p class="pq-lead">
    <?= $esReservas
      ? 'Puede ser una lista de precios, una pizarra o tus fotos de Instagram. La IA hace el resto.'
      : 'Puede ser una carta impresa, una pizarra o tus fotos de Instagram. La IA hace el resto.' ?>
  </p>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($negocio['menu_foto'])): ?>
    <div class="pq-card-borde" style="margin-top: 20px; display: flex; gap: 12px; align-items: center">
      <img src="<?= e(base_url($negocio['menu_foto'])) ?>" alt="Foto de tu menú" style="width: 64px; height: 64px; object-fit: cover; border-radius: 10px">
      <div class="pq-stack">
        <span style="font-size: 13px; font-weight: 600">Ya tienes una foto guardada</span>
        <span class="pq-ayuda">Sube otra si quieres reemplazarla.</span>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('/panel/onboarding/foto')) ?>" enctype="multipart/form-data" style="margin-top: 20px">
    <?= csrf_campo() ?>
    <div class="pq-campo">
      <label class="pq-label" for="foto"><?= $esReservas ? 'Foto de tus servicios' : 'Foto del menú' ?></label>
      <input class="pq-input" type="file" id="foto" name="foto" accept="image/png,image/jpeg,image/webp" required>
      <p class="pq-ayuda">JPG, PNG o WEBP. Máximo 8 MB.</p>
    </div>
    <button type="submit" class="pq-btn pq-btn-sello">Usar esta foto →</button>
  </form>

  <?php if (!empty($negocio['menu_foto'])): ?>
    <p class="pq-centro" style="margin-top: 16px">
      <a href="<?= e(base_url('/panel/onboarding/productos')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave)">seguir con la foto que ya subí →</a>
    </p>
  <?php endif; ?>
</div>
