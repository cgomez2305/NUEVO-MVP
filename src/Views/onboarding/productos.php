<?php
$pasoActual = 2;
$volverUrl = '/panel/onboarding/foto';
require __DIR__ . '/_pasos.php';
$categorias = array_values(array_unique(array_map(fn ($p) => (string) $p['categoria'], $productos)));
?>
<main class="pq-onb-cuerpo" data-guardia-cambios="pq-catalogo">
  <?php if ($productos === []): ?>
    <h1 class="pq-h1">Veci va a leer tu menú</h1>
    <p class="pq-lead pq-onb-bajada">Encuentra los productos y precios en tu foto y te arma la carta. Tú solo revisas.</p>

    <?php
    // Mientras el servidor lee la foto (puede tardar unos segundos), una
    // línea la recorre de arriba abajo: dice "estoy leyendo", no "se colgó".
    ?>
    <form method="post" action="<?= e(base_url('/panel/onboarding/analizar')) ?>" class="pq-onb-leer" data-enviando="Leyendo tu menú…">
      <?= csrf_campo() ?>
      <div class="pq-onb-escaner">
        <img src="<?= e(base_url($negocio['menu_foto'])) ?>" alt="La foto de tu menú">
        <span class="pq-onb-escaner-linea" aria-hidden="true"></span>
      </div>
      <button type="submit" class="pq-btn pq-btn-sello">Leer mi menú</button>
      <p class="pq-ayuda pq-centro">Tarda unos segundos. No cierres esta pantalla.</p>
    </form>
  <?php else: ?>
    <h1 class="pq-h1">Revisa tu carta</h1>
    <p class="pq-lead pq-onb-bajada">Encontramos <?= count($productos) ?> producto<?= count($productos) === 1 ? '' : 's' ?>. Corrige lo que haga falta: se guarda todo junto al final.</p>

    <?php
    // Un solo formulario para toda la lista (los campos de cada fila lo
    // apuntan con form="pq-catalogo"): así "Guardar y continuar" guarda
    // todo, y los menús ⋮ de cada fila pueden tener sus propios formularios
    // sin anidarlos.
    ?>
    <form method="post" action="<?= e(base_url('/panel/onboarding/catalogo')) ?>" id="pq-catalogo" data-enviando="Guardando…">
      <?= csrf_campo() ?>
    </form>
    <datalist id="categorias-sugeridas">
      <?php foreach ($categorias as $cat): ?><option value="<?= e($cat) ?>"><?php endforeach; ?>
    </datalist>

    <ol class="pq-onb-lista">
      <?php foreach ($productos as $producto): ?>
        <?php $id = (int) $producto['id']; $agotado = (int) $producto['agotado'] === 1; ?>
        <li class="pq-onb-item<?= $agotado ? ' pq-onb-item-agotado' : '' ?>">
          <div class="pq-onb-item-fila">
            <label class="pq-sr-solo" for="p<?= $id ?>-nombre">Nombre</label>
            <input form="pq-catalogo" class="pq-input pq-onb-item-nombre" id="p<?= $id ?>-nombre" type="text" name="items[<?= $id ?>][nombre]" value="<?= e($producto['nombre']) ?>" required maxlength="120">
            <details class="pq-menu-kebab">
              <summary aria-label="Más opciones para <?= e($producto['nombre']) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
              </summary>
              <div class="pq-menu-kebab-panel">
                <form method="post" action="<?= e(base_url('/panel/productos/' . $id . '/agotado')) ?>">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="volver" value="/panel/onboarding/productos">
                  <button type="submit"><?= $agotado ? 'Marcar disponible' : 'Marcar agotado' ?></button>
                </form>
                <hr>
                <form method="post" action="<?= e(base_url('/panel/productos/' . $id . '/eliminar')) ?>" data-confirmar="¿Quitar «<?= e($producto['nombre']) ?>» de tu carta?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="volver" value="/panel/onboarding/productos">
                  <button type="submit" class="pq-peligro">Quitar de la carta</button>
                </form>
              </div>
            </details>
          </div>
          <div class="pq-onb-item-campos">
            <div class="pq-campo">
              <label class="pq-label" for="p<?= $id ?>-cat">Categoría</label>
              <input form="pq-catalogo" class="pq-input" id="p<?= $id ?>-cat" type="text" name="items[<?= $id ?>][categoria]" value="<?= e($producto['categoria']) ?>" list="categorias-sugeridas" maxlength="60">
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="p<?= $id ?>-precio">Precio</label>
              <div class="pq-campo-dinero">
                <input form="pq-catalogo" class="pq-input pq-mono" id="p<?= $id ?>-precio" type="text" inputmode="numeric" name="items[<?= $id ?>][precio]" value="<?= number_format((int) $producto['precio'], 0, ',', '.') ?>" data-precio-cop required>
              </div>
            </div>
          </div>
          <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado pq-onb-item-estado">Agotado</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>

    <details class="pq-agregar-panel">
      <summary class="pq-btn pq-btn-ghost">+ Agregar un producto que faltó</summary>
      <form method="post" action="<?= e(base_url('/panel/productos')) ?>" class="pq-agregar-panel-form">
        <?= csrf_campo() ?>
        <input type="hidden" name="volver" value="/panel/onboarding/productos">
        <?php // crearProducto() lee estas dos casillas: sin ellas el producto nacía agotado y oculto. ?>
        <input type="hidden" name="disponible" value="1">
        <input type="hidden" name="visible" value="1">
        <div class="pq-campo">
          <label class="pq-label" for="nuevo-nombre">Nombre</label>
          <input class="pq-input" id="nuevo-nombre" type="text" name="nombre" required maxlength="120">
        </div>
        <div class="pq-onb-item-campos">
          <div class="pq-campo">
            <label class="pq-label" for="nuevo-cat">Categoría</label>
            <input class="pq-input" id="nuevo-cat" type="text" name="categoria" list="categorias-sugeridas" maxlength="60">
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="nuevo-precio">Precio</label>
            <div class="pq-campo-dinero">
              <input class="pq-input pq-mono" id="nuevo-precio" type="text" inputmode="numeric" name="precio" placeholder="0" data-precio-cop required>
            </div>
          </div>
        </div>
        <button type="submit" class="pq-btn pq-btn-ghost">Agregar a la carta</button>
      </form>
    </details>

    <div class="pq-onb-pie">
      <button type="submit" form="pq-catalogo" class="pq-btn pq-btn-sello">Guardar y continuar →</button>
      <p class="pq-ayuda pq-centro">Fotos y descripciones las agregas después, desde tu panel.</p>
    </div>
  <?php endif; ?>
</main>
