<?php
/**
 * Líneas de ajuste de un pedido ya hecho (comanda de la confirmación y del
 * panel): subtotal, descuento y domicilio, para que el total cuadre a la vista.
 * Espera: $pedido (con total, descuento, cupon_codigo, costo_domicilio, zona_domicilio).
 */
$pqDescuento = (int) ($pedido['descuento'] ?? 0);
$pqDomicilio = (int) ($pedido['costo_domicilio'] ?? 0);
$pqZona = (string) ($pedido['zona_domicilio'] ?? '');
?>
<?php if ($pqDescuento > 0 || $pqDomicilio > 0 || $pqZona !== ''): ?>
  <div class="pq-comanda-ajuste">
    <span>Subtotal</span>
    <span class="pq-plato-guia" aria-hidden="true"></span>
    <span><?= pesos((int) $pedido['total'] + $pqDescuento - $pqDomicilio) ?></span>
  </div>
<?php endif; ?>
<?php if ($pqDescuento > 0): ?>
  <div class="pq-comanda-ajuste pq-comanda-ajuste-cupon">
    <span><?= !empty($pedido['cupon_codigo']) ? 'Cupón <strong>' . e($pedido['cupon_codigo']) . '</strong>' : 'Descuento' ?></span>
    <span class="pq-plato-guia" aria-hidden="true"></span>
    <span>−<?= pesos($pqDescuento) ?></span>
  </div>
<?php endif; ?>
<?php if ($pqDomicilio > 0 || $pqZona !== ''): ?>
  <div class="pq-comanda-ajuste">
    <span>Domicilio<?= $pqZona !== '' ? ' · ' . e($pqZona) : '' ?></span>
    <span class="pq-plato-guia" aria-hidden="true"></span>
    <span><?= $pqDomicilio > 0 ? pesos($pqDomicilio) : 'Gratis' ?></span>
  </div>
<?php endif; ?>
