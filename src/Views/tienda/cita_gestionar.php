<?php
$estadoLegible = ['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'completada' => 'Completada', 'cancelada' => 'Cancelada'];
?>
<div class="pq-content-tienda" style="padding-top: 24px">

  <div class="pq-centro" style="margin-bottom: 20px">
    <h1 class="pq-tienda-nombre" style="font-size: 24px">Tu cita en <?= e($cita['negocio_nombre']) ?></h1>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="pq-card" style="margin-bottom: 12px; border: 1px solid var(--caja); color: var(--caja)"><?= e($ok) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="pq-alerta"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="pq-card" style="background: #FFFFFF; border: 1px solid #E7E0CF">
    <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0">
      <span><?= e($cita['nombre_servicio']) ?></span>
      <span class="pq-mono"><?= pesos((int) $cita['precio']) ?></span>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 700; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #C9BFA4">
      <span>Cuándo</span>
      <span class="pq-mono"><?= e(date('d M, g:i a', strtotime((string) $cita['fecha_hora']))) ?></span>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 8px">
      <span>Estado</span>
      <span class="pq-chip <?= chip_estado($cita['estado']) ?>"><?= e($estadoLegible[$cita['estado']] ?? $cita['estado']) ?></span>
    </div>
  </div>

  <?php if (!in_array($cita['estado'], ['cancelada', 'completada'], true)): ?>
    <div style="display: flex; gap: 10px; margin-top: 20px">
      <a href="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/reprogramar')) ?>" class="pq-btn pq-btn-oscuro" style="flex-grow: 1">Reprogramar</a>
    </div>

    <form method="post" action="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/cancelar')) ?>" style="margin-top: 10px" onsubmit="return confirm('¿Seguro que quieres cancelar tu cita?')">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-mono pq-centro" style="display: block; width: 100%; background: none; border: none; color: #9c2c17; font-size: 13px; cursor: pointer; padding: 10px">Cancelar cita</button>
    </form>
  <?php endif; ?>

</div>
