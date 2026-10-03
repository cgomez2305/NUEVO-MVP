<?php
$nombresBonitos = ['gratis' => 'Gratis', 'barrio' => 'Barrio', 'pro' => 'Pro'];
$planActualNombre = $negocio['plan_nombre'] ?? 'gratis';
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Tu plan</span>
    <h1 class="pq-h1">Plan <?= e($nombresBonitos[$planActualNombre] ?? $planActualNombre) ?></h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">
  <?php if ($planActualNombre === 'gratis'): ?>
    Sin costo. Sube de plan cuando tu negocio lo necesite.
  <?php elseif (!empty($negocio['plan_vence_en'])): ?>
    Activo hasta el <?= e(fecha_larga((string) $negocio['plan_vence_en'])) ?><?= !empty($negocio['precio_sede_extra']) && (int) ($negocio['sedes_extra'] ?? 0) > 0 ? ', con ' . (int) $negocio['sedes_extra'] . ' sede' . ((int) $negocio['sedes_extra'] === 1 ? '' : 's') . ' extra' : '' ?>.
  <?php else: ?>
    Activo.
  <?php endif; ?>
</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok pq-pagina-aviso"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso"><?= e($error) ?></div>
<?php endif; ?>

<?php
// Cuánto le devolvió el copiloto frente a lo que paga: solo si hay algo
// real que mostrar (nunca "0 veces" ni cifras infladas).
$precioMes = (int) ($negocio['plan_precio_mensual'] ?? 0);
if (!empty($recuperado) && (int) $recuperado['total'] > 0):
?>
  <section class="pq-recuperado pq-recuperado-plan" aria-label="Lo recuperado con Veci este mes">
    <div class="pq-recuperado-cifra">
      <span class="pq-recuperado-etiqueta">Este mes Veci te ayudó a recuperar</span>
      <span class="pq-recuperado-valor pq-mono"><?= pesos((int) $recuperado['total']) ?></span>
    </div>
    <p class="pq-ayuda pq-recuperado-explica">
      <?= (int) $recuperado['clientes'] === 1 ? '1 cliente volvió' : (int) $recuperado['clientes'] . ' clientes volvieron' ?> después de tu mensaje del copiloto<?php if ($precioMes > 0 && (int) $recuperado['total'] >= $precioMes): ?>: <?= e(number_format((int) $recuperado['total'] / $precioMes, 1, ',', '.')) ?> veces lo que pagas al mes<?php endif; ?>.
      <a href="<?= e(base_url('/panel/copiloto')) ?>" class="pq-enlace-suave">Ver quiénes</a>
    </p>
  </section>
<?php endif; ?>

<?php if ((int) ($negocio['publicada'] ?? 1) !== 1 && $negocio['rol'] === 'dueno'): ?>
  <div class="pq-alerta pq-alerta-aviso pq-pagina-aviso">Tu tienda todavía no está abierta. Puedes elegir tu plan ahora y <a href="<?= e(base_url('/panel/onboarding')) ?>">terminar de armarla</a> cuando quieras.</div>
<?php endif; ?>

<?php if ($negocio['plan_estado'] === 'degradado_a_gratis'): ?>
  <div class="pq-alerta pq-alerta-aviso pq-pagina-aviso">Tu plan pago venció sin que confirmáramos un pago nuevo, así que volviste al plan Gratis. Tu tienda nunca se bloqueó.</div>
<?php endif; ?>

