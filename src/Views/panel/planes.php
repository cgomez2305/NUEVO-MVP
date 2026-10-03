<?php use App\Models\PlanTratamiento; ?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Consultorio</span>
    <h1 class="pq-h1">Planes de tratamiento</h1>
  </div>
  <a class="pq-btn pq-btn-sello pq-btn-chico" href="<?= e(base_url('/panel/planes/nuevo')) ?>">Nuevo plan</a>
</div>
<p class="pq-lead pq-pagina-bajada-panel">El presupuesto por fases que el paciente aprueba desde su celular y va pagando con abonos. Veci lleva el avance y el saldo; no es una historia clínica.</p>

<?php if ($planes === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6v4H9z"/><path d="M8 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><path d="M9 12h6M9 16h4"/></svg>
    <p><strong>Todavía no hay planes.</strong><br>Arma uno desde una cita en la agenda o con <a href="<?= e(base_url('/panel/planes/nuevo')) ?>">Nuevo plan</a>.</p>
  </div>
<?php else: ?>
  <ul class="pq-admin-tarjeta pq-admin-filas pq-planes-lista">
    <?php foreach ($planes as $plan): ?>
      <?php
      $estado = PlanTratamiento::vencido($plan) ? 'vencido' : $plan['estado'];
      $pagadoPct = (int) $plan['total'] > 0 ? min(100, (int) round((int) $plan['pagado'] * 100 / (int) $plan['total'])) : 0;
      ?>
      <li>
        <a class="pq-admin-fila pq-plan-fila" href="<?= e(base_url('/panel/planes/' . (int) $plan['id'])) ?>">
          <span class="pq-admin-fila-texto">
            <strong><?= e($plan['cliente_nombre']) ?> · <?= e($plan['titulo']) ?></strong>
            <span class="pq-ayuda">
              <span class="pq-mono"><?= pesos((int) $plan['pagado']) ?></span> de <span class="pq-mono"><?= pesos((int) $plan['total']) ?></span>
              <?= $plan['estado'] === 'aprobado' && PlanTratamiento::saldo($plan) > 0 ? ' · debe ' . pesos(PlanTratamiento::saldo($plan)) : '' ?>
            </span>
            <span class="pq-plan-barra" aria-hidden="true"><span style="width: <?= $pagadoPct ?>%"></span></span>
          </span>
          <span class="pq-chip <?= match ($estado) { 'aprobado' => 'pq-chip-curso', 'terminado' => 'pq-chip-caja', 'propuesto' => 'pq-chip-pendiente', default => 'pq-chip-cancelado' } ?>"><?= e($estado === 'vencido' ? 'Venció' : PlanTratamiento::ETIQUETAS[$estado]) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
