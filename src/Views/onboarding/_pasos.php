<?php
/**
 * Indicador de progreso del onboarding — se incluye con $pasoActual,
 * $totalPasos y $pasoNombre ya definidos en el archivo que lo requiere.
 * El texto "Paso X de Y · Nombre" existe porque la barra segmentada sola
 * no dice cuánto falta ni en qué paso se está parado.
 *
 * También muestra aquí el aviso "Termina de configurar tu negocio" que
 * deja el login cuando devuelve a alguien a un onboarding sin terminar, y
 * el aviso de que el análisis con IA usó el catálogo de ejemplo por haber
 * llegado al límite del plan Gratis (ver OnboardingController::analizar):
 * este parcial se incluye en los 5 pasos, así que es el único lugar donde
 * hace falta leerlos para que se vean sin importar en cuál paso quedó.
 */
$pqOnboardingOk = flash_obtener('ok');
$pqOnboardingAviso = flash_obtener('aviso');
?>
<?php if ($pqOnboardingOk !== null): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-bottom: 16px"><?= e($pqOnboardingOk) ?></div>
<?php endif; ?>
<?php if ($pqOnboardingAviso !== null): ?>
  <div class="pq-alerta pq-alerta-aviso" style="margin-bottom: 16px"><?= e($pqOnboardingAviso) ?></div>
<?php endif; ?>
<div class="pq-onboarding-progreso">
  <span class="pq-onboarding-progreso-texto">Paso <?= (int) $pasoActual ?> de <?= (int) $totalPasos ?> · <?= e($pasoNombre) ?></span>
  <div class="pq-onboarding-pasos" role="img" aria-label="Paso <?= (int) $pasoActual ?> de <?= (int) $totalPasos ?>: <?= e($pasoNombre) ?>">
    <?php for ($i = 1; $i <= $totalPasos; $i++): ?>
      <span class="pq-onboarding-paso<?= $i < $pasoActual ? ' pq-onboarding-paso-hecho' : ($i === $pasoActual ? ' pq-onboarding-paso-activo' : '') ?>"></span>
    <?php endfor; ?>
  </div>
</div>
