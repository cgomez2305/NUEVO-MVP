<a href="<?= e(base_url('/panel/recordatorios')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Recordatorios</a>

<div style="display: flex; align-items: center; gap: 12px; margin-top: 20px">
  <div class="pq-avatar" style="background: var(--aji); width: 44px; height: 44px">
    <?= e(mb_strtoupper(mb_substr($cita['cliente_nombre'], 0, 1))) ?>
  </div>
  <div>
    <span class="pq-serif" style="font-size: 22px; display: block; line-height: 1"><?= e($cita['cliente_nombre']) ?></span>
    <span class="pq-ayuda"><?= e($cita['cliente_telefono']) ?> · <?= e($cita['nombre_servicio']) ?></span>
  </div>
</div>

<div style="margin-top: 20px">
  <span class="pq-eyebrow">Mensaje de recordatorio</span>
  <div style="margin-top: 10px; background: #DCF8C6; border-radius: 12px 12px 2px 12px; padding: 14px; font-size: 14px; line-height: 1.5; color: #111b21">
    <?= e($mensaje) ?>
  </div>
</div>

<a href="<?= e($enlaceWhatsapp) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-whatsapp" style="margin-top: 20px">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
  Enviar por WhatsApp
</a>

<form method="post" action="<?= e(base_url('/panel/recordatorios/' . $cita['id'] . '/enviar')) ?>" style="margin-top: 10px">
  <?= csrf_campo() ?>
  <button type="submit" class="pq-btn pq-btn-ghost">Ya lo envié</button>
</form>
