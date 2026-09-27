<span class="pq-eyebrow">Nuevo negocio</span>
<h1 class="pq-h1">Publica tu tienda en 10 minutos</h1>
<p class="pq-lead">Sin comisión por pedido. Solo tu nombre, tu WhatsApp y una contraseña.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 20px"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/registro')) ?>" style="margin-top: 20px">
  <?= csrf_campo() ?>

  <div class="pq-campo">
    <label class="pq-label" for="nombre">Nombre del negocio</label>
    <input class="pq-input" type="text" id="nombre" name="nombre" placeholder="Doña María" required maxlength="120">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="whatsapp">Tu WhatsApp</label>
    <input class="pq-input" type="tel" id="whatsapp" name="whatsapp" placeholder="300 123 4567" required maxlength="20">
  </div>

  <div class="pq-campo">
    <label class="pq-label" for="password">Contraseña</label>
    <input class="pq-input" type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" required minlength="6">
  </div>

  <button type="submit" class="pq-btn pq-btn-sello">Crear mi tienda →</button>
</form>

<p class="pq-lead pq-centro" style="margin-top: 20px">
  ¿Ya tienes tienda? <a href="<?= e(base_url('/login')) ?>" style="color: var(--sello); font-weight: 600">Inicia sesión</a>
</p>
