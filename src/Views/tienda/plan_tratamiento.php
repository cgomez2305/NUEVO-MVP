<?php
use App\Models\PlanTratamiento;

$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ir al consultorio';
require __DIR__ . '/_cabecera_corta.php';

$estado = $vencido ? 'vencido' : $plan['estado'];
$saldo = PlanTratamiento::saldo($plan);
$pagadoPct = (int) $plan['total'] > 0 ? min(100, (int) round((int) $plan['pagado'] * 100 / (int) $plan['total'])) : 0;
$sello = match ($estado) {
    'aprobado'  => ['En tratamiento', 'ok'],
    'terminado' => ['Terminado', 'ok'],
    'propuesto' => ['Por aprobar', ''],
    'vencido'   => ['Vencido', 'no'],
    default     => [PlanTratamiento::ETIQUETAS[$estado] ?? $estado, 'no'],
};
$validoHasta = date('Y-m-d', (strtotime((string) $plan['creado_en']) ?: time()) + (int) $plan['validez_dias'] * 86400);
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Tu plan de tratamiento</h1>
  <p class="pq-pagina-bajada"><?= e($plan['titulo']) ?> · <?= e(nombre_publico_sede($negocio)) ?></p>

  <?php if (!empty($ok)): ?>
    <div class="pq-alerta pq-alerta-ok pq-confirmacion-aviso"><?= e($ok) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="pq-alerta pq-confirmacion-aviso" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <?php // Las fases como una tarjeta de citas de papel: cada sesión es un hueco que se perfora al atenderla. ?>
  <div class="pq-comanda pq-comanda-final pq-plan-carta">
    <div class="pq-comanda-hoja">
      <span class="pq-sello<?= $sello[1] !== '' ? ' pq-sello-' . $sello[1] : '' ?>" aria-hidden="true"><?= e($sello[0]) ?></span>
      <p class="pq-comanda-cabeza"><span>Plan #<?= (int) $plan['id'] ?> · <?= e(explode(' ', trim((string) $plan['cliente_nombre']))[0]) ?></span></p>
      <ol class="pq-plan-fases pq-plan-fases-tienda">
        <?php foreach ($fases as $fase): ?>
          <li class="pq-plan-fase<?= (int) $fase['hechas'] >= (int) $fase['sesiones'] ? ' pq-plan-fase-lista' : '' ?>">
            <span class="pq-plan-fase-numero" aria-hidden="true"><?= (int) $fase['orden'] ?></span>
            <span class="pq-plan-fase-texto">
              <strong><?= e($fase['nombre']) ?></strong>
              <span class="pq-ayuda"><?= (int) $fase['sesiones'] ?> sesión<?= (int) $fase['sesiones'] === 1 ? '' : 'es' ?><?= $plan['estado'] === 'aprobado' ? ' · ' . (int) $fase['hechas'] . ' hecha' . ((int) $fase['hechas'] === 1 ? '' : 's') : '' ?></span>
              <?php if ($plan['estado'] !== 'propuesto'): ?>
                <span class="pq-plan-perforado" role="img" aria-label="<?= (int) $fase['hechas'] ?> de <?= (int) $fase['sesiones'] ?> sesiones hechas">
                  <?php for ($i = 0; $i < (int) $fase['sesiones']; $i++): ?><span class="pq-plan-hueco<?= $i < (int) $fase['hechas'] ? ' pq-plan-hueco-hecho' : ($i < (int) $fase['hechas'] + (int) $fase['agendadas'] ? ' pq-plan-hueco-agendado' : '') ?>"></span><?php endfor; ?>
                </span>
              <?php endif; ?>
            </span>
            <span class="pq-plan-fase-valor"><?= pesos((int) $fase['valor']) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="pq-comanda-ajuste pq-cotizacion-total">
        <span><strong>Total del tratamiento</strong></span>
        <span class="pq-plato-guia" aria-hidden="true"></span>
        <span><strong><?= pesos((int) $plan['total']) ?></strong></span>
      </div>
      <?php if ($plan['estado'] !== 'propuesto'): ?>
        <div class="pq-comanda-ajuste"><span>Abonado</span><span class="pq-plato-guia" aria-hidden="true"></span><span><?= pesos((int) $plan['pagado']) ?></span></div>
        <div class="pq-comanda-ajuste"><span><strong>Saldo</strong></span><span class="pq-plato-guia" aria-hidden="true"></span><span><strong><?= pesos($saldo) ?></strong></span></div>
        <span class="pq-plan-barra" aria-hidden="true"><span style="width: <?= $pagadoPct ?>%"></span></span>
      <?php endif; ?>
      <?php if ($estado === 'propuesto'): ?>
        <dl class="pq-comanda-datos"><div><dt>Válido hasta</dt><dd><?= e(fecha_larga($validoHasta)) ?></dd></div></dl>
      <?php endif; ?>
      <?php if (!empty($plan['nota'])): ?>
        <p class="pq-ayuda pq-comanda-nota"><?= nl2br(e($plan['nota'])) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($estado === 'propuesto'): ?>
    <form method="post" action="<?= e(base_url('/plan/' . $plan['token'])) ?>" class="pq-cotizacion-respuesta">
      <?= csrf_campo() ?>
      <button type="submit" name="respuesta" value="aprobar" class="pq-btn pq-btn-oscuro pq-btn-ancho">Aprobar mi plan</button>
      <button type="submit" name="respuesta" value="rechazar" class="pq-btn pq-btn-ghost-oscuro pq-btn-ancho">Por ahora no</button>
      <p class="pq-ayuda">Aprobarlo no te cobra nada todavía: abonas en el consultorio o por transferencia, como acuerdes.</p>
    </form>
  <?php elseif (PlanTratamiento::recibeAbonos($plan) && !empty($negocio['llave_breb_valor'])): ?>
    <?php
    $pagoTitulo = 'Si quieres abonar por transferencia';
    $pagoMetodo = 'Bre-B';
    $pagoLlave = (string) $negocio['llave_breb_valor'];
    $pagoReferencia = null; // el webhook de Bre-B solo concilia pedidos y citas: aquí el comprobante va por WhatsApp
    $pagoMonto = null;
    $pagoPara = 'reserva';
    $pagoComprobante = 'Al consultorio, con el botón de abajo: así anotan tu abono.';
    require __DIR__ . '/_pasos_pago.php';
    ?>
    <a class="pq-btn pq-btn-oscuro pq-btn-ancho pq-pago-wa" href="https://wa.me/57<?= e(preg_replace('/\D+/', '', (string) $negocio['whatsapp'])) ?>?text=<?= rawurlencode('Hola, te mando el comprobante de mi abono al plan de tratamiento #' . (int) $plan['id'] . '.') ?>" target="_blank" rel="noopener">Enviar el comprobante por WhatsApp</a>
  <?php endif; ?>

  <?php if ($abonos !== []): ?>
    <section class="pq-evidencia" aria-labelledby="pq-abonos-titulo">
      <h2 class="pq-carta-titulo" id="pq-abonos-titulo">Tus abonos</h2>
      <ul class="pq-plan-abonos-tienda">
        <?php foreach ($abonos as $abono): ?>
          <li><span><?= e(fecha_larga(date('Y-m-d', strtotime((string) $abono['creado_en'])))) ?></span><span class="pq-plato-guia" aria-hidden="true"></span><strong><?= pesos((int) $abono['monto']) ?></strong></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
  <p class="pq-ayuda pq-plan-legal">Este plan es el presupuesto de tu tratamiento; tu historia clínica la lleva el consultorio aparte.</p>
</div>
