<?php
$color = color_seguro($negocio['color_marca'] ?? null);
$inicial = mb_strtoupper(mb_substr((string) $negocio['nombre'], 0, 1));
$suspendido = (int) $negocio['suspendido'] === 1;
$diasCreado = dias_desde((string) $negocio['creado_en']);
$pendientes = array_values(array_filter($pagosPlan, fn ($p) => $p['confirmado_en'] === null));
$historial = array_values(array_filter($pagosPlan, fn ($p) => $p['confirmado_en'] !== null));
$roles = ['dueno' => 'Dueño', 'colaborador' => 'Colaborador'];
$whatsappLegible = fn (string $n) => strlen($n) === 10 ? substr($n, 0, 3) . ' ' . substr($n, 3, 3) . ' ' . substr($n, 6) : $n;
?>
<a href="<?= e(base_url('/admin')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Negocios
</a>

<header class="pq-admin-ficha">
  <span class="pq-letrero-insignia pq-admin-ficha-insignia" style="--marca: <?= e($color) ?>; --marca-sobre: <?= e(color_texto_sobre($color)) ?>" aria-hidden="true"><?= e($inicial) ?></span>
  <div class="pq-admin-ficha-texto">
    <span class="pq-eyebrow"><?= $negocio['tipo_negocio'] === 'reservas' ? 'Reservas' : 'Pedidos' ?> · se unió <?= e(hace_dias($diasCreado)) ?></span>
    <h1 class="pq-h1"><?= e($negocio['nombre']) ?></h1>
    <?php if ($suspendido): ?>
      <span class="pq-chip pq-chip-cancelado">Suspendido<?= !empty($negocio['suspendido_en']) ? ' desde el ' . e(fecha_larga((string) $negocio['suspendido_en'])) : '' ?></span>
    <?php endif; ?>
  </div>
</header>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" role="status"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!empty($resetEnlace)): ?>
  <?php
  // El enlace se muestra una sola vez (flash): se copia o se manda directo
  // por WhatsApp al número de esa persona, sin pasarlo a mano.
  $resetWhatsapp = '';
  foreach ($usuarios as $u) {
      if (str_contains((string) $resetUsuario, (string) $u['whatsapp'])) {
          $resetWhatsapp = (string) $u['whatsapp'];
      }
  }
  $mensajeReset = "Hola, te escribimos de Veci. Con este enlace pones una contraseña nueva (sirve una sola vez, durante 1 hora):\n" . $resetEnlace;
  ?>
  <section class="pq-admin-enlace" aria-labelledby="pq-titulo-enlace">
    <h2 class="pq-seccion-titulo" id="pq-titulo-enlace">Enlace para <?= e($resetUsuario) ?></h2>
    <p class="pq-ayuda">Sirve una vez, durante 1 hora. No se vuelve a mostrar: mándalo ya.</p>
    <input class="pq-input pq-mono" type="text" readonly data-seleccionar-al-tocar value="<?= e($resetEnlace) ?>" aria-label="Enlace de recuperación">
    <div class="pq-admin-enlace-botones">
      <?php if ($resetWhatsapp !== ''): ?>
        <a class="pq-btn pq-btn-whatsapp" href="https://wa.me/57<?= e($resetWhatsapp) ?>?text=<?= rawurlencode($mensajeReset) ?>" target="_blank" rel="noopener">Mandar por WhatsApp</a>
      <?php endif; ?>
      <button type="button" class="pq-btn pq-btn-ghost" data-copiar="<?= e($resetEnlace) ?>">Copiar enlace</button>
    </div>
  </section>
<?php endif; ?>

