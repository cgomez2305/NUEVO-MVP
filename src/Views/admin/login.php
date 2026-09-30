<span class="pq-eyebrow">Panel interno</span>
<h1 class="pq-h1">Equipo Veci</h1>
<p class="pq-lead">Acceso solo para el equipo de soporte. Se crea con bin/crear_admin.php.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 20px"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/admin/login')) ?>" style="margin-top: 20px">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="correo">Correo</label>
    <input class="pq-input" type="email" id="correo" name="correo" required>
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password">Contraseña</label>
    <input class="pq-input" type="password" id="password" name="password" required>
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Entrar →</button>
</form>
