<div class="pq-topbar" style="border-bottom: none; padding-top: 20px">
  <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-mono" style="font-size: 11px; color: var(--gris-suave); text-decoration: none">‹ seguir comprando</a>
</div>

<div class="pq-content-tienda" style="padding-top: 0">
  <h1 class="pq-tienda-nombre" style="font-size: 26px">Tu carrito</h1>
  <span class="pq-tienda-desc"><?= e($negocio['nombre']) ?></span>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($carrito['lineas'] === []): ?>
    <p class="pq-ayuda" style="margin-top: 24px">Aún no has agregado productos.</p>
    <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-btn pq-btn-oscuro" style="margin-top: 16px">Ver el menú →</a>
  <?php else: ?>

    <div style="margin-top: 16px">
      <?php foreach ($carrito['lineas'] as $linea): $producto = $linea['producto']; ?>
        <div class="pq-fila-carrito">
          <div class="pq-fila-carrito-icono" style="background: <?= e($producto['color']) ?>"></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600"><?= e($producto['nombre']) ?></span>
            <span class="pq-mono" style="font-size: 12px; color: var(--gris-texto)"><?= $linea['cantidad'] ?> × <?= pesos((int) $producto['precio']) ?></span>
          </div>
          <span class="pq-mono" style="font-size: 13px"><?= pesos((int) $producto['precio'] * $linea['cantidad']) ?></span>
          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/quitar')) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
            <button type="submit" aria-label="Quitar <?= e($producto['nombre']) ?>" style="background: none; border: none; color: var(--gris-suave); font-size: 18px; cursor: pointer; line-height: 1">×</button>
          </form>
        </div>
      <?php endforeach; ?>

      <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #C9BFA4">
        <span>Total</span>
        <span class="pq-mono"><?= pesos($carrito['total']) ?></span>
      </div>
    </div>

    <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/pedido')) ?>" style="margin-top: 24px">
      <?= csrf_campo() ?>

      <div class="pq-campo">
        <label class="pq-label" for="nombre">Tu nombre</label>
        <input class="pq-input" type="text" id="nombre" name="nombre" required maxlength="120">
      </div>

      <div class="pq-campo">
        <label class="pq-label" for="telefono">Tu WhatsApp</label>
        <input class="pq-input" type="tel" id="telefono" name="telefono" placeholder="300 123 4567" required maxlength="20">
      </div>

      <div class="pq-campo">
        <label class="pq-label">Cómo lo recibes</label>
        <div class="pq-opciones-entrega">
          <label class="pq-opcion-entrega">
            <input type="radio" name="tipo_entrega" value="domicilio" checked>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.4 7-11.5a7 7 0 1 0-14 0C5 14.6 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.6"/></svg>
            Domicilio
          </label>
          <label class="pq-opcion-entrega">
            <input type="radio" name="tipo_entrega" value="recoger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
            Recoger en el local
          </label>
        </div>
      </div>

      <div class="pq-campo" data-mostrar-si="tipo_entrega=domicilio">
        <label class="pq-label" for="direccion">Dirección de entrega</label>
        <textarea class="pq-input" id="direccion" name="direccion" rows="2" placeholder="Calle, número, barrio, referencia..." data-requerido-si-visible></textarea>
      </div>

      <div class="pq-campo">
        <label class="pq-label" for="metodo_pago">Cómo vas a pagar</label>
        <select class="pq-select" id="metodo_pago" name="metodo_pago">
          <option value="breb">Bre-B</option>
          <option value="nequi">Nequi</option>
          <option value="efectivo">Efectivo contra entrega</option>
        </select>
      </div>

      <label style="display: flex; gap: 10px; align-items: flex-start; font-size: 12px; color: var(--gris-texto); margin-bottom: 20px; line-height: 1.5">
        <input type="checkbox" name="autorizo_datos" value="1" required style="margin-top: 3px">
        Autorizo a <?= e($negocio['nombre']) ?> a guardar mi nombre y WhatsApp para procesar este pedido y avisarme de futuras promociones, según la Ley 1581 de 2012 y la <a href="https://tuveci.co/privacidad.html" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline">política de privacidad</a>.
      </label>

      <button type="submit" class="pq-btn pq-btn-whatsapp">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
        Pedir por WhatsApp
      </button>
    </form>

  <?php endif; ?>
</div>
