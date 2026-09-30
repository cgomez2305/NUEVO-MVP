<span class="pq-eyebrow">Tu cuenta</span>
<h1 class="pq-h1" style="font-size: 28px">Mi cuenta</h1>
<p class="pq-lead">Guarda un correo para poder recuperar tu contraseña si algún día la olvidas.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/panel/cuenta/correo')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 20px">
  <?= csrf_campo() ?>
  <span style="font-size: 13px; font-weight: 600">Correo de recuperación</span>
  <input class="pq-input" type="email" name="correo" placeholder="tu@correo.com" maxlength="160" value="<?= e($usuario['correo'] ?? '') ?>">
  <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar correo</button>
</form>

<form method="post" action="<?= e(base_url('/panel/cuenta/password')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 16px">
  <?= csrf_campo() ?>
  <span style="font-size: 13px; font-weight: 600">Cambiar contraseña</span>
  <input class="pq-input" type="password" name="password_actual" placeholder="Contraseña actual" required>
  <input class="pq-input" type="password" name="password_nueva" placeholder="Nueva (mínimo 6 caracteres)" required minlength="6">
  <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Cambiar contraseña</button>
</form>

<form method="post" action="<?= e(base_url('/logout')) ?>" style="margin-top: 24px; padding-top: 16px; border-top: 1px dashed var(--borde)">
  <?= csrf_campo() ?>
  <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Cerrar sesión</button>
</form>
