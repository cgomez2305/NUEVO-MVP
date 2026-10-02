<?php
/**
 * Lista editable de servicios del onboarding. Espera $servicios (array) y
 * $volver (ruta a la que regresar tras guardar). Revisar lo que detectó la
 * IA se siente como revisar una lista, no como administrar un catálogo:
 * fila compacta, menú ⋮ para lo secundario y el anticipo en un desplegable.
 * El panel tiene su propia vista (panel/servicios.php).
 */
?>
<div class="pq-stack" style="gap: 8px">
  <?php foreach ($servicios as $servicio): ?>
    <?php $agotado = (int) $servicio['agotado'] === 1; ?>
      <?php
        $formId = 'srv-form-' . $servicio['id'];
        $depositoResumen = 'Anticipo: sin anticipo';
        if ($servicio['deposito_tipo'] === 'porcentaje') {
            $depositoResumen = 'Anticipo: ' . ((int) $servicio['deposito_valor']) . '% del precio';
        } elseif ($servicio['deposito_tipo'] === 'monto_fijo') {
            $depositoResumen = 'Anticipo: ' . pesos((int) $servicio['deposito_valor']) . ' fijo';
        }
      ?>
      <div class="pq-card-borde pq-servicio-compacto<?= $agotado ? ' pq-servicio-compacto-agotado' : '' ?>">
        <div class="pq-servicio-compacto-fila">
          <span class="pq-servicio-compacto-punto" style="background: <?= e($servicio['color']) ?>" aria-hidden="true"></span>
          <form method="post" id="<?= e($formId) ?>" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/actualizar')) ?>" class="pq-servicio-compacto-nombre">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <input class="pq-input" type="text" name="nombre" value="<?= e($servicio['nombre']) ?>" required maxlength="120">
          </form>
          <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado">No disponible</span><?php endif; ?>
          <details class="pq-menu-kebab">
            <summary aria-label="Más opciones">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
            </summary>
            <div class="pq-menu-kebab-panel">
              <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/agotado')) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="volver" value="<?= e($volver) ?>">
                <button type="submit"><?= $agotado ? 'Marcar disponible' : 'Marcar no disponible' ?></button>
              </form>
              <hr>
              <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar «<?= e($servicio['nombre']) ?>» de tu catálogo? No se puede deshacer.">
                <?= csrf_campo() ?>
                <input type="hidden" name="volver" value="<?= e($volver) ?>">
                <button type="submit" class="pq-peligro">Eliminar</button>
              </form>
            </div>
          </details>
        </div>

        <div class="pq-servicio-compacto-campos">
          <div class="pq-campo-dinero">
            <input form="<?= e($formId) ?>" class="pq-input pq-mono" type="text" inputmode="numeric" name="precio" value="<?= number_format((int) $servicio['precio'], 0, ',', '.') ?>" data-precio-cop required>
          </div>
          <div class="pq-campo-sufijo" data-sufijo="min" style="width: 64px; flex-shrink: 0">
            <input form="<?= e($formId) ?>" class="pq-input pq-mono" type="number" name="duracion_min" value="<?= (int) $servicio['duracion_min'] ?>" min="5" step="5" required aria-label="Duración en minutos">
          </div>
          <button type="submit" form="<?= e($formId) ?>" class="pq-btn-icono" aria-label="Guardar cambios">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
          </button>
        </div>

        <details class="pq-servicio-anticipo">
          <summary><?= e($depositoResumen) ?> <span class="pq-servicio-anticipo-flecha" aria-hidden="true">›</span></summary>
          <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/deposito')) ?>" style="display: flex; gap: 8px; align-items: center; margin-top: 10px">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <select class="pq-select" name="deposito_tipo" style="flex-grow: 1; font-size: 12px">
              <option value="ninguno" <?= $servicio['deposito_tipo'] === 'ninguno' ? 'selected' : '' ?>>Sin anticipo</option>
              <option value="porcentaje" <?= $servicio['deposito_tipo'] === 'porcentaje' ? 'selected' : '' ?>>% del precio</option>
              <option value="monto_fijo" <?= $servicio['deposito_tipo'] === 'monto_fijo' ? 'selected' : '' ?>>Monto fijo ($)</option>
            </select>
            <input class="pq-input pq-mono" style="width: 80px" type="number" name="deposito_valor" value="<?= (int) $servicio['deposito_valor'] ?>" min="0" placeholder="0">
            <button type="submit" class="pq-btn-icono" aria-label="Guardar anticipo">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            </button>
          </form>
        </details>
      </div>
  <?php endforeach; ?>

  <?php if ($servicios === []): ?>
    <p class="pq-ayuda">Aún no tienes servicios en tu catálogo.</p>
  <?php endif; ?>
</div>

  <details class="pq-agregar-toggle" style="margin-top: 10px">
    <summary>+ Agregar servicio</summary>
    <form method="post" action="<?= e(base_url('/panel/servicios')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px">
      <?= csrf_campo() ?>
      <input type="hidden" name="volver" value="<?= e($volver) ?>">
      <input class="pq-input" type="text" name="nombre" placeholder="Nombre del servicio" required maxlength="120">
      <div style="display: flex; gap: 8px">
        <div class="pq-campo-dinero">
          <input class="pq-input pq-mono" type="text" inputmode="numeric" name="precio" placeholder="0" data-precio-cop required>
        </div>
        <div class="pq-campo-sufijo" data-sufijo="min" style="width: 90px; flex-shrink: 0">
          <input class="pq-input pq-mono" type="number" name="duracion_min" placeholder="Min" min="5" step="5" value="30" required aria-label="Duración en minutos">
        </div>
        <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">+ Agregar</button>
      </div>
    </form>
  </details>

