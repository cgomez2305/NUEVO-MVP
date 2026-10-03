<?php
$volverUrl = '/cita/' . $cita['token_gestion'];
$volverTexto = 'Ver mi visita';
require __DIR__ . '/_cabecera_corta.php';

$porTipo = [];
foreach ($items as $item) {
    $porTipo[$item['tipo']][] = $item;
}
$titulosTipo = ['mano_obra' => 'Mano de obra', 'material' => 'Materiales', 'otro' => 'Otros', 'descuento' => 'Descuentos'];
$estado = $vencida ? 'vencida' : $cotizacion['estado'];
$sello = match ($estado) {
    'aprobada'  => ['Aprobada', 'ok'],
    'rechazada' => ['No aprobada', 'no'],
    'vencida'   => ['Vencida', 'no'],
    default     => ['Por aprobar', ''],
};
$validaHasta = date('Y-m-d', (strtotime((string) $cotizacion['creado_en']) ?: time()) + (int) $cotizacion['validez_dias'] * 86400);
$anticipo = (int) $cotizacion['anticipo'];
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Tu cotización</h1>
  <p class="pq-pagina-bajada">De <?= e(nombre_publico_sede($negocio)) ?> para <?= e($cita['nombre_servicio']) ?><?= !empty($cita['direccion']) ? ' en ' . e($cita['direccion']) : '' ?>.</p>

  <?php if (!empty($ok)): ?>
    <div class="pq-alerta pq-alerta-ok pq-confirmacion-aviso"><?= e($ok) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="pq-alerta pq-confirmacion-aviso" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <?php // El presupuesto en el mismo papel que la comanda: renglones con puntos guía y el total abajo. ?>
  <div class="pq-comanda pq-comanda-final pq-cotizacion">
    <div class="pq-comanda-hoja">
      <span class="pq-sello<?= $sello[1] !== '' ? ' pq-sello-' . $sello[1] : '' ?>" aria-hidden="true"><?= e($sello[0]) ?></span>
      <p class="pq-comanda-cabeza"><span>Cotización #<?= (int) $cotizacion['id'] ?></span></p>
      <?php foreach ($titulosTipo as $tipo => $titulo): ?>
        <?php if (!empty($porTipo[$tipo])): ?>
          <p class="pq-cotizacion-grupo"><?= e($titulo) ?></p>
          <?php foreach ($porTipo[$tipo] as $item): ?>
            <?php $subtotal = (int) $item['cantidad'] * (int) $item['valor_unitario']; ?>
            <div class="pq-comanda-fila">
              <span class="pq-comanda-nombre"><?= (int) $item['cantidad'] > 1 ? (int) $item['cantidad'] . ' × ' : '' ?><?= e($item['descripcion']) ?></span>
              <span class="pq-plato-guia" aria-hidden="true"></span>
              <span class="pq-comanda-subtotal"><?= $tipo === 'descuento' ? '−' : '' ?><?= pesos($subtotal) ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endforeach; ?>
      <div class="pq-comanda-ajuste pq-cotizacion-total">
        <span><strong>Total</strong></span>
        <span class="pq-plato-guia" aria-hidden="true"></span>
        <span><strong><?= pesos((int) $cotizacion['total']) ?></strong></span>
      </div>
      <?php if ($anticipo > 0): ?>
        <div class="pq-comanda-ajuste">
          <span>Anticipo para materiales</span>
          <span class="pq-plato-guia" aria-hidden="true"></span>
          <span><?= pesos($anticipo) ?></span>
        </div>
      <?php endif; ?>
      <dl class="pq-comanda-datos">
        <div>
          <dt>Garantía</dt>
          <dd><?= (int) $cotizacion['garantia_dias'] > 0 ? (int) $cotizacion['garantia_dias'] . ' días sobre el trabajo' : 'Sin garantía' ?></dd>
        </div>
        <?php if ($estado === 'enviada'): ?>
          <div>
            <dt>Válida hasta</dt>
            <dd><?= e(fecha_larga($validaHasta)) ?></dd>
          </div>
        <?php endif; ?>
      </dl>
      <?php if (!empty($cotizacion['nota'])): ?>
        <p class="pq-ayuda pq-comanda-nota"><?= nl2br(e($cotizacion['nota'])) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($estado === 'enviada'): ?>
    <form method="post" action="<?= e(base_url('/cotizacion/' . $cotizacion['token'])) ?>" class="pq-cotizacion-respuesta">
      <?= csrf_campo() ?>
      <button type="submit" name="respuesta" value="aprobar" class="pq-btn pq-btn-oscuro pq-btn-ancho">Aprobar por <?= pesos((int) $cotizacion['total']) ?></button>
      <button type="submit" name="respuesta" value="rechazar" class="pq-btn pq-btn-ghost-oscuro pq-btn-ancho">Por ahora no</button>
      <p class="pq-ayuda">Aprobarla le dice al negocio que puede seguir con el trabajo. ¿Dudas? Escríbele antes por WhatsApp.</p>
    </form>
  <?php elseif ($estado === 'aprobada' && $anticipo > 0 && (int) $cotizacion['anticipo_pagado'] === 0 && !empty($negocio['llave_breb_valor'])): ?>
    <?php
    $pagoTitulo = 'Cómo pagar el anticipo de materiales';
    $pagoMetodo = 'Bre-B';
    $pagoLlave = (string) $negocio['llave_breb_valor'];
    $pagoReferencia = 'VECI-Q' . (int) $cotizacion['id'];
    $pagoMonto = $anticipo;
    $pagoPara = 'reserva';
    require __DIR__ . '/_pasos_pago.php';
    ?>
  <?php elseif ($estado === 'aprobada' && $anticipo > 0): ?>
    <div class="pq-alerta pq-alerta-ok pq-confirmacion-aviso">El negocio ya recibió tu anticipo de <?= pesos($anticipo) ?>.</div>
  <?php endif; ?>
</div>
