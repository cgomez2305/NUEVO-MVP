<?php
$esNuevo = $producto === null;
$volver = base_url('/panel/productos');
// Tiendas (fase 4): por peso, precio y costo son del kilo y el stock se
// escribe en kilos (se guarda en gramos).
$porPeso = ($producto['vende_por'] ?? 'unidad') === 'peso';
$codigoActual = $producto['codigo_barras'] ?? ($codigoInicial ?? null);
$stockMostrado = '';
if (isset($producto['stock']) && $producto['stock'] !== null) {
    $stockMostrado = $porPeso
        ? rtrim(rtrim(number_format((int) $producto['stock'] / 1000, 3, ',', ''), '0'), ',')
        : (string) (int) $producto['stock'];
}
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
  <?php
  // Primer botón del formulario, apagado: Enter en un campo no envía nada.
  // El lector de códigos escribe el código y manda Enter; sin esto, ese
  // Enter guardaba el producto a medio llenar (o, con foto, la borraba:
  // "Eliminar foto" era el primer botón).
  ?>
  <button type="submit" disabled hidden aria-hidden="true" tabindex="-1"></button>
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

  <fieldset class="pq-mostrador-metodos pq-mostrador-metodos-2 pq-producto-vende-por">
    <legend class="pq-label">¿Cómo lo vendes?</legend>
    <label class="pq-mostrador-metodo"><input type="radio" name="vende_por" value="unidad"<?= !$porPeso ? ' checked' : '' ?> data-vende-por><span>Por unidad</span></label>
    <label class="pq-mostrador-metodo"><input type="radio" name="vende_por" value="peso"<?= $porPeso ? ' checked' : '' ?> data-vende-por><span>Por peso (kilo)</span></label>
  </fieldset>

  <div class="pq-campo">
    <label class="pq-label" for="precio" data-etiqueta-unidad="Precio" data-etiqueta-peso="Precio del kilo"><?= $porPeso ? 'Precio del kilo' : 'Precio' ?></label>
    <div class="pq-campo-dinero">
      <input class="pq-input pq-mono" type="text" inputmode="numeric" id="precio" name="precio" data-precio
             value="<?= isset($producto['precio']) ? number_format((int) $producto['precio'], 0, '', '.') : '' ?>" required>
    </div>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="costo"><span data-etiqueta-unidad="Lo que te cuesta" data-etiqueta-peso="Lo que te cuesta el kilo"><?= $porPeso ? 'Lo que te cuesta el kilo' : 'Lo que te cuesta' ?></span> <span class="pq-ayuda">(opcional)</span></label>
    <div class="pq-campo-dinero">
      <input class="pq-input pq-mono" type="text" inputmode="numeric" id="costo" name="costo" data-precio
             value="<?= isset($producto['costo']) && $producto['costo'] !== null ? number_format((int) $producto['costo'], 0, '', '.') : '' ?>" placeholder="Lo que le pagas al proveedor">
    </div>
    <span class="pq-ayuda" data-margen-producto>Con el costo, Veci te dice cuánto te deja de verdad cada venta. Las compras a proveedor lo ponen solas.</span>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="codigo_barras">Código de barras <span class="pq-ayuda">(opcional)</span></label>
    <input class="pq-input pq-mono" type="text" id="codigo_barras" name="codigo_barras" maxlength="32" value="<?= e((string) $codigoActual) ?>"
           autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Pásalo por el lector o escribe los números" data-campo-lector
           data-consulta-url="<?= e(base_url('/panel/codigos/')) ?>"<?= $producto !== null ? ' data-producto-id="' . (int) $producto['id'] . '"' : '' ?>>
    <span class="pq-ayuda pq-codigo-sugerencia" data-codigo-sugerencia aria-live="polite" hidden></span>
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

  <?php
  // Combo: solo si este producto no está ya DENTRO de otro combo (un combo
  // de combos no hay cómo explicarlo en la tienda) y hay con qué armarlo.
  $esParteDeCombo = false;
  foreach (\App\Models\Producto::componentesPorCombo((int) $negocio['id']) as $partesDeOtro) {
      foreach ($partesDeOtro as $parte) {
          if (!$esNuevo && (int) $parte['id'] === (int) $producto['id']) {
              $esParteDeCombo = true;
          }
      }
  }
  $cantidadesCombo = [];
  foreach ($producto['combo'] ?? [] as $parte) {
      $cantidadesCombo[(int) $parte['id']] = (int) $parte['cantidad'];
  }
  $precioSeparado = (int) ($producto['precio_separado'] ?? 0);
  ?>
  <?php if (!$esParteDeCombo && $partesPosibles !== []): ?>
    <details class="pq-combo-armar"<?= $cantidadesCombo !== [] ? ' open' : '' ?>>
      <summary>
        <span class="pq-combo-armar-titulo">Es un combo</span>
        <span class="pq-ayuda"><?= $cantidadesCombo !== [] ? count($cantidadesCombo) . ' productos adentro' : 'Opcional: varios productos a un precio' ?></span>
      </summary>
      <input type="hidden" name="combo_presente" value="1">
      <p class="pq-ayuda">Elige qué trae. Si alguna parte se agota, el combo también; y vender el combo descuenta las unidades de sus partes.</p>
      <ul class="pq-combo-partes" data-combo-partes>
        <?php foreach ($partesPosibles as $parte): ?>
          <?php $idParte = (int) $parte['id']; ?>
          <li>
            <label for="combo-<?= $idParte ?>">
              <span class="pq-combo-parte-nombre"><?= e($parte['nombre']) ?></span>
              <span class="pq-ayuda pq-mono"><?= pesos((int) $parte['precio']) ?></span>
            </label>
            <input class="pq-input pq-mono" type="number" inputmode="numeric" min="0" max="20" step="1" id="combo-<?= $idParte ?>" name="combo[<?= $idParte ?>]" value="<?= $cantidadesCombo[$idParte] ?? '' ?>" placeholder="0" data-precio-unidad="<?= (int) $parte['precio'] ?>" aria-label="Unidades de <?= e($parte['nombre']) ?> en el combo">
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="pq-combo-resumen" data-combo-resumen<?= $precioSeparado === 0 ? ' hidden' : '' ?>>
        Por separado: <strong class="pq-mono" data-combo-separado><?= pesos($precioSeparado) ?></strong>
        <span data-combo-ahorro><?php if ($precioSeparado > (int) ($producto['precio'] ?? 0)): ?>· tu cliente ahorra <strong class="pq-mono"><?= pesos($precioSeparado - (int) $producto['precio']) ?></strong><?php endif; ?></span>
      </p>
    </details>
  <?php endif; ?>

  <div class="pq-campo">
    <label class="pq-label" for="stock"><span data-etiqueta-unidad="Unidades disponibles" data-etiqueta-peso="Kilos disponibles"><?= $porPeso ? 'Kilos disponibles' : 'Unidades disponibles' ?></span> <span class="pq-ayuda">(opcional)</span></label>
    <input class="pq-input pq-mono pq-campo-unidades" type="text" inputmode="decimal" maxlength="10" id="stock" name="stock" value="<?= e($stockMostrado) ?>" placeholder="Sin contar">
    <span class="pq-ayuda">Cada venta o pedido descuenta; en 0 sale agotado solo. Vacío = no contar. Si lo vendes por peso, escribe los kilos (ej.: 2,5).</span>
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
