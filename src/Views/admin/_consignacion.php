<?php
/**
 * Un pago de plan por confirmar, dibujado como el desprendible de una
 * consignación: arriba lo que debería haber llegado (impreso), un corte
 * perforado, y abajo lo que el equipo escribe al mirar la cuenta Bre-B de
 * Veci. Cuando lo escrito es igual a lo esperado aparece el sello
 * "Coincide" y se habilita confirmar (interacciones.js). Sin JS el botón
 * queda activo y el servidor hace la misma verificación
 * (AdminController::confirmarPago) — nunca se activa un plan con otro monto.
 *
 * Espera: $pago (de PagoPlan, con plan_nombre y, si $mostrarNegocio,
 * negocio_nombre), $mostrarNegocio (bool), $volver ('/admin' o '').
 */
$pqPagoId = (int) $pago['id'];
$pqMonto = (int) $pago['monto'];
$pqDiasPedido = dias_desde((string) $pago['creado_en']);
$pqEsSede = ($pago['concepto'] ?? 'plan') === 'sede_extra';
?>
<article class="pq-consignacion" aria-labelledby="pq-consignacion-<?= $pqPagoId ?>">
  <header class="pq-consignacion-cabeza">
    <span class="pq-consignacion-tipo">Consignación Bre-B</span>
    <span class="pq-consignacion-numero">N.º <?= str_pad((string) $pqPagoId, 5, '0', STR_PAD_LEFT) ?></span>
  </header>

  <dl class="pq-consignacion-datos">
    <?php if (!empty($mostrarNegocio)): ?>
      <div class="pq-consignacion-dato pq-consignacion-dato-ancho">
        <dt>Negocio</dt>
        <dd id="pq-consignacion-<?= $pqPagoId ?>"><a href="<?= e(base_url('/admin/negocios/' . (int) $pago['negocio_id'])) ?>"><?= e($pago['negocio_nombre']) ?></a></dd>
      </div>
    <?php else: ?>
      <span class="pq-sr-solo" id="pq-consignacion-<?= $pqPagoId ?>">Pago del plan <?= e(ucfirst((string) $pago['plan_nombre'])) ?></span>
    <?php endif; ?>
    <div class="pq-consignacion-dato">
      <dt><?= $pqEsSede ? 'Concepto' : 'Plan' ?></dt>
      <dd><?= $pqEsSede ? 'Sede extra · hasta el ' . e(fecha_larga((string) $pago['periodo_fin'])) : e(ucfirst((string) $pago['plan_nombre'])) . ' · ' . ($pago['ciclo'] === 'anual' ? 'anual' : 'mensual') . ((int) ($pago['sedes_extra'] ?? 0) > 0 ? ' + ' . (int) $pago['sedes_extra'] . ' sede extra' : '') ?></dd>
    </div>
    <div class="pq-consignacion-dato">
      <dt>Pedido</dt>
      <dd><?= e(hace_dias($pqDiasPedido)) ?></dd>
    </div>
    <div class="pq-consignacion-dato pq-consignacion-esperado">
      <dt>Debe llegar</dt>
      <dd><?= pesos($pqMonto) ?></dd>
    </div>
    <?php if ((int) ($pago['descuento'] ?? 0) > 0): ?>
      <div class="pq-consignacion-dato pq-consignacion-dato-ancho">
        <dt>Oferta</dt>
        <dd><span class="pq-mono"><?= e((string) $pago['oferta_codigo']) ?></span>: <?= pesos((int) $pago['monto_lista']) ?> − <?= pesos((int) $pago['descuento']) ?> (ya descontado arriba)</dd>
      </div>
    <?php endif; ?>
  </dl>

  <div class="pq-consignacion-corte" aria-hidden="true"></div>

  <form method="post" action="<?= e(base_url('/admin/pagos/' . $pqPagoId . '/confirmar')) ?>" class="pq-consignacion-form" data-consignacion="<?= $pqMonto ?>" data-confirmar="¿Ya viste esta transferencia en la cuenta Bre-B de Veci? <?= $pqEsSede ? 'Esto le suma la sede extra al negocio de inmediato.' : 'Esto activa el plan del negocio de inmediato.' ?>">
    <?= csrf_campo() ?>
    <?php if (!empty($volver)): ?><input type="hidden" name="volver" value="<?= e($volver) ?>"><?php endif; ?>
    <label class="pq-label" for="monto-<?= $pqPagoId ?>">¿Cuánto llegó a la cuenta de Veci?</label>
    <div class="pq-consignacion-fila">
      <div class="pq-campo-dinero">
        <input class="pq-input pq-mono" id="monto-<?= $pqPagoId ?>" type="text" inputmode="numeric" name="monto_recibido" placeholder="0" autocomplete="off" required data-precio-cop data-monto-recibido>
      </div>
      <span class="pq-consignacion-sello" data-sello-coincide hidden>Coincide</span>
    </div>
    <p class="pq-ayuda pq-consignacion-diferencia" data-monto-diferencia aria-live="polite"></p>
    <div class="pq-consignacion-botones">
      <button type="submit" class="pq-btn pq-btn-sello" data-confirmar-pago><?= $pqEsSede ? 'Confirmar y activar sede' : 'Confirmar y activar plan' ?></button>
      <button type="submit" form="pq-descartar-<?= $pqPagoId ?>" class="pq-btn pq-btn-ghost">Descartar</button>
    </div>
  </form>
  <form method="post" action="<?= e(base_url('/admin/pagos/' . $pqPagoId . '/rechazar')) ?>" id="pq-descartar-<?= $pqPagoId ?>" data-confirmar="¿Descartar esta solicitud? El dueño podrá volver a pedir el cambio de plan.">
    <?= csrf_campo() ?>
    <?php if (!empty($volver)): ?><input type="hidden" name="volver" value="<?= e($volver) ?>"><?php endif; ?>
  </form>
</article>
