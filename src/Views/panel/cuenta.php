<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Tu cuenta</span>
    <h1 class="pq-h1">Mi cuenta</h1>
  </div>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok pq-pagina-aviso"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso"><?= e($error) ?></div>
<?php endif; ?>

<div class="pq-cuenta-grupo">
  <form method="post" action="<?= e(base_url('/panel/cuenta/correo')) ?>" class="pq-card pq-form-panel">
    <?= csrf_campo() ?>
    <div class="pq-campo">
      <label class="pq-label" for="cuenta-correo">Correo de recuperación</label>
      <input class="pq-input" id="cuenta-correo" type="email" name="correo" placeholder="tu@correo.com" maxlength="160" autocomplete="email" value="<?= e($usuario['correo'] ?? '') ?>">
      <span class="pq-ayuda">Solo para recuperar tu contraseña si algún día la olvidas.</span>
    </div>
    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Guardar correo</button>
  </form>

  <form method="post" action="<?= e(base_url('/panel/cuenta/password')) ?>" class="pq-card pq-form-panel">
    <?= csrf_campo() ?>
    <div class="pq-campo">
      <label class="pq-label" for="cuenta-actual">Contraseña actual</label>
      <input class="pq-input" id="cuenta-actual" type="password" name="password_actual" required autocomplete="current-password">
    </div>
    <div class="pq-campo">
      <label class="pq-label" for="cuenta-nueva">Contraseña nueva</label>
      <input class="pq-input" id="cuenta-nueva" type="password" name="password_nueva" required minlength="8" autocomplete="new-password">
      <span class="pq-ayuda">Mínimo 8 caracteres.</span>
    </div>
    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Cambiar contraseña</button>
  </form>

  <?php
  // Antes este botón aparecía al pie de TODAS las pantallas del panel, sin
  // contexto. Vive aquí, explicado, y solo se muestra si el navegador
  // soporta notificaciones (panel-push.js quita el "hidden").
  ?>
  <section class="pq-card pq-cuenta-push" id="push-seccion" hidden>
    <span class="pq-cuenta-push-titulo">Avisos de pedidos en este celular</span>
    <p class="pq-ayuda">Te llega una notificación cada vez que entra un pedido o una reserva, aunque tengas el panel cerrado. Se activa por dispositivo.</p>
    <button type="button" id="push-boton" class="pq-btn pq-btn-ghost pq-btn-chico" data-csrf="<?= e(csrf_token()) ?>">Activar notificaciones</button>
    <p id="push-estado" class="pq-ayuda" aria-live="polite"></p>
  </section>
</div>

<form method="post" action="<?= e(base_url('/logout')) ?>" class="pq-cuenta-salir">
  <?= csrf_campo() ?>
  <button type="submit" class="pq-enlace-boton">Cerrar sesión</button>
</form>
