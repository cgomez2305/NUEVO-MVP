<?php
/**
 * Líneas de ajuste de la comanda del carrito, entre los productos y el
 * total: subtotal y cupón (y, cuando exista, domicilio). Lo pinta
 * carrito.php y lo vuelve a mandar TiendaController::responderCarritoJson
 * cuando cambian las cantidades, para que el JS no repita las reglas.
 *
 * Espera: $negocio, $carrito (de resumenCarrito()).
 */
$pqCupon = $carrito['cupon'] ?? null;
?>
<?php if ($pqCupon !== null): ?>
  <div class="pq-comanda-ajuste">
    <span>Subtotal</span>
    <span class="pq-plato-guia" aria-hidden="true"></span>
    <span><?= pesos((int) $carrito['subtotal']) ?></span>
  </div>
  <div class="pq-comanda-ajuste <?= $pqCupon['ok'] ? 'pq-comanda-ajuste-cupon' : 'pq-comanda-ajuste-aviso' ?>">
    <span>
      Cupón <strong><?= e($pqCupon['codigo']) ?></strong><?= $pqCupon['ok'] && $pqCupon['etiqueta'] !== '' ? ' · ' . e($pqCupon['etiqueta']) : '' ?>
    </span>
    <span class="pq-plato-guia" aria-hidden="true"></span>
    <span><?= $pqCupon['ok'] ? '−' . pesos((int) $carrito['descuento']) : 'No aplica' ?></span>
  </div>
  <?php if (!$pqCupon['ok']): ?>
    <p class="pq-comanda-nota pq-comanda-nota-aviso"><?= e($pqCupon['mensaje']) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/cupon/quitar')) ?>" class="pq-comanda-quitar-cupon">
    <?= csrf_campo() ?>
    <button type="submit">Quitar cupón</button>
  </form>
<?php endif; ?>
