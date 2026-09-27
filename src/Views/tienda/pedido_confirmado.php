<div class="pq-content-tienda" style="padding-top: 24px">

  <div class="pq-centro" style="margin-bottom: 20px">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#16A36A" stroke-width="2.2" style="margin: 0 auto 10px"><path d="M5 13l5 5L20 7"/></svg>
    <h1 class="pq-tienda-nombre" style="font-size: 24px">Tu pedido está listo</h1>
    <span class="pq-tienda-desc">Pedido #<?= (int) $pedido['id'] ?> · <?= e($negocio['nombre']) ?></span>
  </div>

  <div class="pq-card" style="background: #FFFFFF; border: 1px solid #E7E0CF">
    <?php foreach ($items as $item): ?>
      <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0">
        <span><?= $item['cantidad'] ?> × <?= e($item['nombre']) ?></span>
        <span class="pq-mono"><?= pesos($item['precio'] * $item['cantidad']) ?></span>
      </div>
    <?php endforeach; ?>
    <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 700; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #C9BFA4">
      <span>Total</span>
      <span class="pq-mono"><?= pesos((int) $pedido['total']) ?></span>
    </div>
  </div>

  <div class="pq-card" style="margin-top: 14px; display: flex; flex-direction: column; gap: 6px">
    <span class="pq-mono" style="font-size: 11px; color: var(--gris-texto)">PAGA POR <?= strtoupper(e($pedido['metodo_pago'])) ?></span>
    <?php if ($pedido['metodo_pago'] === 'efectivo'): ?>
      <span style="font-size: 14px">Paga en efectivo cuando te entreguen el pedido.</span>
    <?php else: ?>
      <span style="font-size: 14px">Llave: <strong><?= e($negocio['llave_breb_valor'] ?? $negocio['whatsapp']) ?></strong></span>
      <span class="pq-ayuda">Envía el comprobante por WhatsApp para que confirmen tu pedido.</span>
    <?php endif; ?>
  </div>

  <a href="<?= e($enlaceWhatsapp) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-whatsapp" style="margin-top: 20px">
    <svg width="19" height="19" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
    Confirmar por WhatsApp
  </a>
  <p class="pq-ayuda pq-centro" style="margin-top: 10px">Se abre WhatsApp con tu pedido ya escrito, listo para enviar a <?= e($negocio['nombre']) ?>.</p>

  <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-mono pq-centro" style="display: block; margin-top: 20px; font-size: 12px; color: var(--gris-suave)">volver a la tienda</a>

</div>
