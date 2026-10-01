<?php
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$diasAbrev = [1 => 'LUN', 2 => 'MAR', 3 => 'MIÉ', 4 => 'JUE', 5 => 'VIE', 6 => 'SÁB', 7 => 'DOM'];
$porDefecto = ['08:00', '18:00'];
$pasoActual = 3;
$totalPasos = 4;
$pasoNombre = 'Horarios';
?>
<div class="pq-topbar" style="border-bottom: none">
  <a href="<?= e(base_url('/panel/onboarding/productos')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Atrás</a>
</div>
<?php require __DIR__ . '/_pasos.php'; ?>

<div class="pq-content">
  <h1 class="pq-h1" style="font-size: 28px">Tu horario de atención</h1>
  <p class="pq-lead">Con esto calculamos qué horas puede reservar tu cliente. Puedes cambiarlo después desde el panel.</p>

  <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" style="margin-top: 16px; width: auto" data-aplicar-horario-semana="1">Usar el horario del lunes toda la semana</button>

  <form method="post" action="<?= e(base_url('/panel/onboarding/horario')) ?>" style="margin-top: 12px">
    <?= csrf_campo() ?>

    <div class="pq-dias-compacto">
      <?php foreach ($dias as $num => $nombre): ?>
        <?php $abierto = isset($horario[(string) $num]); $rango = $horario[(string) $num] ?? $porDefecto; ?>
        <div class="pq-dia-fila-compacta">
          <label class="pq-dia-switch-chico">
            <input type="checkbox" name="abierto_<?= $num ?>" value="1" data-dia="<?= $num ?>" <?= $abierto ? 'checked' : '' ?>>
            <span class="pq-dia-switch-pista"></span>
          </label>
          <span class="pq-dia-fila-compacta-nombre">
            <span class="pq-dia-nombre-largo"><?= e($nombre) ?></span>
            <span class="pq-dia-nombre-corto pq-mono"><?= e($diasAbrev[$num]) ?></span>
          </span>
          <input class="pq-input pq-mono pq-input-chico" type="time" name="inicio_<?= $num ?>" data-inicio-dia="<?= $num ?>" value="<?= e($rango[0]) ?>">
          <span class="pq-ayuda">–</span>
          <input class="pq-input pq-mono pq-input-chico" type="time" name="fin_<?= $num ?>" data-fin-dia="<?= $num ?>" value="<?= e($rango[1]) ?>">
        </div>
      <?php endforeach; ?>
    </div>

    <div class="pq-campo" style="margin-top: 16px">
      <label class="pq-label" for="intervalo">¿Cada cuánto puede comenzar una cita?</label>
      <select class="pq-select" id="intervalo" name="intervalo">
        <?php foreach ([15, 20, 30, 45, 60] as $min): ?>
          <option value="<?= $min ?>" <?= (int) ($negocio['intervalo_citas_min'] ?? 30) === $min ? 'selected' : '' ?>><?= $min ?> minutos</option>
        <?php endforeach; ?>
      </select>
      <p class="pq-ayuda">Esto define los horarios que verá el cliente. La duración de cada servicio sigue siendo independiente.</p>
    </div>

    <button type="submit" class="pq-btn pq-btn-sello" style="margin-top: 20px">Continuar →</button>
  </form>
</div>
