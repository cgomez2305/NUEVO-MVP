<?php
/**
 * Parcial compartido entre el onboarding y el panel de servicios.
 * Espera $servicios (array) y $volver (ruta a la que regresar tras guardar).
 * $compacto (opcional, default false): en el onboarding se deja en true
 * para que revisar lo que detectó la IA se sienta como revisar una lista,
 * no como administrar un catálogo — fila compacta, menú ⋮ para las
 * acciones secundarias y el anticipo oculto detrás de un desplegable. El
 * panel usa el editor completo, sin cambios.
 */
$compacto = $compacto ?? false;
?>
<div class="pq-stack" style="gap: 8px">
  <?php foreach ($servicios as $servicio): ?>
    <?php $agotado = (int) $servicio['agotado'] === 1; ?>
    <?php if ($compacto): ?>
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
            <input form="<?= e($formId) ?>" class="pq-input pq-mono" type="number" name="precio" value="<?= (int) $servicio['precio'] ?>" min="0" step="500" required>
          </div>
          <input form="<?= e($formId) ?>" class="pq-input pq-mono" style="width: 64px" type="number" name="duracion_min" value="<?= (int) $servicio['duracion_min'] ?>" min="5" step="5" required aria-label="Duración en minutos">
          <span class="pq-ayuda">min</span>
          <button type="submit" form="<?= e($formId) ?>" class="pq-btn-icono" aria-label="Guardar cambios">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
          </button>
        </div>

        <details class="pq-servicio-anticipo">
          <summary><?= e($depositoResumen) ?></summary>
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
    <?php else: ?>
      <div class="pq-card-borde" style="display: flex; flex-direction: column; gap: 8px<?= $agotado ? '; opacity: .6' : '' ?>">
        <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/actualizar')) ?>"
              style="display: flex; flex-direction: column; gap: 8px">
          <?= csrf_campo() ?>
          <input type="hidden" name="volver" value="<?= e($volver) ?>">
          <div style="display: flex; gap: 8px; align-items: center">
            <span style="width: 12px; height: 12px; border-radius: 4px; background: <?= e($servicio['color']) ?>; flex-shrink: 0" aria-hidden="true"></span>
            <input class="pq-input" style="flex-grow: 1" type="text" name="nombre" value="<?= e($servicio['nombre']) ?>" required maxlength="120">
            <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado">No disponible</span><?php endif; ?>
          </div>
          <div style="display: flex; gap: 8px; align-items: flex-start">
            <div class="pq-campo-dinero">
              <input class="pq-input pq-mono" type="number" name="precio" value="<?= (int) $servicio['precio'] ?>" min="0" step="500" required>
            </div>
            <div style="width: 92px; flex-shrink: 0">
              <input class="pq-input pq-mono" type="number" name="duracion_min" value="<?= (int) $servicio['duracion_min'] ?>" min="5" step="5" required aria-label="Duración en minutos">
              <span class="pq-ayuda" style="display: block; text-align: center; margin-top: 2px">minutos</span>
            </div>
            <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Guardar</button>
          </div>
        </form>

        <div style="border-top: 1px dashed var(--borde); margin: 2px 0; padding-top: 10px">
          <span class="pq-ayuda" style="font-weight: 600; color: var(--gris-texto)">Anticipo para confirmar la cita (opcional)</span>
          <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/deposito')) ?>" style="display: flex; gap: 8px; align-items: center; margin-top: 6px">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <select class="pq-select" name="deposito_tipo" style="flex-grow: 1; font-size: 12px">
              <option value="ninguno" <?= $servicio['deposito_tipo'] === 'ninguno' ? 'selected' : '' ?>>Sin anticipo</option>
              <option value="porcentaje" <?= $servicio['deposito_tipo'] === 'porcentaje' ? 'selected' : '' ?>>% del precio</option>
              <option value="monto_fijo" <?= $servicio['deposito_tipo'] === 'monto_fijo' ? 'selected' : '' ?>>Monto fijo ($)</option>
            </select>
            <input class="pq-input pq-mono" style="width: 90px" type="number" name="deposito_valor" value="<?= (int) $servicio['deposito_valor'] ?>" min="0" placeholder="0">
            <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Guardar</button>
          </form>
          <?php if ($servicio['deposito_tipo'] !== 'ninguno'): ?>
            <span class="pq-ayuda" style="display: block; margin-top: 6px">Se le pedirá <?= pesos(\App\Models\Servicio::calcularAnticipo($servicio)) ?> de anticipo al reservar.</span>
          <?php endif; ?>
        </div>

        <div style="display: flex; gap: 16px; align-items: center; align-self: flex-end">
          <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/agotado')) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">
              <?= $agotado ? 'marcar disponible' : 'marcar no disponible' ?>
            </button>
          </form>
          <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar «<?= e($servicio['nombre']) ?>» de tu catálogo? No se puede deshacer.">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="<?= e($volver) ?>">
            <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">eliminar</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if ($servicios === []): ?>
    <p class="pq-ayuda">Aún no tienes servicios en tu catálogo.</p>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(base_url('/panel/servicios')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 16px">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">
  <input class="pq-input" type="text" name="nombre" placeholder="<?= $compacto ? 'Agregar otro servicio' : 'Nuevo servicio' ?>" required maxlength="120">
  <div style="display: flex; gap: 8px">
    <div class="pq-campo-dinero">
      <input class="pq-input pq-mono" type="number" name="precio" placeholder="0" min="0" step="500" required>
    </div>
    <input class="pq-input pq-mono" style="width: 90px" type="number" name="duracion_min" placeholder="Min" min="5" step="5" value="30" required aria-label="Duración en minutos">
    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">+ Agregar</button>
  </div>
</form>
