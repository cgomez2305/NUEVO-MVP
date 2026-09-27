<div class="pq-topbar" style="border-bottom: none">
  <a href="<?= e(base_url('/panel/onboarding/productos')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Atrás</a>
  <span class="pq-chip">PASO 3 DE 3</span>
</div>

<div class="pq-content">
  <h1 class="pq-h1" style="font-size: 28px">Elige cómo cobras</h1>
  <p class="pq-lead">Tu llave Bre-B recibe pagos en menos de 20 segundos, sin comisión de tarjeta.</p>

  <form method="post" action="<?= e(base_url('/panel/onboarding/publicar')) ?>" style="margin-top: 20px">
    <?= csrf_campo() ?>

    <div class="pq-campo">
      <label class="pq-label" for="llave_tipo">Tipo de llave</label>
      <select class="pq-select" id="llave_tipo" name="llave_tipo">
        <option value="celular">Celular</option>
        <option value="cedula">Cédula</option>
        <option value="correo">Correo</option>
      </select>
    </div>

    <div class="pq-campo">
      <label class="pq-label" for="llave_valor">Valor de la llave</label>
      <input class="pq-input" type="text" id="llave_valor" name="llave_valor"
             value="<?= e($negocio['llave_breb_valor'] ?? $negocio['whatsapp']) ?>" required>
      <p class="pq-ayuda">Por defecto usamos tu número de WhatsApp.</p>
    </div>

    <div class="pq-card" style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 20px">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5B7F3A" stroke-width="1.8" style="flex-shrink: 0; margin-top: 2px" aria-hidden="true"><path d="M12 2 3 7v6c0 5 4 8 9 9 5-1 9-4 9-9V7Z"/></svg>
      <span style="font-size: 13px; color: var(--gris-texto); line-height: 1.5">Gratis para personas naturales los primeros 3 años. El comprobante te lo confirma el cliente por WhatsApp.</span>
    </div>

    <button type="submit" class="pq-btn pq-btn-caja">Publicar mi tienda →</button>
  </form>
</div>
