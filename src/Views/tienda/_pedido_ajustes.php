<?php
/**
 * Líneas de ajuste de un pedido ya hecho (comanda de la confirmación y del
 * panel): subtotal y descuento, para que el total cuadre a la vista.
 * Espera: $pedido (con total, descuento, cupon_codigo).
 */
$pqDescuento = (int) ($pedido['descuento'] ?? 0);
?>
<?php if ($pqDescuento > 0): ?>
  <div class="pq-comanda-ajuste">
    <span>Subtotal</span>
    <span class="pq-plato-guia" aria-hidden="true"></span>
    <span><?= pesos((int) $pedido['total'] + $pqDescuento) ?></span>
  </div>
  <div class="pq-comanda-ajuste pq-comanda-ajuste-cupon">
    <span><?= !empty($pedido['cupon_codigo']) ? 'Cupón <strong>' . e($pedido['cupon_codigo']) . '</strong>' : 'Descuento' ?></span>
    <span class="pq-plato-guia" aria-hidden="true"></span>
    <span>−<?= pesos($pqDescuento) ?></span>
  </div>
<?php endif; ?>
