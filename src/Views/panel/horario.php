<?php
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$porDefecto = ['08:00', '18:00'];
?>
<span class="pq-eyebrow">Horario de atención</span>
<h1 class="pq-h1" style="font-size: 28px">¿Cuándo puede reservar tu cliente?</h1>
<p class="pq-lead">Los días que dejes sin marcar aparecen como cerrados en tu tienda.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-card" style="margin-top: 16px; border: 1px solid var(--caja); color: var(--caja)"><?= e($ok) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/panel/horario')) ?>" style="margin-top: 20px">
  <?= csrf_campo() ?>

  <div class="pq-stack" style="gap: 10px">
    <?php foreach ($dias as $num => $nombre): ?>
      <?php $abierto = isset($horario[(string) $num]); $rango = $horario[(string) $num] ?? $porDefecto; ?>
      <div class="pq-card-borde" style="display: flex; flex-direction: column; gap: 10px">
        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600">
          <input type="checkbox" name="abierto_<?= $num ?>" value="1" <?= $abierto ? 'checked' : '' ?>>
          <?= e($nombre) ?>
        </label>
        <div style="display: flex; align-items: center; gap: 8px">
          <input class="pq-input pq-mono" style="flex: 1; min-width: 0" type="time" name="inicio_<?= $num ?>" value="<?= e($rango[0]) ?>">
          <span class="pq-ayuda" style="flex-shrink: 0">a</span>
          <input class="pq-input pq-mono" style="flex: 1; min-width: 0" type="time" name="fin_<?= $num ?>" value="<?= e($rango[1]) ?>">
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="pq-campo" style="margin-top: 16px">
    <label class="pq-label" for="intervalo">Cada cuánto abres un turno</label>
    <select class="pq-select" id="intervalo" name="intervalo">
      <?php foreach ([15, 20, 30, 45, 60] as $min): ?>
        <option value="<?= $min ?>" <?= (int) ($negocio['intervalo_citas_min'] ?? 30) === $min ? 'selected' : '' ?>><?= $min ?> minutos</option>
      <?php endforeach; ?>
    </select>
  </div>

  <button type="submit" class="pq-btn pq-btn-sello" style="margin-top: 20px">Guardar horario</button>
</form>

<div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid #E7E0CF">
  <span style="font-size: 13px; font-weight: 600">¿Vacaciones o un festivo puntual?</span>
  <p class="pq-ayuda" style="margin-top: 4px">Bloquea días sueltos sin tocar tu horario semanal.</p>
  <a href="<?= e(base_url('/panel/horario/fechas')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico" style="margin-top: 10px">Días no disponibles →</a>
</div>
