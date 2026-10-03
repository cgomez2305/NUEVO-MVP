<?php
/**
 * Lo que dicen los clientes: promedio, cuántas y las últimas con
 * comentario. Solo aparece con suficientes reseñas reales (ver
 * TiendaController::resenasParaTienda); nunca se rellena.
 *
 * Espera: $resenas (null o ['resumen' => ..., 'lista' => ...]).
 */
$resenas = $resenas ?? null;
if ($resenas === null) {
    return;
}
$pqResumen = $resenas['resumen'];
$pqEstrella = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.1l-5.7 3.2 1.2-6.4-4.7-4.4 6.4-.8L12 2.8Z"/></svg>';
?>
<section class="pq-resenas-tienda" id="resenas" aria-labelledby="pq-titulo-resenas">
  <div class="pq-resenas-cabeza">
    <h2 class="pq-info-titulo" id="pq-titulo-resenas">Lo que dicen los vecinos</h2>
    <p class="pq-resenas-nota">
      <span class="pq-resenas-promedio"><?= e(number_format((float) $pqResumen['promedio'], 1, ',', '')) ?></span>
      <span class="pq-resenas-mini" aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++): ?><span class="<?= $i <= round((float) $pqResumen['promedio']) ? 'pq-resena-llena' : '' ?>"><?= $pqEstrella ?></span><?php endfor; ?></span>
      <span class="pq-ayuda"><?= (int) $pqResumen['total'] ?> reseñas de clientes que compraron</span>
    </p>
  </div>
  <?php if ($resenas['lista'] !== []): ?>
    <ul class="pq-resenas-lista">
      <?php foreach ($resenas['lista'] as $r): ?>
        <li>
          <span class="pq-resenas-mini" aria-label="<?= (int) $r['estrellas'] ?> de 5"><?php for ($i = 1; $i <= 5; $i++): ?><span class="<?= $i <= (int) $r['estrellas'] ? 'pq-resena-llena' : '' ?>"><?= $pqEstrella ?></span><?php endfor; ?></span>
          <p>"<?= e($r['comentario']) ?>"</p>
          <span class="pq-ayuda"><?= e(\App\Models\Resena::nombrePublico((string) $r['cliente_nombre'])) ?> · <?= e(hace_dias(dias_desde((string) $r['respondida_en']))) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
