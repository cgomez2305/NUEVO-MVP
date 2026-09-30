<?php
$esNuevo = $producto === null;
$volver = base_url('/panel/productos');
?>
<a href="<?= e($volver) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Productos</a>

<h1 class="pq-h1" style="font-size: 26px; margin-top: 8px"><?= $esNuevo ? 'Nuevo producto' : 'Editar producto' ?></h1>

<form method="post"
      action="<?= e($esNuevo ? base_url('/panel/productos') : base_url('/panel/productos/' . $producto['id'] . '/actualizar')) ?>"
      enctype="multipart/form-data" class="pq-stack" style="gap: 16px; margin-top: 20px; max-width: 460px">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="nombre">Nombre</label>
    <input class="pq-input" type="text" id="nombre" name="nombre" value="<?= e($producto['nombre'] ?? '') ?>" required maxlength="120">
  </div>

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="categoria">Categoría</label>
    <select class="pq-select" id="categoria" name="categoria">
      <?php $categoriaActual = $producto['categoria'] ?? ''; $coincide = false; ?>
      <?php foreach ($categorias as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $categoriaActual === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
        <?php if ($categoriaActual === $cat) { $coincide = true; } ?>
      <?php endforeach; ?>
      <option value="__otra__" <?= !$coincide ? 'selected' : '' ?>>Otra categoría...</option>
    </select>
    <div data-mostrar-si="categoria=__otra__" style="margin-top: 8px">
      <input class="pq-input" type="text" name="categoria_otra"
             value="<?= !$coincide ? e($categoriaActual) : '' ?>" placeholder="Nombre de la nueva categoría"
             data-requerido-si-visible maxlength="60">
    </div>
  </div>

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="precio">Precio</label>
    <div class="pq-campo-dinero">
      <input class="pq-input pq-mono" type="text" inputmode="numeric" id="precio" name="precio" data-precio
             value="<?= isset($producto['precio']) ? number_format((int) $producto['precio'], 0, '', '.') : '' ?>" required>
    </div>
  </div>

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="descripcion">Descripción (opcional)</label>
    <textarea class="pq-input" id="descripcion" name="descripcion" rows="2" maxlength="160" placeholder="Una descripción breve del producto..."><?= e($producto['descripcion'] ?? '') ?></textarea>
  </div>

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label">Imagen</label>
    <?php if (!empty($producto['imagen'])): ?>
      <div class="pq-foto-actual">
        <img src="<?= e(base_url($producto['imagen'])) ?>" alt="">
        <div class="pq-foto-actual-botones">
          <label for="imagen">Cambiar imagen</label>
          <button type="submit" name="quitar_imagen" value="1" formnovalidate>Eliminar foto</button>
        </div>
      </div>
      <input id="imagen" type="file" name="imagen" accept="image/jpeg,image/png,image/webp" style="position: absolute; width: 1px; height: 1px; opacity: 0">
    <?php else: ?>
      <label class="pq-subir-foto" for="imagen">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        <strong>Agregar foto del producto</strong>
        <span>JPG o PNG · Máx. 5 MB</span>
        <input id="imagen" type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
      </label>
    <?php endif; ?>
  </div>

  <div class="pq-switch-fila">
    <div class="pq-switch-texto">
      <strong>Disponible para vender</strong>
      <span>Si lo apagas, se ve en tu tienda pero no se puede agregar al carrito.</span>
    </div>
    <label class="pq-switch">
      <input type="checkbox" name="disponible" <?= ($producto === null || (int) $producto['agotado'] === 0) ? 'checked' : '' ?>>
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

  <div style="display: flex; gap: 10px; margin-top: 4px">
    <a href="<?= e($volver) ?>" class="pq-btn pq-btn-ghost" style="width: auto; flex-grow: 1">Cancelar</a>
    <button type="submit" class="pq-btn pq-btn-sello" style="width: auto; flex-grow: 1"><?= $esNuevo ? 'Agregar producto' : 'Guardar cambios' ?></button>
  </div>
</form>
