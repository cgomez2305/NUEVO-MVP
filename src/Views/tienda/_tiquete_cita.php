<?php
/**
 * El turno como tiquete: misma hoja con borde rasgado que la comanda, la
 * hora en grande y un sello de caucho con el estado. Compartido por la
 * confirmación de la reserva y por la pantalla de gestión de la cita.
 *
 * Espera: $cita, $selloTexto, $selloTono ('' | 'ok' | 'no').
 */
$tsCita = strtotime((string) $cita['fecha_hora']) ?: 0;
$cobrado = $cita['precio_final'] !== null ? (int) $cita['precio_final'] : null;
$valorCita = \App\Models\Cita::valor($cita);
?>
<div class="pq-comanda pq-comanda-final">
  <div class="pq-comanda-hoja">
    <span class="pq-sello<?= $selloTono !== '' ? ' pq-sello-' . e($selloTono) : '' ?>" aria-hidden="true"><?= e($selloTexto) ?></span>
    <p class="pq-comanda-cabeza">
      <span>Reserva #<?= (int) $cita['id'] ?></span>
    </p>
    <div class="pq-tiquete-turno">
      <span class="pq-turno-hora"><?= e(hora_completa(date('H:i', $tsCita))) ?></span>
      <span class="pq-turno-fecha"><?= e(ucfirst((date('Y-m-d', $tsCita) === date('Y-m-d') ? 'hoy, ' : '') . fecha_larga(date('Y-m-d', $tsCita)))) ?></span>
    </div>
    <div class="pq-comanda-linea">
      <div class="pq-comanda-fila">
        <span class="pq-comanda-nombre"><?= e($cita['nombre_servicio']) ?></span>
        <span class="pq-plato-guia" aria-hidden="true"></span>
        <span class="pq-comanda-subtotal"><?= e(precio_texto($cita)) ?></span>
      </div>
      <?php if ($cobrado === null && precio_es_estimado($cita)): ?>
        <p class="pq-ayuda pq-comanda-nota">Valor estimado: se confirma al ver el trabajo.</p>
      <?php endif; ?>
    </div>
    <?php if ($cobrado !== null && $cobrado !== (int) $cita['precio']): ?>
      <div class="pq-comanda-ajuste">
        <span><?= $cita['ajuste_estado'] === 'aprobado' ? 'Valor aprobado' : 'Valor final' ?></span>
        <span class="pq-plato-guia" aria-hidden="true"></span>
        <span><?= pesos($cobrado) ?></span>
      </div>
    <?php endif; ?>
    <?php if ((int) ($cita['descuento'] ?? 0) > 0): ?>
      <div class="pq-comanda-ajuste pq-comanda-ajuste-cupon">
        <span><?= !empty($cita['cupon_codigo']) ? 'Cupón <strong>' . e($cita['cupon_codigo']) . '</strong>' : 'Descuento' ?></span>
        <span class="pq-plato-guia" aria-hidden="true"></span>
        <span>−<?= pesos((int) $cita['descuento']) ?></span>
      </div>
      <div class="pq-comanda-ajuste">
        <span><strong>A pagar</strong></span>
        <span class="pq-plato-guia" aria-hidden="true"></span>
        <span><strong><?= pesos($valorCita) ?></strong></span>
      </div>
    <?php endif; ?>
    <dl class="pq-comanda-datos">
      <div>
        <dt>Duración</dt>
        <dd><?= (int) $cita['duracion_min'] ?> min</dd>
      </div>
      <?php if (!empty($cita['empleado_nombre'])): ?>
        <div>
          <dt>Con</dt>
          <dd><?= e($cita['empleado_nombre']) ?></dd>
        </div>
      <?php endif; ?>
      <?php if ((int) $cita['anticipo_monto'] > 0): ?>
        <div>
          <dt>Anticipo</dt>
          <dd><?= pesos((int) $cita['anticipo_monto']) ?> · <?= $cita['anticipo_estado'] === 'pagado' ? 'pagado' : 'pendiente' ?></dd>
        </div>
      <?php endif; ?>
    </dl>
  </div>
</div>
