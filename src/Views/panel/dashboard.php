<span class="pq-eyebrow"><?= e(strftime_es()) ?></span>
<h1 class="pq-h1">Hola, <?= e($negocio['nombre']) ?></h1>

<?php if ((int) $negocio['publicada'] === 1): ?>
  <p class="pq-lead">
    Tu tienda está publicada en
    <a href="<?= e(url_publica('/t/' . $negocio['slug'])) ?>" target="_blank" rel="noopener" style="color: var(--sello); font-weight: 600">
      <?= e(url_publica('/t/' . $negocio['slug'])) ?>
    </a>
  </p>
<?php else: ?>
  <div class="pq-alerta" style="margin-top: 16px">
    Tu tienda todavía no está publicada.
    <a href="<?= e(base_url('/panel/onboarding/foto')) ?>" style="font-weight: 700">Termina el alta →</a>
  </div>
<?php endif; ?>

<div class="pq-stats">
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--caja)"><?= $pedidosHoy ?></span>
    <span class="pq-stat-label">pedidos hoy</span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--sello)"><?= $recompraPct ?>%</span>
    <span class="pq-stat-label">recompra del mes</span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--aji)"><?= $aReactivar ?></span>
    <span class="pq-stat-label">por reactivar</span>
  </div>
</div>

<?php if ($aReactivar > 0): ?>
  <a href="<?= e(base_url('/panel/copiloto')) ?>" class="pq-btn pq-btn-sello" style="margin-top: 20px">Ver a quién escribirle hoy →</a>
<?php endif; ?>

<div style="margin-top: 28px">
  <span style="font-size: 13px; font-weight: 600; color: var(--gris-texto)">Últimos pedidos</span>

  <?php if ($ultimosPedidos === []): ?>
    <p class="pq-ayuda" style="margin-top: 10px">Todavía no te han hecho pedidos.</p>
  <?php else: ?>
    <div class="pq-stack" style="gap: 8px; margin-top: 10px">
      <?php foreach ($ultimosPedidos as $pedido): ?>
        <div class="pq-card-borde" style="display: flex; align-items: center; justify-content: space-between">
          <div class="pq-stack">
            <span style="font-size: 14px; font-weight: 600"><?= e($pedido['cliente_nombre']) ?></span>
            <span class="pq-ayuda"><?= e(date('d M, g:i a', strtotime((string) $pedido['creado_en']))) ?></span>
          </div>
          <div class="pq-stack" style="align-items: flex-end">
            <span class="pq-mono" style="font-size: 13px"><?= pesos((int) $pedido['total']) ?></span>
            <span class="pq-chip <?= e(chip_estado($pedido['estado'])) ?>"><?= e($pedido['estado']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <a href="<?= e(base_url('/panel/pedidos')) ?>" class="pq-mono" style="display: block; margin-top: 12px; font-size: 12px; color: var(--sello)">ver todos los pedidos →</a>
  <?php endif; ?>
</div>
