<span class="pq-eyebrow pq-eyebrow-sutil">Nuevo negocio</span>
<h1 class="pq-h1">Publica tu tienda en minutos</h1>
<p class="pq-lead">Sin comisión por pedido. Solo tu nombre, tu WhatsApp y una contraseña.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-auth-mt5"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!empty($planElegido)): ?>
  <p class="pq-registro-invita">Elegiste el plan <strong><?= e(ucfirst($planElegido)) ?></strong>. Tu cuenta nace en Gratis y lo activas en el siguiente paso, sin cobros automáticos.</p>
<?php endif; ?>
<?php if (!empty($invitadoPor)): ?>
  <p class="pq-registro-invita"><strong><?= e($invitadoPor) ?></strong> te invitó a Veci.</p>
<?php endif; ?>
<form class="pq-auth-mt5" method="post" action="<?= e(base_url('/registro')) ?>">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="nombre">Nombre del negocio</label>
    <input class="pq-input" type="text" id="nombre" name="nombre" placeholder="Doña María" required maxlength="120">
  </div>

  <div class="pq-campo">
    <label class="pq-label">¿A qué te dedicas?</label>
    <p class="pq-ayuda pq-auth-ayuda-tipo">Podrás cambiar algunas configuraciones después, pero esto define cómo funciona tu tienda.</p>
    <div class="pq-stack pq-auth-opciones">
      <label class="pq-card-borde pq-tipo-opcion">
        <input class="pq-sr-solo" type="radio" name="tipo_negocio" value="pedidos"<?= ($modoInicial ?? 'pedidos') !== 'reservas' ? ' checked' : '' ?>>
        <span class="pq-tipo-opcion-icono">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
        </span>
        <span class="pq-stack pq-tipo-opcion-texto">
          <span class="pq-tipo-opcion-titulo">Productos con carrito</span>
          <span class="pq-ayuda">Comida, panadería, tienda de barrio: el cliente arma su pedido y paga.</span>
        </span>
        <span class="pq-tipo-opcion-check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
        </span>
      </label>
      <label class="pq-card-borde pq-tipo-opcion">
        <input class="pq-sr-solo" type="radio" name="tipo_negocio" value="reservas"<?= ($modoInicial ?? '') === 'reservas' ? ' checked' : '' ?>>
        <span class="pq-tipo-opcion-icono">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </span>
        <span class="pq-stack pq-tipo-opcion-texto">
          <span class="pq-tipo-opcion-titulo">Servicios con cita previa</span>
          <span class="pq-ayuda">Peluquería, spa, taller, consultorio: el cliente reserva un horario.</span>
        </span>
        <span class="pq-tipo-opcion-check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
        </span>
      </label>
      <label class="pq-card-borde pq-tipo-opcion">
        <input class="pq-sr-solo" type="radio" name="tipo_negocio" value="domicilio">
        <span class="pq-tipo-opcion-icono">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M14.5 13.5l-3 3m0-3 3 3"/></svg>
        </span>
        <span class="pq-stack pq-tipo-opcion-texto">
          <span class="pq-tipo-opcion-titulo">Visitas a domicilio</span>
          <span class="pq-ayuda">Plomero, técnico de aires, electricista: vas a la casa del cliente y cotizas allá.</span>
        </span>
        <span class="pq-tipo-opcion-check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
        </span>
      </label>
      <label class="pq-card-borde pq-tipo-opcion">
        <input class="pq-sr-solo" type="radio" name="tipo_negocio" value="salud">
        <span class="pq-tipo-opcion-icono">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/></svg>
        </span>
        <span class="pq-stack pq-tipo-opcion-texto">
          <span class="pq-tipo-opcion-titulo">Consultorio de salud</span>
          <span class="pq-ayuda">Odontología, fisioterapia, psicología, nutrición: citas y planes de tratamiento con abonos.</span>
        </span>
        <span class="pq-tipo-opcion-check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
        </span>
      </label>
      <label class="pq-card-borde pq-tipo-opcion">
        <input class="pq-sr-solo" type="radio" name="tipo_negocio" value="profesional">
        <span class="pq-tipo-opcion-icono">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/></svg>
        </span>
        <span class="pq-stack pq-tipo-opcion-texto">
          <span class="pq-tipo-opcion-titulo">Servicios profesionales</span>
          <span class="pq-ayuda">Abogado, contador, arquitecto, consultor: tus clientes agendan una consulta contigo.</span>
        </span>
        <span class="pq-tipo-opcion-check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
        </span>
      </label>
    </div>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="whatsapp">Tu WhatsApp</label>
    <div class="pq-input-telefono">
      <span class="pq-input-telefono-prefijo pq-mono">🇨🇴 +57</span>
      <input class="pq-input pq-mono" type="tel" inputmode="numeric" id="whatsapp" name="whatsapp" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
    </div>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password">Contraseña</label>
    <div class="pq-input-password">
      <input class="pq-input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
      <button type="button" class="pq-input-password-ojo" data-mostrar-contrasena="#password" aria-label="Mostrar contraseña">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>
    <p class="pq-ayuda" data-requisito-largo="#password" data-largo-minimo="8">Mínimo 8 caracteres.</p>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="correo">Tu correo (opcional)</label>
    <input class="pq-input" type="email" id="correo" name="correo" placeholder="tu@correo.com" maxlength="160">
    <p class="pq-ayuda">Úsalo como respaldo para recuperar tu cuenta.</p>
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Continuar →</button>

  <p class="pq-ayuda pq-centro pq-auth-mt3">
    Al crear tu tienda aceptas los <a class="pq-auth-enlace" href="https://tuveci.co/terminos.html" target="_blank" rel="noopener">términos de servicio</a>
    y la <a class="pq-auth-enlace" href="https://tuveci.co/privacidad.html" target="_blank" rel="noopener">política de privacidad</a>.
  </p>
</form>

<p class="pq-lead pq-centro pq-auth-mt5">
  ¿Ya tienes tienda? <a class="pq-enlace-sello" href="<?= e(base_url('/login')) ?>">Inicia sesión</a>
</p>
