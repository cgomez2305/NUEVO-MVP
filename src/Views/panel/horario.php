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
      <div class="pq-card-borde" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap">
        <label style="display: flex; align-items: center; gap: 8px; width: 110px; font-size: 13px; font-weight: 600">
          <input type="checkbox" name="abierto_<?= $num ?>" value="1" <?= $abierto ? 'checked' : '' ?>>
          <?= e($nombre) ?>
        </label>
        <input class="pq-input pq-mono" style="width: 110px" type="time" name="inicio_<?= $num ?>" value="<?= e($rango[0]) ?>">
        <span class="pq-ayuda">a</span>
        <input class="pq-input pq-mono" style="width: 110px" type="time" name="fin_<?= $num ?>" value="<?= e($rango[1]) ?>">
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
