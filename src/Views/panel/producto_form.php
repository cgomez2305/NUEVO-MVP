<?php
/**
 * Alta/edición de UN producto, en su propia página (antes vivía como
 * formulario abierto dentro de la tarjeta, en productos/_gestor.php).
 * $producto es null cuando se está creando uno nuevo.
 */
$esNuevo = $producto === null;
$volver = '/panel/productos';
?>
<a href="<?= e(base_url($volver)) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Productos</a>

<div style="margin-top: 16px">
  <span class="pq-eyebrow"><?= $esNuevo ? 'Nuevo producto' : 'Editar producto' ?></span>
  <h1 class="pq-h1" style="font-size: 26px"><?= $esNuevo ? 'Agrega un producto' : e($producto['nombre']) ?></h1>
</div>

<?php if ($categorias !== []): ?>
  <datalist id="categorias-sugeridas">
    <?php foreach ($categorias as $cat): ?>
      <option value="<?= e($cat) ?>">
    <?php endforeach; ?>
  </datalist>
<?php endif; ?>

<form method="post"
      action="<?= e(base_url($esNuevo ? '/panel/productos' : '/panel/productos/' . $producto['id'] . '/actualizar')) ?>"
      enctype="multipart/form-data" class="pq-card-borde" style="margin-top: 18px; display: flex; flex-direction: column; gap: 16px">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">

  <div style="display: flex; gap: 14px; align-items: flex-start">
    <div class="pq-producto-miniatura" style="width: 72px; height: 72px; background: <?= e($producto['color'] ?? '#5B7F3A') ?>">
      <?php if (!$esNuevo && !empty($producto['imagen'])): ?>
        <img src="<?= e(base_url($producto['imagen'])) ?>" alt="">
      <?php endif; ?>
    </div>
    <div style="flex-grow: 1; min-width: 0">
      <label class="pq-label" for="campo-imagen">Foto (opcional)</label>
      <input class="pq-input" id="campo-imagen" type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
      <span class="pq-ayuda">JPG, PNG o WEBP · máximo 5 MB</span>
    </div>
  </div>

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="campo-nombre">Nombre</label>
    <input class="pq-input" id="campo-nombre" type="text" name="nombre" value="<?= e($producto['nombre'] ?? '') ?>" required maxlength="120">
  </div>

  <div style="display: flex; gap: 12px">
    <div class="pq-campo" style="margin-bottom: 0; flex: 1; min-width: 0">
      <label class="pq-label" for="campo-categoria">Categoría</label>
      <input class="pq-input" id="campo-categoria" type="text" name="categoria" value="<?= e($producto['categoria'] ?? '') ?>" list="categorias-sugeridas" placeholder="General" maxlength="60">
    </div>
    <div class="pq-campo" style="margin-bottom: 0; flex: 1; min-width: 0">
      <label class="pq-label" for="campo-precio">Precio</label>
      <div class="pq-campo-dinero">
        <input class="pq-input pq-mono" id="campo-precio" type="number" name="precio" value="<?= (int) ($producto['precio'] ?? 0) ?>" min="0" step="500" required>
      </div>
    </div>
  </div>

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="campo-descripcion">Descripción (opcional)</label>
    <textarea class="pq-input" id="campo-descripcion" name="descripcion" rows="3" maxlength="160" placeholder="Corta, se ve en tu tienda"><?= e($producto['descripcion'] ?? '') ?></textarea>
  </div>

  <button type="submit" class="pq-btn pq-btn-sello"><?= $esNuevo ? 'Crear producto' : 'Guardar cambios' ?></button>
</form>

<?php if (!$esNuevo): ?>
  <div class="pq-card-borde" style="margin-top: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap">
    <div>
      <span style="font-weight: 600; font-size: 13px; display: block">
        <?= ((int) $producto['agotado'] === 1) ? 'Marcado como agotado' : 'Disponible en tu tienda' ?>
      </span>
      <span class="pq-ayuda">Un producto agotado se sigue viendo en tu tienda, pero nadie lo puede agregar al carrito.</span>
    </div>
    <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/agotado')) ?>">
      <?= csrf_campo() ?>
      <input type="hidden" name="volver" value="/panel/productos/<?= (int) $producto['id'] ?>/editar">
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">
        <?= ((int) $producto['agotado'] === 1) ? 'Marcar disponible' : 'Marcar agotado' ?>
      </button>
    </form>
  </div>

  <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/eliminar')) ?>"
        data-confirmar="¿Eliminar «<?= e($producto['nombre']) ?>» de tu catálogo? No se puede deshacer." style="margin-top: 16px; text-align: center">
    <?= csrf_campo() ?>
    <input type="hidden" name="volver" value="<?= e($volver) ?>">
    <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 12px; cursor: pointer; padding: 0">eliminar producto</button>
  </form>
<?php endif; ?>
