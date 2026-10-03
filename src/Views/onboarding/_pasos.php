<?php
/**
 * Cabecera de cada paso del onboarding: logo, progreso con el nombre de
 * cada paso y "Atrás". Se incluye con $pasoActual definido (y $volverUrl
 * opcional); el número de pasos sale del tipo de negocio.
 *
 * También muestra aquí el aviso "Termina de configurar tu negocio" que
 * deja el login cuando devuelve a alguien a un onboarding sin terminar, y
 * el aviso de que el análisis con IA usó el catálogo de ejemplo por haber
 * llegado al límite del plan Gratis (ver OnboardingController::analizar):
 * este parcial se incluye en todos los pasos, así que es el único lugar
 * donde hace falta leerlos para que se vean sin importar en cuál quedó.
 */
$pqOnboardingOk = flash_obtener('ok');
$pqOnboardingAviso = flash_obtener('aviso');
$pqNombresPasos = ($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas'
    ? ['Foto', 'Servicios', 'Horario', 'Abrir']
    : ['Foto', 'Catálogo', 'Abrir'];
$totalPasos = count($pqNombresPasos);
$volverUrl = $volverUrl ?? null;
?>
<header class="pq-onb-cabeza">
  <div class="pq-onb-cabeza-fila">
    <?php if ($volverUrl !== null): ?>
      <a href="<?= e(base_url($volverUrl)) ?>" class="pq-volver-panel pq-onb-volver">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Atrás
      </a>
    <?php else: ?>
      <img src="<?= e(base_url('assets/img/logo-veci-lockup-transparente.png')) ?>" alt="Veci" class="pq-onb-logo">
    <?php endif; ?>
    <span class="pq-onb-cuenta">Paso <?= (int) $pasoActual ?> de <?= $totalPasos ?></span>
  </div>
  <ol class="pq-onb-pasos" aria-label="Progreso: paso <?= (int) $pasoActual ?> de <?= $totalPasos ?>">
    <?php foreach ($pqNombresPasos as $i => $nombrePaso): ?>
      <?php $n = $i + 1; ?>
      <li class="pq-onb-paso<?= $n < $pasoActual ? ' pq-onb-paso-hecho' : ($n === (int) $pasoActual ? ' pq-onb-paso-activo' : '') ?>"<?= $n === (int) $pasoActual ? ' aria-current="step"' : '' ?>>
        <span class="pq-onb-paso-barra" aria-hidden="true"></span>
        <span class="pq-onb-paso-nombre"><?= e($nombrePaso) ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
</header>
<?php if ($pqOnboardingOk !== null): ?>
  <div class="pq-alerta pq-alerta-ok pq-onb-aviso"><?= e($pqOnboardingOk) ?></div>
<?php endif; ?>
<?php if ($pqOnboardingAviso !== null): ?>
  <div class="pq-alerta pq-alerta-aviso pq-onb-aviso"><?= e($pqOnboardingAviso) ?></div>
<?php endif; ?>
