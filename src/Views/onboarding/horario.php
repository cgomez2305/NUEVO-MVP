<?php
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$porDefecto = ['08:00', '18:00'];
$pasoActual = 3;
$totalPasos = 4;
?>
<div class="pq-topbar pq-onboarding-cabecera" style="border-bottom: none">
  <a href="<?= e(base_url('/panel/onboarding/productos')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Atrás</a>
  <?php require __DIR__ . '/_pasos.php'; ?>
</div>

<div class="pq-content">
  <h1 class="pq-h1" style="font-size: 28px">Tu horario de atención</h1>
  <p class="pq-lead">Con esto calculamos qué horas puede reservar tu cliente. Puedes cambiarlo después desde el panel.</p>

  <form method="post" action="<?= e(base_url('/panel/onboarding/horario')) ?>" style="margin-top: 20px">
    <?= csrf_campo() ?>

    <div class="pq-stack" style="gap: 10px">
      <?php foreach ($dias as $num => $nombre): ?>
        <?php $abierto = isset($horario[(string) $num]); $rango = $horario[(string) $num] ?? $porDefecto; ?>
        <div class="pq-card-borde pq-dia-fila">
          <label class="pq-dia-switch">
            <input type="checkbox" name="abierto_<?= $num ?>" value="1" <?= $abierto ? 'checked' : '' ?>>
            <span class="pq-dia-switch-pista"></span>
            <span class="pq-dia-switch-nombre"><?= e($nombre) ?></span>
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

    <button type="submit" class="pq-btn pq-btn-sello" style="margin-top: 20px">Guardar horario →</button>
  </form>
</div>
