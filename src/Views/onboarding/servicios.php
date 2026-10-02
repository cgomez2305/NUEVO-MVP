<?php
$pasoActual = 2;
$volverUrl = '/panel/onboarding/foto';
require __DIR__ . '/_pasos.php';
$totalItems = count($servicios);
$cosa = 'servicio';
$queLee = 'tus servicios';
$aMano = $modo === 'a_mano';
?>
<main class="pq-onb-cuerpo" data-guardia-cambios="pq-catalogo">
  <?php if ($modo === 'leer'): ?>
    <?php require __DIR__ . '/_leer_foto.php'; ?>
  <?php else: ?>
    <?php if ($aMano): ?>
      <h1 class="pq-h1">Escribe tus servicios</h1>
      <p class="pq-lead pq-onb-bajada">Empieza por los que más te piden. Con 3 o 4 ya puedes abrir la agenda; el resto lo sumas después desde tu panel.</p>
    <?php else: ?>
      <h1 class="pq-h1">Revisa tus servicios</h1>
      <p class="pq-lead pq-onb-bajada"><?= $totalItems ?> servicio<?= $totalItems === 1 ? '' : 's' ?>. Corrige lo que haga falta: se guarda todo junto al final.</p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="pq-alerta pq-onb-aviso" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('/panel/onboarding/catalogo')) ?>" id="pq-catalogo" data-enviando="Guardando…">
      <?= csrf_campo() ?>
    </form>

    <?php if ($servicios !== []): ?>
      <ol class="pq-onb-lista">
        <?php foreach ($servicios as $servicio): ?>
          <?php
          $id = (int) $servicio['id'];
          $agotado = (int) $servicio['agotado'] === 1;
          $sinPrecio = (int) $servicio['precio'] === 0;
          ?>
          <li class="pq-onb-item<?= $agotado ? ' pq-onb-item-agotado' : '' ?><?= $sinPrecio ? ' pq-onb-item-falta' : '' ?>">
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
                  <input form="pq-catalogo" class="pq-input pq-mono" id="s<?= $id ?>-precio" type="text" inputmode="numeric" name="items[<?= $id ?>][precio]" value="<?= $sinPrecio ? '' : number_format((int) $servicio['precio'], 0, ',', '.') ?>" placeholder="0" data-precio-cop required<?= $sinPrecio ? ' aria-describedby="s' . $id . '-falta"' : '' ?>>
                </div>
              </div>
              <div class="pq-campo">
                <label class="pq-label" for="s<?= $id ?>-dur">Duración</label>
                <div class="pq-campo-sufijo" data-sufijo="min">
                  <input form="pq-catalogo" class="pq-input pq-mono" id="s<?= $id ?>-dur" type="number" name="items[<?= $id ?>][duracion_min]" value="<?= (int) $servicio['duracion_min'] ?>" min="5" max="480" step="5" required>
                </div>
              </div>
            </div>
            <?php if ($sinPrecio): ?><p class="pq-onb-item-nota" id="s<?= $id ?>-falta">No se alcanzaba a leer el precio en la foto.</p><?php endif; ?>
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
            <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado pq-onb-item-estado">Pausado</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <details class="pq-agregar-panel"<?= $aMano || $agregando ? ' open' : '' ?>>
      <summary class="pq-btn pq-btn-ghost">+ Agregar un servicio que faltó</summary>
      <form method="post" action="<?= e(base_url('/panel/servicios')) ?>" class="pq-agregar-panel-form">
        <?= csrf_campo() ?>
        <input type="hidden" name="volver" value="/panel/onboarding/productos?a_mano=1">
        <div class="pq-campo">
          <label class="pq-label" for="nuevo-nombre">Nombre</label>
          <input class="pq-input" id="nuevo-nombre" type="text" name="nombre" required maxlength="120" placeholder="<?= $aMano ? 'Ej: Corte y barba' : '' ?>"<?= $agregando ? ' autofocus' : '' ?>>
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
              <input class="pq-input pq-mono" id="nuevo-dur" type="number" name="duracion_min" min="5" max="480" step="5" value="30" required>
            </div>
          </div>
        </div>
        <button type="submit" class="pq-btn <?= $aMano ? 'pq-btn-sello' : 'pq-btn-ghost' ?>">Agregar a la lista</button>
      </form>
    </details>

    <?php if ($aMano): ?>
      <p class="pq-centro pq-onb-alterno">
        <a class="pq-enlace-sello" href="<?= e(base_url('/panel/onboarding/foto')) ?>"><?= empty($negocio['menu_foto']) ? 'Mejor le tomo foto a mi lista de precios' : 'Mejor que Veci lea una foto' ?></a>
      </p>
    <?php else: ?>
      <a href="<?= e(base_url('/panel/onboarding/foto')) ?>" class="pq-enlace-fila pq-onb-otra-foto">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
        <span>
          <strong>¿Tienes otra lista de precios?</strong>
          <span class="pq-ayuda">Tómale foto y Veci la suma a esta</span>
        </span>
        <svg class="pq-enlace-fila-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
      </a>

      <div class="pq-onb-pie">
        <button type="submit" form="pq-catalogo" class="pq-btn pq-btn-sello">Guardar y continuar →</button>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
