<span class="pq-eyebrow">Recordatorios</span>
<h1 class="pq-h1" style="font-size: 28px">Citas de mañana</h1>
<p class="pq-lead">Citas en las próximas 24-30 horas que todavía no tienen recordatorio enviado.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>

<?php if (!$apiConectada): ?>
  <div class="pq-card-borde" style="margin-top: 16px; font-size: 12px; color: var(--gris-texto)">
    No tienes conectada una cuenta de WhatsApp Business API, así que estos recordatorios se envían a mano: toca "Enviar" y confirma el mensaje ya escrito en WhatsApp.
  </div>
<?php endif; ?>

<?php if ($citas === []): ?>
  <p class="pq-ayuda" style="margin-top: 20px">No hay citas próximas sin recordatorio por ahora.</p>
<?php else: ?>
  <div class="pq-stack" style="gap: 10px; margin-top: 20px">
    <?php foreach ($citas as $cita): ?>
      <div class="pq-lead">
        <div class="pq-avatar" style="background: var(--aji)">
          <?= e(mb_strtoupper(mb_substr($cita['cliente_nombre'], 0, 1))) ?>
        </div>
        <div class="pq-stack" style="flex-grow: 1">
          <span style="font-size: 14px; font-weight: 600"><?= e($cita['cliente_nombre']) ?></span>
          <span class="pq-ayuda"><?= e($cita['nombre_servicio']) ?> · <?= e(date('d M, g:i a', strtotime((string) $cita['fecha_hora']))) ?></span>
        </div>
        <a href="<?= e(base_url('/panel/recordatorios/' . $cita['id'] . '/mensaje')) ?>" class="pq-btn pq-btn-sello pq-btn-chico">Enviar</a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
