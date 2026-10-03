<?php
/**
 * "Confirma que eres tú" antes de descargar datos de clientes (ver
 * PanelController::abrirDescargaCsv). Tras confirmar, la misma pantalla
 * dispara la descarga y ofrece volver.
 */
?>
<?php if ($descarga !== null): ?>
  <a href="<?= e(base_url($descarga['volver'])) ?>" class="pq-volver-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
    Volver
  </a>
<?php endif; ?>

<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Seguridad</span>
    <h1 class="pq-h1"><?= $listo ? 'Tu archivo se está descargando' : 'Confirma que eres tú' ?></h1>
  </div>
</div>

<?php if ($listo && $descarga !== null): ?>
  <meta http-equiv="refresh" content="0;url=<?= e(base_url($descarga['url'])) ?>">
  <p class="pq-lead pq-pagina-bajada-panel">Si no empieza en unos segundos, descárgalo aquí. Por los próximos 10 minutos no te volvemos a pedir la contraseña.</p>
  <div class="pq-confirmar-acciones">
    <a href="<?= e(base_url($descarga['url'])) ?>" class="pq-btn pq-btn-sello">Descargar <?= e($descarga['nombre']) ?></a>
    <a href="<?= e(base_url($descarga['volver'])) ?>" class="pq-btn pq-btn-ghost">Volver</a>
  </div>
<?php else: ?>
  <p class="pq-lead pq-pagina-bajada-panel">Vas a descargar <?= e($descarga['nombre'] ?? 'datos de tu negocio') ?> con nombres y teléfonos. Antes, escribe tu contraseña: así nadie que encuentre tu sesión abierta puede llevarse tu lista de clientes.</p>
  <?php if (!empty($error)): ?>
    <div class="pq-alerta pq-pagina-aviso" role="alert"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" action="<?= e(base_url('/panel/confirmar')) ?>" class="pq-card pq-form-panel pq-identidad-form">
    <?= csrf_campo() ?>
    <?php if ($descarga !== null): ?>
      <input type="hidden" name="descargar" value="<?= e($descarga['clave']) ?>">
      <?php foreach ($descarga['filtros'] as $nombreFiltro => $valorFiltro): ?>
        <input type="hidden" name="<?= e($nombreFiltro) ?>" value="<?= e($valorFiltro) ?>">
      <?php endforeach; ?>
    <?php endif; ?>
    <div class="pq-campo">
      <label class="pq-label" for="confirmar-password">Tu contraseña de Veci</label>
      <input class="pq-input" id="confirmar-password" type="password" name="confirmar_password" required autocomplete="current-password" autofocus>
    </div>
    <button type="submit" class="pq-btn pq-btn-sello">Confirmar y descargar</button>
  </form>
<?php endif; ?>
