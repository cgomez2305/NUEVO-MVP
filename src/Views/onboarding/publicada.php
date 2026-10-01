<?php $esReservas = $negocio['tipo_negocio'] === 'reservas'; ?>
<div class="pq-content" style="display: flex; flex-direction: column; align-items: center; gap: 20px; text-align: center; padding-top: 48px">

  <div style="width: 88px; height: 88px; border-radius: 50%; border: 3px solid var(--caja); display: flex; align-items: center; justify-content: center; transform: rotate(-6deg)">
    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#16A36A" stroke-width="2.4"><path d="M5 13l5 5L20 7"/></svg>
  </div>

  <div>
    <h1 class="pq-h1" style="font-size: 28px">¡Tu tienda está publicada!</h1>
    <p class="pq-lead" style="max-width: 280px; margin-left: auto; margin-right: auto">
      Ya puedes recibir clientes. Compártela en tu estado de WhatsApp o en Instagram.
    </p>
  </div>

  <?php $urlTienda = url_publica('/t/' . $negocio['slug']); ?>
  <div class="pq-card" style="width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px">
    <span class="pq-mono" style="font-size: 13px; word-break: break-all"><?= e($urlTienda) ?></span>
    <button type="button" class="pq-btn-icono" style="flex-shrink: 0" data-copiar="<?= e($urlTienda) ?>" aria-label="Copiar enlace">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
    </button>
  </div>

  <div class="pq-stack" style="gap: 10px; width: 100%">
    <a href="https://wa.me/?text=<?= rawurlencode('Mira mi tienda: ' . $urlTienda) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-caja">Compartir por WhatsApp →</a>
    <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-btn pq-btn-oscuro">Ver mi tienda</a>
    <a href="<?= e(base_url('/panel')) ?>" class="pq-btn pq-btn-ghost">Ir a mi panel</a>
  </div>

  <div class="pq-card-borde" style="width: 100%; text-align: left; padding: 14px 16px">
    <span class="pq-eyebrow" style="display: block; margin-bottom: 8px">Tu tienda está lista</span>
    <div class="pq-stack" style="gap: 6px">
      <span class="pq-publicada-check">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
        <?= $esReservas ? $totalCatalogo . ' servicio' . ($totalCatalogo === 1 ? '' : 's') . ' publicado' . ($totalCatalogo === 1 ? '' : 's') : $totalCatalogo . ' producto' . ($totalCatalogo === 1 ? '' : 's') . ' publicado' . ($totalCatalogo === 1 ? '' : 's') ?>
      </span>
      <?php if ($esReservas): ?>
        <span class="pq-publicada-check">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
          Horario configurado
        </span>
      <?php endif; ?>
      <span class="pq-publicada-check">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
        Cobros configurados
      </span>
      <?php if (empty($negocio['direccion'])): ?>
        <a href="<?= e(base_url('/panel/sedes/' . $negocio['id'] . '/editar')) ?>" class="pq-publicada-check pq-publicada-check-pendiente">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
          Agregar dirección (opcional)
        </a>
      <?php endif; ?>
    </div>
  </div>

</div>
