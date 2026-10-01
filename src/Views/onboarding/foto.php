<?php $esReservas = $negocio['tipo_negocio'] === 'reservas'; $pasoActual = 1; $totalPasos = $esReservas ? 4 : 3; $pasoNombre = 'Foto'; ?>
<div class="pq-topbar" style="border-bottom: none">
  <a href="<?= e(base_url('/')) ?>" style="display: inline-flex; text-decoration: none">
    <img src="<?= e(base_url('assets/img/logo-veci-lockup.png')) ?>" alt="Veci" style="height: 24px; width: auto">
  </a>
</div>
<?php require __DIR__ . '/_pasos.php'; ?>

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

  <form method="post" action="<?= e(base_url('/panel/onboarding/foto')) ?>" enctype="multipart/form-data" style="margin-top: 20px" data-form-foto>
    <?= csrf_campo() ?>

    <label class="pq-subir-foto" for="foto" data-dropzone-foto>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
      <strong><?= $esReservas ? 'Sube una foto de tus servicios' : 'Sube una foto de tu menú' ?></strong>
      <span class="pq-subir-foto-botones">
        <span class="pq-subir-foto-boton pq-subir-foto-boton-principal">Tomar foto</span>
        <span class="pq-subir-foto-boton">Elegir archivo</span>
      </span>
      <span>JPG, PNG o WEBP · Máx. 8 MB</span>
      <input id="foto" type="file" name="foto" accept="image/png,image/jpeg,image/webp" required data-input-foto>
    </label>

    <div class="pq-foto-previa" data-previa-foto hidden>
      <img data-previa-foto-img alt="">
      <div class="pq-foto-previa-info">
        <strong data-previa-foto-nombre></strong>
        <span data-previa-foto-tamano></span>
      </div>
      <button type="button" class="pq-foto-previa-cambiar" data-previa-foto-cambiar>Cambiar foto</button>
    </div>

    <button type="submit" class="pq-btn pq-btn-sello" style="margin-top: 20px" data-boton-foto>Analizar esta foto →</button>
  </form>

  <?php if (!empty($negocio['menu_foto'])): ?>
    <p class="pq-centro" style="margin-top: 16px">
      <a href="<?= e(base_url('/panel/onboarding/productos')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave)">seguir con la foto que ya subí →</a>
    </p>
  <?php endif; ?>
</div>
