<span class="pq-eyebrow">Recuperar acceso</span>
<h1 class="pq-h1">¿Perdiste tu contraseña?</h1>
<p class="pq-lead">Si guardaste un correo en "Mi cuenta", te mandamos un enlace para elegir una nueva.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok pq-auth-mt5"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-auth-mt5"><?= e($error) ?></div>
<?php endif; ?>

<form class="pq-auth-mt5" method="post" action="<?= e(base_url('/olvide-password')) ?>">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="whatsapp">Tu WhatsApp</label>
    <input class="pq-input" type="tel" id="whatsapp" name="whatsapp" placeholder="300 123 4567" required maxlength="20">
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Enviar enlace →</button>
</form>

<p class="pq-ayuda pq-centro pq-auth-mt6">
  ¿No tienes correo registrado? Escríbenos por WhatsApp a soporte y te ayudamos a recuperar el acceso.
</p>

<p class="pq-lead pq-centro pq-auth-mt3">
  <a class="pq-enlace-sello" href="<?= e(base_url('/login')) ?>">Volver a iniciar sesión</a>
</p>
