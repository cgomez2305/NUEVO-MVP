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
      <?php if ($compacto): ?>
        <?php $formId = 'prod-form-' . $producto['id']; ?>
        <div class="pq-card-borde pq-servicio-compacto<?= $agotado ? ' pq-servicio-compacto-agotado' : '' ?>">
          <div class="pq-servicio-compacto-fila">
            <span class="pq-servicio-compacto-punto" style="background: <?= e($producto['color']) ?>" aria-hidden="true"></span>
            <form method="post" id="<?= e($formId) ?>" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/actualizar')) ?>" class="pq-servicio-compacto-nombre">
              <?= csrf_campo() ?>
              <input type="hidden" name="volver" value="<?= e($volver) ?>">
              <input class="pq-input" type="text" name="nombre" value="<?= e($producto['nombre']) ?>" required maxlength="120">
            </form>
            <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado">Agotado</span><?php endif; ?>
            <details class="pq-menu-kebab">
              <summary aria-label="Más opciones">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
              </summary>
              <div class="pq-menu-kebab-panel">
                <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/agotado')) ?>">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="volver" value="<?= e($volver) ?>">
                  <button type="submit"><?= $agotado ? 'Marcar disponible' : 'Marcar agotado' ?></button>
                </form>
                <hr>
                <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar «<?= e($producto['nombre']) ?>» de tu catálogo? No se puede deshacer.">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="volver" value="<?= e($volver) ?>">
                  <button type="submit" class="pq-peligro">Eliminar</button>
                </form>
              </div>
            </details>
          </div>
          <div class="pq-servicio-compacto-campos">
            <input form="<?= e($formId) ?>" class="pq-input" style="flex-grow: 1; min-width: 0" type="text" name="categoria" value="<?= e($producto['categoria']) ?>" list="categorias-sugeridas" placeholder="Categoría">
            <div class="pq-campo-dinero" style="width: 110px; flex-shrink: 0">
              <input form="<?= e($formId) ?>" class="pq-input pq-mono" type="number" name="precio" value="<?= (int) $producto['precio'] ?>" min="0" step="500" required>
            </div>
            <button type="submit" form="<?= e($formId) ?>" class="pq-btn-icono" aria-label="Guardar cambios">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            </button>
          </div>
        </div>
      <?php else: ?>
        <div class="pq-card-borde" style="display: flex; flex-direction: column; gap: 8px<?= $agotado ? '; opacity: .6' : '' ?>">
          <form method="post" action="<?= e(base_url('/panel/productos/' . $producto['id'] . '/actualizar')) ?>"
                enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: flex-start">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="<?= e($volver) ?>">

            <div class="pq-producto-miniatura" style="background: <?= e($producto['color']) ?>">
              <?php if (!empty($producto['imagen'])): ?>
                <img src="<?= e(base_url($producto['imagen'])) ?>" alt="">
              <?php endif; ?>
            </div>

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

              <textarea class="pq-input" name="descripcion" rows="2" placeholder="Descripción corta (opcional)" maxlength="160"><?= e($producto['descripcion'] ?? '') ?></textarea>
              <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                <input class="pq-input" style="flex-grow: 1; min-width: 160px" type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
                <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar</button>
              </div>
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
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if ($productos === []): ?>
  <p class="pq-ayuda">Aún no tienes productos en tu catálogo.</p>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/panel/productos')) ?>" enctype="multipart/form-data" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 16px">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">
  <input class="pq-input" type="text" name="nombre" placeholder="<?= $compacto ? 'Agregar otro producto' : 'Nuevo producto' ?>" required maxlength="120">
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
