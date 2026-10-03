<?php
/**
 * Pasos para pagar por transferencia (Bre-B o Nequi) en las confirmaciones
 * de pedido y de cita: llave para copiar, referencia (solo Bre-B, que la
 * concilia el webhook) y "envía el comprobante". Mismos pasos numerados con
 * la marca que el resto de la tienda.
 *
 * Espera: $pagoTitulo, $pagoMetodo ('Bre-B' | 'Nequi'), $pagoLlave,
 * $pagoReferencia (?string), $pagoMonto (?int), $pagoPara ('pedido' | 'reserva').
 * Opcional: $pagoComprobante, el texto del último paso cuando el
 * comprobante no va "junto con tu pedido/reserva" (cotización, plan).
 */
$iconoCopiar = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>';
$paso = 0;
?>
<section class="pq-pago" aria-labelledby="pq-pago-titulo">
  <h2 class="pq-pago-titulo" id="pq-pago-titulo"><?= e($pagoTitulo) ?></h2>
  <ol class="pq-pago-pasos">
    <li class="pq-pago-paso">
      <span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span>
      <div class="pq-pago-paso-cuerpo">
        <span class="pq-pago-paso-titulo">Transfiere por <?= e($pagoMetodo) ?><?= $pagoMonto !== null ? ' ' . pesos($pagoMonto) : '' ?></span>
        <div class="pq-pago-dato">
          <span><span class="pq-pago-dato-etiqueta">Llave</span> <strong><?= e($pagoLlave) ?></strong></span>
          <button type="button" class="pq-btn-icono pq-pago-copiar" data-copiar="<?= e($pagoLlave) ?>" aria-label="Copiar la llave"><?= $iconoCopiar ?></button>
        </div>
      </div>
    </li>
    <?php if (!empty($pagoReferencia)): ?>
      <li class="pq-pago-paso">
        <span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span>
        <div class="pq-pago-paso-cuerpo">
          <span class="pq-pago-paso-titulo">Escribe esta referencia en el concepto</span>
          <div class="pq-pago-dato">
            <strong class="pq-pago-referencia"><?= e($pagoReferencia) ?></strong>
            <button type="button" class="pq-btn-icono pq-pago-copiar" data-copiar="<?= e($pagoReferencia) ?>" aria-label="Copiar la referencia"><?= $iconoCopiar ?></button>
          </div>
          <span class="pq-ayuda">Así el negocio reconoce tu pago sin preguntarte.</span>
        </div>
      </li>
    <?php endif; ?>
    <li class="pq-pago-paso">
      <span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span>
      <div class="pq-pago-paso-cuerpo">
        <span class="pq-pago-paso-titulo">Envía el comprobante por WhatsApp</span>
        <span class="pq-ayuda"><?= e($pagoComprobante ?? 'Con el botón de abajo, junto con tu ' . $pagoPara . '.') ?></span>
      </div>
    </li>
  </ol>
</section>
