<div class="pq-content-tienda" style="padding-top: 24px">

  <div class="pq-centro" style="margin-bottom: 20px">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#16A36A" stroke-width="2.2" style="margin: 0 auto 10px"><path d="M5 13l5 5L20 7"/></svg>
    <h1 class="pq-tienda-nombre" style="font-size: 24px">Tu cita está reservada</h1>
    <span class="pq-tienda-desc">Reserva #<?= (int) $cita['id'] ?> · <?= e($negocio['nombre']) ?></span>
  </div>

  <div class="pq-card" style="background: #FFFFFF; border: 1px solid #E7E0CF">
    <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0">
      <span><?= e($cita['nombre_servicio']) ?></span>
      <span class="pq-mono"><?= pesos((int) $cita['precio']) ?></span>
    </div>
    <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 700; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #C9BFA4">
      <span>Cuándo</span>
      <span class="pq-mono"><?= e(fecha_corta((string) $cita['fecha_hora'], ', ')) ?></span>
    </div>
  </div>

  <?php if ((int) $cita['anticipo_monto'] > 0): ?>
    <div class="pq-alerta pq-alerta-aviso" style="margin-top: 14px">
      Esta cita necesita un anticipo de <strong><?= pesos((int) $cita['anticipo_monto']) ?></strong> para quedar confirmada.
      <?php if ($cita['anticipo_estado'] === 'pagado'): ?>
        <strong>· Ya lo registramos, ¡gracias!</strong>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($negocio['llave_breb_valor']) && $cita['anticipo_estado'] !== 'pagado'): ?>
    <div class="pq-card" style="margin-top: 14px; display: flex; flex-direction: column; gap: 6px">
      <span class="pq-mono" style="font-size: 11px; color: var(--gris-texto)">
        PAGA POR BRE-B<?= (int) $cita['anticipo_monto'] === 0 ? ' (OPCIONAL)' : '' ?>
      </span>
      <span style="font-size: 14px">Llave: <strong><?= e($negocio['llave_breb_valor']) ?></strong></span>
      <?php if ((int) $cita['anticipo_monto'] > 0): ?>
        <span style="font-size: 14px">Monto del anticipo: <strong><?= pesos((int) $cita['anticipo_monto']) ?></strong></span>
      <?php endif; ?>
      <span class="pq-ayuda">Incluye este código en el concepto de tu transferencia: <strong class="pq-mono">VECI-C<?= (int) $cita['id'] ?></strong></span>
    </div>
  <?php endif; ?>

  <a href="<?= e($enlaceWhatsapp) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-whatsapp" style="margin-top: 20px">
    <svg width="19" height="19" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
    Confirmar por WhatsApp
  </a>
  <p class="pq-ayuda pq-centro" style="margin-top: 10px">Se abre WhatsApp con tu reserva ya escrita, lista para enviar a <?= e($negocio['nombre']) ?>.</p>

  <a href="<?= e(base_url('/cita/' . $cita['token_gestion'])) ?>" class="pq-mono pq-centro" style="display: block; margin-top: 20px; font-size: 12px; color: var(--gris-suave)">reprogramar o cancelar esta cita</a>
  <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-mono pq-centro" style="display: block; margin-top: 10px; font-size: 12px; color: var(--gris-suave)">volver a la tienda</a>

</div>
