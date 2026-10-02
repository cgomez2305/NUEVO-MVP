<a href="<?= e(base_url('/panel/sedes')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Sedes
</a>

<span class="pq-eyebrow pq-eyebrow-tras-volver">Editar sede</span>
<h1 class="pq-h1"><?= e($sede['nombre']) ?></h1>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/panel/sedes/' . $sede['id'] . '/actualizar')) ?>" class="pq-card pq-form-panel">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="nombre">Nombre de la sede</label>
    <input class="pq-input" type="text" id="nombre" name="nombre" value="<?= e($sede['nombre']) ?>" required maxlength="120">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="whatsapp">WhatsApp para pedidos de esta sede</label>
    <input class="pq-input pq-mono" type="tel" inputmode="tel" id="whatsapp" name="whatsapp" value="<?= e($sede['whatsapp']) ?>" placeholder="300 000 0000" required maxlength="20">
    <span class="pq-ayuda">Aquí llegan los pedidos y reservas de esta sede.</span>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="direccion">Dirección <span class="pq-ayuda">(opcional)</span></label>
    <input class="pq-input" type="text" id="direccion" name="direccion" value="<?= e($sede['direccion'] ?? '') ?>" placeholder="Cra 15 #8-20, Barrio Centro" maxlength="200">
    <span class="pq-ayuda">Sale en tu tienda con un enlace a Google Maps. Déjala vacía si solo atiendes a domicilio.</span>
  </div>

  <?php $llaveTipoSede = $sede['llave_breb_tipo'] ?? 'celular'; ?>
  <div class="pq-campo">
    <label class="pq-label" for="llave_valor">Llave Bre-B donde te pagan</label>
    <div class="pq-onb-item-campos pq-llave-fila">
      <select class="pq-select" name="llave_tipo" aria-label="Tipo de llave" data-llave-tipo-input>
        <?php foreach (['celular' => 'Celular', 'cedula' => 'Cédula', 'correo' => 'Correo'] as $valorTipo => $textoTipo): ?>
          <option value="<?= $valorTipo ?>"<?= $llaveTipoSede === $valorTipo ? ' selected' : '' ?>><?= $textoTipo ?></option>
        <?php endforeach; ?>
      </select>
      <input class="pq-input pq-mono<?= !empty($error) ? ' pq-input-invalido' : '' ?>" type="<?= $llaveTipoSede === 'correo' ? 'email' : 'tel' ?>" inputmode="<?= $llaveTipoSede === 'correo' ? 'email' : 'numeric' ?>" id="llave_valor" name="llave_valor" value="<?= e($sede['llave_breb_valor'] ?? $sede['whatsapp']) ?>" maxlength="120" autocomplete="off" required data-llave-valor-input>
    </div>
    <span class="pq-ayuda">Aquí te transfieren tus clientes. Revísala bien: si está mal, el pago rebota.</span>
  </div>

  <?php $colorActualMarca = strtoupper(color_seguro($sede['color_marca'] ?? null)); ?>
  <fieldset class="pq-onb-colores">
    <legend class="pq-label">Color de tu toldo <span class="pq-ayuda">(es el mismo en todas tus sedes)</span></legend>
    <div class="pq-onb-colores-lista">
      <?php foreach (paleta_marca() as $hex => $nombreColor): ?>
        <label class="pq-onb-color" style="--muestra: <?= e($hex) ?>">
          <input type="radio" name="color_marca" value="<?= e($hex) ?>"<?= $colorActualMarca === $hex ? ' checked' : '' ?>>
          <span class="pq-onb-color-muestra" aria-hidden="true"></span>
          <span class="pq-onb-color-nombre"><?= e($nombreColor) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <?php if ($sede['tipo_negocio'] === 'pedidos'): ?>
    <label class="pq-interruptor pq-interruptor-con-texto">
      <input type="checkbox" name="acepta_mesa" value="1"<?= (int) ($sede['acepta_mesa'] ?? 1) === 1 ? ' checked' : '' ?>>
      <span class="pq-interruptor-pista" aria-hidden="true"></span>
      <span>
        <strong>Ofrecer "Para comer aquí"</strong>
        <span class="pq-ayuda">Apágalo si no tienes mesas: tus clientes dejarán de ver esa opción al pedir.</span>
      </span>
    </label>
  <?php endif; ?>

  <div class="pq-form-panel-botones">
    <a href="<?= e(base_url('/panel/sedes')) ?>" class="pq-btn pq-btn-ghost">Cancelar</a>
    <button type="submit" class="pq-btn pq-btn-sello">Guardar cambios</button>
  </div>
</form>
