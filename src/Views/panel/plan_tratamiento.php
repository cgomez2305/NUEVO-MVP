<?php
use App\Models\PlanTratamiento;

$id = (int) $plan['id'];
$base = '/panel/planes/' . $id;
$estado = PlanTratamiento::vencido($plan) ? 'vencido' : $plan['estado'];
$saldo = PlanTratamiento::saldo($plan);
$pagadoPct = (int) $plan['total'] > 0 ? min(100, (int) round((int) $plan['pagado'] * 100 / (int) $plan['total'])) : 0;
$esDueno = $negocio['rol'] === 'dueno';
$abierto = in_array($plan['estado'], ['propuesto', 'aprobado'], true);
$fasesPorId = array_column($fases, null, 'id');
$hoy = date('Y-m-d');
/** Las sesiones de una fase como agujeros de una tarjeta de citas: hecha (perforada), agendada (marcada) o pendiente. */
$tarjeta = static function (array $fase): string {
    $html = '<span class="pq-plan-perforado" role="img" aria-label="' . (int) $fase['hechas'] . ' de ' . (int) $fase['sesiones'] . ' sesiones hechas">';
    for ($i = 0; $i < (int) $fase['sesiones']; $i++) {
        $clase = $i < (int) $fase['hechas'] ? ' pq-plan-hueco-hecho' : ($i < (int) $fase['hechas'] + (int) $fase['agendadas'] ? ' pq-plan-hueco-agendado' : '');
        $html .= '<span class="pq-plan-hueco' . $clase . '"></span>';
    }

    return $html . '</span>';
};
?>
<div class="pq-pagina-cabeza">
  <div>
    <a class="pq-volver-panel" href="<?= e(base_url('/panel/planes')) ?>">← Planes</a>
    <h1 class="pq-h1"><?= e($plan['titulo']) ?></h1>
  </div>
  <span class="pq-chip <?= match ($estado) { 'aprobado' => 'pq-chip-curso', 'terminado' => 'pq-chip-caja', 'propuesto' => 'pq-chip-pendiente', default => 'pq-chip-cancelado' } ?>"><?= e($estado === 'vencido' ? 'Venció sin aprobar' : PlanTratamiento::ETIQUETAS[$estado]) ?></span>
</div>
<p class="pq-lead pq-pagina-bajada-panel"><?= e($plan['cliente_nombre']) ?> · creado el <?= e(fecha_larga(date('Y-m-d', strtotime((string) $plan['creado_en'])))) ?><?php if (!empty($plan['rehecho_de'])): ?> · <a href="<?= e(base_url('/panel/planes/' . (int) $plan['rehecho_de'])) ?>">rehecho del plan #<?= (int) $plan['rehecho_de'] ?></a><?php endif; ?></p>
<?php if (in_array($plan['estado'], ['aprobado', 'terminado'], true) && !empty($plan['respondido_en'])): ?>
  <?php // La constancia de la aprobación: por dónde y, si fue en persona, quién la marcó. ?>
  <p class="pq-ayuda pq-plan-constancia">
    <?= $plan['aprobado_canal'] === 'consultorio'
        ? 'Aprobado en el consultorio el ' . e(fecha_corta((string) $plan['respondido_en'])) . (!empty($plan['aprobado_por_nombre']) ? ' · lo marcó ' . e($plan['aprobado_por_nombre']) : '')
        : 'Aprobado por el paciente desde su enlace el ' . e(fecha_corta((string) $plan['respondido_en'])) ?>
  </p>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if (!empty($whatsapp)): ?>
  <div class="pq-visita-wa">
    <span><strong>Mensaje listo.</strong> Ábrelo en WhatsApp y dale Enviar.</span>
    <a class="pq-btn pq-btn-sello pq-btn-chico" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">Abrir WhatsApp</a>
  </div>
<?php endif; ?>

