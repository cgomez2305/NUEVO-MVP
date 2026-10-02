<span class="pq-eyebrow">Panel interno</span>
<h1 class="pq-h1">Equipo Veci</h1>
<p class="pq-lead">Acceso solo para el equipo de soporte de Veci.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-auth-mt5" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form class="pq-auth-mt5" method="post" action="<?= e(base_url('/admin/login')) ?>">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="correo">Correo</label>
    <input class="pq-input" type="email" id="correo" name="correo" required autocomplete="username">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password">Contraseña</label>
    <input class="pq-input" type="password" id="password" name="password" required autocomplete="current-password">
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Entrar →</button>
</form>
