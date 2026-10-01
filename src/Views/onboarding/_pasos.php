<?php
/**
 * Indicador de progreso del onboarding — se incluye con $pasoActual,
 * $totalPasos y $pasoNombre ya definidos en el archivo que lo requiere.
 * El texto "Paso X de Y · Nombre" existe porque la barra segmentada sola
 * no dice cuánto falta ni en qué paso se está parado.
 */
?>
<div class="pq-onboarding-progreso">
  <span class="pq-onboarding-progreso-texto">Paso <?= (int) $pasoActual ?> de <?= (int) $totalPasos ?> · <?= e($pasoNombre) ?></span>
  <div class="pq-onboarding-pasos" role="img" aria-label="Paso <?= (int) $pasoActual ?> de <?= (int) $totalPasos ?>: <?= e($pasoNombre) ?>">
    <?php for ($i = 1; $i <= $totalPasos; $i++): ?>
      <span class="pq-onboarding-paso<?= $i < $pasoActual ? ' pq-onboarding-paso-hecho' : ($i === $pasoActual ? ' pq-onboarding-paso-activo' : '') ?>"></span>
    <?php endfor; ?>
  </div>
</div>
