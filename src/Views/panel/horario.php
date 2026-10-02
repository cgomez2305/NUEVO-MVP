<?php
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$porDefecto = ['08:00', '18:00'];
$resumen = horario_resumen($horario);
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Horario de atención</span>
    <h1 class="pq-h1">¿Cuándo pueden reservar?</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Los días apagados se ven como cerrados en tu agenda online.</p>

<?php
// Una fila por día con un interruptor: apagado, las horas se esconden y
// aparece "Cerrado" (CSS con :has, sin JS). Antes eran 7 tarjetas con
// casilla + dos horas siempre visibles, aunque el día estuviera cerrado.
?>
<form method="post" action="<?= e(base_url('/panel/horario')) ?>" class="pq-horario-form">
  <?= csrf_campo() ?>

  <fieldset class="pq-semana">
    <legend class="pq-sr-solo">Días y horas de atención</legend>
    <?php foreach ($dias as $num => $nombre): ?>
      <?php $abierto = isset($horario[(string) $num]); $rango = $horario[(string) $num] ?? $porDefecto; ?>
      <div class="pq-semana-dia">
        <label class="pq-interruptor">
          <input type="checkbox" name="abierto_<?= $num ?>" value="1" <?= $abierto ? 'checked' : '' ?>>
          <span class="pq-interruptor-pista" aria-hidden="true"></span>
          <span class="pq-semana-nombre"><?= e($nombre) ?></span>
        </label>
        <div class="pq-semana-horas">
          <input class="pq-input pq-mono" type="time" name="inicio_<?= $num ?>" value="<?= e($rango[0]) ?>" aria-label="<?= e($nombre) ?>: abre a las">
          <span class="pq-ayuda" aria-hidden="true">a</span>
          <input class="pq-input pq-mono" type="time" name="fin_<?= $num ?>" value="<?= e($rango[1]) ?>" aria-label="<?= e($nombre) ?>: cierra a las">
        </div>
        <span class="pq-semana-cerrado">Cerrado</span>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <div class="pq-campo pq-horario-intervalo">
    <label class="pq-label" for="intervalo">Cada cuánto abres un turno</label>
    <select class="pq-select" id="intervalo" name="intervalo">
      <?php foreach ([15, 20, 30, 45, 60] as $min): ?>
        <option value="<?= $min ?>" <?= (int) ($negocio['intervalo_citas_min'] ?? 30) === $min ? 'selected' : '' ?>><?= $min ?> minutos</option>
      <?php endforeach; ?>
    </select>
  </div>

  <button type="submit" class="pq-btn pq-btn-sello pq-horario-guardar">Guardar horario</button>
</form>

<?php if ($resumen !== []): ?>
  <section class="pq-escaparate" aria-labelledby="pq-horario-vista">
    <div class="pq-toldo pq-toldo-corto" aria-hidden="true"></div>
    <div class="pq-escaparate-cuerpo pq-escaparate-cuerpo-simple">
      <span class="pq-escaparate-eyebrow">Así lo ven tus clientes (horario guardado)</span>
      <h2 class="pq-info-titulo" id="pq-horario-vista">Horario</h2>
      <dl class="pq-horario">
        <?php foreach ($resumen as $linea): ?>
          <div class="pq-horario-fila<?= $linea['hoy'] ? ' pq-horario-hoy' : '' ?><?= $linea['rango'] === 'Cerrado' ? ' pq-horario-cerrado' : '' ?>">
            <dt><?= e($linea['dia']) ?><?php if ($linea['hoy']): ?> <span class="pq-horario-etiqueta">hoy</span><?php endif; ?></dt>
            <dd><?= e($linea['rango']) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>
  </section>
<?php endif; ?>

<a href="<?= e(base_url('/panel/horario/fechas')) ?>" class="pq-enlace-fila">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M9 14l6 4M15 14l-6 4"/></svg>
  <span>
    <strong>Vacaciones y festivos</strong>
    <span class="pq-ayuda">Bloquea días sueltos sin tocar tu horario semanal</span>
  </span>
  <svg class="pq-enlace-fila-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
</a>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