<div class="pq-ficha-panel">
  <?php // El estado de cuenta arriba: es lo que más se pregunta en recepción. ?>
  <section class="pq-ficha-bloque pq-plan-cuenta" aria-label="Cuenta del plan">
    <div class="pq-plan-cifras">
      <span><span class="pq-ayuda">Total</span><strong class="pq-mono"><?= pesos((int) $plan['total']) ?></strong></span>
      <span><span class="pq-ayuda">Abonado</span><strong class="pq-mono"><?= pesos((int) $plan['pagado']) ?></strong></span>
      <span><span class="pq-ayuda">Saldo</span><strong class="pq-mono<?= $saldo > 0 ? ' pq-plan-debe' : '' ?>"><?= pesos($saldo) ?></strong></span>
    </div>
    <span class="pq-plan-barra pq-plan-barra-grande" aria-hidden="true"><span style="width: <?= $pagadoPct ?>%"></span></span>
    <div class="pq-visita-acciones">
      <a class="pq-btn pq-btn-ghost pq-btn-chico" href="<?= e(url_publica('/plan/' . $plan['token'])) ?>" target="_blank" rel="noopener">Verlo como el paciente</a>
      <?php if ($estado === 'propuesto'): ?>
        <a class="pq-btn pq-btn-ghost pq-btn-chico" href="<?= e('https://wa.me/57' . preg_replace('/\D+/', '', (string) $plan['cliente_telefono']) . '?text=' . rawurlencode(PlanTratamiento::mensajePlan($plan, $negocio))) ?>" target="_blank" rel="noopener">Mandarle el plan</a>
      <?php endif; ?>
      <?php if (PlanTratamiento::rehacible($plan)): ?>
        <a class="pq-btn pq-btn-sello pq-btn-chico" href="<?= e(base_url('/panel/planes/nuevo?desde=' . $id)) ?>">Hacer uno nuevo a partir de este</a>
      <?php endif; ?>
      <?php if (PlanTratamiento::recibeAbonos($plan)): ?>
        <?php if ($recordar['permitido']): ?>
          <form method="post" action="<?= e(base_url($base . '/recordar')) ?>" target="_blank">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Recordarle el saldo</button>
          </form>
        <?php else: ?>
          <span class="pq-ayuda pq-plan-recordar-no">Recordar el saldo: <?= e((string) $recordar['razon']) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php if ($estado === 'propuesto'): ?>
      <?php // Para el paciente que no abre enlaces: lo aprueba en recepción y queda la constancia de quién lo marcó. ?>
      <form method="post" action="<?= e(base_url($base . '/aprobar-consultorio')) ?>" class="pq-plan-presencial">
        <?= csrf_campo() ?>
        <label class="pq-reglas-opcion"><input type="checkbox" name="confirmo" value="1" required><span>El paciente revisó las fases y el total, y lo aprobó aquí en persona</span></label>
        <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Aprobado en el consultorio</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="pq-ficha-bloque" aria-labelledby="pq-fases-titulo">
    <h2 class="pq-seccion-titulo" id="pq-fases-titulo">Fases</h2>
    <ol class="pq-plan-fases">
      <?php foreach ($fases as $fase): ?>
        <li class="pq-plan-fase<?= (int) $fase['hechas'] >= (int) $fase['sesiones'] ? ' pq-plan-fase-lista' : '' ?>">
          <span class="pq-plan-fase-numero" aria-hidden="true"><?= (int) $fase['orden'] ?></span>
          <span class="pq-plan-fase-texto">
            <strong><?= e($fase['nombre']) ?></strong>
            <span class="pq-ayuda"><?= (int) $fase['hechas'] ?> de <?= (int) $fase['sesiones'] === 1 ? '1 sesión' : (int) $fase['sesiones'] . ' sesiones' ?><?= (int) $fase['agendadas'] > 0 ? ' · ' . (int) $fase['agendadas'] . ' agendada' . ((int) $fase['agendadas'] === 1 ? '' : 's') : '' ?></span>
            <?= $tarjeta($fase) ?>
          </span>
          <span class="pq-mono pq-plan-fase-valor"><?= pesos((int) $fase['valor']) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if (!empty($plan['nota'])): ?>
      <p class="pq-ayuda">Nota: <?= e($plan['nota']) ?></p>
    <?php endif; ?>
  </section>

  <section class="pq-ficha-bloque" id="sesiones" aria-labelledby="pq-sesiones-titulo">
    <h2 class="pq-seccion-titulo" id="pq-sesiones-titulo">Citas del plan</h2>
    <p class="pq-ayuda">Una cita del plan vale $0 en la caja: la plata entra por los abonos (así no se cobra dos veces).</p>
    <?php if ($citas !== []): ?>
      <ul class="pq-admin-filas pq-plan-citas">
        <?php foreach ($citas as $cita): ?>
          <li class="pq-admin-fila">
            <span class="pq-admin-fila-texto">
              <strong><?= e(fecha_corta((string) $cita['fecha_hora'])) ?> · <?= e($cita['nombre_servicio']) ?></strong>
              <span class="pq-ayuda"><?= e($fasesPorId[(int) $cita['plan_fase_id']]['nombre'] ?? '') ?> · <?= e(\App\Models\Cita::ETIQUETAS[$cita['estado']] ?? $cita['estado']) ?><?= !empty($cita['empleado_nombre']) ? ' · con ' . e($cita['empleado_nombre']) : '' ?></span>
            </span>
            <?php if ($cita['estado'] !== 'completada'): ?>
              <form method="post" action="<?= e(base_url($base . '/citas/' . (int) $cita['id'] . '/desvincular')) ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-enlace-boton">Sacar del plan</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($plan['estado'] === 'aprobado'): ?>
      <?php if ($vinculables !== []): ?>
        <form method="post" action="<?= e(base_url($base . '/vincular')) ?>" class="pq-plan-vincular">
          <?= csrf_campo() ?>
          <select class="pq-select" name="cita_id" required aria-label="Cita del paciente">
            <option value="">Cita de <?= e(explode(' ', trim((string) $plan['cliente_nombre']))[0]) ?>…</option>
            <?php foreach ($vinculables as $cita): ?>
              <option value="<?= (int) $cita['id'] ?>"><?= e(fecha_corta((string) $cita['fecha_hora'])) ?> · <?= e($cita['nombre_servicio']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="pq-select" name="fase_id" required aria-label="Fase">
            <?php foreach ($fases as $fase): ?>
              <option value="<?= (int) $fase['id'] ?>" <?= (int) $fase['hechas'] < (int) $fase['sesiones'] && !isset($elegida) && ($elegida = true) ? 'selected' : '' ?>><?= (int) $fase['orden'] ?>. <?= e($fase['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Poner en el plan</button>
        </form>
      <?php else: ?>
        <p class="pq-ayuda">Cuando <?= e(explode(' ', trim((string) $plan['cliente_nombre']))[0]) ?> reserve (o le agendes) una cita, aparece aquí para ponerla en su fase.</p>
      <?php endif; ?>
    <?php elseif ($plan['estado'] === 'propuesto'): ?>
      <p class="pq-ayuda">Las citas se ponen en el plan cuando el paciente lo apruebe.</p>
    <?php endif; ?>
  </section>

  <section class="pq-ficha-bloque" id="abonos" aria-labelledby="pq-abonos-titulo">
    <h2 class="pq-seccion-titulo" id="pq-abonos-titulo">Abonos</h2>
    <?php if ($abonos !== []): ?>
      <ul class="pq-admin-filas pq-plan-abonos">
        <?php foreach ($abonos as $abono): ?>
          <?php $anulado = (int) $abono['anulado'] === 1; ?>
          <li class="pq-admin-fila<?= $anulado ? ' pq-plan-abono-anulado' : '' ?>">
            <span class="pq-admin-fila-texto">
              <strong class="pq-mono"><?= pesos((int) $abono['monto']) ?></strong>
              <span class="pq-ayuda"><?= e(fecha_corta((string) $abono['creado_en'])) ?> · <?= e(PlanTratamiento::METODOS_ABONO[$abono['metodo']] ?? $abono['metodo']) ?><?= !empty($abono['nota']) ? ' · ' . e($abono['nota']) : '' ?><?= !empty($abono['usuario_nombre']) ? ' · lo anotó ' . e($abono['usuario_nombre']) : '' ?><?= $anulado ? ' · anulado' : '' ?></span>
            </span>
            <?php if ($esDueno && !$anulado && date('Y-m-d', strtotime((string) $abono['creado_en'])) === $hoy): ?>
              <form method="post" action="<?= e(base_url($base . '/abonos/' . (int) $abono['id'] . '/anular')) ?>" data-confirmar="¿Anular el abono de <?= pesos((int) $abono['monto']) ?>?">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Anular</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if (PlanTratamiento::recibeAbonos($plan)): ?>
      <form method="post" action="<?= e(base_url($base . '/abonos')) ?>" class="pq-plan-abonar">
        <?= csrf_campo() ?>
        <div class="pq-campo-dinero"><input class="pq-input pq-mono" type="text" inputmode="numeric" name="monto" placeholder="Valor" required data-precio-cop aria-label="Valor del abono"></div>
        <select class="pq-select" name="metodo" aria-label="Cómo pagó">
          <?php foreach (PlanTratamiento::METODOS_ABONO as $clave => $etiqueta): ?>
            <option value="<?= $clave ?>"><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="pq-input" type="text" name="nota" maxlength="160" placeholder="Nota (opcional)" aria-label="Nota del abono">
        <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Anotar abono</button>
      </form>
    <?php elseif ($plan['estado'] === 'propuesto'): ?>
      <p class="pq-ayuda">Los abonos se anotan cuando el paciente apruebe el plan.</p>
    <?php elseif (in_array($plan['estado'], ['aprobado', 'terminado'], true)): ?>
      <p class="pq-ayuda pq-plan-pago">Pagado completo.</p>
    <?php endif; ?>
  </section>

  <?php if ($esDueno && $abierto): ?>
    <section class="pq-ficha-bloque pq-ficha-acciones">
      <?php if ($plan['estado'] === 'aprobado'): ?>
        <form method="post" action="<?= e(base_url($base . '/cerrar')) ?>" data-confirmar="¿Marcar el plan como terminado? Si queda saldo, lo sigues pudiendo abonar y recordar. Las citas que sigan agendadas quedan dentro del plan, sin cobro aparte.">
          <?= csrf_campo() ?>
          <input type="hidden" name="estado" value="terminado">
          <button type="submit" class="pq-enlace-boton">Terminar el plan</button>
        </form>
      <?php endif; ?>
      <form method="post" action="<?= e(base_url($base . '/cerrar')) ?>" data-confirmar="¿Cancelar este plan? Los abonos quedan registrados y las citas que seguían agendadas vuelven a cobrarse aparte.">
        <?= csrf_campo() ?>
        <input type="hidden" name="estado" value="cancelado">
        <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Cancelar el plan</button>
      </form>
    </section>
  <?php endif; ?>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
