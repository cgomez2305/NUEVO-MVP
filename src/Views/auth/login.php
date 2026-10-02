<span class="pq-eyebrow pq-eyebrow-sutil">Bienvenido de nuevo</span>
<h1 class="pq-h1">Entra a tu panel</h1>
<p class="pq-lead">Con el WhatsApp y la contraseña de tu negocio.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-auth-mt5"><?= e($error) ?></div>
<?php endif; ?>

<form class="pq-auth-mt5" method="post" action="<?= e(base_url('/login')) ?>">
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

  <p class="pq-lead pq-centro pq-auth-mt4">
    <a class="pq-auth-enlace-suave" href="<?= e(base_url('/olvide-password')) ?>">¿Olvidaste tu contraseña?</a>
  </p>
</form>

<div class="pq-auth-separador"><span>o</span></div>

<p class="pq-ayuda pq-centro pq-auth-pregunta">¿Aún no tienes una cuenta?</p>
<a href="<?= e(base_url('/registro')) ?>" class="pq-btn pq-btn-ghost pq-auth-mt2">Crear mi tienda gratis →</a>

<form class="pq-auth-mt4" method="post" action="<?= e(base_url('/login')) ?>">
  <?= csrf_campo() ?>
  <input type="hidden" name="whatsapp" value="3001234567">
  <input type="hidden" name="password" value="veci123">
  <button type="submit" class="pq-ayuda pq-centro pq-auth-demo">Probar una tienda de ejemplo</button>
</form>
