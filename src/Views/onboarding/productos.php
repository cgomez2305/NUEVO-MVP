<?php $pasoActual = 2; $totalPasos = 3; $pasoNombre = 'Productos'; ?>
<div class="pq-topbar" style="border-bottom: none">
  <a href="<?= e(base_url('/panel/onboarding/foto')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Atrás</a>
</div>
<?php require __DIR__ . '/_pasos.php'; ?>

<div class="pq-content">
  <h1 class="pq-h1" style="font-size: 28px">La IA arma tu tienda</h1>
  <p class="pq-lead">Encuentra productos y precios en tu foto. Tú solo revisas.</p>

  <?php if ($productos === []): ?>
    <div class="pq-centro" style="margin-top: 40px; display: flex; flex-direction: column; align-items: center; gap: 18px">
      <img src="<?= e(base_url($negocio['menu_foto'])) ?>" alt="Foto de tu menú" style="width: 140px; height: 140px; object-fit: cover; border-radius: 16px">
      <form method="post" action="<?= e(base_url('/panel/onboarding/analizar')) ?>">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-btn pq-btn-sello">Analizar foto con IA</button>
      </form>
    </div>
  <?php else: ?>
    <div style="margin-top: 20px">
      <span class="pq-contador-badge" style="margin-bottom: 10px">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l5 5L20 7"/></svg>
        <?= count($productos) ?> productos encontrados
      </span>
      <?php $compacto = true; require __DIR__ . '/../productos/_gestor.php'; ?>
    </div>

    <a href="<?= e(base_url('/panel/onboarding/pago')) ?>" class="pq-btn pq-btn-sello" style="margin-top: 24px">Continuar →</a>
  <?php endif; ?>
</div>
