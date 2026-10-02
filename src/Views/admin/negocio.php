<p class="pq-lead"><a href="<?= e(base_url('/admin')) ?>" style="color: var(--gris-suave)">← Todos los negocios</a></p>

<div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-top: 8px">
  <div>
    <span class="pq-eyebrow"><?= $negocio['tipo_negocio'] === 'reservas' ? 'Servicios con cita' : 'Productos con carrito' ?></span>
    <h1 class="pq-h1" style="font-size: 26px">
      <?= e($negocio['nombre']) ?>
      <?php if ((int) $negocio['suspendido'] === 1): ?>
        <span class="pq-chip pq-chip-cancelado" style="font-size: 11px; padding: 3px 8px; margin-left: 6px">Suspendido</span>
      <?php endif; ?>
    </h1>
  </div>

  <?php if ((int) $negocio['suspendido'] === 1): ?>
    <form method="post" action="<?= e(base_url('/admin/negocios/' . $negocio['id'] . '/reactivar')) ?>">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Reactivar cuenta</button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e(base_url('/admin/negocios/' . $negocio['id'] . '/suspender')) ?>" data-confirmar="¿Suspender esta cuenta? Nadie de este negocio podrá entrar ni su tienda pública responderá.">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Suspender cuenta</button>
    </form>
  <?php endif; ?>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!empty($resetEnlace)): ?>
  <div class="pq-card" style="margin-top: 16px; border: 1px solid var(--sello)">
    <span style="font-size: 13px; font-weight: 600">Enlace de recuperación para <?= e($resetUsuario) ?></span>
    <p class="pq-ayuda" style="margin-top: 6px">Válido por 1 hora, un solo uso. Cópialo y mándalo por WhatsApp — no se va a volver a mostrar.</p>
    <input class="pq-input pq-mono" style="margin-top: 8px; font-size: 11px" type="text" readonly data-seleccionar-al-tocar value="<?= e($resetEnlace) ?>">
  </div>
<?php endif; ?>

<div style="margin-top: 24px">
  <span style="font-size: 13px; font-weight: 600">Plan: <?= e(ucfirst($plan['nombre'])) ?></span>
  <span class="pq-ayuda">
    <?php if ($negocio['plan_estado'] === 'degradado_a_gratis'): ?>
      · degradado a Gratis por falta de pago
    <?php elseif (!empty($negocio['plan_vence_en'])): ?>
      · vence el <?= e(date('d/m/Y', strtotime((string) $negocio['plan_vence_en']))) ?>
    <?php endif; ?>
  </span>

  <div class="pq-stack" style="gap: 8px; margin-top: 8px">
    <?php foreach ($pagosPlan as $pago): ?>
      <div class="pq-card-borde" style="display: flex; align-items: center; justify-content: space-between; gap: 12px<?= $pago['confirmado_en'] === null ? '; border-color: var(--sello)' : '' ?>">
        <div class="pq-stack" style="gap: 2px">
          <span style="font-size: 13px; font-weight: 600">
            Plan <?= e(ucfirst($pago['plan_nombre'])) ?> (<?= $pago['ciclo'] === 'anual' ? 'anual' : 'mensual' ?>) · <?= pesos((int) $pago['monto']) ?>
          </span>
          <span class="pq-ayuda">
            Pedido el <?= e(date('d/m/Y', strtotime((string) $pago['creado_en']))) ?>
            <?= $pago['confirmado_en'] !== null ? ' · confirmado el ' . e(date('d/m/Y', strtotime((string) $pago['confirmado_en']))) : ' · esperando confirmación' ?>
          </span>
        </div>
        <?php if ($pago['confirmado_en'] === null): ?>
          <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
            <form method="post" action="<?= e(base_url('/admin/pagos/' . $pago['id'] . '/confirmar')) ?>" style="display: flex; gap: 6px; align-items: center" data-confirmar="¿Ya viste esta transferencia en el Bre-B de Veci? Esto activa el plan del negocio de inmediato.">
              <?= csrf_campo() ?>
              <input class="pq-input" type="text" inputmode="numeric" name="monto_recibido" required placeholder="Monto recibido" aria-label="Monto que llegó a Bre-B" style="width: 140px; padding: 7px 10px; font-size: 13px">
              <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Confirmar</button>
            </form>
            <form method="post" action="<?= e(base_url('/admin/pagos/' . $pago['id'] . '/rechazar')) ?>" data-confirmar="¿Descartar esta solicitud? El dueño podrá volver a pedir el cambio.">
              <?= csrf_campo() ?>
              <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Descartar</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($pagosPlan === []): ?>
      <p class="pq-ayuda">Sin pagos de plan registrados todavía.</p>
    <?php endif; ?>
  </div>
</div>

<div style="margin-top: 24px">
  <span style="font-size: 13px; font-weight: 600">Sedes (<?= count($sedes) ?>)</span>
  <div class="pq-stack" style="gap: 8px; margin-top: 8px">
    <?php foreach ($sedes as $sede): ?>
      <div class="pq-card-borde" style="display: flex; align-items: center; justify-content: space-between">
        <span style="font-size: 13px"><?= e($sede['nombre']) ?> <span class="pq-ayuda pq-mono">/t/<?= e($sede['slug']) ?></span></span>
        <span class="pq-chip <?= (int) $sede['publicada'] === 1 ? 'pq-chip-caja' : 'pq-chip-pendiente' ?>"><?= (int) $sede['publicada'] === 1 ? 'Publicada' : 'Sin publicar' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div style="margin-top: 24px">
  <span style="font-size: 13px; font-weight: 600">Usuarios (<?= count($usuarios) ?>)</span>
  <div class="pq-stack" style="gap: 8px; margin-top: 8px">
    <?php foreach ($usuarios as $usuario): ?>
      <div class="pq-card-borde" style="display: flex; align-items: center; justify-content: space-between; gap: 12px">
        <div class="pq-stack" style="gap: 2px">
          <span style="font-size: 13px; font-weight: 600"><?= e($usuario['nombre']) ?> <span class="pq-chip" style="font-size: 11px; padding: 2px 7px"><?= e($usuario['rol']) ?></span></span>
          <span class="pq-ayuda pq-mono"><?= e($usuario['whatsapp']) ?><?= !empty($usuario['correo']) ? ' · ' . e($usuario['correo']) : '' ?></span>
        </div>
        <form method="post" action="<?= e(base_url('/admin/usuarios/' . $usuario['id'] . '/generar-reset')) ?>">
          <?= csrf_campo() ?>
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Generar enlace de recuperación</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
