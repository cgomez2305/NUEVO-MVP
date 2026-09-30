<?php
/**
 * Parcial compartido entre el onboarding y el panel de productos.
 * Espera $productos (array) y $volver (ruta a la que regresar tras guardar).
 * $compacto (opcional, default false): en el onboarding se deja en true
 * para no abrumar con foto/descripción justo en el alta — ahí solo se
 * confirma nombre, categoría y precio. El panel usa el editor completo.
 */
$compacto = $compacto ?? false;
$categorias = $categorias ?? [];

$porCategoria = [];
foreach ($productos as $producto) {
    $porCategoria[$producto['categoria']][] = $producto;
}
$agruparPorCategoria = !$compacto && count($porCategoria) > 1;
?>
<?php if ($categorias !== []): ?>
  <datalist id="categorias-sugeridas">
    <?php foreach ($categorias as $cat): ?>
      <option value="<?= e($cat) ?>">
    <?php endforeach; ?>
  </datalist>
<?php endif; ?>

<?php foreach ($porCategoria as $categoria => $items): ?>
  <?php if ($agruparPorCategoria): ?>
    <span class="pq-eyebrow" style="display: block; margin-top: 20px"><?= e($categoria) ?></span>
  <?php endif; ?>
  <div class="pq-stack" style="gap: 8px; margin-top: 8px">
    <?php foreach ($items as $producto): ?>
      <?php $agotado = (int) $producto['agotado'] === 1; ?>
      <div class="pq-card-borde" style="display: flex; flex-direction: column; gap: 8px<?= $agotado ? '; opacity: .6' : '' ?>">
        <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/actualizar')) ?>"
              enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: flex-start">
          <?= csrf_campo() ?>
          <input type="hidden" name="volver" value="<?= e($volver) ?>">

          <?php if (!$compacto): ?>
            <div class="pq-producto-miniatura" style="background: <?= e($producto['color']) ?>">
              <?php if (!empty($producto['imagen'])): ?>
                <img src="<?= e(base_url($producto['imagen'])) ?>" alt="">
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div style="flex-grow: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px">
            <div style="display: flex; gap: 8px; align-items: center">
              <input class="pq-input" style="flex-grow: 1" type="text" name="nombre" value="<?= e($producto['nombre']) ?>" required maxlength="120">
              <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado">Agotado</span><?php endif; ?>
            </div>

            <div style="display: flex; gap: 8px">
              <input class="pq-input" style="flex: 1; min-width: 0" type="text" name="categoria" value="<?= e($producto['categoria']) ?>" list="categorias-sugeridas" placeholder="Categoría" maxlength="60">
              <div class="pq-campo-dinero" style="flex: 1; min-width: 0">
                <input class="pq-input pq-mono" type="number" name="precio" value="<?= (int) $producto['precio'] ?>" min="0" step="500" required>
              </div>
            </div>

            <?php if (!$compacto): ?>
              <textarea class="pq-input" name="descripcion" rows="2" placeholder="Descripción corta (opcional)" maxlength="160"><?= e($producto['descripcion'] ?? '') ?></textarea>
              <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                <input class="pq-input" style="flex-grow: 1; min-width: 160px" type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
                <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar</button>
              </div>
            <?php else: ?>
              <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico" style="align-self: flex-end">Guardar</button>
            <?php endif; ?>
          </div>
        </form>
        <div style="display: flex; gap: 16px; align-items: center; align-self: flex-end">
          <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/agotado')) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">
              <?= $agotado ? 'marcar disponible' : 'marcar agotado' ?>
            </button>
          </form>
          <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar «<?= e($producto['nombre']) ?>» de tu catálogo? No se puede deshacer.">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">eliminar</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if ($productos === []): ?>
  <p class="pq-ayuda">Aún no tienes productos en tu catálogo.</p>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/panel/productos')) ?>" enctype="multipart/form-data" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 16px">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">
  <input class="pq-input" type="text" name="nombre" placeholder="Nuevo producto" required maxlength="120">
  <div style="display: flex; gap: 8px">
    <input class="pq-input" style="flex: 1; min-width: 0" type="text" name="categoria" list="categorias-sugeridas" placeholder="Categoría" maxlength="60">
    <div class="pq-campo-dinero" style="flex: 1; min-width: 0">
      <input class="pq-input pq-mono" type="number" name="precio" placeholder="0" min="0" step="500" required>
    </div>
  </div>
  <?php if (!$compacto): ?>
    <textarea class="pq-input" name="descripcion" rows="2" placeholder="Descripción corta (opcional)" maxlength="160"></textarea>
    <input class="pq-input" type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
  <?php endif; ?>
  <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">+ Agregar</button>
</form>
