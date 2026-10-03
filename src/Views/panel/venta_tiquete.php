<?php
use App\Models\Producto;
use App\Models\Venta;

$anulada = (int) $venta['anulada'] === 1;
$puedeAnular = $negocio['rol'] === 'dueno' && Venta::anulableHoy($venta);
?>
<a href="<?= e(base_url('/panel/mostrador')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Mostrador
</a>
<span class="pq-eyebrow pq-eyebrow-tras-volver">Venta de mostrador</span>
<h1 class="pq-h1">Venta #<?= (int) $venta['id'] ?></h1>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php // El tiquete de la venta: el mismo papel de la comanda, listo para el rollo de 80 mm. ?>
<div class="pq-comanda pq-comanda-final pq-comanda-imprimible">
  <article class="pq-comanda-hoja" aria-label="Tiquete de la venta #<?= (int) $venta['id'] ?>">
    <span class="pq-sello<?= $anulada ? ' pq-sello-no' : ' pq-sello-ok' ?>" aria-hidden="true"><?= $anulada ? 'Anulada' : ($venta['metodo'] === 'fiado' ? 'Fiado' : 'Pagada') ?></span>
    <p class="pq-comanda-negocio"><?= e(nombre_publico_sede($negocio)) ?></p>
    <p class="pq-comanda-cabeza">
      <span>Venta #<?= (int) $venta['id'] ?></span>
      <span><?= e(fecha_corta((string) $venta['creado_en'])) ?></span>
    </p>
    <?php foreach ($items as $item): ?>
      <?php $porPeso = (int) $item['por_peso'] === 1; ?>
      <div class="pq-comanda-linea">
        <div class="pq-comanda-fila">
          <span class="pq-comanda-nombre">
            <?php if (!$porPeso): ?><span class="pq-comanda-cantidad"><?= (int) round((float) $item['cantidad']) ?>×</span><?php endif; ?>
            <?= e($item['nombre']) ?>
          </span>
          <span class="pq-plato-guia" aria-hidden="true"></span>
          <span class="pq-comanda-subtotal"><?= pesos((int) $item['subtotal']) ?></span>
        </div>
        <?php if ($porPeso): ?>
          <p class="pq-comanda-nota"><?= e(Producto::cantidadLegible((float) $item['cantidad'], true)) ?> a <?= pesos((int) $item['precio_unitario']) ?> el kilo</p>
        <?php elseif ((float) $item['cantidad'] > 1): ?>
          <p class="pq-comanda-nota"><?= pesos((int) $item['precio_unitario']) ?> c/u</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="pq-comanda-total">
      <span>Total</span>
      <span><?= pesos((int) $venta['total']) ?></span>
    </div>
    <dl class="pq-comanda-datos">
      <div><dt>Pago</dt><dd><?= e(Venta::METODOS[$venta['metodo']] ?? $venta['metodo']) ?></dd></div>
      <?php if ($venta['metodo'] === 'efectivo'): ?>
        <div><dt>Recibido</dt><dd><?= pesos((int) $venta['recibido']) ?></dd></div>
        <div><dt>Vueltas</dt><dd><strong><?= pesos((int) $venta['cambio']) ?></strong></dd></div>
      <?php endif; ?>
      <?php if ($venta['metodo'] === 'fiado'): ?>
        <div><dt>Cliente</dt><dd><?= e((string) ($venta['cliente_nombre'] ?? 'Cliente borrado')) ?></dd></div>
      <?php endif; ?>
      <div><dt>Atendió</dt><dd><?= e((string) ($venta['usuario_nombre'] ?? '—')) ?></dd></div>
      <?php if ($anulada): ?>
        <div class="pq-comanda-dato-nota"><dt>Anulada</dt><dd><?= e((string) ($venta['anulo_nombre'] ?? '—')) ?> · <?= e(fecha_corta((string) $venta['anulada_en'])) ?></dd></div>
      <?php endif; ?>
    </dl>
  </article>
</div>

<div class="pq-detalle-secundarias">
  <a class="pq-btn pq-btn-sello" href="<?= e(base_url('/panel/mostrador')) ?>">Nueva venta</a>
  <button type="button" class="pq-btn pq-btn-ghost" data-imprimir aria-label="Imprimir el tiquete de la venta">Imprimir</button>
</div>
<?php if ($venta['metodo'] === 'fiado' && $venta['cliente_id'] !== null): ?>
  <p class="pq-ayuda pq-mostrador-tiquete-nota"><a href="<?= e(base_url('/panel/fiado/' . (int) $venta['cliente_id'])) ?>">Ver la cuenta de fiado de <?= e((string) $venta['cliente_nombre']) ?></a></p>
<?php endif; ?>

<?php if ($puedeAnular): ?>
  <details class="pq-detalle-mas">
    <summary>Anular esta venta</summary>
    <div class="pq-detalle-mas-cuerpo">
      <p class="pq-ayuda">Devuelve los productos al inventario<?= $venta['metodo'] === 'fiado' ? ', borra el cargo de la cuenta del cliente' : '' ?> y la venta deja de contar en el cierre de caja. Solo se puede el mismo día.</p>
      <form method="post" action="<?= e(base_url('/panel/mostrador/ventas/' . (int) $venta['id'] . '/anular')) ?>" data-confirmar="¿Anular la venta #<?= (int) $venta['id'] ?> por <?= e(pesos((int) $venta['total'])) ?>? No se puede deshacer.">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-boton-peligro">Anular venta</button>
      </form>
    </div>
  </details>
<?php elseif (!$anulada && $negocio['rol'] === 'dueno'): ?>
  <p class="pq-ayuda pq-mostrador-tiquete-nota">Una venta de otro día ya no se anula: haría descuadrar un cierre que ya se contó.</p>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
