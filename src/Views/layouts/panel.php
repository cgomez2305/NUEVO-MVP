<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<link rel="manifest" href="<?= e(base_url('manifest.json')) ?>">
<meta name="theme-color" content="#3B4CCA">
<link rel="apple-touch-icon" href="<?= e(base_url('assets/img/icon-192.png')) ?>">
</head>
<?php
// Un ícono por sección del nav, para que la barra deje de ser puro texto
// mono (ver public/assets/css/app.css). Mismo trazo (stroke 2, 24x24) que
// usa el resto de la marca.
$pqIconos = [
    'panel'          => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'pedidos'        => '<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
    'citas'          => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'recordatorios'  => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
    'copiloto'       => '<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/>',
    'servicios'      => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 16l9 5 9-5M3 12l9 5 9-5"/>',
    'empleados'      => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="18" cy="8.5" r="2.6"/><path d="M16.5 14.3c2.3.5 4 2.5 4.5 5.7"/>',
    'horario'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'productos'      => '<path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/>',
    'sedes'          => '<path d="M12 21s7-6.4 7-11.5a7 7 0 1 0-14 0C5 14.6 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.6"/>',
    'colaboradores'  => '<circle cx="8" cy="9" r="3"/><path d="M2 20c0-3 2.7-5.5 6-5.5s6 2.5 6 5.5"/><circle cx="17.5" cy="8" r="2.3"/><path d="M15.8 14.7c2.4.4 4.2 2.5 4.2 5.3"/>',
    'cuenta'         => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c1-4 4-6 7.5-6s6.5 2 7.5 6"/>',
];
$pqIcono = static function (string $clave) use ($pqIconos): string {
    return isset($pqIconos[$clave])
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $pqIconos[$clave] . '</svg>'
        : '';
};
?>
<body class="pq-panel-bg" data-negocio-id="<?= (int) $negocio['id'] ?>" data-es-reservas="<?= ($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas' ? '1' : '0' ?>">
  <div class="pq-shell">
    <div class="pq-topbar">
      <a href="<?= e(base_url('/panel')) ?>" class="pq-topbar-brand">
        <img src="<?= e(base_url('assets/img/logo-veci-lockup.png')) ?>" alt="Veci">
      </a>
      <nav class="pq-topbar-links">
        <a href="<?= e(base_url('/panel')) ?>" class="<?= ($activo ?? '') === 'panel' ? 'activo' : '' ?>"><?= $pqIcono('panel') ?>Panel</a>
        <?php if (($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas'): ?>
          <a href="<?= e(base_url('/panel/citas')) ?>" class="<?= ($activo ?? '') === 'citas' ? 'activo' : '' ?>"><?= $pqIcono('citas') ?>Agenda</a>
          <a href="<?= e(base_url('/panel/recordatorios')) ?>" class="<?= ($activo ?? '') === 'recordatorios' ? 'activo' : '' ?>"><?= $pqIcono('recordatorios') ?>Recordatorios</a>
          <?php if ($negocio['rol'] === 'dueno'): ?>
            <a href="<?= e(base_url('/panel/copiloto')) ?>" class="<?= ($activo ?? '') === 'copiloto' ? 'activo' : '' ?>"><?= $pqIcono('copiloto') ?>Copiloto</a>
          <?php endif; ?>
          <a href="<?= e(base_url('/panel/servicios')) ?>" class="<?= ($activo ?? '') === 'servicios' ? 'activo' : '' ?>"><?= $pqIcono('servicios') ?>Servicios</a>
          <?php if ($negocio['rol'] === 'dueno'): ?>
            <a href="<?= e(base_url('/panel/empleados')) ?>" class="<?= ($activo ?? '') === 'empleados' ? 'activo' : '' ?>"><?= $pqIcono('empleados') ?>Empleados</a>
            <a href="<?= e(base_url('/panel/horario')) ?>" class="<?= ($activo ?? '') === 'horario' ? 'activo' : '' ?>"><?= $pqIcono('horario') ?>Horario</a>
          <?php endif; ?>
        <?php else: ?>
          <a href="<?= e(base_url('/panel/pedidos')) ?>" class="<?= ($activo ?? '') === 'pedidos' ? 'activo' : '' ?>"><?= $pqIcono('pedidos') ?>Pedidos</a>
          <?php if ($negocio['rol'] === 'dueno'): ?>
            <a href="<?= e(base_url('/panel/copiloto')) ?>" class="<?= ($activo ?? '') === 'copiloto' ? 'activo' : '' ?>"><?= $pqIcono('copiloto') ?>Copiloto</a>
          <?php endif; ?>
          <a href="<?= e(base_url('/panel/productos')) ?>" class="<?= ($activo ?? '') === 'productos' ? 'activo' : '' ?>"><?= $pqIcono('productos') ?>Menú</a>
        <?php endif; ?>
        <a href="<?= e(base_url('/panel/sedes')) ?>" class="<?= ($activo ?? '') === 'sedes' ? 'activo' : '' ?>"><?= $pqIcono('sedes') ?>Sedes</a>
        <?php if ($negocio['rol'] === 'dueno'): ?>
          <a href="<?= e(base_url('/panel/colaboradores')) ?>" class="<?= ($activo ?? '') === 'colaboradores' ? 'activo' : '' ?>"><?= $pqIcono('colaboradores') ?>Colaboradores</a>
        <?php endif; ?>
        <a href="<?= e(base_url('/panel/cuenta')) ?>" class="<?= ($activo ?? '') === 'cuenta' ? 'activo' : '' ?>"><?= $pqIcono('cuenta') ?>Mi cuenta</a>
      </nav>
    </div>

    <?php $sedesAcceso = \App\Auth::sedesAccesibles($negocio); ?>
    <?php if (count($sedesAcceso) > 1): ?>
      <form method="post" action="<?= e(base_url('/panel/sede/cambiar')) ?>" class="pq-sede-switcher">
        <?= csrf_campo() ?>
        <input type="hidden" name="volver" value="<?= e(base_url('/panel')) ?>">
        <select class="pq-select" name="sede_id" onchange="this.form.submit()">
          <?php foreach ($sedesAcceso as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $negocio['id'] ? 'selected' : '' ?>>
              <?= e($s['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>

    <div class="pq-content">
      <?= $contenido ?>
    </div>

    <div style="padding: 0 20px 12px">
      <button type="button" id="push-boton" class="pq-btn pq-btn-ghost pq-btn-chico" data-csrf="<?= e(csrf_token()) ?>">Activar notificaciones</button>
      <p id="push-estado" class="pq-ayuda" style="margin-top: 6px"></p>
    </div>

    <form method="post" action="<?= e(base_url('/logout')) ?>" style="padding: 0 20px 24px">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Cerrar sesión</button>
    </form>
  </div>
  <script src="<?= e(base_url('assets/js/panel-notificaciones.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/panel-push.js')) ?>" defer></script>
</body>
</html>
