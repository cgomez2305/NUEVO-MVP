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
    <?php $motivoIdentidad = 'Con ese correo se puede recuperar el acceso a tu cuenta.'; $idCampoIdentidad = 'confirmar-correo'; require __DIR__ . '/_confirmar_identidad.php'; ?>
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

<?php
// Seguridad: dónde está abierta la cuenta y qué ha pasado en ella. La
// bitácora es la forma de darse cuenta a tiempo si algo no lo hizo uno.
$alerta = ['login_nuevo', 'cuenta_frenada', 'cobro_cambiado', 'correo_cambiado', 'reset_generado', 'reset_solicitado', 'password_restablecida', 'exportacion', 'colaborador_creado', 'sede_whatsapp', 'negocio_suspendido'];
$esteCelular = \App\Models\DispositivoConfianza::deEsteNavegador((int) $negocio['usuario_id']);
// Los 5 más recientes; el resto se cuenta (una lista de 20 celulares no se lee).
$masDispositivos = max(0, count($dispositivos) - 5);
$dispositivos = array_slice($dispositivos, 0, 5);
// Eventos iguales seguidos (mismo tipo, persona, celular y detalle) van en
// una sola línea con "×N": diez inicios de sesión no son diez alarmas.
$agrupados = [];
foreach ($eventos as $evento) {
    $clave = $evento['tipo'] . '|' . $evento['usuario_id'] . '|' . $evento['admin_id'] . '|' . $evento['descripcion'] . '|' . $evento['detalle'] . '|' . $evento['ip'];
    $ultimo = array_key_last($agrupados);
    if ($ultimo !== null && $agrupados[$ultimo]['clave'] === $clave) {
        $agrupados[$ultimo]['veces']++;
        $agrupados[$ultimo]['desde'] = $evento['creado_en'];
        continue;
    }
    $agrupados[] = $evento + ['clave' => $clave, 'veces' => 1, 'desde' => $evento['creado_en']];
}
?>
<section class="pq-seguridad" aria-labelledby="pq-titulo-seguridad">
  <h2 class="pq-seccion-titulo" id="pq-titulo-seguridad">Seguridad</h2>

  <div class="pq-card pq-seguridad-celulares">
    <span class="pq-cuenta-push-titulo">Dónde has entrado</span>
    <?php if ($dispositivos === []): ?>
      <p class="pq-ayuda">Todavía no hay celulares guardados.</p>
    <?php else: ?>
      <ul class="pq-seguridad-dispositivos">
        <?php foreach ($dispositivos as $dispositivo): ?>
          <li>
            <span><?= e($dispositivo['descripcion'] !== '' ? $dispositivo['descripcion'] : 'Navegador desconocido') ?><?php if ($esteCelular !== null && (int) $esteCelular['id'] === (int) $dispositivo['id']): ?> <span class="pq-chip-este">Este</span><?php endif; ?></span>
            <span class="pq-ayuda pq-mono">Última vez <?= e(fecha_corta((string) $dispositivo['usado_en'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($masDispositivos > 0): ?>
        <p class="pq-ayuda">Y <?= $masDispositivos ?> más, usados antes.</p>
      <?php endif; ?>
    <?php endif; ?>
    <p class="pq-ayuda">Si perdiste un celular o dejaste la sesión abierta en un computador ajeno, ciérrala desde aquí.</p>
    <form method="post" action="<?= e(base_url('/panel/cuenta/cerrar-sesiones')) ?>" data-confirmar="¿Cerrar tu sesión en todos los demás celulares y computadores? Este se queda abierto.">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Cerrar sesión en los demás dispositivos</button>
    </form>
  </div>

  <h3 class="pq-seguridad-subtitulo">Actividad reciente<?= $negocio['rol'] === 'dueno' ? ' del negocio' : '' ?></h3>
  <?php if ($eventos === []): ?>
    <p class="pq-ayuda">Aquí vas a ver cada inicio de sesión y los cambios importantes de tu cuenta.</p>
  <?php else: ?>
    <ol class="pq-bitacora">
      <?php foreach ($agrupados as $evento): $tipo = (string) $evento['tipo']; ?>
        <li class="pq-bitacora-fila<?= in_array($tipo, $alerta, true) ? ' pq-bitacora-alerta' : '' ?>">
          <span class="pq-bitacora-punto" aria-hidden="true"></span>
          <div class="pq-bitacora-texto">
            <span class="pq-bitacora-titulo"><?= e(\App\Models\EventoSeguridad::TIPOS[$tipo] ?? $tipo) ?><?= $evento['detalle'] !== '' ? ' · ' . e($evento['detalle']) : '' ?><?php if ($evento['veces'] > 1): ?> <span class="pq-bitacora-veces">×<?= (int) $evento['veces'] ?></span><?php endif; ?></span>
            <span class="pq-ayuda">
              <?= $evento['admin_id'] !== null ? 'Equipo de Veci' : e($evento['usuario_nombre'] ?? 'Usuario eliminado') ?>
              <?= $evento['descripcion'] !== '' ? ' · ' . e($evento['descripcion']) : '' ?>
              <?= $evento['ip'] !== '' && $evento['admin_id'] === null ? ' · IP ' . e($evento['ip']) : '' ?>
            </span>
          </div>
          <time class="pq-bitacora-hora pq-mono" datetime="<?= e(date('c', strtotime((string) $evento['creado_en']))) ?>"><?= e(fecha_corta((string) $evento['creado_en'], ' ')) ?></time>
        </li>
      <?php endforeach; ?>
    </ol>
    <p class="pq-ayuda pq-bitacora-nota">¿Ves algo que no hiciste tú? Cambia tu contraseña y cierra la sesión en los demás dispositivos. Se guarda 6 meses.</p>
  <?php endif; ?>
</section>

<form method="post" action="<?= e(base_url('/logout')) ?>" class="pq-cuenta-salir">
  <?= csrf_campo() ?>
  <button type="submit" class="pq-enlace-boton">Cerrar sesión</button>
</form>
