<span class="pq-eyebrow">Bienvenido de nuevo</span>
<h1 class="pq-h1">Entra a tu panel</h1>
<p class="pq-lead">Con el WhatsApp y la contraseña de tu negocio.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 20px"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/login')) ?>" style="margin-top: 20px">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="whatsapp">WhatsApp</label>
    <input class="pq-input" type="tel" id="whatsapp" name="whatsapp" placeholder="300 123 4567" required maxlength="20">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password">Contraseña</label>
    <input class="pq-input" type="password" id="password" name="password" required>
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Entrar →</button>
</form>

<p class="pq-lead pq-centro" style="margin-top: 16px">
  <a href="<?= e(base_url('/olvide-password')) ?>" style="color: var(--gris-suave)">¿Olvidaste tu contraseña?</a>
</p>

<p class="pq-lead pq-centro" style="margin-top: 8px">
  ¿Aún no tienes tienda? <a href="<?= e(base_url('/registro')) ?>" style="color: var(--sello); font-weight: 600">Créala gratis</a>
</p>

<p class="pq-ayuda pq-centro" style="margin-top: 24px">
  Demo: WhatsApp <strong>3001234567</strong> · contraseña <strong>veci123</strong>
</p>
