<?php
/**
 * Modo "leer" del paso del catálogo: la foto subida y el botón que la
 * manda a leer. Sirve para la primera foto y para sumar otra página a un
 * catálogo que ya tiene ítems (lo leído se suma, no reemplaza).
 *
 * Espera: $negocio, $totalItems (int), $cosa ('producto'|'servicio'),
 * $queLee ('tu menú'|'tus servicios'), $error (?string).
 */
$sumando = $totalItems > 0;
?>
<?php if ($sumando): ?>
  <h1 class="pq-h1">Leer la otra foto</h1>
  <p class="pq-lead pq-onb-bajada">Lo que encuentre se suma a los <?= (int) $totalItems ?> <?= $cosa ?>s que ya tienes. No se repite ni se borra nada.</p>
<?php else: ?>
  <h1 class="pq-h1">Veci va a leer <?= e($queLee) ?></h1>
  <p class="pq-lead pq-onb-bajada">Encuentra <?= $cosa === 'servicio' ? 'servicios, precios y duración' : 'productos y precios' ?> en tu foto y te arma la lista. Tú solo revisas.</p>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-onb-aviso" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php
// Mientras el servidor lee la foto (puede tardar unos segundos), una
// línea la recorre de arriba abajo: dice "estoy leyendo", no "se colgó".
?>
<form method="post" action="<?= e(base_url('/panel/onboarding/analizar')) ?>" class="pq-onb-leer" data-enviando="Leyendo <?= e($queLee) ?>…">
  <?= csrf_campo() ?>
  <div class="pq-onb-escaner">
    <img src="<?= e(base_url($negocio['menu_foto'])) ?>" alt="La foto de <?= e($queLee) ?>">
    <span class="pq-onb-escaner-linea" aria-hidden="true"></span>
  </div>
  <button type="submit" class="pq-btn pq-btn-sello"><?= $sumando ? 'Leer y sumar' : 'Leer ' . e(str_replace('tu ', 'mi ', str_replace('tus ', 'mis ', $queLee))) ?></button>
  <p class="pq-ayuda pq-centro">Tarda hasta un minuto si la carta es larga. No cierres esta pantalla.</p>
</form>

<p class="pq-centro pq-onb-alterno">
  <?php if ($sumando): ?>
    <a class="pq-enlace-sello" href="<?= e(base_url('/panel/onboarding/productos?omitir_foto=1')) ?>">Mejor no: volver a mi lista</a>
  <?php else: ?>
    <a class="pq-enlace-sello" href="<?= e(base_url('/panel/onboarding/productos?a_mano=1')) ?>">Prefiero escribirla yo</a>
  <?php endif; ?>
</p>