<?php if ($pendientes !== []): ?>
  <section class="pq-admin-seccion" aria-labelledby="pq-titulo-pendientes">
    <h2 class="pq-seccion-titulo" id="pq-titulo-pendientes">Pago por confirmar</h2>
    <div class="pq-consignaciones">
      <?php foreach ($pendientes as $pago): ?>
        <?php $mostrarNegocio = false; $volver = ''; require __DIR__ . '/_consignacion.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-plan">
  <h2 class="pq-seccion-titulo" id="pq-titulo-plan">Plan</h2>
  <div class="pq-admin-tarjeta">
    <div class="pq-admin-plan">
      <strong class="pq-admin-plan-nombre"><?= e(ucfirst((string) $plan['nombre'])) ?></strong>
      <span class="pq-ayuda">
        <?php if ($negocio['plan_estado'] === 'degradado_a_gratis'): ?>
          Volvió a Gratis porque no se renovó el pago.
        <?php elseif (!empty($negocio['plan_vence_en'])): ?>
          Pagado hasta el <?= e(fecha_larga((string) $negocio['plan_vence_en'])) ?>.
        <?php else: ?>
          Sin cobro.
        <?php endif; ?>
      </span>
    </div>
    <?php if ($historial !== []): ?>
      <ul class="pq-admin-pagos">
        <?php foreach ($historial as $pago): ?>
          <li>
            <span><?= ($pago['concepto'] ?? 'plan') === 'sede_extra' ? 'Sede extra' : e(ucfirst((string) $pago['plan_nombre'])) . ' · ' . ($pago['ciclo'] === 'anual' ? 'anual' : 'mensual') . ((int) ($pago['sedes_extra'] ?? 0) > 0 ? ' + ' . (int) $pago['sedes_extra'] . ' sede extra' : '') ?></span>
            <span class="pq-mono"><?= pesos((int) $pago['monto']) ?></span>
            <span class="pq-ayuda">confirmado el <?= e(fecha_larga((string) $pago['confirmado_en'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php elseif ($pendientes === []): ?>
      <p class="pq-ayuda pq-admin-sin">Nunca ha pagado un plan.</p>
    <?php endif; ?>
  </div>
</section>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-sedes">
  <h2 class="pq-seccion-titulo" id="pq-titulo-sedes">Sedes <span class="pq-admin-cuenta"><?= count($sedes) ?></span></h2>
  <ul class="pq-admin-tarjeta pq-admin-filas">
    <?php foreach ($sedes as $sede): ?>
      <?php $abierta = (int) $sede['publicada'] === 1; ?>
      <li class="pq-admin-fila">
        <span class="pq-admin-fila-texto">
          <strong><?= e($sede['nombre']) ?></strong>
          <?php if ($abierta): ?>
            <a class="pq-ayuda pq-mono" href="<?= e(base_url('/t/' . $sede['slug'])) ?>" target="_blank" rel="noopener">/t/<?= e($sede['slug']) ?> ↗</a>
          <?php else: ?>
            <?php $pasoAlta = \App\Models\Sede::siguientePasoOnboarding(array_merge($sede, ['tipo_negocio' => $negocio['tipo_negocio']])); ?>
            <span class="pq-ayuda">Se quedó en el paso «<?= e(['foto' => 'Foto', 'productos' => $negocio['tipo_negocio'] === 'reservas' ? 'Servicios' : 'Catálogo', 'horario' => 'Horario', 'pago' => 'Abrir'][$pasoAlta]) ?>» del alta</span>
          <?php endif; ?>
        </span>
        <span class="pq-chip <?= $abierta ? 'pq-chip-caja' : 'pq-chip-pendiente' ?>"><?= $abierta ? 'Abierta' : 'Sin abrir' ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-personas">
  <h2 class="pq-seccion-titulo" id="pq-titulo-personas">Quiénes entran <span class="pq-admin-cuenta"><?= count($usuarios) ?></span></h2>
  <ul class="pq-admin-tarjeta pq-admin-filas">
    <?php foreach ($usuarios as $usuario): ?>
      <li class="pq-admin-fila">
        <span class="pq-admin-fila-texto">
          <strong><?= e($usuario['nombre']) ?> <span class="pq-ayuda">· <?= e($roles[$usuario['rol']] ?? $usuario['rol']) ?></span></strong>
          <span class="pq-ayuda pq-mono"><?= e($whatsappLegible((string) $usuario['whatsapp'])) ?><?= !empty($usuario['correo']) ? ' · ' . e($usuario['correo']) : '' ?></span>
        </span>
        <form method="post" action="<?= e(base_url('/admin/usuarios/' . $usuario['id'] . '/generar-reset')) ?>">
          <?= csrf_campo() ?>
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Enlace de contraseña</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php
// Lo irreversible-a-medias va al final y aparte, no como primer botón de
// la pantalla: suspender deja sin tienda y sin panel a todo el negocio.
?>
<section class="pq-admin-seccion pq-admin-delicado" aria-labelledby="pq-titulo-delicado">
  <h2 class="pq-seccion-titulo" id="pq-titulo-delicado">Zona delicada</h2>
  <?php if ($suspendido): ?>
    <p class="pq-ayuda">La cuenta está suspendida: nadie del negocio entra al panel y su tienda no responde.</p>
    <form method="post" action="<?= e(base_url('/admin/negocios/' . $negocio['id'] . '/reactivar')) ?>">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-sello">Reactivar cuenta</button>
    </form>
  <?php else: ?>
    <p class="pq-ayuda">Suspender deja a todo el negocio sin panel y sin tienda pública hasta que se reactive. Úsalo por fraude o abuso, no por falta de pago (eso ya baja el plan a Gratis solo).</p>
    <form method="post" action="<?= e(base_url('/admin/negocios/' . $negocio['id'] . '/suspender')) ?>" data-confirmar="¿Suspender a <?= e($negocio['nombre']) ?>? Nadie del negocio podrá entrar y su tienda dejará de responder.">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-ghost pq-peligro-btn">Suspender cuenta</button>
    </form>
  <?php endif; ?>
</section>
