<span class="pq-eyebrow">Nueva contraseña</span>
<h1 class="pq-h1">Elige tu nueva contraseña</h1>
<p class="pq-lead">Este enlace es de un solo uso y vence en 1 hora.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 20px"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/reset-password/' . $token)) ?>" style="margin-top: 20px">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="password">Nueva contraseña</label>
    <input class="pq-input" type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" required minlength="6">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password_confirmar">Confírmala</label>
    <input class="pq-input" type="password" id="password_confirmar" name="password_confirmar" required minlength="6">
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Guardar contraseña →</button>
</form>
