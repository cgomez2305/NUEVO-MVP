<?php
/**
 * Indicador de progreso del onboarding — se incluye con $pasoActual y
 * $totalPasos ya definidos en el archivo que lo requiere. La barra
 * segmentada acompaña el texto "Paso X de Y" en vez de reemplazarlo,
 * para que siga siendo legible sin depender del color.
 */
?>
<div class="pq-onboarding-pasos" role="img" aria-label="Paso <?= (int) $pasoActual ?> de <?= (int) $totalPasos ?>">
  <?php for ($i = 1; $i <= $totalPasos; $i++): ?>
    <span class="pq-onboarding-paso<?= $i < $pasoActual ? ' pq-onboarding-paso-hecho' : ($i === $pasoActual ? ' pq-onboarding-paso-activo' : '') ?>"></span>
  <?php endfor; ?>
</div>
