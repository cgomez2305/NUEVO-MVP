<?php
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ir a la tienda';
require __DIR__ . '/_cabecera_corta.php';

$respondida = $resena['respondida_en'] !== null;
$nombreNegocio = nombre_publico_sede($negocio);
$estrellaSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.1l-5.7 3.2 1.2-6.4-4.7-4.4 6.4-.8L12 2.8Z"/></svg>';
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <?php if ($respondida): ?>
    <h1 class="pq-pagina-titulo">¡Gracias, <?= e(explode(' ', trim((string) $resena['cliente_nombre']))[0]) ?>!</h1>
    <p class="pq-pagina-bajada">Tu opinión ya le llegó a <?= e($nombreNegocio) ?>.</p>
    <p class="pq-resena-estrellas-fijas" aria-label="Calificaste con <?= (int) $resena['estrellas'] ?> de 5 estrellas">
      <?php for ($i = 1; $i <= 5; $i++): ?><span class="<?= $i <= (int) $resena['estrellas'] ? 'pq-resena-llena' : '' ?>"><?= $estrellaSvg ?></span><?php endfor; ?>
    </p>
    <?php if (!empty($resena['comentario'])): ?>
      <blockquote class="pq-resena-cita">"<?= e($resena['comentario']) ?>"</blockquote>
    <?php endif; ?>
    <a class="pq-btn pq-btn-oscuro pq-resena-volver" href="<?= e(base_url('/t/' . $negocio['slug'])) ?>">Volver a la tienda</a>
  <?php else: ?>
    <h1 class="pq-pagina-titulo">¿Cómo te fue con <?= e($que) ?>?</h1>
    <p class="pq-pagina-bajada">Tu calificación ayuda a <?= e($nombreNegocio) ?> a mejorar y a otros vecinos a decidirse. Toma 10 segundos.</p>

    <?php if (!empty($error)): ?>
      <div class="pq-alerta" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('/r/' . $resena['token'])) ?>" class="pq-resena-form">
      <?= csrf_campo() ?>
      <?php
      // Las estrellas son radios de verdad (funciona sin JS y con teclado);
      // van al revés en el HTML para que con :has() se pinten "hasta aquí".
      ?>
      <fieldset class="pq-resena-estrellas">
        <legend class="pq-sr-solo">Calificación de 1 a 5 estrellas</legend>
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" name="estrellas" id="estrella-<?= $i ?>" value="<?= $i ?>" required>
          <label for="estrella-<?= $i ?>" title="<?= $i ?> de 5"><?= $estrellaSvg ?><span class="pq-sr-solo"><?= $i ?> estrella<?= $i === 1 ? '' : 's' ?></span></label>
        <?php endfor; ?>
      </fieldset>
      <p class="pq-resena-escala" aria-hidden="true"><span>Muy mal</span><span>Excelente</span></p>

      <div class="pq-campo">
        <label class="pq-label" for="comentario">Cuéntanos más <span class="pq-ayuda">(opcional)</span></label>
        <textarea class="pq-input" id="comentario" name="comentario" rows="3" maxlength="400" placeholder="¿Qué fue lo mejor? ¿Qué mejorarías?"></textarea>
        <span class="pq-ayuda">Se publica con tu nombre y la inicial de tu apellido.</span>
      </div>
      <button type="submit" class="pq-btn pq-btn-oscuro pq-resena-enviar">Enviar calificación</button>
    </form>
  <?php endif; ?>
</div>
