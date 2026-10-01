<span class="pq-eyebrow pq-eyebrow-sutil">Bienvenido de nuevo</span>
<h1 class="pq-h1">Entra a tu panel</h1>
<p class="pq-lead">Con el WhatsApp y la contraseña de tu negocio.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 20px"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/login')) ?>" style="margin-top: 20px">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="whatsapp">WhatsApp</label>
    <div class="pq-input-telefono">
      <span class="pq-input-telefono-prefijo pq-mono">🇨🇴 +57</span>
      <input class="pq-input pq-mono" type="tel" inputmode="numeric" id="whatsapp" name="whatsapp" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
    </div>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password">Contraseña</label>
    <div class="pq-input-password">
      <input class="pq-input" type="password" id="password" name="password" required autocomplete="current-password">
      <button type="button" class="pq-input-password-ojo" data-mostrar-contrasena="#password" aria-label="Mostrar contraseña">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Entrar →</button>

  <p class="pq-lead pq-centro" style="margin-top: 16px">
    <a href="<?= e(base_url('/olvide-password')) ?>" style="color: var(--gris-suave)">¿Olvidaste tu contraseña?</a>
  </p>
</form>

<div class="pq-auth-separador"><span>o</span></div>

<p class="pq-ayuda pq-centro" style="font-weight: 600; color: var(--gris-texto)">¿Aún no tienes una cuenta?</p>
<a href="<?= e(base_url('/registro')) ?>" class="pq-btn pq-btn-ghost" style="margin-top: 8px">Crear mi tienda gratis →</a>

<form method="post" action="<?= e(base_url('/login')) ?>" style="margin-top: 14px">
  <?= csrf_campo() ?>
  <input type="hidden" name="whatsapp" value="3001234567">
  <input type="hidden" name="password" value="veci123">
  <button type="submit" class="pq-ayuda pq-centro" style="display: block; width: 100%; background: none; border: none; text-decoration: underline; cursor: pointer; color: var(--gris-suave)">Probar una tienda de ejemplo</button>
</form>
