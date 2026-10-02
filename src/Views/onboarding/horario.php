<?php
$pasoActual = 3;
$volverUrl = '/panel/onboarding/productos';
require __DIR__ . '/_pasos.php';
?>
<main class="pq-onb-cuerpo">
  <h1 class="pq-h1">¿Cuándo atiendes?</h1>
  <p class="pq-lead pq-onb-bajada">Con esto Veci calcula qué horas puede reservar tu cliente. Lo cambias cuando quieras desde el panel.</p>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta pq-onb-aviso" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('/panel/onboarding/horario')) ?>" class="pq-onb-form" data-enviando="Guardando…">
    <?= csrf_campo() ?>
    <?php $sugerirSiVacio = true; require __DIR__ . '/../panel/_semana.php'; ?>

    <div class="pq-campo pq-horario-intervalo">
      <label class="pq-label" for="intervalo">¿Cada cuánto puede empezar una cita?</label>
      <select class="pq-select" id="intervalo" name="intervalo">
        <?php foreach ([15, 20, 30, 45, 60] as $min): ?>
          <option value="<?= $min ?>" <?= (int) ($negocio['intervalo_citas_min'] ?? 30) === $min ? 'selected' : '' ?>><?= $min ?> minutos</option>
        <?php endforeach; ?>
      </select>
      <p class="pq-ayuda">Con 30 minutos, el cliente ve 9:00, 9:30, 10:00… La duración de cada servicio va aparte.</p>
    </div>

    <div class="pq-onb-pie">
      <button type="submit" class="pq-btn pq-btn-sello">Guardar y continuar →</button>
    </div>
  </form>
</main>
