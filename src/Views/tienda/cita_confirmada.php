<?php
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Volver a la tienda';
require __DIR__ . '/_cabecera_corta.php';

$nombreNegocio = nombre_publico_sede($negocio);
$pideAnticipo = (int) $cita['anticipo_monto'] > 0;
$mostrarPago = !empty($negocio['llave_breb_valor']) && $cita['anticipo_estado'] !== 'pagado';
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Tu reserva está lista para enviar</h1>
  <p class="pq-pagina-bajada"><?= e($nombreNegocio) ?> todavía no la ha recibido: se la mandas tú por WhatsApp, ya escrita.</p>

  <?php $selloTexto = 'Por enviar'; $selloTono = ''; require __DIR__ . '/_tiquete_cita.php'; ?>

  <?php if ($pideAnticipo && $cita['anticipo_estado'] === 'pagado'): ?>
    <div class="pq-alerta pq-alerta-ok pq-confirmacion-aviso">Ya registramos tu anticipo de <strong><?= pesos((int) $cita['anticipo_monto']) ?></strong>. ¡Gracias!</div>
  <?php elseif ($pideAnticipo): ?>
    <div class="pq-alerta pq-alerta-aviso pq-confirmacion-aviso">Esta cita queda confirmada cuando pagues el anticipo de <strong><?= pesos((int) $cita['anticipo_monto']) ?></strong>.</div>
  <?php endif; ?>

  <?php if ($mostrarPago): ?>
    <?php
    $pagoTitulo = $pideAnticipo ? 'Cómo pagar el anticipo' : 'Si quieres, adelanta el pago';
    $pagoMetodo = 'Bre-B';
    $pagoLlave = (string) $negocio['llave_breb_valor'];
    $pagoReferencia = 'VECI-C' . (int) $cita['id'];
    $pagoMonto = $pideAnticipo ? (int) $cita['anticipo_monto'] : null;
    $pagoPara = 'reserva';
    require __DIR__ . '/_pasos_pago.php';
    ?>
  <?php endif; ?>

  <?php if (!empty($tarjeta)): ?>
    <?php $pqSellos = $tarjeta['sellos']; $pqMeta = $tarjeta['meta']; $pqPremio = $tarjeta['premio']; $pqNuevo = $tarjeta['nuevo']; require __DIR__ . '/_tarjeta_sellos.php'; ?>
  <?php endif; ?>

  <a href="<?= e($enlaceWhatsapp) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-whatsapp pq-confirmar-boton">
    <svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
    <?= $mostrarPago ? 'Enviar reserva y comprobante' : 'Enviar reserva por WhatsApp' ?>
  </a>
  <p class="pq-ayuda pq-checkout-pie">Se abre WhatsApp con la reserva escrita. Revísala y pulsa Enviar para que <?= e($nombreNegocio) ?> la reciba.</p>

  <a href="<?= e(base_url('/cita/' . $cita['token_gestion'])) ?>" class="pq-enlace-accion">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
    Reprogramar o cancelar esta cita
  </a>
</div>
