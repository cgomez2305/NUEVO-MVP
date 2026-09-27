<div class="pq-topbar" style="border-bottom: none">
  <a href="<?= e(base_url('/panel/onboarding/foto')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Atrás</a>
  <span class="pq-chip">PASO 2 DE 4</span>
</div>

<div class="pq-content">
  <h1 class="pq-h1" style="font-size: 28px">La IA arma tu lista de servicios</h1>
  <p class="pq-lead">Encuentra servicios, precios y duración en tu foto. Tú solo revisas.</p>

  <?php if ($servicios === []): ?>
    <div class="pq-centro" style="margin-top: 40px; display: flex; flex-direction: column; align-items: center; gap: 18px">
      <img src="<?= e(base_url($negocio['menu_foto'])) ?>" alt="Foto de tus servicios" style="width: 140px; height: 140px; object-fit: cover; border-radius: 16px">
      <form method="post" action="<?= e(base_url('/panel/onboarding/analizar')) ?>">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-btn pq-btn-sello">Analizar foto con IA</button>
      </form>
    </div>
  <?php else: ?>
    <div style="margin-top: 20px">
      <span class="pq-mono" style="font-size: 12px; color: var(--caja); display: block; margin-bottom: 10px">
        <?= count($servicios) ?> SERVICIOS ENCONTRADOS
      </span>
      <?php require __DIR__ . '/../servicios/_gestor.php'; ?>
    </div>

    <a href="<?= e(base_url('/panel/onboarding/horario')) ?>" class="pq-btn pq-btn-sello" style="margin-top: 24px">Todo correcto, seguir →</a>
  <?php endif; ?>
</div>
