<?php
$diasSemana = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
$meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
?>
<a href="<?= e(base_url('/panel/horario')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Horario
</a>

<span class="pq-eyebrow pq-eyebrow-tras-volver">Días no disponibles</span>
<h1 class="pq-h1">Vacaciones y festivos</h1>
<p class="pq-lead pq-pagina-bajada-panel">Estos días no se pueden reservar, aunque tu horario semanal los tenga abiertos.</p>

<form method="post" action="<?= e(base_url('/panel/horario/fechas')) ?>" class="pq-card pq-fechas-form">
  <?= csrf_campo() ?>
  <div class="pq-campo">
    <label class="pq-label" for="pq-fecha-bloquear">Día</label>
    <input class="pq-input pq-mono" id="pq-fecha-bloquear" type="date" name="fecha" min="<?= e(date('Y-m-d')) ?>" required>
  </div>
  <div class="pq-campo">
    <label class="pq-label" for="pq-fecha-motivo">Motivo <span class="pq-ayuda">(opcional, solo lo ves tú)</span></label>
    <input class="pq-input" id="pq-fecha-motivo" type="text" name="motivo" placeholder="Vacaciones, festivo…" maxlength="120">
  </div>
  <button type="submit" class="pq-btn pq-btn-sello">Bloquear este día</button>
</form>

<?php
// Cada día bloqueado es la misma hojita de almanaque que el cliente toca
// al reservar, tachada: así se entiende de un vistazo qué día "desaparece"
// de la agenda online.
?>
<section class="pq-inicio-bloque" aria-labelledby="pq-titulo-bloqueados">
  <h2 class="pq-seccion-titulo" id="pq-titulo-bloqueados">Días bloqueados<?php if ($fechas !== []): ?> <span class="pq-seccion-cuenta"><?= count($fechas) ?></span><?php endif; ?></h2>
  <?php if ($fechas === []): ?>
    <p class="pq-ayuda pq-inicio-vacio">No tienes días bloqueados por ahora.</p>
  <?php else: ?>
    <ul class="pq-fechas-lista">
      <?php foreach ($fechas as $fecha): ?>
        <?php $ts = strtotime((string) $fecha['fecha']) ?: 0; $larga = fecha_larga((string) $fecha['fecha']); ?>
        <li class="pq-fecha-bloqueada">
          <span class="pq-dia pq-dia-bloqueado" aria-hidden="true">
            <span class="pq-dia-semana"><?= $diasSemana[(int) date('w', $ts)] ?></span>
            <span class="pq-dia-numero"><?= (int) date('j', $ts) ?></span>
            <span class="pq-dia-mes"><?= $meses[(int) date('n', $ts) - 1] ?></span>
          </span>
          <span class="pq-fecha-bloqueada-texto">
            <strong><?= e(ucfirst($larga)) ?></strong>
            <span class="pq-ayuda"><?= !empty($fecha['motivo']) ? e($fecha['motivo']) : 'Sin motivo' ?></span>
          </span>
          <form method="post" action="<?= e(base_url('/panel/horario/fechas/' . $fecha['id'] . '/eliminar')) ?>" data-confirmar="¿Volver a abrir el <?= e($larga) ?>? Se podrá reservar de nuevo.">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-enlace-boton" aria-label="Volver a abrir el <?= e($larga) ?>">Abrir</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
