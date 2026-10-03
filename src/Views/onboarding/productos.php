<?php
$pasoActual = 2;
$volverUrl = '/panel/onboarding/foto';
require __DIR__ . '/_pasos.php';
$categorias = array_values(array_unique(array_map(fn ($p) => (string) $p['categoria'], $productos)));
$totalItems = count($productos);
$cosa = 'producto';
$queLee = 'tu menú';
$aMano = $modo === 'a_mano';
?>
<main class="pq-onb-cuerpo" data-guardia-cambios="pq-catalogo">
  <?php if ($modo === 'leer'): ?>
    <?php require __DIR__ . '/_leer_foto.php'; ?>
  <?php else: ?>
    <?php if ($aMano): ?>
      <h1 class="pq-h1">Escribe tu carta</h1>
      <p class="pq-lead pq-onb-bajada">Empieza por lo que más vendes: con 5 o 6 productos ya puedes abrir. El resto lo sumas después desde tu panel.</p>
    <?php else: ?>
      <h1 class="pq-h1">Revisa tu carta</h1>
      <p class="pq-lead pq-onb-bajada"><?= $totalItems ?> producto<?= $totalItems === 1 ? '' : 's' ?>. Corrige lo que haga falta: se guarda todo junto al final.</p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="pq-alerta pq-onb-aviso" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

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

    <?php if ($productos !== []): ?>
      <ol class="pq-onb-lista">
        <?php foreach ($productos as $producto): ?>
          <?php
          $id = (int) $producto['id'];
          $agotado = (int) $producto['agotado'] === 1;
          // La lectura deja el precio en 0 cuando no se veía en la foto: el
          // campo sale vacío y marcado, en vez de un "0" que parece regalado.
          $sinPrecio = (int) $producto['precio'] === 0;
          ?>
          <li class="pq-onb-item<?= $agotado ? ' pq-onb-item-agotado' : '' ?><?= $sinPrecio ? ' pq-onb-item-falta' : '' ?>">
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
                  <input form="pq-catalogo" class="pq-input pq-mono" id="p<?= $id ?>-precio" type="text" inputmode="numeric" name="items[<?= $id ?>][precio]" value="<?= $sinPrecio ? '' : number_format((int) $producto['precio'], 0, ',', '.') ?>" placeholder="0" data-precio-cop required<?= $sinPrecio ? ' aria-describedby="p' . $id . '-falta"' : '' ?>>
                </div>
              </div>
            </div>
            <?php if ($sinPrecio): ?><p class="pq-onb-item-nota" id="p<?= $id ?>-falta">No se alcanzaba a leer el precio en la foto.</p><?php endif; ?>
            <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado pq-onb-item-estado">Agotado</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <?php
    // Escribiendo a mano el formulario va abierto y vuelve abierto (con el
    // cursor en el nombre) después de cada producto: se cargan seguidos.
    ?>
    <details class="pq-agregar-panel"<?= $aMano || $agregando ? ' open' : '' ?>>
      <summary class="pq-btn pq-btn-ghost">+ Agregar un producto que faltó</summary>
      <form method="post" action="<?= e(base_url('/panel/productos')) ?>" class="pq-agregar-panel-form">
        <?= csrf_campo() ?>
        <input type="hidden" name="volver" value="/panel/onboarding/productos?a_mano=1">
        <?php // crearProducto() lee estas dos casillas: sin ellas el producto nacía agotado y oculto. ?>
        <input type="hidden" name="disponible" value="1">
        <input type="hidden" name="visible" value="1">
        <div class="pq-campo">
          <label class="pq-label" for="nuevo-nombre">Nombre</label>
          <input class="pq-input" id="nuevo-nombre" type="text" name="nombre" required maxlength="120" placeholder="<?= $aMano ? 'Ej: Arepa de choclo' : '' ?>"<?= $agregando ? ' autofocus' : '' ?>>
        </div>
        <div class="pq-onb-item-campos">
          <div class="pq-campo">
            <label class="pq-label" for="nuevo-cat">Categoría</label>
            <input class="pq-input" id="nuevo-cat" type="text" name="categoria" list="categorias-sugeridas" maxlength="60" placeholder="<?= $aMano ? 'Ej: Desayunos' : '' ?>">
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="nuevo-precio">Precio</label>
            <div class="pq-campo-dinero">
              <input class="pq-input pq-mono" id="nuevo-precio" type="text" inputmode="numeric" name="precio" placeholder="0" data-precio-cop required>
            </div>
          </div>
        </div>
        <button type="submit" class="pq-btn <?= $aMano ? 'pq-btn-sello' : 'pq-btn-ghost' ?>">Agregar a la carta</button>
      </form>
    </details>

    <?php if ($aMano): ?>
      <p class="pq-centro pq-onb-alterno">
        <a class="pq-enlace-sello" href="<?= e(base_url('/panel/onboarding/foto')) ?>"><?= empty($negocio['menu_foto']) ? 'Mejor le tomo foto a mi menú' : 'Mejor que Veci lea una foto' ?></a>
      </p>
    <?php else: ?>
      <a href="<?= e(base_url('/panel/onboarding/foto')) ?>" class="pq-enlace-fila pq-onb-otra-foto">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
        <span>
          <strong>¿Tu menú tiene otra página?</strong>
          <span class="pq-ayuda">Tómale foto y Veci la suma a esta carta</span>
        </span>
        <svg class="pq-enlace-fila-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
      </a>

      <div class="pq-onb-pie">
        <button type="submit" form="pq-catalogo" class="pq-btn pq-btn-sello">Guardar y continuar →</button>
        <p class="pq-ayuda pq-centro">Fotos y descripciones las agregas después, desde tu panel.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
