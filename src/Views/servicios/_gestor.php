<?php
/**
 * Parcial compartido entre el onboarding y el panel de servicios.
 * Espera $servicios (array) y $volver (ruta a la que regresar tras guardar).
 */
?>
<div class="pq-stack" style="gap: 8px">
  <?php foreach ($servicios as $servicio): ?>
    <?php $agotado = (int) $servicio['agotado'] === 1; ?>
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
        <div style="display: flex; gap: 8px">
          <input class="pq-input pq-mono" style="flex-grow: 1" type="number" name="precio" value="<?= (int) $servicio['precio'] ?>" min="0" step="500" required>
          <input class="pq-input pq-mono" style="width: 90px" type="number" name="duracion_min" value="<?= (int) $servicio['duracion_min'] ?>" min="5" step="5" required title="Duración en minutos">
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Guardar</button>
        </div>
        <span class="pq-ayuda">Duración en minutos</span>
      </form>
      <div style="display: flex; gap: 16px; align-items: center; align-self: flex-end">
        <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/agotado')) ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="volver" value="<?= e($volver) ?>">
          <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">
            <?= $agotado ? 'marcar disponible' : 'marcar no disponible' ?>
          </button>
        </form>
        <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/eliminar')) ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="volver" value="<?= e($volver) ?>">
          <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">eliminar</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($servicios === []): ?>
    <p class="pq-ayuda">Aún no tienes servicios en tu catálogo.</p>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(base_url('/panel/servicios')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 16px">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">
  <input class="pq-input" type="text" name="nombre" placeholder="Nuevo servicio" required maxlength="120">
  <div style="display: flex; gap: 8px">
    <input class="pq-input pq-mono" style="flex-grow: 1" type="number" name="precio" placeholder="Precio" min="0" step="500" required>
    <input class="pq-input pq-mono" style="width: 90px" type="number" name="duracion_min" placeholder="Min" min="5" step="5" value="30" required>
    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">+ Agregar</button>
  </div>
</form>
