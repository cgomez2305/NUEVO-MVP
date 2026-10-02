<?php
$esNuevo = $producto === null;
$volver = base_url('/panel/productos');
?>
<a href="<?= e($volver) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Productos
</a>

<span class="pq-eyebrow pq-eyebrow-tras-volver"><?= $esNuevo ? 'Nuevo producto' : 'Editar producto' ?></span>
<h1 class="pq-h1"><?= $esNuevo ? '¿Qué vas a vender?' : e($producto['nombre']) ?></h1>

<form method="post"
      action="<?= e($esNuevo ? base_url('/panel/productos') : base_url('/panel/productos/' . $producto['id'] . '/actualizar')) ?>"
      enctype="multipart/form-data" class="pq-card pq-form-panel">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">

  <div class="pq-campo">
    <label class="pq-label" for="nombre">Nombre</label>
    <input class="pq-input" type="text" id="nombre" name="nombre" value="<?= e($producto['nombre'] ?? '') ?>" required maxlength="120">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="categoria">Categoría</label>
    <select class="pq-select" id="categoria" name="categoria">
      <?php $categoriaActual = $producto['categoria'] ?? ''; $coincide = false; ?>
      <?php foreach ($categorias as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $categoriaActual === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
        <?php if ($categoriaActual === $cat) { $coincide = true; } ?>
      <?php endforeach; ?>
      <option value="__otra__" <?= !$coincide ? 'selected' : '' ?>>Otra categoría...</option>
    </select>
    <div data-mostrar-si="categoria=__otra__" class="pq-campo-extra">
      <input class="pq-input" type="text" name="categoria_otra"
             value="<?= !$coincide ? e($categoriaActual) : '' ?>" placeholder="Nombre de la nueva categoría"
             data-requerido-si-visible maxlength="60">
    </div>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="precio">Precio</label>
    <div class="pq-campo-dinero">
      <input class="pq-input pq-mono" type="text" inputmode="numeric" id="precio" name="precio" data-precio
             value="<?= isset($producto['precio']) ? number_format((int) $producto['precio'], 0, '', '.') : '' ?>" required>
    </div>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="descripcion">Descripción <span class="pq-ayuda">(opcional)</span></label>
    <textarea class="pq-input" id="descripcion" name="descripcion" rows="2" maxlength="160" placeholder="Una descripción breve del producto..."><?= e($producto['descripcion'] ?? '') ?></textarea>
  </div>

  <div class="pq-campo">
    <label class="pq-label">Imagen</label>
    <?php if (!empty($producto['imagen'])): ?>
      <div class="pq-foto-actual">
        <img src="<?= e(base_url($producto['imagen'])) ?>" alt="">
        <div class="pq-foto-actual-botones">
          <label for="imagen">Cambiar imagen</label>
          <button type="submit" name="quitar_imagen" value="1" formnovalidate>Eliminar foto</button>
        </div>
      </div>
      <input id="imagen" type="file" name="imagen" accept="image/jpeg,image/png,image/webp" class="pq-sr-solo">
    <?php else: ?>
      <label class="pq-subir-foto" for="imagen">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        <strong>Agregar foto del producto</strong>
        <span>JPG o PNG · Máx. 5 MB</span>
        <input id="imagen" type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
      </label>
    <?php endif; ?>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="stock">Unidades disponibles <span class="pq-ayuda">(opcional)</span></label>
    <input class="pq-input pq-mono pq-campo-unidades" type="number" inputmode="numeric" min="0" step="1" id="stock" name="stock" value="<?= isset($producto['stock']) && $producto['stock'] !== null ? (int) $producto['stock'] : '' ?>" placeholder="Sin contar">
    <span class="pq-ayuda">Para lo que se hace en cantidad fija (20 empanadas, 8 tortas). Cada pedido descuenta; en 0 sale agotado solo. Vacío = no contar.</span>
  </div>

  <div class="pq-switch-fila">
    <div class="pq-switch-texto">
      <strong>Disponible para vender</strong>
      <span>Si lo apagas, se ve en tu tienda pero no se puede agregar al carrito.</span>
      <?php if (($producto['motivo_agotado'] ?? '') === 'hoy'): ?>
        <span class="pq-switch-nota">Ahora está agotado solo por hoy: mañana vuelve solo.</span>
      <?php endif; ?>
    </div>
    <label class="pq-switch">
      <input type="checkbox" name="disponible" <?= ($producto === null || (int) ($producto['agotado_fijo'] ?? $producto['agotado']) === 0) ? 'checked' : '' ?>>
      <span class="pq-switch-riel"></span>
    </label>
  </div>

  <div class="pq-switch-fila">
    <div class="pq-switch-texto">
      <strong>Visible en la tienda</strong>
      <span>Si lo apagas, el producto desaparece por completo de tu tienda pública.</span>
    </div>
    <label class="pq-switch">
      <input type="checkbox" name="visible" <?= ($producto === null || (int) $producto['activo'] === 1) ? 'checked' : '' ?>>
      <span class="pq-switch-riel"></span>
    </label>
  </div>

  <div class="pq-form-panel-botones">
    <a href="<?= e($volver) ?>" class="pq-btn pq-btn-ghost">Cancelar</a>
    <button type="submit" class="pq-btn pq-btn-sello"><?= $esNuevo ? 'Agregar producto' : 'Guardar cambios' ?></button>
  </div>
</form>
