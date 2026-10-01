<span class="pq-eyebrow">Nuevo negocio</span>
<h1 class="pq-h1">Publica tu tienda en 10 minutos</h1>
<p class="pq-lead">Sin comisión por pedido. Solo tu nombre, tu WhatsApp y una contraseña.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 20px"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/registro')) ?>" style="margin-top: 20px">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="nombre">Nombre del negocio</label>
    <input class="pq-input" type="text" id="nombre" name="nombre" placeholder="Doña María" required maxlength="120">
  </div>

  <div class="pq-campo">
    <label class="pq-label">¿Qué vendes?</label>
    <div class="pq-stack" style="gap: 8px">
      <label class="pq-card-borde pq-tipo-opcion">
        <input type="radio" name="tipo_negocio" value="pedidos" checked style="position: absolute; opacity: 0">
        <span class="pq-tipo-opcion-icono">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
        </span>
        <span class="pq-stack" style="gap: 2px">
          <span style="font-size: 14px; font-weight: 600">Productos con carrito</span>
          <span class="pq-ayuda">Comida, panadería, tienda de barrio: el cliente arma su pedido y paga.</span>
        </span>
      </label>
      <label class="pq-card-borde pq-tipo-opcion">
        <input type="radio" name="tipo_negocio" value="reservas" style="position: absolute; opacity: 0">
        <span class="pq-tipo-opcion-icono">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </span>
        <span class="pq-stack" style="gap: 2px">
          <span style="font-size: 14px; font-weight: 600">Servicios con cita previa</span>
          <span class="pq-ayuda">Peluquería, spa, taller, consultorio: el cliente reserva un horario.</span>
        </span>
      </label>
    </div>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="whatsapp">Tu WhatsApp</label>
    <input class="pq-input" type="tel" id="whatsapp" name="whatsapp" placeholder="300 123 4567" required maxlength="20">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password">Contraseña</label>
    <input class="pq-input" type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" required minlength="6">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="correo">Tu correo (opcional)</label>
    <input class="pq-input" type="email" id="correo" name="correo" placeholder="para recuperar tu contraseña si la olvidas" maxlength="160">
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Crear mi tienda →</button>

  <p class="pq-ayuda pq-centro" style="margin-top: 12px">
    Al crear tu tienda aceptas los <a href="https://tuveci.co/terminos.html" target="_blank" rel="noopener" style="color: var(--sello)">términos de servicio</a>
    y la <a href="https://tuveci.co/privacidad.html" target="_blank" rel="noopener" style="color: var(--sello)">política de privacidad</a>.
  </p>
</form>

<p class="pq-lead pq-centro" style="margin-top: 20px">
  ¿Ya tienes tienda? <a href="<?= e(base_url('/login')) ?>" style="color: var(--sello); font-weight: 600">Inicia sesión</a>
</p>
