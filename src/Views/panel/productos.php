<div class="pq-catalogo-cabecera">
  <div>
    <span class="pq-eyebrow">Tu menú</span>
    <h1 class="pq-h1">Productos</h1>
    <p class="pq-lead">Gestiona lo que vendes en tu tienda</p>
  </div>
  <a href="<?= e(base_url('/panel/productos/nuevo')) ?>" class="pq-btn pq-btn-sello pq-btn-chico">+ Nuevo producto</a>
</div>

<form method="get" action="<?= e(base_url('/panel/productos')) ?>" class="pq-filtro-barra">
  <input class="pq-input" type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar producto...">
  <select class="pq-select" name="categoria" data-autoenviar>
    <option value="">Todas las categorías</option>
    <?php foreach ($categorias as $cat): ?>
      <option value="<?= e($cat) ?>" <?= $filtroCategoria === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="pq-select" name="disponibilidad" data-autoenviar>
    <option value="">Todos</option>
    <option value="disponibles" <?= $disponibilidad === 'disponibles' ? 'selected' : '' ?>>Disponibles</option>
    <option value="agotados" <?= $disponibilidad === 'agotados' ? 'selected' : '' ?>>Agotados</option>
  </select>
  <select class="pq-select" name="orden" data-autoenviar>
    <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Ordenar: Nombre</option>
    <option value="precio" <?= $orden === 'precio' ? 'selected' : '' ?>>Ordenar: Precio</option>
    <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Ordenar: Más recientes</option>
  </select>
  <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico pq-filtro-boton">Buscar</button>
</form>

<?php if ($porCategoria === []): ?>
  <?php if ($totalProductos === 0): ?>
    <div class="pq-catalogo-vacio">
      Todavía no tienes productos en tu catálogo.
      <a href="<?= e(base_url('/panel/productos/nuevo')) ?>" class="pq-enlace-sello">Agrega el primero →</a>
    </div>
  <?php else: ?>
    <div class="pq-catalogo-vacio">No hay productos que coincidan con la búsqueda.</div>
  <?php endif; ?>
<?php else: ?>
  <?php foreach ($porCategoria as $nombreCategoria => $productosCategoria): ?>
    <div class="pq-categoria-grupo">
      <span class="pq-seccion-titulo"><?= e($nombreCategoria) ?> · <?= count($productosCategoria) ?></span>
      <div class="pq-catalogo-grid">
        <?php foreach ($productosCategoria as $producto): ?>
          <?php
          $oculto = (int) $producto['activo'] !== 1;
          $agotado = (int) $producto['agotado'] === 1;
          ?>
          <div class="pq-producto-card<?= $oculto ? ' pq-producto-oculto' : '' ?>">
            <?php // Sin foto, la inicial sobre su color: identifica el producto sin fingir una imagen. ?>
            <div class="pq-producto-miniatura<?= empty($producto['imagen']) ? ' pq-producto-miniatura-inicial' : '' ?>" style="--color-producto: <?= e(color_seguro($producto['color'], '#8a8d97')) ?>">
              <?php if (!empty($producto['imagen'])): ?>
                <img src="<?= e(base_url($producto['imagen'])) ?>" alt="">
              <?php else: ?>
                <span aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $producto['nombre'], 0, 1))) ?></span>
              <?php endif; ?>
            </div>
            <?php // Todo el bloque del nombre lleva a editar: en celular reemplaza al botón "Editar". ?>
            <a class="pq-producto-card-info" href="<?= e(base_url('/panel/productos/' . $producto['id'] . '/editar')) ?>">
              <span class="pq-producto-card-nombre"><?= e($producto['nombre']) ?></span>
              <span class="pq-producto-card-meta">
                <?php if ($oculto): ?>
                  <span class="pq-chip pq-chip-cancelado">Oculto de la tienda</span>
                <?php elseif ($agotado): ?>
                  <span class="pq-chip pq-chip-pendiente">Agotado</span>
                <?php else: ?>
                  <span class="pq-chip pq-chip-caja">Disponible</span>
                <?php endif; ?>
                <span class="pq-producto-card-precio"><?= pesos((int) $producto['precio']) ?></span>
              </span>
            </a>
            <div class="pq-producto-card-acciones">
              <a href="<?= e(base_url('/panel/productos/' . $producto['id'] . '/editar')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico pq-producto-editar">Editar</a>
              <details class="pq-menu-kebab">
                <summary aria-label="Más acciones">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                </summary>
                <div class="pq-menu-kebab-panel">
                  <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/visible')) ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="volver" value="<?= e(base_url('/panel/productos')) ?>">
                    <button type="submit"><?= $oculto ? 'Mostrar en la tienda' : 'Ocultar de la tienda' ?></button>
                  </form>
                  <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/agotado')) ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="volver" value="<?= e(base_url('/panel/productos')) ?>">
                    <button type="submit"><?= $agotado ? 'Marcar disponible' : 'Marcar agotado' ?></button>
                  </form>
                  <hr>
                  <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar «<?= e($producto['nombre']) ?>» de tu catálogo? No se puede deshacer.">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="volver" value="<?= e(base_url('/panel/productos')) ?>">
                    <button type="submit" class="pq-peligro">Eliminar</button>
                  </form>
                </div>
              </details>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($ok): ?>
  <div class="pq-toast">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