<?php if ($pendiente !== null): ?>
  <div class="pq-card pq-plan-pendiente">
    <?php if ($pendiente['concepto'] === 'sede_extra'): ?>
      <p class="pq-plan-pendiente-titulo">Solicitud pendiente: <?= (int) $pendiente['sedes_extra'] ?> sede extra hasta el <?= e(fecha_larga((string) $pendiente['periodo_fin'])) ?></p>
    <?php else: ?>
      <p class="pq-plan-pendiente-titulo">Solicitud pendiente: plan <?= e($nombresBonitos[$pendiente['plan_nombre']] ?? $pendiente['plan_nombre']) ?> (<?= $pendiente['ciclo'] === 'anual' ? 'anual' : 'mensual' ?>)<?= (int) $pendiente['sedes_extra'] > 0 ? ' con ' . (int) $pendiente['sedes_extra'] . ' sede' . ((int) $pendiente['sedes_extra'] === 1 ? '' : 's') . ' extra' : '' ?></p>
    <?php endif; ?>
    <?php if (!empty($wompi)): ?>
      <?php
      // Web Checkout de Wompi: monto y referencia firmados (no se pueden
      // cambiar en el navegador). Una referencia nueva por intento.
      $referenciaWompi = \App\Services\Wompi::referencia((int) $pendiente['id']);
      $centavos = (int) $pendiente['monto'] * 100;
      ?>
      <form method="get" action="<?= e(\App\Services\Wompi::urlCheckout()) ?>" class="pq-plan-wompi">
        <input type="hidden" name="public-key" value="<?= e(\App\Services\Wompi::llavePublica()) ?>">
        <input type="hidden" name="currency" value="COP">
        <input type="hidden" name="amount-in-cents" value="<?= $centavos ?>">
        <input type="hidden" name="reference" value="<?= e($referenciaWompi) ?>">
        <input type="hidden" name="signature:integrity" value="<?= e(\App\Services\Wompi::firmaIntegridad($referenciaWompi, $centavos)) ?>">
        <input type="hidden" name="redirect-url" value="<?= e(url_publica('/panel/plan/pago')) ?>">
        <button type="submit" class="pq-btn pq-btn-sello">Pagar <?= pesos((int) $pendiente['monto']) ?> ahora</button>
        <span class="pq-ayuda">Tarjeta, PSE, Nequi o Bancolombia, con Wompi. El plan se activa solo al aprobarse.</span>
      </form>
      <p class="pq-plan-pendiente-o"><span>o</span></p>
    <?php endif; ?>
    <?php if ((int) ($pendiente['descuento'] ?? 0) > 0): ?>
      <p class="pq-ayuda pq-plan-pendiente-oferta">Con el código <strong class="pq-mono"><?= e((string) $pendiente['oferta_codigo']) ?></strong>: <?= pesos((int) $pendiente['monto_lista']) ?> − <?= pesos((int) $pendiente['descuento']) ?> el primer mes.</p>
    <?php endif; ?>
    <p class="pq-plan-pendiente-texto">
      Transfiere <strong><?= pesos((int) $pendiente['monto']) ?></strong> por Bre-B
      <?php if (!empty($llaveBreb)): ?>
        a la llave <strong class="pq-mono"><?= e($llaveBreb) ?></strong>
      <?php endif; ?>
      y confirmamos tu plan apenas lo veamos (normalmente el mismo día). Si tienes dudas, escríbenos a soporte@tuveci.co.
    </p>
    <form method="post" action="<?= e(base_url('/panel/plan/cancelar')) ?>" data-confirmar="¿Cancelar esta solicitud? Si ya transferiste, escríbenos antes.">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Cancelar solicitud</button>
    </form>
  </div>
<?php endif; ?>

<?php if ($limitePedidosMes !== null || $limiteIaMes !== null): ?>
  <div class="pq-card pq-plan-uso">
    <span class="pq-plan-uso-titulo">Uso de este mes</span>
    <?php if ($limitePedidosMes !== null): ?>
      <div class="pq-barra-uso">
        <div class="pq-barra-uso-fondo"><div class="pq-barra-uso-relleno" style="width: <?= min(100, (int) round($usadosEsteMes / $limitePedidosMes * 100)) ?>%"></div></div>
        <span class="pq-barra-uso-texto"><?= (int) $usadosEsteMes ?> de <?= (int) $limitePedidosMes ?> <?= e($sustantivo) ?></span>
      </div>
    <?php endif; ?>
    <?php if ($limiteIaMes !== null): ?>
      <div class="pq-barra-uso">
        <div class="pq-barra-uso-fondo"><div class="pq-barra-uso-relleno" style="width: <?= min(100, (int) round($iaUsadaEsteMes / $limiteIaMes * 100)) ?>%"></div></div>
        <span class="pq-barra-uso-texto"><?= (int) $iaUsadaEsteMes ?> de <?= (int) $limiteIaMes ?> análisis con IA</span>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
