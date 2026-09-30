<?php
/**
 * Catálogo de solo lectura: buscar/filtrar/ordenar, agrupado por
 * categoría, con el "⋮" para editar/agotar/eliminar. Editar de verdad
 * pasa a producto_form.php (/panel/productos/{id}/editar) — esta vista
 * ya no trae ningún <form> de edición.
 */
$hayFiltro = $busqueda !== '' || $categoriaFiltro !== '' || $disponibilidad !== '';

$porCategoria = [];
foreach ($productos as $producto) {
    $porCategoria[$producto['categoria']][] = $producto;
}
$agruparPorCategoria = count($porCategoria) > 1;

$accionesMenu = static function (array $producto): void {
    $agotado = (int) $producto['agotado'] === 1;
    ?>
    <details class="pq-menu">
      <summary aria-label="Más acciones para <?= e($producto['nombre']) ?>">
        <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
      </summary>
      <div class="pq-menu-panel">
        <a class="pq-menu-item" href="<?= e(base_url('/panel/productos/' . $producto['id'] . '/editar')) ?>">Editar</a>
        <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/agotado')) ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="volver" value="/panel/productos">
          <button type="submit" class="pq-menu-item"><?= $agotado ? 'Marcar disponible' : 'Marcar agotado' ?></button>
        </form>
        <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/eliminar')) ?>"
              data-confirmar="¿Eliminar «<?= e($producto['nombre']) ?>» de tu catálogo? No se puede deshacer.">
          <?= csrf_campo() ?>
          <input type="hidden" name="volver" value="/panel/productos">
          <button type="submit" class="pq-menu-item pq-menu-item-peligro">Eliminar</button>
        </form>
      </div>
    </details>
    <?php
};
?>
<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap">
  <div>
    <span class="pq-eyebrow">Tu menú</span>
    <h1 class="pq-h1" style="font-size: 28px">Productos</h1>
    <p class="pq-lead" style="margin-top: 2px">Lo que cambies aquí se ve de inmediato en tu tienda.</p>
  </div>
  <a href="<?= e(base_url('/panel/productos/nuevo')) ?>" class="pq-btn pq-btn-sello pq-btn-chico">+ Nuevo producto</a>
</div>

<?php if ($ok !== null): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>

<form method="get" action="<?= e(base_url('/panel/productos')) ?>" class="pq-filtro-historial">
  <input class="pq-input" type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar producto...">
  <?php if ($categorias !== []): ?>
    <select class="pq-select" name="categoria" data-autoenviar>
      <option value="">Todas las categorías</option>
      <?php foreach ($categorias as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $categoriaFiltro === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <select class="pq-select" name="disponibilidad" data-autoenviar>
    <option value="">Todos</option>
    <option value="disponibles" <?= $disponibilidad === 'disponibles' ? 'selected' : '' ?>>Disponibles</option>
    <option value="agotados" <?= $disponibilidad === 'agotados' ? 'selected' : '' ?>>Agotados</option>
  </select>
  <select class="pq-select" name="orden" data-autoenviar>
    <option value="">Orden manual</option>
    <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre (A-Z)</option>
    <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio (menor a mayor)</option>
    <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio (mayor a menor)</option>
  </select>
  <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico" style="width: auto">Buscar</button>
</form>

<?php if ($productos === []): ?>
  <div class="pq-tabla-wrap" style="margin-top: 16px">
    <p class="pq-tabla-vacia">
      <?= $hayFiltro ? 'Nada coincide con esa búsqueda.' : 'Aún no tienes productos en tu catálogo.' ?>
    </p>
  </div>
<?php else: ?>
  <?php foreach ($porCategoria as $categoria => $items): ?>
    <?php if ($agruparPorCategoria): ?>
      <span class="pq-seccion-titulo" style="display: block; margin-top: 24px"><?= e($categoria) ?></span>
    <?php endif; ?>
    <div class="pq-catalogo-grid">
      <?php foreach ($items as $producto): ?>
        <?php $agotado = (int) $producto['agotado'] === 1; ?>
        <div class="pq-catalogo-card<?= $agotado ? ' pq-catalogo-card-agotado' : '' ?>">
          <div class="pq-catalogo-card-media" style="background: <?= e($producto['color']) ?>">
            <?php if (!empty($producto['imagen'])): ?>
              <img src="<?= e(base_url($producto['imagen'])) ?>" alt="">
            <?php endif; ?>
            <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado pq-catalogo-card-chip">Agotado</span><?php endif; ?>
          </div>
          <div class="pq-catalogo-card-body">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px">
              <span class="pq-catalogo-card-nombre"><?= e($producto['nombre']) ?></span>
              <?php $accionesMenu($producto); ?>
            </div>
            <?php if (!empty($producto['descripcion'])): ?>
              <span class="pq-catalogo-card-desc"><?= e($producto['descripcion']) ?></span>
            <?php endif; ?>
            <span class="pq-mono pq-catalogo-card-precio"><?= pesos((int) $producto['precio']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
