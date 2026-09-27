<?php $ok = flash_obtener('ok'); ?>

<span class="pq-eyebrow">Copiloto</span>
<h1 class="pq-h1">Buenos días, <?= e($negocio['nombre']) ?></h1>

<?php if ($ok): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>

<div class="pq-stats">
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--caja)"><?= $pedidosHoy ?></span>
    <span class="pq-stat-label"><?= $negocio['tipo_negocio'] === 'reservas' ? 'citas hoy' : 'pedidos hoy' ?></span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--sello)"><?= $recompraPct ?>%</span>
    <span class="pq-stat-label">recompra del mes</span>
  </div>
</div>

<div style="margin-top: 24px">
  <span style="font-size: 13px; font-weight: 600; color: var(--gris-texto)">A quién escribirle hoy</span>

  <?php if ($lista === []): ?>
    <p class="pq-ayuda" style="margin-top: 10px">Nadie se está quedando atrás por ahora. Vuelve a revisar mañana.</p>
  <?php else: ?>
    <div style="margin-top: 12px">
      <?php foreach ($lista as $fila): $cliente = $fila['cliente']; ?>
        <div class="pq-lead">
          <div class="pq-avatar" style="background: var(--aji)">
            <?= e(mb_strtoupper(mb_substr($cliente['nombre'], 0, 1))) ?>
          </div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600"><?= e($cliente['nombre']) ?></span>
            <span class="pq-ayuda"><?= e($fila['motivo']) ?></span>
          </div>
          <a href="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje')) ?>" class="pq-btn pq-btn-sello pq-btn-chico">Enviar</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