// Código de oferta de Veci (p. ej. el del asistente del sitio): solo antes
// del primer pago y sin otra solicitud pendiente. Se aplica aquí (con la
// cédula o NIT del titular) y se canjea al pedir el plan.
?>
<?php if (!empty($puedeUsarOferta) && $pendiente === null): ?>
  <?php if (!empty($ofertaAplicada)): ?>
    <div class="pq-oferta-aplicada" role="status">
      <span class="pq-oferta-cupon pq-mono"><?= e((string) $ofertaAplicada['codigo']) ?></span>
      <span class="pq-oferta-texto"><?= (int) $ofertaAplicada['porcentaje'] ?>% menos en el primer mes de Barrio o Pro con pago mensual. Elige tu plan abajo.</span>
      <form method="post" action="<?= e(base_url('/panel/plan/oferta/quitar')) ?>">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-enlace-boton">Quitar</button>
      </form>
    </div>
  <?php elseif (!empty($ofertaPorVerificar)): ?>
    <?php // El código va al WhatsApp con que se registró la cuenta; sin él la oferta no queda aplicada. ?>
    <div class="pq-card pq-oferta-form pq-oferta-verificar">
      <p class="pq-oferta-resumen">Confirma tu WhatsApp para usar <span class="pq-mono"><?= e((string) $ofertaPorVerificar['codigo']) ?></span></p>
      <p class="pq-ayuda">Te enviamos un código de 6 dígitos al WhatsApp de tu cuenta (<span class="pq-mono"><?= e($whatsappEnmascarado) ?></span>). Vence en 10 minutos.</p>
      <form method="post" action="<?= e(base_url('/panel/plan/oferta/verificar')) ?>" class="pq-oferta-campos">
        <?= csrf_campo() ?>
        <div class="pq-campo">
          <label class="pq-label" for="oferta-otp">Código de WhatsApp</label>
          <input class="pq-input pq-mono" id="oferta-otp" name="codigo_whatsapp" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required placeholder="123456">
        </div>
        <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Confirmar</button>
      </form>
      <div class="pq-oferta-otras">
        <form method="post" action="<?= e(base_url('/panel/plan/oferta/codigo')) ?>">
          <?= csrf_campo() ?>
          <button type="submit" class="pq-enlace-boton">Enviarme otro código</button>
        </form>
        <form method="post" action="<?= e(base_url('/panel/plan/oferta/quitar')) ?>">
          <?= csrf_campo() ?>
          <button type="submit" class="pq-enlace-boton">Cancelar</button>
        </form>
      </div>
    </div>
  <?php else: ?>
    <details class="pq-card pq-oferta-form"<?= $codigoSugerido !== '' ? ' open' : '' ?>>
      <summary class="pq-oferta-resumen">¿Tienes un código de oferta?</summary>
      <form method="post" action="<?= e(base_url('/panel/plan/oferta')) ?>" class="pq-oferta-campos">
        <?= csrf_campo() ?>
        <div class="pq-campo">
          <label class="pq-label" for="oferta-codigo">Código</label>
          <input class="pq-input pq-mono" id="oferta-codigo" name="codigo" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" required value="<?= e($codigoSugerido) ?>" placeholder="VECICHAT30">
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="oferta-documento">Cédula o NIT del titular</label>
          <input class="pq-input pq-mono" id="oferta-documento" name="documento" inputmode="numeric" maxlength="20" autocomplete="off" required placeholder="1020304050">
          <span class="pq-ayuda">Las ofertas son para negocios nuevos, una por persona o empresa. No guardamos el número: solo una huella que sirve para reconocerlo si se repite.</span>
        </div>
        <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Aplicar código</button>
      </form>
    </details>
  <?php endif; ?>
<?php endif; ?>

