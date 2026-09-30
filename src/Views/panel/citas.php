<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px">
  <div>
    <span class="pq-eyebrow">Agenda</span>
    <h1 class="pq-h1" style="font-size: 28px">Tus próximas citas</h1>
  </div>
  <a href="<?= e(base_url('/panel/citas/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar CSV</a>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>

<?php if ($citas === []): ?>
  <p class="pq-lead" style="margin-top: 16px">Todavía no tienes citas reservadas.</p>
<?php else: ?>
  <div class="pq-stack" style="gap: 10px; margin-top: 20px">
    <?php foreach ($citas as $cita): ?>
      <div class="pq-card-borde">
        <div style="display: flex; align-items: center; justify-content: space-between">
          <div class="pq-stack">
            <span style="font-size: 14px; font-weight: 600">
              <?= e(date('d M, g:i a', strtotime((string) $cita['fecha_hora']))) ?> · <?= e($cita['cliente_nombre']) ?>
            </span>
            <span class="pq-ayuda">
              <?= e($cita['nombre_servicio']) ?> · <?= (int) $cita['duracion_min'] ?> min
              <?php if (!empty($cita['empleado_nombre'])): ?> · <?= e($cita['empleado_nombre']) ?><?php endif; ?>
            </span>
          </div>
          <span class="pq-mono" style="font-size: 14px; font-weight: 600"><?= pesos((int) $cita['precio']) ?></span>
        </div>

        <?php if ((int) $cita['anticipo_monto'] > 0): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px">
            <span class="pq-ayuda">Anticipo: <?= pesos((int) $cita['anticipo_monto']) ?></span>
            <?php if ($cita['anticipo_estado'] === 'pagado'): ?>
              <span class="pq-chip pq-chip-caja">Anticipo pagado</span>
            <?php else: ?>
              <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/anticipo')) ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-chip pq-chip-pendiente" style="border: none; cursor: pointer">Marcar anticipo pagado</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/estado')) ?>"
              style="display: flex; gap: 8px; margin-top: 12px">
          <?= csrf_campo() ?>
          <select class="pq-select" name="estado" style="flex-grow: 1">
            <?php foreach (['pendiente', 'confirmada', 'completada', 'cancelada'] as $estado): ?>
              <option value="<?= e($estado) ?>" <?= $cita['estado'] === $estado ? 'selected' : '' ?>><?= e(ucfirst($estado)) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Actualizar</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
