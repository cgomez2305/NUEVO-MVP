<a href="<?= e(base_url('/panel/horario')) ?>" class="pq-mono" style="font-size: 11px; color: var(--gris-suave); text-decoration: none">‹ horario</a>

<span class="pq-eyebrow" style="display: block; margin-top: 12px">Días no disponibles</span>
<h1 class="pq-h1" style="font-size: 28px">Vacaciones y festivos</h1>
<p class="pq-lead">Estos días no aparecerán como disponibles en tu tienda, aunque tu horario semanal los tenga abiertos.</p>

<form method="post" action="<?= e(base_url('/panel/horario/fechas')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 20px">
  <?= csrf_campo() ?>
  <div style="display: flex; gap: 8px; flex-wrap: wrap">
    <input class="pq-input pq-mono" style="flex-grow: 1; min-width: 160px" type="date" name="fecha" min="<?= e(date('Y-m-d')) ?>" required>
    <input class="pq-input" style="flex-grow: 2; min-width: 160px" type="text" name="motivo" placeholder="Motivo (opcional): vacaciones, festivo..." maxlength="120">
    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Bloquear</button>
  </div>
</form>

<div class="pq-stack" style="gap: 8px; margin-top: 20px">
  <?php if ($fechas === []): ?>
    <p class="pq-ayuda">No tienes días bloqueados por ahora.</p>
  <?php endif; ?>

  <?php foreach ($fechas as $fecha): ?>
    <div class="pq-card-borde" style="display: flex; align-items: center; justify-content: space-between; gap: 10px">
      <div class="pq-stack">
        <span style="font-size: 14px; font-weight: 600"><?= e(date('d M Y', strtotime((string) $fecha['fecha']))) ?></span>
        <?php if (!empty($fecha['motivo'])): ?>
          <span class="pq-ayuda"><?= e($fecha['motivo']) ?></span>
        <?php endif; ?>
      </div>
      <form method="post" action="<?= e(base_url('/panel/horario/fechas/' . $fecha['id'] . '/eliminar')) ?>" data-confirmar="¿Destrabar el <?= e(date('d M Y', strtotime((string) $fecha['fecha']))) ?>? Volverá a verse disponible en tu tienda.">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">quitar</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
