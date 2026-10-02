<?php
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Volver a la tienda';
require __DIR__ . '/_cabecera_corta.php';

$metodosLegibles = ['breb' => 'Bre-B', 'nequi' => 'Nequi', 'efectivo' => 'Efectivo'];
$metodo = $metodosLegibles[$pedido['metodo_pago']] ?? ucfirst((string) $pedido['metodo_pago']);
$nombreNegocio = nombre_publico_sede($negocio);
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Tu pedido está listo para enviar</h1>
  <p class="pq-pagina-bajada"><?= e($nombreNegocio) ?> todavía no lo ha recibido: se lo mandas tú por WhatsApp, ya escrito.</p>

  <?php
  // La misma comanda del carrito, ahora "sellada": el sello dice en qué
  // estado está el pedido, como el sello de caucho de la caja del negocio.
  ?>
  <div class="pq-comanda pq-comanda-final">
    <div class="pq-comanda-hoja">
      <span class="pq-sello" aria-hidden="true">Por enviar</span>
      <p class="pq-comanda-cabeza">
        <span>Pedido #<?= (int) $pedido['id'] ?></span>
        <span><?= e(fecha_corta((string) $pedido['creado_en'])) ?></span>
      </p>
      <?php foreach ($items as $item): ?>
        <div class="pq-comanda-linea">
          <div class="pq-comanda-fila">
            <span class="pq-comanda-nombre"><span class="pq-comanda-cantidad"><?= (int) $item['cantidad'] ?>×</span> <?= e($item['nombre']) ?></span>
            <span class="pq-plato-guia" aria-hidden="true"></span>
            <span class="pq-comanda-subtotal"><?= pesos((int) $item['precio'] * (int) $item['cantidad']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="pq-comanda-total">
        <span>Total</span>
        <span><?= pesos((int) $pedido['total']) ?></span>
      </div>

      <dl class="pq-comanda-datos">
        <div>
          <dt>Entrega</dt>
          <dd>
            <?php if ($pedido['tipo_entrega'] === 'recoger'): ?>
              Recoges en el local
            <?php elseif ($pedido['tipo_entrega'] === 'mesa'): ?>
              Para comer aquí · Mesa <?= e($pedido['mesa']) ?>
            <?php else: ?>
              Domicilio a <?= e($pedido['direccion']) ?>
            <?php endif; ?>
          </dd>
        </div>
        <div>
          <dt>Pago</dt>
          <dd><?= e($metodo) ?><?= $pedido['metodo_pago'] === 'efectivo' ? ' al recibir' : '' ?></dd>
        </div>
        <?php if (!empty($pedido['notas'])): ?>
          <div>
            <dt>Nota</dt>
            <dd><?= e($pedido['notas']) ?></dd>
          </div>
        <?php endif; ?>
      </dl>
    </div>
  </div>

  <?php if ($pedido['metodo_pago'] !== 'efectivo'): ?>
    <?php
    $pagoTitulo = 'Cómo pagar';
    $pagoMetodo = $metodo;
    $pagoLlave = (string) ($negocio['llave_breb_valor'] ?? $negocio['whatsapp']);
    $pagoReferencia = $pedido['metodo_pago'] === 'breb' ? 'VECI-P' . (int) $pedido['id'] : null;
    $pagoMonto = (int) $pedido['total'];
    $pagoPara = 'pedido';
    require __DIR__ . '/_pasos_pago.php';
    ?>
  <?php endif; ?>

  <a href="<?= e($enlaceWhatsapp) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-whatsapp pq-confirmar-boton">
    <svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
    <?= $pedido['metodo_pago'] === 'efectivo' ? 'Enviar pedido por WhatsApp' : 'Enviar pedido y comprobante' ?>
  </a>
  <p class="pq-ayuda pq-checkout-pie">Se abre WhatsApp con el pedido escrito. Revísalo y pulsa Enviar para que <?= e($nombreNegocio) ?> lo reciba.</p>
</div>
