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
    Activo hasta el <?= e(fecha_larga((string) $negocio['plan_vence_en'])) ?>.
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

<?php if ($negocio['plan_estado'] === 'degradado_a_gratis'): ?>
  <div class="pq-alerta pq-alerta-aviso pq-pagina-aviso">Tu plan pago venció sin que confirmáramos un pago nuevo, así que volviste al plan Gratis. Tu tienda nunca se bloqueó.</div>
<?php endif; ?>

<?php if ($pendiente !== null): ?>
  <div class="pq-card pq-plan-pendiente">
    <p class="pq-plan-pendiente-titulo">Solicitud pendiente: plan <?= e($nombresBonitos[$pendiente['plan_nombre']] ?? $pendiente['plan_nombre']) ?> (<?= $pendiente['ciclo'] === 'anual' ? 'anual' : 'mensual' ?>)</p>
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

<div class="pq-planes-grilla pq-planes-grilla-panel">
  <?php foreach ($planes as $plan): ?>
    <?php
      $esActual = $plan['nombre'] === $planActualNombre;
      $bullets = [];
      $bullets[] = $plan['limite_pedidos_mes'] === null ? 'Pedidos o citas ilimitados' : 'Hasta ' . (int) $plan['limite_pedidos_mes'] . ' pedidos o citas/mes';
      $bullets[] = $plan['limite_ia_mes'] === null ? 'Análisis con IA ilimitados' : (int) $plan['limite_ia_mes'] . ' análisis con IA/mes';
      if ($plan['incluye_copiloto']) {
          $bullets[] = 'Copiloto de recompra';
      }
      $bullets[] = $plan['incluye_estadisticas_completas'] ? 'Historial completo + exportar a CSV' : 'Historial de 30 días';
      if ($plan['incluye_multisede']) {
          $bullets[] = (int) $plan['sedes_incluidas'] . ' sedes incluidas' . ($plan['precio_sede_extra'] ? ' (+' . pesos((int) $plan['precio_sede_extra']) . '/sede extra)' : '');
      }
      if ($plan['nombre'] === 'gratis') {
          $bullets[] = 'Sello "Hecho con Veci" visible';
      }
    ?>
    <div class="pq-plan-card<?= $esActual ? ' pq-plan-card-actual' : '' ?>">
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
              <input type="radio" name="ciclo" value="mensual" checked> Mensual · <?= pesos((int) $plan['precio_mensual']) ?>
            </label>
            <label class="pq-plan-card-ciclo">
              <input type="radio" name="ciclo" value="anual"> Anual · <?= pesos((int) $plan['precio_anual']) ?>
            </label>
          <?php endif; ?>
          <button type="submit" class="pq-btn <?= $plan['nombre'] === 'gratis' ? 'pq-btn-ghost' : 'pq-btn-sello' ?> pq-btn-chico pq-plan-card-boton">
            <?= $plan['nombre'] === 'gratis' ? 'Bajar a Gratis' : ($esActual ? 'Renovar' : 'Elegir ' . e($nombresBonitos[$plan['nombre']])) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<p class="pq-ayuda pq-plan-pie">Veci no procesa el dinero: transfieres directo por Bre-B y un admin confirma el pago a mano — sin comisión de pasarela.</p>
