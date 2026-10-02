<a href="<?= e(base_url('/panel/sedes')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Sedes
</a>

<span class="pq-eyebrow pq-eyebrow-tras-volver">Editar sede</span>
<h1 class="pq-h1"><?= e($sede['nombre']) ?></h1>

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
