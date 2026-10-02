<?php
use App\Models\Resena;

$esReservas = ($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas';
$estrella = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.1l-5.7 3.2 1.2-6.4-4.7-4.4 6.4-.8L12 2.8Z"/></svg>';
$mini = static function (int $n) use ($estrella): string {
    $html = '<span class="pq-panel-estrellas" aria-label="' . $n . ' de 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="' . ($i <= $n ? 'pq-panel-estrella-llena' : '') . '">' . $estrella . '</span>';
    }

    return $html . '</span>';
};
$maximo = max(1, max($resumen['por_estrella']));
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Crecimiento</span>
    <h1 class="pq-h1">Reseñas</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Solo opina quien te compró: la reseña se pide desde un <?= $esReservas ? 'cita atendida' : 'pedido entregado' ?> con un enlace de un solo uso.</p>

<?php if ($resumen['total'] === 0): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5l2.5 5.2 5.7.7-4.2 3.9 1.1 5.6L12 16.1l-5.1 2.8 1.1-5.6-4.2-3.9 5.7-.7L12 3.5Z"/></svg>
    <p><strong>Todavía no tienes reseñas.</strong><br>
      <?= $esReservas ? 'En la agenda, cuando marques una cita como atendida' : 'Abre un pedido entregado' ?> y toca <strong>Pedir reseña</strong>: se abre WhatsApp con el mensaje listo.
      <?php if ($pendientes > 0): ?><br><?= $pendientes ?> pedida<?= $pendientes === 1 ? '' : 's' ?> esperando respuesta.<?php endif; ?>
    </p>
  </div>
<?php else: ?>
  <?php // El promedio grande y la distribución: se ve de un vistazo si las malas son pocas o un patrón. ?>
  <section class="pq-resenas-resumen" aria-label="Resumen de calificaciones">
    <div class="pq-resenas-resumen-nota">
      <span class="pq-resenas-resumen-promedio"><?= e(number_format((float) $resumen['promedio'], 1, ',', '')) ?></span>
      <?= $mini((int) round((float) $resumen['promedio'])) ?>
      <span class="pq-ayuda"><?= (int) $resumen['total'] ?> reseña<?= $resumen['total'] === 1 ? '' : 's' ?><?= $pendientes > 0 ? ' · ' . $pendientes . ' esperando respuesta' : '' ?></span>
      <?php if ($resumen['total'] < Resena::MINIMO_PARA_MOSTRAR): ?>
        <span class="pq-ayuda pq-resenas-resumen-aviso">Tu tienda las muestra desde <?= Resena::MINIMO_PARA_MOSTRAR ?> reseñas.</span>
      <?php endif; ?>
    </div>
    <ol class="pq-resenas-barras">
      <?php foreach ($resumen['por_estrella'] as $n => $cuantas): ?>
        <li style="--parte: <?= round($cuantas / $maximo, 3) ?>">
          <span><?= $n ?> <?= $estrella ?></span>
          <span class="pq-resenas-barra" aria-hidden="true"></span>
          <span class="pq-mono"><?= $cuantas ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>

  <ul class="pq-admin-tarjeta pq-resenas-panel-lista" role="list">
    <?php foreach ($resenas as $r): ?>
      <?php $oculto = (int) $r['comentario_oculto'] === 1; ?>
      <li>
        <div class="pq-resenas-panel-fila">
          <?= $mini((int) $r['estrellas']) ?>
          <span class="pq-ayuda"><?= e(hace_dias(dias_desde((string) $r['respondida_en']))) ?></span>
        </div>
        <?php if (!empty($r['comentario'])): ?>
          <p class="pq-resenas-panel-texto<?= $oculto ? ' pq-resenas-panel-texto-oculto' : '' ?>">"<?= e($r['comentario']) ?>"</p>
          <?php if ($oculto): ?><span class="pq-chip pq-chip-pendiente">Comentario oculto en la tienda</span><?php endif; ?>
        <?php endif; ?>
        <div class="pq-resenas-panel-fila">
          <span class="pq-ayuda"><?= e($r['cliente_nombre']) ?> · <?= $r['pedido_id'] !== null ? 'pedido #' . (int) $r['pedido_id'] : 'cita' ?></span>
          <span class="pq-resenas-panel-acciones">
            <a class="pq-enlace-boton" href="https://wa.me/57<?= e(preg_replace('/\D+/', '', (string) $r['cliente_telefono'])) ?>" target="_blank" rel="noopener">Escribirle</a>
            <?php if (!empty($r['comentario'])): ?>
              <form method="post" action="<?= e(base_url('/panel/resenas/' . $r['id'] . '/comentario')) ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-enlace-boton"><?= $oculto ? 'Mostrar comentario' : 'Ocultar comentario' ?></button>
              </form>
            <?php endif; ?>
          </span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="pq-ayuda pq-resenas-panel-nota">Ocultar quita el texto de la tienda (insultos, datos personales), pero sus estrellas siguen contando: el promedio no se maquilla.</p>
<?php endif; ?>
