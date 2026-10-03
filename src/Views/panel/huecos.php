<?php
/**
 * Llenar huecos del próximo día de atención: a quién ya le toca volver y en qué espacio libre
 * cabe su servicio de siempre. Cada fila dice POR QUÉ (su ritmo) y QUÉ se
 * le ofrece (hora y persona): nada de "probabilidad" sin explicación.
 */
$fechaHuecos = \App\Models\Huecos::proximoDiaDeAtencion($negocio);
$cuando = $fechaHuecos !== null ? \App\Models\Huecos::cuando($fechaHuecos) : 'mañana';
?>
<a href="<?= e(base_url('/panel/copiloto')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Copiloto
</a>

<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow"><?= $fechaHuecos !== null ? e(ucfirst(fecha_larga($fechaHuecos))) : 'Próximos días' ?></span>
    <h1 class="pq-h1">Llena los huecos de tu agenda</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Clientes a los que ya les toca volver y cuyo servicio de siempre cabe en un espacio libre <?= e($cuando) ?>.</p>

<?php if ($huecos === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
    <p>Por ahora no hay a quién ofrecerle un espacio: o la agenda de ese día está llena, o tus clientes todavía no están en su fecha de volver (o no aceptan promociones).</p>
  </div>
<?php else: ?>
  <ul class="pq-huecos-lista">
    <?php foreach ($huecos as $hueco): $cliente = $hueco['cliente']; $atraso = (int) $hueco['dias'] - (int) $hueco['frecuencia']; ?>
      <li class="pq-hueco">
        <span class="pq-hueco-hora pq-mono"><?= e(hora_completa($hueco['hora'])) ?></span>
        <div class="pq-hueco-texto">
          <span class="pq-hueco-nombre"><?= e($cliente['nombre']) ?></span>
          <span class="pq-hueco-servicio"><?= e($hueco['servicio']) ?><?= $hueco['empleado'] !== null ? ' · con ' . e($hueco['empleado']) : '' ?></span>
          <span class="pq-ayuda">
            Viene cada <?= (int) $hueco['frecuencia'] ?> días · van <?= (int) $hueco['dias'] ?>
            <?= $atraso > 0 ? '(' . $atraso . ($atraso === 1 ? ' día' : ' días') . ' de más)' : ($atraso === 0 ? '(hoy le toca)' : '(le toca en ' . -$atraso . (-$atraso === 1 ? ' día' : ' días') . ')') ?>
          </span>
        </div>
        <form method="post" action="<?= e(base_url('/panel/copiloto/huecos/' . (int) $cliente['id'] . '/whatsapp')) ?>">
          <?= csrf_campo() ?>
          <button type="submit" class="pq-btn pq-btn-whatsapp pq-btn-chico">Ofrecerle el espacio</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="pq-ayuda pq-huecos-nota">El mensaje le ofrece esa hora y lleva su enlace para dejar de recibir promociones. Si reserva en los próximos días, se suma a lo recuperado con Veci.</p>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
