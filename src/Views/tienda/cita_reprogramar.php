<div class="pq-topbar" style="border-bottom: none; padding-top: 20px">
  <a href="<?= e(base_url('/cita/' . $cita['token_gestion'])) ?>" class="pq-mono" style="font-size: 11px; color: var(--gris-suave); text-decoration: none">‹ volver a tu cita</a>
</div>

<?php $diasCorto = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']; ?>
<div class="pq-content-tienda" style="padding-top: 0">
  <h1 class="pq-tienda-nombre" style="font-size: 24px">Reprogramar <?= e($cita['nombre_servicio']) ?></h1>
  <span class="pq-tienda-desc"><?= e(nombre_publico_sede($negocio)) ?> · <?= (int) $cita['duracion_min'] ?> min</span>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
  <?php endif; ?>

  <div style="margin-top: 20px">
    <span class="pq-label">Elige el día</span>
    <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; margin-top: 8px">
      <?php foreach ($fechasDisponibles as $opcion): ?>
        <?php $esHoy = $opcion === date('Y-m-d'); $activo = $opcion === $fecha; ?>
        <a href="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/reprogramar')) . '?fecha=' . $opcion ?>"
           class="pq-chip <?= $activo ? 'pq-chip-caja' : '' ?>" style="text-decoration: none; white-space: nowrap; flex-shrink: 0">
          <?= $esHoy ? 'Hoy' : e($diasCorto[(int) date('w', strtotime($opcion))] . ' ' . date('d', strtotime($opcion))) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="margin-top: 20px">
    <span class="pq-label">Horarios disponibles</span>

    <?php if (!empty($bloqueada)): ?>
      <p class="pq-ayuda" style="margin-top: 10px"><?= e(nombre_publico_sede($negocio)) ?> no atiende ese día. Elige otra fecha.</p>
    <?php elseif ($slots === []): ?>
      <p class="pq-ayuda" style="margin-top: 10px">No hay horarios disponibles ese día. Elige otra fecha.</p>
    <?php else: ?>
      <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px">
        <?php foreach ($slots as $slot): ?>
          <form method="post" action="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/reprogramar')) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
            <input type="hidden" name="hora" value="<?= e($slot) ?>">
            <button type="submit" class="pq-btn pq-btn-ghost-oscuro pq-btn-chico pq-mono"><?= e($slot) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
