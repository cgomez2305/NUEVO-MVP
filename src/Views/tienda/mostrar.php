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
    <?php
    // Se agrupa por categoría preservando el orden en que aparece cada
    // producto (para no reordenar el catálogo del dueño). Si todo el menú
    // sigue en la categoría "General" de siempre, no tiene sentido imprimir
    // ese título una sola vez, así que solo se muestran cuando hay 2+.
    $porCategoria = [];
    foreach ($productos as $producto) {
        $porCategoria[$producto['categoria']][] = $producto;
    }
    $mostrarTitulos = count($porCategoria) > 1;
    $contador = 0;
    ?>
    <?php foreach ($porCategoria as $categoria => $items): ?>
      <?php if ($mostrarTitulos): ?>
        <h2 class="pq-menu-categoria"><?= e($categoria) ?></h2>
      <?php endif; ?>
      <div class="pq-productos">
        <?php foreach ($items as $producto): ?>
          <?php $agotado = (int) $producto['agotado'] === 1; ?>
          <div class="pq-producto<?= $agotado ? ' pq-producto-agotado' : '' ?>" style="--i: <?= $contador++ % 6 ?>">
            <div class="pq-producto-foto" style="background: <?= e($producto['color']) ?>">
              <?php if (!empty($producto['imagen'])): ?>
                <img src="<?= e(base_url($producto['imagen'])) ?>" alt="<?= e($producto['nombre']) ?>" loading="lazy">
              <?php endif; ?>
            </div>
            <span class="pq-producto-nombre"><?= e($producto['nombre']) ?></span>
            <?php if (!empty($producto['descripcion'])): ?>
              <span class="pq-producto-desc"><?= e($producto['descripcion']) ?></span>
            <?php endif; ?>
            <div class="pq-producto-fila">
              <span class="pq-producto-precio"><?= pesos((int) $producto['precio']) ?></span>
              <?php if ($agotado): ?>
                <span class="pq-chip pq-chip-cancelado">Agotado</span>
              <?php else: ?>
                <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/agregar')) ?>" data-carrito-form="agregar">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                  <button type="submit" class="pq-add" aria-label="Agregar <?= e($producto['nombre']) ?>">+</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<a href="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito')) ?>" class="pq-barra-carrito<?= $carrito['cantidad'] > 0 ? '' : ' pq-barra-carrito-oculta' ?>" id="pq-barra-carrito">
  <span style="font-size: 14px; font-weight: 600" id="pq-barra-carrito-resumen"><?= $carrito['cantidad'] ?> producto<?= $carrito['cantidad'] === 1 ? '' : 's' ?> · <?= pesos($carrito['total']) ?></span>
  <span class="pq-mono" style="font-size: 12px; color: var(--mostaza)">Ver carrito →</span>
</a>
