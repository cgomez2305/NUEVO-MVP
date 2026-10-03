<a href="<?= e(base_url('/panel/recordatorios')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Recordatorios
</a>

<div class="pq-detalle-cabeza">
  <div class="pq-detalle-quien">
    <div class="pq-avatar pq-detalle-avatar"><?= e(mb_strtoupper(mb_substr($cita['cliente_nombre'], 0, 1))) ?></div>
    <div>
      <h1 class="pq-detalle-titulo"><?= e($cita['cliente_nombre']) ?></h1>
      <span class="pq-ayuda"><?= e($cita['cliente_telefono']) ?> · <?= e($cita['nombre_servicio']) ?></span>
    </div>
  </div>
</div>

<?php
// El mensaje se muestra como la burbuja que va a recibir el cliente en
// WhatsApp: así el dueño lo revisa tal cual antes de mandarlo.
?>
<section class="pq-wa-vista" aria-labelledby="pq-titulo-mensaje">
  <h2 class="pq-seccion-titulo" id="pq-titulo-mensaje">Así le llega</h2>
  <div class="pq-wa-fondo">
    <p class="pq-burbuja-out"><?= nl2br(e($mensaje)) ?></p>
  </div>
</section>

<a href="<?= e($enlaceWhatsapp) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-whatsapp pq-detalle-accion">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
  Abrir WhatsApp con el mensaje
</a>

<form method="post" action="<?= e(base_url('/panel/recordatorios/' . $cita['id'] . '/enviar')) ?>" class="pq-recordatorio-listo">
  <?= csrf_campo() ?>
  <button type="submit" class="pq-btn pq-btn-ghost">Ya lo envié</button>
  <p class="pq-ayuda">Márcalo después de enviarlo para que salga de la lista.</p>
</form>
