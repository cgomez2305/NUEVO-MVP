<div class="pq-tienda-header">
  <div class="pq-tienda-logo" style="background: <?= e($negocio['color_marca']) ?>">
    <?= e($negocio['inicial'] ?? mb_strtoupper(mb_substr($negocio['nombre'], 0, 1))) ?>
  </div>
  <div>
    <h1 class="pq-tienda-nombre"><?= e($negocio['nombre']) ?></h1>
    <?php if (!empty($negocio['descripcion'])): ?>
      <span class="pq-tienda-desc"><?= e($negocio['descripcion']) ?></span>
    <?php endif; ?>
  </div>
</div>

<div class="pq-content-tienda">
  <?php if ($productos === []): ?>
    <p class="pq-ayuda" style="margin-top: 24px">Este negocio todavía no tiene productos publicados.</p>
  <?php else: ?>
    <div class="pq-productos">
      <?php foreach ($productos as $i => $producto): ?>
        <?php $agotado = (int) $producto['agotado'] === 1; ?>
        <div class="pq-producto<?= $agotado ? ' pq-producto-agotado' : '' ?>" style="--i: <?= (int) $i ?>">
          <div class="pq-producto-foto" style="background: <?= e($producto['color']) ?>"></div>
          <span class="pq-producto-nombre"><?= e($producto['nombre']) ?></span>
          <div class="pq-producto-fila">
            <span class="pq-producto-precio"><?= pesos((int) $producto['precio']) ?></span>
            <?php if ($agotado): ?>
              <span class="pq-chip pq-chip-cancelado">Agotado</span>
            <?php else: ?>
              <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/agregar')) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                <button type="submit" class="pq-add" aria-label="Agregar <?= e($producto['nombre']) ?>">+</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($carrito['cantidad'] > 0): ?>
  <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito')) ?>" class="pq-barra-carrito">
    <span style="font-size: 14px; font-weight: 600"><?= $carrito['cantidad'] ?> producto<?= $carrito['cantidad'] === 1 ? '' : 's' ?> · <?= pesos($carrito['total']) ?></span>
    <span class="pq-mono" style="font-size: 12px; color: var(--mostaza)">Ver carrito →</span>
  </a>
<?php endif; ?>
