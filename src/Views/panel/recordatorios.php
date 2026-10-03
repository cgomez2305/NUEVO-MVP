<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Recordatorios</span>
    <h1 class="pq-h1">Citas de mañana</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Todas las citas de mañana que aún no tienen recordatorio, para enviarlos de una vez.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok pq-pagina-aviso"><?= e($ok) ?></div>
<?php endif; ?>

<?php if (!$apiConectada): ?>
  <p class="pq-nota-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
    <span>Se envían a mano: toca <strong>Escribir</strong> y WhatsApp se abre con el mensaje listo. Solo tienes que pulsar Enviar.</span>
  </p>
<?php endif; ?>

<?php if ($citas === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
    <p><strong>Nada pendiente.</strong><br>Todas las citas de mañana ya tienen su recordatorio.</p>
  </div>
<?php else: ?>
  <div class="pq-inicio-lista">
    <?php foreach ($citas as $cita): ?>
      <div class="pq-fila-pedido">
        <span class="pq-fila-hora"><?= e(date('g:i', strtotime((string) $cita['fecha_hora']) ?: 0)) ?></span>
        <span class="pq-fila-texto">
          <span class="pq-fila-nombre"><?= e($cita['cliente_nombre']) ?></span>
          <span class="pq-ayuda"><?= e($cita['nombre_servicio']) ?> · <span class="pq-fila-fecha"><?= e(fecha_corta((string) $cita['fecha_hora'], ', ')) ?></span></span>
        </span>
        <a href="<?= e(base_url('/panel/recordatorios/' . $cita['id'] . '/mensaje')) ?>" class="pq-btn pq-btn-sello pq-btn-chico">Escribir</a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