<div class="pq-planes-grilla pq-planes-grilla-panel">
  <?php foreach ($planes as $plan): ?>
    <?php
      $esActual = $plan['nombre'] === $planActualNombre;
      // Lo que de verdad se cobraría hoy: con más sedes que las incluidas, las extra van en el precio.
      $extrasPlan = \App\Models\Plan::sedesExtraNecesarias($plan, (int) $totalSedes);
      $bullets = [];
      $bullets[] = $plan['limite_pedidos_mes'] === null ? 'Pedidos o citas ilimitados' : 'Hasta ' . (int) $plan['limite_pedidos_mes'] . ' pedidos o citas/mes';
      $bullets[] = $plan['limite_ia_mes'] === null ? 'Análisis con IA ilimitados' : (int) $plan['limite_ia_mes'] . ' análisis con IA/mes';
      if ($plan['incluye_copiloto']) {
          $bullets[] = 'Copiloto de recompra';
      }
      $bullets[] = $plan['incluye_estadisticas_completas'] ? 'Historial completo y estadísticas' : 'Historial de 30 días';
      if ($plan['incluye_multisede']) {
          $bullets[] = (int) $plan['sedes_incluidas'] . ' sedes incluidas' . ($plan['precio_sede_extra'] ? ' (+' . pesos((int) $plan['precio_sede_extra']) . '/sede extra)' : '');
      }
      if ($plan['nombre'] === 'gratis') {
          $bullets[] = 'Sello "Hecho con Veci" visible';
      }
      // Con más sedes de las que el plan incluye (y sin sedes extra a la venta), se avisa antes de elegirlo.
      $sobran = empty($plan['precio_sede_extra']) ? (int) $totalSedes - (int) $plan['sedes_incluidas'] : 0;
      if ($sobran > 0) {
          $bullets[] = 'Ojo: incluye ' . (int) $plan['sedes_incluidas'] . ' sede; ' . ($sobran === 1 ? 'tu otra sede queda' : 'tus otras ' . $sobran . ' sedes quedan') . ' en pausa';
      }
    ?>
    <?php
      $esElegido = ($planElegido ?? null) === $plan['nombre'];
      $conOferta = !empty($ofertaAplicada) && in_array($plan['nombre'], \App\Models\OfertaPlan::PLANES_VALIDOS, true);
      $descuentoOferta = $conOferta ? (int) round((int) $plan['precio_mensual'] * (int) $ofertaAplicada['porcentaje'] / 100) : 0;
      $anualElegido = $esElegido && ($cicloElegido ?? 'mensual') === 'anual';
    ?>
    <div class="pq-plan-card<?= $esActual ? ' pq-plan-card-actual' : '' ?><?= $esElegido ? ' pq-plan-card-elegido' : '' ?>" id="plan-<?= e($plan['nombre']) ?>">
      <?php if ($esElegido && !$esActual): ?><span class="pq-plan-card-badge pq-plan-card-badge-elegido">Lo elegiste</span><?php endif; ?>
      <?php if ($esActual): ?><span class="pq-plan-card-badge">Tu plan</span><?php endif; ?>
      <span class="pq-plan-card-nombre"><?= e($nombresBonitos[$plan['nombre']] ?? $plan['nombre']) ?></span>
      <span class="pq-plan-card-precio"><?= pesos((int) $plan['precio_mensual']) ?><small>/mes</small></span>
      <ul class="pq-plan-card-bullets">
        <?php foreach ($bullets as $bullet): ?><li><?= e($bullet) ?></li><?php endforeach; ?>
      </ul>
      <?php // Un plan pago actual también lleva formulario: es como se renueva (el período nuevo arranca donde termina el vigente, ver PagoPlan::confirmar). ?>
      <?php if (!$esActual || $plan['nombre'] !== 'gratis'): ?>
        <form method="post" action="<?= e(base_url('/panel/plan/solicitar')) ?>"<?= $plan['nombre'] === 'gratis' ? ' data-confirmar="¿Bajar a Gratis ahora? Pierdes lo que quede del período pagado y vuelven los límites del plan Gratis."' : '' ?>>
          <?= csrf_campo() ?>
          <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
          <?php if ($plan['nombre'] !== 'gratis'): ?>
            <label class="pq-plan-card-ciclo">
              <input type="radio" name="ciclo" value="mensual"<?= $anualElegido ? '' : ' checked' ?>> Mensual ·
              <?php if ($conOferta): ?>
                <s class="pq-precio-tachado"><?= pesos(\App\Models\Plan::precio($plan, 'mensual', $extrasPlan)) ?></s> <strong><?= pesos(\App\Models\Plan::precio($plan, 'mensual', $extrasPlan) - $descuentoOferta) ?></strong> <span class="pq-ayuda">el primer mes</span>
              <?php else: ?>
                <?= pesos(\App\Models\Plan::precio($plan, 'mensual', $extrasPlan)) ?>
              <?php endif; ?>
            </label>
            <label class="pq-plan-card-ciclo">
              <input type="radio" name="ciclo" value="anual"<?= $anualElegido ? ' checked' : '' ?>> Anual · <?= pesos(\App\Models\Plan::precio($plan, 'anual', $extrasPlan)) ?> <span class="pq-ayuda">(2 meses gratis<?= $conOferta ? '; sin el código' : '' ?>)</span>
            </label>
            <?php if ($extrasPlan > 0): ?>
              <span class="pq-ayuda">Incluye <?= $extrasPlan ?> sede<?= $extrasPlan === 1 ? '' : 's' ?> extra (<?= pesos((int) $plan['precio_sede_extra']) ?>/mes c/u).</span>
            <?php endif; ?>
          <?php endif; ?>
          <button type="submit" class="pq-btn <?= $plan['nombre'] === 'gratis' ? 'pq-btn-ghost' : 'pq-btn-sello' ?> pq-btn-chico pq-plan-card-boton">
            <?= $plan['nombre'] === 'gratis' ? 'Bajar a Gratis' : ($esActual ? 'Renovar' : 'Elegir ' . e($nombresBonitos[$plan['nombre']])) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<p class="pq-ayuda pq-plan-pie"><?= !empty($wompi) ? 'Pagas con Wompi (el plan se activa solo) o transfieres por Bre-B y un admin confirma el pago.' : 'Transfieres directo por Bre-B y un admin confirma el pago a mano — sin comisión de pasarela.' ?></p>
