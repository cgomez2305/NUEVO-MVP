<?php
$esReservas = $negocio['tipo_negocio'] === 'reservas';
$pasoActual = 1;
$queFoto = $esReservas ? 'tu lista de servicios' : 'tu menú';
// Con catálogo ya armado, esta pantalla es "sumar otra página": lo que se
// lea se agrega, y "Atrás" vuelve a la lista en vez de no existir.
$sumando = $totalCatalogo > 0;
$cosas = $esReservas ? 'servicios' : 'productos';
$volverUrl = $sumando ? '/panel/onboarding/productos' : null;
require __DIR__ . '/_pasos.php';
?>
<main class="pq-onb-cuerpo">
  <?php if ($sumando): ?>
    <h1 class="pq-h1">Suma otra foto</h1>
    <p class="pq-lead pq-onb-bajada">Otra página del menú, la pizarra del día o los <?= $cosas ?> de temporada. Lo que Veci lea se agrega a los <?= (int) $totalCatalogo ?> que ya tienes; no se borra nada.</p>
  <?php else: ?>
    <h1 class="pq-h1">Tómale una foto a <?= e($queFoto) ?></h1>
    <p class="pq-lead pq-onb-bajada">
      <?= $esReservas
        ? 'Una lista de precios, una pizarra o un pantallazo de Instagram. Veci lee los servicios, precios y duración por ti.'
        : 'La carta impresa, la pizarra o un pantallazo de Instagram. Veci lee los productos y precios por ti.' ?>
    </p>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta pq-onb-aviso" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('/panel/onboarding/foto')) ?>" enctype="multipart/form-data" class="pq-onb-form" data-form-foto data-enviando="Subiendo la foto…">
    <?= csrf_campo() ?>

    <label class="pq-subir-foto" for="foto" data-dropzone-foto>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
      <strong><?= $esReservas ? 'Foto de tus servicios' : 'Foto de tu menú' ?></strong>
      <span class="pq-subir-foto-botones">
        <span class="pq-subir-foto-boton pq-subir-foto-boton-principal">Tomar foto</span>
        <span class="pq-subir-foto-boton">Elegir archivo</span>
      </span>
      <span>JPG, PNG o WEBP · la foto normal del celular sirve</span>
      <input id="foto" type="file" name="foto" accept="image/png,image/jpeg,image/webp" required data-input-foto>
    </label>

    <div class="pq-foto-previa" data-previa-foto hidden>
      <img data-previa-foto-img alt="Vista previa de la foto elegida">
      <div class="pq-foto-previa-info">
        <strong data-previa-foto-nombre></strong>
        <span data-previa-foto-tamano></span>
      </div>
      <button type="button" class="pq-foto-previa-cambiar" data-previa-foto-cambiar>Cambiar</button>
    </div>

    <ul class="pq-onb-consejos" aria-label="Para que salga bien">
      <li>Con buena luz y de frente</li>
      <li>Que se lean los precios</li>
      <?php if (!$sumando): ?><li>Si tu menú tiene varias páginas, empieza por una: las otras las sumas en el siguiente paso</li><?php endif; ?>
    </ul>

    <button type="submit" class="pq-btn pq-btn-sello" data-boton-foto>Usar esta foto →</button>
  </form>

  <?php if ($sumando): ?>
    <p class="pq-centro pq-onb-alterno">
      <a class="pq-enlace-sello" href="<?= e(base_url('/panel/onboarding/productos')) ?>">Volver a mi <?= $esReservas ? 'lista' : 'carta' ?> (<?= (int) $totalCatalogo ?> <?= $cosas ?>)</a>
    </p>
  <?php elseif (!empty($negocio['menu_foto'])): ?>
    <a href="<?= e(base_url('/panel/onboarding/productos')) ?>" class="pq-enlace-fila pq-onb-foto-guardada">
      <img src="<?= e(base_url($negocio['menu_foto'])) ?>" alt="" class="pq-onb-foto-mini">
      <span>
        <strong>Seguir con la foto que ya subiste</strong>
        <span class="pq-ayuda">No hace falta subirla otra vez</span>
      </span>
      <svg class="pq-enlace-fila-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </a>
  <?php endif; ?>

  <?php if (!$sumando): ?>
    <?php // Sin menú impreso (o sin ganas de foto) también se puede abrir. ?>
    <p class="pq-centro pq-onb-alterno">
      <a class="pq-enlace-sello" href="<?= e(base_url('/panel/onboarding/productos?a_mano=1')) ?>">No tengo foto: <?= $esReservas ? 'escribo mis servicios' : 'escribo mi carta' ?> a mano</a>
    </p>
  <?php endif; ?>
</main>
