<?php
use App\Models\Producto;
?>
<a href="<?= e(base_url('/panel/compras')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Compras
</a>
<span class="pq-eyebrow pq-eyebrow-tras-volver">Compra #<?= (int) $compra['id'] ?></span>
<h1 class="pq-h1"><?= e($compra['proveedor']) ?></h1>

<?php // La remisión del proveedor, en el mismo papel de la comanda: qué llegó y cómo quedó el inventario. ?>
<div class="pq-comanda pq-comanda-final pq-comanda-imprimible">
  <article class="pq-comanda-hoja" aria-label="Compra #<?= (int) $compra['id'] ?>">
    <p class="pq-comanda-negocio"><?= e(nombre_publico_sede($negocio)) ?></p>
    <p class="pq-comanda-cabeza">
      <span>Compra #<?= (int) $compra['id'] ?></span>
      <span><?= e(fecha_corta((string) $compra['creado_en'])) ?></span>
    </p>
    <?php foreach ($items as $item): ?>
      <?php
      $porPeso = (int) $item['por_peso'] === 1;
      $cantidad = (float) $item['cantidad'];
      $entro = $porPeso ? (int) round($cantidad * Producto::GRAMOS_POR_KILO) : (int) $cantidad;
      $legible = static fn (int $stock): string => $porPeso ? Producto::gramosLegibles($stock) : (string) $stock;
      ?>
      <div class="pq-comanda-linea">
        <div class="pq-comanda-fila">
          <span class="pq-comanda-nombre"><span class="pq-comanda-cantidad"><?= e(Producto::cantidadLegible($cantidad, $porPeso)) ?><?= $porPeso ? '' : '×' ?></span> <?= e($item['nombre']) ?></span>
          <span class="pq-plato-guia" aria-hidden="true"></span>
          <span class="pq-comanda-subtotal"><?= pesos((int) $item['subtotal']) ?></span>
        </div>
        <p class="pq-comanda-nota">
          <?= pesos((int) $item['costo_unitario']) ?> <?= $porPeso ? 'el kilo' : 'c/u' ?> ·
          <?= $item['stock_antes'] === null
              ? 'empezó a contarse con ' . e($legible($entro))
              : 'inventario ' . e($legible((int) $item['stock_antes'])) . ' → ' . e($legible(max(0, (int) $item['stock_antes']) + $entro)) ?>
        </p>
      </div>
    <?php endforeach; ?>
    <div class="pq-comanda-total">
      <span>Total</span>
      <span><?= pesos((int) $compra['total']) ?></span>
    </div>
    <dl class="pq-comanda-datos">
      <div><dt>Registró</dt><dd><?= e((string) ($compra['usuario_nombre'] ?? '—')) ?></dd></div>
    </dl>
  </article>
</div>

<div class="pq-detalle-secundarias">
  <a class="pq-btn pq-btn-sello" href="<?= e(base_url('/panel/compras')) ?>">Nueva compra</a>
  <button type="button" class="pq-btn pq-btn-ghost" data-imprimir aria-label="Imprimir la compra">Imprimir</button>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
