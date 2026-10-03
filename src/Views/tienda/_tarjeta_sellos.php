<?php
/**
 * Tarjeta de sellos: una casilla por compra y la última con el premio.
 * Se usa en las confirmaciones de la tienda y como vista previa en el
 * panel (/panel/fidelidad), por eso solo lee variables con prefijo.
 *
 * Espera: $pqSellos (int, disponibles), $pqMeta (int), $pqPremio (string),
 * opcional $pqNuevo (bool: la última casilla puesta cae con animación,
 * "este pedido te sumó un sello") y $pqTitulo.
 */
$pqSellos = max(0, (int) $pqSellos);
$pqMeta = max(1, (int) $pqMeta);
$pqNuevo = !empty($pqNuevo);
$pqPuestos = min($pqSellos, $pqMeta);
$pqCompleta = $pqSellos >= $pqMeta;
// Hasta 6 casillas en una fila; más, en dos filas parejas.
$pqColumnas = $pqMeta <= 6 ? $pqMeta : (int) ceil($pqMeta / 2);
?>
<section class="pq-tarjeta-sellos<?= $pqCompleta ? ' pq-tarjeta-sellos-completa' : '' ?>" aria-label="Tarjeta de sellos: <?= $pqPuestos ?> de <?= $pqMeta ?>">
  <div class="pq-tarjeta-sellos-cabeza">
    <span class="pq-tarjeta-sellos-titulo"><?= e($pqTitulo ?? 'Tu tarjeta de sellos') ?></span>
    <span class="pq-tarjeta-sellos-cuenta"><?= $pqPuestos ?>/<?= $pqMeta ?></span>
  </div>
  <ol class="pq-casillas-sello" style="--columnas: <?= $pqColumnas ?>" aria-hidden="true">
    <?php for ($i = 1; $i <= $pqMeta; $i++): ?>
      <?php
      $pqPuesto = $i <= $pqPuestos;
      $pqEsPremio = $i === $pqMeta;
      // Cada sello cae un poco torcido, como uno de caucho puesto a mano;
      // el ángulo sale de la posición para que no "baile" al recargar.
      $pqGiro = (($i * 37) % 17) - 8;
      $pqClases = 'pq-casilla-sello'
          . ($pqPuesto ? ' pq-casilla-sello-puesta' : '')
          . ($pqEsPremio ? ' pq-casilla-sello-premio' : '')
          . ($pqNuevo && $pqPuesto && $i === $pqPuestos ? ' pq-casilla-sello-nueva' : '');
      ?>
      <li class="<?= $pqClases ?>" style="--giro: <?= $pqGiro ?>deg">
        <?php if ($pqPuesto && !$pqEsPremio): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.2 4.2L19 7"/></svg>
        <?php elseif ($pqEsPremio): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="9" width="17" height="11.5" rx="1.5"/><path d="M2.5 9h19V6.5h-19V9ZM12 6.5v14"/><path d="M12 6.5C10.5 3 7 3 7 5s3 1.5 5 1.5ZM12 6.5C13.5 3 17 3 17 5s-3 1.5-5 1.5Z"/></svg>
        <?php else: ?>
          <span><?= $i ?></span>
        <?php endif; ?>
      </li>
    <?php endfor; ?>
  </ol>
  <p class="pq-tarjeta-sellos-texto">
    <?php if ($pqCompleta): ?>
      ¡Completaste la tarjeta! Te toca <strong><?= e($pqPremio) ?></strong>: pídelo en tu próxima compra.
    <?php elseif ($pqPuestos === 0): ?>
      Cada compra suma un sello. Con <?= $pqMeta ?> te llevas <strong><?= e($pqPremio) ?></strong>.
    <?php else: ?>
      <?= $pqMeta - $pqPuestos === 1 ? 'Te falta 1 sello' : 'Te faltan ' . ($pqMeta - $pqPuestos) . ' sellos' ?> para <strong><?= e($pqPremio) ?></strong>.
    <?php endif; ?>
  </p>
</section>
