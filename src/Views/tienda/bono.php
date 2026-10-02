<?php
use App\Models\Bono;

$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ir a la tienda';
require __DIR__ . '/_cabecera_corta.php';

$estado = Bono::estado($bono);
$quedan = max(0, (int) $bono['sesiones_total'] - (int) $bono['usadas']);
$total = (int) $bono['sesiones_total'];
$columnas = $total <= 6 ? $total : (int) ceil($total / 2);
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Tu bono de <?= e(mb_strtolower((string) $bono['nombre_servicio'])) ?></h1>
  <p class="pq-pagina-bajada">
    <?php if ($estado === 'agotado'): ?>
      Ya usaste todas las sesiones. ¡Gracias por venir!
    <?php elseif ($estado === 'vencido'): ?>
      Este bono venció el <?= e(fecha_larga((string) $bono['vence_en'])) ?>. Escríbele al negocio si te quedó alguna pendiente.
    <?php else: ?>
      <?= $quedan === 1 ? 'Te queda 1 sesión' : 'Te quedan ' . $quedan . ' sesiones' ?><?= !empty($bono['vence_en']) ? ', hasta el ' . e(fecha_larga((string) $bono['vence_en'])) : '' ?>. Se descuentan solas al reservar con tu WhatsApp.
    <?php endif; ?>
  </p>

  <?php // Las sesiones como casillas: las usadas llevan el sello del negocio. ?>
  <section class="pq-tarjeta-sellos pq-bono-tarjeta" aria-label="<?= (int) $bono['usadas'] ?> de <?= $total ?> sesiones usadas">
    <div class="pq-tarjeta-sellos-cabeza">
      <span class="pq-tarjeta-sellos-titulo"><?= e($bono['cliente_nombre']) ?></span>
      <span class="pq-tarjeta-sellos-cuenta"><?= (int) $bono['usadas'] ?>/<?= $total ?> usadas</span>
    </div>
    <ol class="pq-casillas-sello" style="--columnas: <?= $columnas ?>" aria-hidden="true">
      <?php for ($i = 1; $i <= $total; $i++): ?>
        <?php $usada = $i <= (int) $bono['usadas']; ?>
        <li class="pq-casilla-sello<?= $usada ? ' pq-casilla-sello-puesta' : '' ?>" style="--giro: <?= (($i * 37) % 17) - 8 ?>deg">
          <?php if ($usada): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.2 4.2L19 7"/></svg>
          <?php else: ?>
            <span><?= $i ?></span>
          <?php endif; ?>
        </li>
      <?php endfor; ?>
    </ol>
  </section>

  <?php if ($estado === 'activo' && !empty($bono['servicio_id'])): ?>
    <a class="pq-btn pq-btn-oscuro pq-bono-reservar" href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . (int) $bono['servicio_id'])) ?>">Reservar mi próxima sesión</a>
  <?php endif; ?>
</div>
