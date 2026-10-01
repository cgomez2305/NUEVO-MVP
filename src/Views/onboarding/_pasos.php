<?php
/**
 * Indicador de progreso del onboarding — se incluye con $pasoActual,
 * $totalPasos y $pasoNombre ya definidos en el archivo que lo requiere.
 * El texto "Paso X de Y · Nombre" existe porque la barra segmentada sola
 * no dice cuánto falta ni en qué paso se está parado.
 *
 * También muestra aquí el aviso "Termina de configurar tu negocio" que
 * deja el login cuando devuelve a alguien a un onboarding sin terminar:
 * este parcial se incluye en los 5 pasos, así que es el único lugar
 * donde hace falta leerlo para que se vea sin importar en cuál quedó.
 */
$pqOnboardingOk = flash_obtener('ok');
?>
<?php if ($pqOnboardingOk !== null): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-bottom: 16px"><?= e($pqOnboardingOk) ?></div>
<?php endif; ?>
<div class="pq-onboarding-progreso">
  <span class="pq-onboarding-progreso-texto">Paso <?= (int) $pasoActual ?> de <?= (int) $totalPasos ?> · <?= e($pasoNombre) ?></span>
  <div class="pq-onboarding-pasos" role="img" aria-label="Paso <?= (int) $pasoActual ?> de <?= (int) $totalPasos ?>: <?= e($pasoNombre) ?>">
    <?php for ($i = 1; $i <= $totalPasos; $i++): ?>
      <span class="pq-onboarding-paso<?= $i < $pasoActual ? ' pq-onboarding-paso-hecho' : ($i === $pasoActual ? ' pq-onboarding-paso-activo' : '') ?>"></span>
    <?php endfor; ?>
  </div>
</div>
