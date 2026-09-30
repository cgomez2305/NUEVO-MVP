<?php
/**
 * Alta/edición de UN servicio, en su propia página (antes vivía como
 * formulario abierto dentro de la tarjeta, en servicios/_gestor.php).
 * $servicio es null cuando se está creando uno nuevo — ahí no se pide
 * anticipo todavía, igual que antes (el anticipo solo aparecía para
 * servicios ya existentes).
 */
$esNuevo = $servicio === null;
$volver = '/panel/servicios';
?>
<a href="<?= e(base_url($volver)) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Servicios</a>

<div style="margin-top: 16px">
  <span class="pq-eyebrow"><?= $esNuevo ? 'Nuevo servicio' : 'Editar servicio' ?></span>
  <h1 class="pq-h1" style="font-size: 26px"><?= $esNuevo ? 'Agrega un servicio' : e($servicio['nombre']) ?></h1>
</div>

<form method="post"
      action="<?= e(base_url($esNuevo ? '/panel/servicios' : '/panel/servicios/' . $servicio['id'] . '/actualizar')) ?>"
      class="pq-card-borde" style="margin-top: 18px; display: flex; flex-direction: column; gap: 16px">
  <?= csrf_campo() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="campo-nombre">
      <span style="display: inline-flex; align-items: center; gap: 8px">
        <span style="width: 11px; height: 11px; border-radius: 4px; background: <?= e($servicio['color'] ?? '#5B7F3A') ?>; flex-shrink: 0" aria-hidden="true"></span>
        Nombre
      </span>
    </label>
    <input class="pq-input" id="campo-nombre" type="text" name="nombre" value="<?= e($servicio['nombre'] ?? '') ?>" required maxlength="120">
  </div>

  <div style="display: flex; gap: 12px">
    <div class="pq-campo" style="margin-bottom: 0; flex: 1; min-width: 0">
      <label class="pq-label" for="campo-precio">Precio</label>
      <div class="pq-campo-dinero">
        <input class="pq-input pq-mono" id="campo-precio" type="number" name="precio" value="<?= (int) ($servicio['precio'] ?? 0) ?>" min="0" step="500" required>
      </div>
    </div>
    <div class="pq-campo" style="margin-bottom: 0; width: 120px; flex-shrink: 0">
      <label class="pq-label" for="campo-duracion">Duración</label>
      <input class="pq-input pq-mono" id="campo-duracion" type="number" name="duracion_min" value="<?= (int) ($servicio['duracion_min'] ?? 30) ?>" min="5" step="5" required aria-describedby="ayuda-duracion">
      <span class="pq-ayuda" id="ayuda-duracion">minutos</span>
    </div>
  </div>

  <button type="submit" class="pq-btn pq-btn-sello"><?= $esNuevo ? 'Crear servicio' : 'Guardar cambios' ?></button>
</form>

<?php if (!$esNuevo): ?>
  <div class="pq-card-borde" style="margin-top: 14px">
    <span class="pq-eyebrow">Anticipo para confirmar la cita</span>
    <p class="pq-ayuda" style="margin-top: 4px">Opcional. Si lo activas, el cliente paga una parte al reservar.</p>
    <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/deposito')) ?>" style="display: flex; gap: 8px; align-items: center; margin-top: 10px; flex-wrap: wrap">
      <?= csrf_campo() ?>
      <input type="hidden" name="volver" value="/panel/servicios/<?= (int) $servicio['id'] ?>/editar">
      <select class="pq-select" name="deposito_tipo" style="flex: 1 1 160px; min-width: 0">
        <option value="ninguno" <?= $servicio['deposito_tipo'] === 'ninguno' ? 'selected' : '' ?>>Sin anticipo</option>
        <option value="porcentaje" <?= $servicio['deposito_tipo'] === 'porcentaje' ? 'selected' : '' ?>>% del precio</option>
        <option value="monto_fijo" <?= $servicio['deposito_tipo'] === 'monto_fijo' ? 'selected' : '' ?>>Monto fijo ($)</option>
      </select>
      <input class="pq-input pq-mono" style="width: 100px; flex-shrink: 0" type="number" name="deposito_valor" value="<?= (int) $servicio['deposito_valor'] ?>" min="0" placeholder="0">
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Guardar</button>
    </form>
    <?php if ($servicio['deposito_tipo'] !== 'ninguno'): ?>
      <span class="pq-ayuda" style="display: block; margin-top: 8px">
        Se le pedirá <?= pesos(\App\Models\Servicio::calcularAnticipo($servicio)) ?> de anticipo al reservar.
      </span>
    <?php endif; ?>
  </div>

  <div class="pq-card-borde" style="margin-top: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap">
    <div>
      <span style="font-weight: 600; font-size: 13px; display: block">
        <?= ((int) $servicio['agotado'] === 1) ? 'Marcado como no disponible' : 'Disponible en tu tienda' ?>
      </span>
      <span class="pq-ayuda">Un servicio no disponible se sigue viendo en tu tienda, pero nadie lo puede reservar.</span>
    </div>
    <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/agotado')) ?>">
      <?= csrf_campo() ?>
      <input type="hidden" name="volver" value="/panel/servicios/<?= (int) $servicio['id'] ?>/editar">
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">
        <?= ((int) $servicio['agotado'] === 1) ? 'Marcar disponible' : 'Marcar no disponible' ?>
      </button>
    </form>
  </div>

  <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/eliminar')) ?>"
        data-confirmar="¿Eliminar «<?= e($servicio['nombre']) ?>» de tu catálogo? No se puede deshacer." style="margin-top: 16px; text-align: center">
    <?= csrf_campo() ?>
    <input type="hidden" name="volver" value="<?= e($volver) ?>">
    <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 12px; cursor: pointer; padding: 0">eliminar servicio</button>
  </form>
<?php endif; ?>
