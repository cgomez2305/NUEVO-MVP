<?php
$nombresBonitos = ['gratis' => 'Gratis', 'barrio' => 'Barrio', 'pro' => 'Pro'];
$planActualNombre = $negocio['plan_nombre'] ?? 'gratis';
?>
<span class="pq-eyebrow">Tu plan</span>
<h1 class="pq-h1" style="font-size: 28px">Plan <?= e($nombresBonitos[$planActualNombre] ?? $planActualNombre) ?></h1>
<p class="pq-lead">
  <?php if ($planActualNombre === 'gratis'): ?>
    Sin costo. Sube de plan cuando tu negocio lo necesite.
  <?php elseif (!empty($negocio['plan_vence_en'])): ?>
    Activo hasta el <?= date('d \d\e M \d\e Y', strtotime($negocio['plan_vence_en'])) ?>.
  <?php else: ?>
    Activo.
  <?php endif; ?>
</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($negocio['plan_estado'] === 'degradado_a_gratis'): ?>
  <div class="pq-alerta pq-alerta-aviso" style="margin-top: 16px">Tu plan pago venció sin que confirmáramos un pago nuevo, así que volviste al plan Gratis. Tu tienda nunca se bloqueó.</div>
<?php endif; ?>

<?php if ($pendiente !== null): ?>
  <div class="pq-card" style="margin-top: 20px; border: 1.5px solid var(--sello)">
    <p style="font-weight: 700; margin-bottom: 4px">Solicitud pendiente: plan <?= e($nombresBonitos[$pendiente['plan_nombre']] ?? $pendiente['plan_nombre']) ?> (<?= $pendiente['ciclo'] === 'anual' ? 'anual' : 'mensual' ?>)</p>
    <p style="font-size: 13.5px; color: var(--gris-texto); line-height: 1.5">
      Transfiere <strong><?= pesos((int) $pendiente['monto']) ?></strong> por Bre-B
      <?php if (!empty($llaveBreb)): ?>
        a la llave <strong><?= e($llaveBreb) ?></strong>
      <?php endif; ?>
      y confirmamos tu plan apenas lo veamos (normalmente el mismo día). Si tienes dudas, escríbenos a soporte@tuveci.co.
    </p>
  </div>
<?php endif; ?>

<?php if ($limitePedidosMes !== null || $limiteIaMes !== null): ?>
  <div class="pq-card" style="margin-top: 16px; display: flex; flex-direction: column; gap: 10px">
    <span style="font-size: 13px; font-weight: 700">Uso de este mes</span>
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

<div class="pq-planes-grilla" style="margin-top: 24px">
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
      <span class="pq-plan-card-precio"><?= $plan['precio_mensual'] > 0 ? pesos((int) $plan['precio_mensual']) . '/mes' : 'Gratis' ?></span>
      <ul class="pq-plan-card-bullets">
        <?php foreach ($bullets as $bullet): ?><li><?= e($bullet) ?></li><?php endforeach; ?>
      </ul>
      <?php if (!$esActual): ?>
        <form method="post" action="<?= e(base_url('/panel/plan/solicitar')) ?>">
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
          <button type="submit" class="pq-btn <?= $plan['nombre'] === 'gratis' ? 'pq-btn-ghost' : 'pq-btn-sello' ?> pq-btn-chico" style="margin-top: 10px">
            <?= $plan['nombre'] === 'gratis' ? 'Bajar a Gratis' : 'Elegir ' . e($nombresBonitos[$plan['nombre']]) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<p class="pq-ayuda" style="margin-top: 16px">Veci no procesa el dinero: transfieres directo por Bre-B y un admin confirma el pago a mano — sin comisión de pasarela.</p>
