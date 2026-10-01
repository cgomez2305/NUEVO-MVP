<?php $esReservas = $negocio['tipo_negocio'] === 'reservas'; $pasoActual = $esReservas ? 4 : 3; $totalPasos = $esReservas ? 4 : 3; $pasoNombre = 'Cobros'; ?>
<div class="pq-topbar" style="border-bottom: none">
  <a href="<?= e(base_url($esReservas ? '/panel/onboarding/horario' : '/panel/onboarding/productos')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Atrás</a>
</div>
<?php require __DIR__ . '/_pasos.php'; ?>

<div class="pq-content">
  <h1 class="pq-h1" style="font-size: 28px">Elige cómo cobras</h1>
  <p class="pq-lead">Tu llave Bre-B recibe pagos en menos de 20 segundos, sin comisión de tarjeta.</p>

  <?php if ($esReservas && !$tieneAnticipos): ?>
    <div class="pq-demo-caja" style="margin-top: 16px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
      <span>Ninguno de tus servicios pide anticipo todavía, así que esto es opcional. Dejamos tu WhatsApp por defecto — puedes configurarlo después desde el panel.</span>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('/panel/onboarding/publicar')) ?>" style="margin-top: 20px">
    <?= csrf_campo() ?>

    <?php $llaveTipo = $negocio['llave_breb_tipo'] ?? 'celular'; ?>
    <div class="pq-campo">
      <label class="pq-label">Tipo de llave</label>
      <div class="pq-llave-tipo">
        <label class="pq-llave-tipo-opcion">
          <input type="radio" name="llave_tipo" value="celular" <?= $llaveTipo === 'celular' ? 'checked' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>
          <span>Celular</span>
        </label>
        <label class="pq-llave-tipo-opcion">
          <input type="radio" name="llave_tipo" value="cedula" <?= $llaveTipo === 'cedula' ? 'checked' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="12" r="2"/><path d="M14 10h4M14 14h3"/></svg>
          <span>Cédula</span>
        </label>
        <label class="pq-llave-tipo-opcion">
          <input type="radio" name="llave_tipo" value="correo" <?= $llaveTipo === 'correo' ? 'checked' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
          <span>Correo</span>
        </label>
      </div>
    </div>

    <div class="pq-campo">
      <label class="pq-label" for="llave_valor">Valor de la llave</label>
      <input class="pq-input" type="text" id="llave_valor" name="llave_valor"
             value="<?= e($negocio['llave_breb_valor'] ?? $negocio['whatsapp']) ?>" required>
      <p class="pq-ayuda">Por defecto usamos tu número de WhatsApp.</p>
    </div>

    <div class="pq-card" style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 20px">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5B7F3A" stroke-width="1.8" style="flex-shrink: 0; margin-top: 2px" aria-hidden="true"><path d="M12 2 3 7v6c0 5 4 8 9 9 5-1 9-4 9-9V7Z"/></svg>
      <span style="font-size: 13px; color: var(--gris-texto); line-height: 1.5">Veci no procesa el dinero: el cliente te paga directamente y te envía el comprobante por WhatsApp.</span>
    </div>

    <button type="submit" class="pq-btn pq-btn-caja">Publicar mi tienda →</button>
  </form>
</div>
