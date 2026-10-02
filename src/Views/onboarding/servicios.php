<?php
$pasoActual = 2;
$volverUrl = '/panel/onboarding/foto';
require __DIR__ . '/_pasos.php';
$esDueno = ($negocio['rol'] ?? 'dueno') === 'dueno';
?>
<main class="pq-onb-cuerpo" data-guardia-cambios="pq-catalogo">
  <?php if ($servicios === []): ?>
    <h1 class="pq-h1">Veci va a leer tus servicios</h1>
    <p class="pq-lead pq-onb-bajada">Encuentra servicios, precios y duración en tu foto y te arma la lista. Tú solo revisas.</p>

    <form method="post" action="<?= e(base_url('/panel/onboarding/analizar')) ?>" class="pq-onb-leer" data-enviando="Leyendo tus servicios…">
      <?= csrf_campo() ?>
      <div class="pq-onb-escaner">
        <img src="<?= e(base_url($negocio['menu_foto'])) ?>" alt="La foto de tus servicios">
        <span class="pq-onb-escaner-linea" aria-hidden="true"></span>
      </div>
      <button type="submit" class="pq-btn pq-btn-sello">Leer mis servicios</button>
      <p class="pq-ayuda pq-centro">Tarda unos segundos. No cierres esta pantalla.</p>
    </form>
  <?php else: ?>
    <h1 class="pq-h1">Revisa tus servicios</h1>
    <p class="pq-lead pq-onb-bajada">Encontramos <?= count($servicios) ?> servicio<?= count($servicios) === 1 ? '' : 's' ?>. Corrige lo que haga falta: se guarda todo junto al final.</p>

    <form method="post" action="<?= e(base_url('/panel/onboarding/catalogo')) ?>" id="pq-catalogo" data-enviando="Guardando…">
      <?= csrf_campo() ?>
    </form>

    <ol class="pq-onb-lista">
      <?php foreach ($servicios as $servicio): ?>
        <?php $id = (int) $servicio['id']; $agotado = (int) $servicio['agotado'] === 1; ?>
        <li class="pq-onb-item<?= $agotado ? ' pq-onb-item-agotado' : '' ?>">
          <div class="pq-onb-item-fila">
            <label class="pq-sr-solo" for="s<?= $id ?>-nombre">Nombre</label>
            <input form="pq-catalogo" class="pq-input pq-onb-item-nombre" id="s<?= $id ?>-nombre" type="text" name="items[<?= $id ?>][nombre]" value="<?= e($servicio['nombre']) ?>" required maxlength="120">
            <details class="pq-menu-kebab">
              <summary aria-label="Más opciones para <?= e($servicio['nombre']) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
              </summary>
              <div class="pq-menu-kebab-panel">
                <form method="post" action="<?= e(base_url('/panel/servicios/' . $id . '/agotado')) ?>">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="volver" value="/panel/onboarding/productos">
                  <button type="submit"><?= $agotado ? 'Volver a ofrecerlo' : 'Pausar (no disponible)' ?></button>
                </form>
                <hr>
                <form method="post" action="<?= e(base_url('/panel/servicios/' . $id . '/eliminar')) ?>" data-confirmar="¿Quitar «<?= e($servicio['nombre']) ?>» de tu lista?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="volver" value="/panel/onboarding/productos">
                  <button type="submit" class="pq-peligro">Quitar de la lista</button>
                </form>
              </div>
            </details>
          </div>
          <div class="pq-onb-item-campos">
            <div class="pq-campo">
              <label class="pq-label" for="s<?= $id ?>-precio">Precio</label>
              <div class="pq-campo-dinero">
                <input form="pq-catalogo" class="pq-input pq-mono" id="s<?= $id ?>-precio" type="text" inputmode="numeric" name="items[<?= $id ?>][precio]" value="<?= number_format((int) $servicio['precio'], 0, ',', '.') ?>" data-precio-cop required>
              </div>
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="s<?= $id ?>-dur">Duración</label>
              <div class="pq-campo-sufijo" data-sufijo="min">
                <input form="pq-catalogo" class="pq-input pq-mono" id="s<?= $id ?>-dur" type="number" name="items[<?= $id ?>][duracion_min]" value="<?= (int) $servicio['duracion_min'] ?>" min="5" step="5" required>
              </div>
            </div>
          </div>
          <?php if ($esDueno): ?>
            <details class="pq-onb-anticipo"<?= $servicio['deposito_tipo'] !== 'ninguno' ? ' open' : '' ?>>
              <summary>¿Pides anticipo para este servicio?</summary>
              <div class="pq-onb-item-campos">
                <select form="pq-catalogo" class="pq-select" name="items[<?= $id ?>][deposito_tipo]" aria-label="Tipo de anticipo">
                  <option value="ninguno" <?= $servicio['deposito_tipo'] === 'ninguno' ? 'selected' : '' ?>>Sin anticipo</option>
                  <option value="porcentaje" <?= $servicio['deposito_tipo'] === 'porcentaje' ? 'selected' : '' ?>>% del precio</option>
                  <option value="monto_fijo" <?= $servicio['deposito_tipo'] === 'monto_fijo' ? 'selected' : '' ?>>Monto fijo</option>
                </select>
                <input form="pq-catalogo" class="pq-input pq-mono" type="text" inputmode="numeric" name="items[<?= $id ?>][deposito_valor]" value="<?= (int) $servicio['deposito_valor'] ?: '' ?>" placeholder="0" aria-label="Valor del anticipo">
              </div>
            </details>
          <?php endif; ?>
          <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado pq-onb-item-estado">Pausado</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>

    <details class="pq-agregar-panel">
      <summary class="pq-btn pq-btn-ghost">+ Agregar un servicio que faltó</summary>
      <form method="post" action="<?= e(base_url('/panel/servicios')) ?>" class="pq-agregar-panel-form">
        <?= csrf_campo() ?>
        <input type="hidden" name="volver" value="/panel/onboarding/productos">
        <div class="pq-campo">
          <label class="pq-label" for="nuevo-nombre">Nombre</label>
          <input class="pq-input" id="nuevo-nombre" type="text" name="nombre" required maxlength="120">
        </div>
        <div class="pq-onb-item-campos">
          <div class="pq-campo">
            <label class="pq-label" for="nuevo-precio">Precio</label>
            <div class="pq-campo-dinero">
              <input class="pq-input pq-mono" id="nuevo-precio" type="text" inputmode="numeric" name="precio" placeholder="0" data-precio-cop required>
            </div>
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="nuevo-dur">Duración</label>
            <div class="pq-campo-sufijo" data-sufijo="min">
              <input class="pq-input pq-mono" id="nuevo-dur" type="number" name="duracion_min" min="5" step="5" value="30" required>
            </div>
          </div>
        </div>
        <button type="submit" class="pq-btn pq-btn-ghost">Agregar a la lista</button>
      </form>
    </details>

    <div class="pq-onb-pie">
      <button type="submit" form="pq-catalogo" class="pq-btn pq-btn-sello">Guardar y continuar →</button>
    </div>
  <?php endif; ?>
</main>
