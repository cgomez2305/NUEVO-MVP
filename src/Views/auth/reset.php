<span class="pq-eyebrow">Nueva contraseña</span>
<h1 class="pq-h1">Elige tu nueva contraseña</h1>
<p class="pq-lead">Este enlace es de un solo uso y vence en 1 hora.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-auth-mt5"><?= e($error) ?></div>
<?php endif; ?>

<form class="pq-auth-mt5" method="post" action="<?= e(base_url('/reset-password/' . $token)) ?>">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="password">Nueva contraseña</label>
    <input class="pq-input" type="password" id="password" name="password" placeholder="Mínimo 8 caracteres" required minlength="8">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password_confirmar">Confírmala</label>
    <input class="pq-input" type="password" id="password_confirmar" name="password_confirmar" required minlength="8">
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Guardar contraseña →</button>
</form>
