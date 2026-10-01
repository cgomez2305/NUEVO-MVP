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
    <span class="pq-mono" style="font-size: 11px; color: var(--gris-texto)">
      <?php if ($pedido['tipo_entrega'] === 'recoger'): ?>RECOGES EN EL LOCAL
      <?php elseif ($pedido['tipo_entrega'] === 'mesa'): ?>PARA COMER AQUÍ
      <?php else: ?>TE LO LLEVAMOS A<?php endif; ?>
    </span>
    <?php if ($pedido['tipo_entrega'] === 'recoger'): ?>
      <span style="font-size: 14px"><?= e($negocio['nombre']) ?></span>
    <?php elseif ($pedido['tipo_entrega'] === 'mesa'): ?>
      <span style="font-size: 14px">Mesa <?= e($pedido['mesa']) ?></span>
    <?php else: ?>
      <span style="font-size: 14px"><?= e($pedido['direccion']) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!empty($pedido['notas'])): ?>
    <div class="pq-card" style="margin-top: 14px; display: flex; flex-direction: column; gap: 6px">
      <span class="pq-mono" style="font-size: 11px; color: var(--gris-texto)">NOTA DEL PEDIDO</span>
      <span style="font-size: 14px"><?= e($pedido['notas']) ?></span>
    </div>
  <?php endif; ?>

  <div class="pq-card" style="margin-top: 14px; display: flex; flex-direction: column; gap: 6px">
    <span class="pq-mono" style="font-size: 11px; color: var(--gris-texto)">PAGA POR <?= strtoupper(e($pedido['metodo_pago'])) ?></span>
    <?php if ($pedido['metodo_pago'] === 'efectivo'): ?>
      <span style="font-size: 14px">Paga en efectivo cuando te entreguen el pedido.</span>
    <?php else: ?>
      <?php $llave = (string) ($negocio['llave_breb_valor'] ?? $negocio['whatsapp']); ?>
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px">
        <span style="font-size: 14px">Llave: <strong><?= e($llave) ?></strong></span>
        <button type="button" class="pq-btn-icono" data-copiar="<?= e($llave) ?>" title="Copiar llave" aria-label="Copiar llave" style="width: 30px; height: 30px; flex-shrink: 0">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="4" width="10" height="14" rx="2"/><path d="M8 8H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-1"/></svg>
        </button>
      </div>
      <?php if ($pedido['metodo_pago'] === 'breb'): ?>
        <span class="pq-ayuda">Incluye este código en el concepto de tu transferencia: <strong class="pq-mono">VECI-P<?= (int) $pedido['id'] ?></strong></span>
      <?php endif; ?>
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
