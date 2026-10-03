<div class="pq-catalogo-cabecera">
  <div>
    <span class="pq-eyebrow">Tu menú</span>
    <h1 class="pq-h1">Productos</h1>
    <p class="pq-lead">Gestiona lo que vendes en tu tienda</p>
  </div>
  <a href="<?= e(base_url('/panel/productos/nuevo')) ?>" class="pq-btn pq-btn-sello pq-btn-chico">+ Nuevo producto</a>
</div>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

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

<?php if (!empty($masVendidos)): ?>
  <?php $tope = max($masVendidos); ?>
  <?php // Lo que más sale en 30 días: barras simples con el número real de pedidos. ?>
  <section class="pq-mas-vendidos" aria-labelledby="pq-titulo-mas-vendidos">
    <h2 class="pq-seccion-titulo" id="pq-titulo-mas-vendidos">Lo que más sale · 30 días</h2>
    <ol class="pq-mas-vendidos-lista">
      <?php foreach ($masVendidos as $idVendido => $veces): ?>
        <?php if (!isset($nombresPorId[$idVendido])) { continue; } ?>
        <li style="--parte: <?= round($veces / $tope, 3) ?>">
          <span class="pq-mas-vendidos-nombre"><?= e($nombresPorId[$idVendido]) ?></span>
          <span class="pq-mas-vendidos-barra" aria-hidden="true"></span>
          <span class="pq-mas-vendidos-veces pq-mono"><?= $veces ?> pedido<?= $veces === 1 ? '' : 's' ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <p class="pq-ayuda">Los 3 primeros con 3 o más pedidos salen en tu tienda como "Lo más pedido".</p>
  </section>
<?php endif; ?>

<?php if (!empty($masDeja['filas'])): ?>
  <?php $topeGanancia = max(array_column($masDeja['filas'], 'ganancia')); ?>
  <?php // Tiendas (fase 4): la ganancia real, no lo vendido. Solo con datos suficientes (ver Venta::loQueMasDeja). ?>
  <section class="pq-mas-vendidos pq-mas-deja" aria-labelledby="pq-titulo-mas-deja">
    <h2 class="pq-seccion-titulo" id="pq-titulo-mas-deja">Lo que más te deja · 30 días</h2>
    <ol class="pq-mas-vendidos-lista">
      <?php foreach ($masDeja['filas'] as $fila): ?>
        <li style="--parte: <?= round($fila['ganancia'] / $topeGanancia, 3) ?>">
          <span class="pq-mas-vendidos-nombre"><?= e($fila['nombre']) ?></span>
          <span class="pq-mas-vendidos-barra" aria-hidden="true"></span>
          <span class="pq-mas-vendidos-veces pq-mono"><?= pesos((int) $fila['ganancia']) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <p class="pq-ayuda">Ganancia = lo cobrado menos el costo, en ventas de mostrador y pedidos entregados. Solo cuenta lo que tiene costo anotado (<?= (int) $masDeja['lineas'] ?> renglones vendidos).</p>
  </section>
<?php endif; ?>

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
                  <span class="pq-chip pq-chip-pendiente"><?= match ($producto['motivo_agotado'] ?? '') { 'hoy' => 'Agotado hoy', 'stock' => (($producto['vende_por'] ?? '') === 'peso' && (int) $producto['stock'] > 0 ? e(\App\Models\Producto::stockLegible($producto)) . ' · menos de 1 kg' : 'Sin unidades'), 'combo' => 'Le falta una parte', default => 'Agotado' } ?></span>
                <?php elseif ($producto['stock'] !== null): ?>
                  <span class="pq-chip <?= \App\Models\Producto::quedaPoco($producto) ? 'pq-chip-pendiente' : 'pq-chip-caja' ?>"><?= e(\App\Models\Producto::stockLegible($producto)) ?></span>
                <?php else: ?>
                  <span class="pq-chip pq-chip-caja">Disponible</span>
                <?php endif; ?>
                <span class="pq-producto-card-precio"><?= pesos((int) $producto['precio']) ?><?= ($producto['vende_por'] ?? '') === 'peso' ? ' / kg' : '' ?></span>
                <?php $margen = \App\Models\Producto::margen($producto); ?>
                <?php if ($margen !== null): ?>
                  <?php // El margen real, solo si hay costo: sin costo no se inventa. ?>
                  <span class="pq-producto-margen<?= $margen['ganancia'] < 0 ? ' pq-producto-margen-perdida' : '' ?>"><?= $margen['ganancia'] < 0 ? 'Bajo costo' : 'Margen ' . $margen['porcentaje'] . ' %' ?></span>
                <?php endif; ?>
                <?php if (!empty($producto['combo'])): ?>
                  <span class="pq-producto-card-combo">Combo · <?= count($producto['combo']) ?> productos</span>
                <?php endif; ?>
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
                  <?php if (($producto['motivo_agotado'] ?? '') === 'stock'): ?>
                    <?php // Sin unidades no se "desagota" con un clic: hay que decir cuántas llegaron. ?>
                    <a href="<?= e(base_url('/panel/productos/' . $producto['id'] . '/editar')) ?>#stock">Cargar unidades</a>
                  <?php else: ?>
                    <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/agotado')) ?>">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="volver" value="<?= e(base_url('/panel/productos')) ?>">
                      <button type="submit"><?= $agotado ? 'Marcar disponible' : 'Agotado hasta nuevo aviso' ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if (!$agotado): ?>
                    <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/agotado-hoy')) ?>">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="volver" value="<?= e(base_url('/panel/productos')) ?>">
                      <button type="submit">Se acabó por hoy</button>
                    </form>
                  <?php endif; ?>
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
