<div class="pq-topbar" style="border-bottom: none; padding-top: 20px">
  <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-mono" style="font-size: 11px; color: var(--gris-suave); text-decoration: none">‹ Seguir comprando</a>
</div>

<div class="pq-content-tienda" style="padding-top: 0">
  <h1 class="pq-tienda-nombre" style="font-size: 26px">Tu carrito</h1>
  <span class="pq-tienda-desc"><?= e(nombre_publico_sede($negocio)) ?></span>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($carrito['lineas'] === []): ?>
    <p class="pq-ayuda" style="margin-top: 24px">Aún no has agregado productos.</p>
    <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-btn pq-btn-oscuro" style="margin-top: 16px">Ver el menú →</a>
  <?php else: ?>

    <div style="margin-top: 16px" id="pq-carrito-lineas">
      <?php foreach ($carrito['lineas'] as $linea): $producto = $linea['producto']; ?>
        <div class="pq-fila-carrito pq-fila-carrito-producto" data-fila-producto="<?= (int) $producto['id'] ?>">
          <div class="pq-fila-carrito-icono" style="background: <?= e($producto['color']) ?>"></div>
          <div class="pq-stack" style="flex-grow: 1; gap: 6px">
            <span style="font-size: 14px; font-weight: 600"><?= e($producto['nombre']) ?></span>
            <span class="pq-mono" style="font-size: 12px; color: var(--gris-texto)"><?= pesos((int) $producto['precio']) ?> c/u</span>
            <div class="pq-stepper-fila">
              <div class="pq-stepper">
                <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/restar')) ?>" data-carrito-form="restar">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                  <button type="submit" class="pq-stepper-boton" aria-label="Quitar una unidad de <?= e($producto['nombre']) ?>">−</button>
                </form>
                <span class="pq-stepper-cantidad pq-mono" id="pq-cantidad-<?= (int) $producto['id'] ?>"><?= $linea['cantidad'] ?></span>
                <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/agregar')) ?>" data-carrito-form="agregar">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                  <input type="hidden" name="volver" value="carrito">
                  <button type="submit" class="pq-stepper-boton" aria-label="Agregar una unidad de <?= e($producto['nombre']) ?>">+</button>
                </form>
              </div>
              <span class="pq-mono" style="font-size: 13px; font-weight: 600" id="pq-subtotal-<?= (int) $producto['id'] ?>"><?= pesos((int) $producto['precio'] * $linea['cantidad']) ?></span>
            </div>
          </div>
          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/quitar')) ?>" data-carrito-form="quitar" class="pq-fila-carrito-eliminar">
            <?= csrf_campo() ?>
            <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
            <button type="submit" aria-label="Eliminar <?= e($producto['nombre']) ?> del carrito">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>

      <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #C9BFA4">
        <span>Total</span>
        <span class="pq-mono" id="pq-carrito-total"><?= pesos($carrito['total']) ?></span>
      </div>
      <p class="pq-ayuda" data-mostrar-si="tipo_entrega=domicilio" style="margin-top: 6px">Si el domicilio tiene costo, te lo confirman por WhatsApp — no está incluido arriba.</p>
    </div>

    <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/pedido')) ?>" style="margin-top: 8px" id="pq-form-pedido">
      <?= csrf_campo() ?>

      <h2 class="pq-seccion-checkout">Tus datos</h2>

      <div class="pq-campo">
        <label class="pq-label" for="nombre">Tu nombre</label>
        <input class="pq-input" type="text" id="nombre" name="nombre" required maxlength="120" autocomplete="name">
      </div>

      <div class="pq-campo">
        <label class="pq-label" for="telefono">Tu WhatsApp</label>
        <div class="pq-input-telefono">
          <span class="pq-input-telefono-prefijo pq-mono">🇨🇴 +57</span>
          <input class="pq-input pq-mono" type="tel" inputmode="numeric" id="telefono" name="telefono" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
        </div>
        <span class="pq-ayuda">Lo usamos para identificar tu pedido y avisarte si algo cambia.</span>
      </div>

      <h2 class="pq-seccion-checkout">Entrega</h2>

      <div class="pq-campo">
        <label class="pq-label">¿Cómo lo recibes?</label>
        <div class="pq-opciones-entrega">
          <label class="pq-opcion-entrega">
            <input type="radio" name="tipo_entrega" value="domicilio" checked>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.4 7-11.5a7 7 0 1 0-14 0C5 14.6 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.6"/></svg>
            Domicilio
          </label>
          <label class="pq-opcion-entrega">
            <input type="radio" name="tipo_entrega" value="recoger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
            Recoger en tienda
          </label>
          <?php if (!empty($negocio['acepta_mesa'])): ?>
            <label class="pq-opcion-entrega">
              <input type="radio" name="tipo_entrega" value="mesa">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
              Comer aquí
            </label>
          <?php endif; ?>
        </div>
      </div>

      <div data-mostrar-si="tipo_entrega=domicilio">
        <div class="pq-campo">
          <label class="pq-label" for="direccion">Dirección</label>
          <input class="pq-input" type="text" id="direccion" name="direccion" placeholder="Calle 34 #20-15" maxlength="200" data-requerido-si-visible autocomplete="address-line1">
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="referencia">Barrio o referencia (opcional)</label>
          <input class="pq-input" type="text" id="referencia" name="referencia" placeholder="Edificio azul, apto 302" maxlength="150" autocomplete="address-line2">
        </div>
      </div>

      <div class="pq-campo" data-mostrar-si="tipo_entrega=recoger">
        <span class="pq-label">Recoges en</span>
        <span style="font-size: 14px; display: block; margin-top: 2px"><?= e(nombre_publico_sede($negocio)) ?></span>
      </div>

      <?php if (!empty($negocio['acepta_mesa'])): ?>
        <div class="pq-campo" data-mostrar-si="tipo_entrega=mesa">
          <label class="pq-label" for="mesa">Número de mesa</label>
          <input class="pq-input" type="text" id="mesa" name="mesa" placeholder="Ej. 4" maxlength="20" data-requerido-si-visible>
        </div>
      <?php endif; ?>

      <div class="pq-campo">
        <label class="pq-label" for="notas">Indicaciones adicionales (opcional)</label>
        <textarea class="pq-input" id="notas" name="notas" rows="2" placeholder="Instrucciones para tu pedido..." maxlength="255"></textarea>
      </div>

      <h2 class="pq-seccion-checkout">Pago</h2>

      <div class="pq-campo">
        <label class="pq-label">Forma de pago</label>
        <div class="pq-opciones-entrega">
          <label class="pq-opcion-entrega">
            <input type="radio" name="metodo_pago" value="breb" checked>
            <svg class="pq-opcion-entrega-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/></svg>
            Bre-B
          </label>
          <label class="pq-opcion-entrega">
            <input type="radio" name="metodo_pago" value="nequi">
            <svg class="pq-opcion-entrega-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
            Nequi
          </label>
          <label class="pq-opcion-entrega">
            <input type="radio" name="metodo_pago" value="efectivo">
            <svg class="pq-opcion-entrega-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
            Efectivo
          </label>
        </div>
      </div>

      <div class="pq-stack" style="gap: 14px; margin: 20px 0 4px">
        <label class="pq-consentimiento">
          <input type="checkbox" name="autorizo_datos" value="1" required>
          <span>
            <span class="pq-consentimiento-titulo">Uso de datos para gestionar tu pedido</span>
            <span class="pq-ayuda" style="margin-top: 1px">Necesario para procesar y avisarte sobre este pedido.</span>
          </span>
        </label>
        <label class="pq-consentimiento">
          <input type="checkbox" name="acepta_marketing" value="1">
          <span>
            <span class="pq-consentimiento-titulo">Quiero recibir promociones por WhatsApp</span>
            <span class="pq-ayuda" style="margin-top: 1px">Opcional.</span>
          </span>
        </label>
        <span class="pq-ayuda">Al continuar aceptas nuestra <a href="https://tuveci.co/privacidad.html" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline">política de privacidad</a>.</span>
      </div>

      <div class="pq-checkout-sticky">
        <div class="pq-stack" style="gap: 0">
          <span class="pq-mono" style="font-size: 10px; color: var(--gris-suave)">TOTAL</span>
          <span class="pq-mono" style="font-size: 16px; font-weight: 700" id="pq-checkout-sticky-total"><?= pesos($carrito['total']) ?></span>
        </div>
        <button type="submit" class="pq-btn pq-btn-whatsapp" style="width: auto; flex-grow: 1; max-width: 260px">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
          Confirmar por WhatsApp
        </button>
      </div>
      <p class="pq-ayuda pq-centro" style="margin-top: 10px">Revisarás el pedido en WhatsApp antes de enviarlo.</p>
    </form>

  <?php endif; ?>
</div>
