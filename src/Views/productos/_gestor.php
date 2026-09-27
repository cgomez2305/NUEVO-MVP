<?php
/**
 * Parcial compartido entre el onboarding y el panel de productos.
 * Espera $productos (array) y $volver (ruta a la que regresar tras guardar).
 */
?>
<div class="pq-stack" style="gap: 8px">
  <?php foreach ($productos as $producto): ?>
    <div class="pq-card-borde" style="display: flex; flex-direction: column; gap: 8px">
      <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/actualizar')) ?>"
            style="display: flex; flex-direction: column; gap: 8px">
        <?= csrf_campo() ?>
        <input type="hidden" name="volver" value="<?= e($volver) ?>">
        <input type="hidden" name="categoria" value="<?= e($producto['categoria']) ?>">
        <div style="display: flex; gap: 8px; align-items: center">
          <span style="width: 12px; height: 12px; border-radius: 4px; background: <?= e($producto['color']) ?>; flex-shrink: 0" aria-hidden="true"></span>
          <input class="pq-input" style="flex-grow: 1" type="text" name="nombre" value="<?= e($producto['nombre']) ?>" required maxlength="120">
        </div>
        <div style="display: flex; gap: 8px">
          <input class="pq-input pq-mono" style="flex-grow: 1" type="number" name="precio" value="<?= (int) $producto['precio'] ?>" min="0" step="500" required>
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Guardar</button>
        </div>
      </form>
      <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/eliminar')) ?>" style="align-self: flex-end">
        <?= csrf_campo() ?>
        <input type="hidden" name="volver" value="<?= e($volver) ?>">
        <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">eliminar</button>
      </form>
    </div>
  <?php endforeach; ?>

  <?php if ($productos === []): ?>
    <p class="pq-ayuda">Aún no tienes productos en tu catálogo.</p>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(base_url('/panel/productos')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 16px">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">
  <input class="pq-input" type="text" name="nombre" placeholder="Nuevo producto" required maxlength="120">
  <div style="display: flex; gap: 8px">
    <input class="pq-input pq-mono" style="flex-grow: 1" type="number" name="precio" placeholder="Precio" min="0" step="500" required>
    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">+ Agregar</button>
  </div>
</form>
