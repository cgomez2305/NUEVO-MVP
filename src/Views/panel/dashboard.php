<span class="pq-eyebrow"><?= e(strftime_es()) ?></span>
<h1 class="pq-h1">Hola, <?= e($negocio['nombre']) ?></h1>

<?php if ((int) $negocio['publicada'] === 1): ?>
  <?php $urlTienda = url_publica('/t/' . $negocio['slug']); ?>
  <div class="pq-compartir">
    <a href="<?= e($urlTienda) ?>" target="_blank" rel="noopener" class="pq-compartir-url"><?= e($urlTienda) ?></a>
    <div class="pq-compartir-botones">
      <button type="button" class="pq-btn-icono" data-copiar="<?= e($urlTienda) ?>" title="Copiar enlace" aria-label="Copiar enlace">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="4" width="10" height="14" rx="2"/><path d="M8 8H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-1"/></svg>
      </button>
      <a class="pq-btn-icono" href="https://wa.me/?text=<?= rawurlencode('Mira mi tienda: ' . $urlTienda) ?>" target="_blank" rel="noopener" title="Compartir por WhatsApp" aria-label="Compartir por WhatsApp">
        <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
      </a>
    </div>
  </div>
<?php else: ?>
  <div class="pq-alerta" style="margin-top: 16px">
    Tu tienda todavía no está publicada.
    <a href="<?= e(base_url('/panel/onboarding/foto')) ?>" style="font-weight: 700">Termina el alta →</a>
  </div>
<?php endif; ?>

<div class="pq-stats">
  <div class="pq-stat">
    <span class="pq-stat-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/></svg></span>
    <span class="pq-stat-valor" style="color: var(--caja)"><?= $pedidosHoy ?></span>
    <span class="pq-stat-label"><?= $esReservas ? 'citas hoy' : 'pedidos hoy' ?></span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/></svg></span>
    <span class="pq-stat-valor" style="color: var(--sello)"><?= $recompraPct ?>%</span>
    <span class="pq-stat-label">recompra del mes</span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5"/><path d="M12 16.5h.01"/><circle cx="12" cy="12" r="9"/></svg></span>
    <span class="pq-stat-valor" style="color: var(--aji)"><?= $aReactivar ?></span>
    <span class="pq-stat-label">por reactivar</span>
  </div>
</div>

<?php if ($aReactivar > 0): ?>
  <a href="<?= e(base_url('/panel/copiloto')) ?>" class="pq-btn pq-btn-sello" style="margin-top: 20px">Ver a quién escribirle hoy →</a>
<?php endif; ?>

<?php if ($listaEsperaCount > 0): ?>
  <a href="<?= e(base_url('/panel/citas')) ?>" class="pq-btn pq-btn-oscuro" style="margin-top: 12px">
    <?= $listaEsperaCount === 1 ? '1 persona espera un cupo' : "{$listaEsperaCount} personas esperan un cupo" ?> →
  </a>
<?php endif; ?>

<?php if ($esReservas): ?>
<div style="margin-top: 28px">
  <span style="font-size: 13px; font-weight: 600; color: var(--gris-texto)">Próximas citas</span>

  <?php if ($proximasCitas === []): ?>
    <p class="pq-ayuda" style="margin-top: 10px">Todavía no tienes citas reservadas.</p>
  <?php else: ?>
    <div class="pq-stack" style="gap: 8px; margin-top: 10px">
      <?php foreach ($proximasCitas as $cita): ?>
        <?php $citaDemorada = $cita['estado'] === 'pendiente' && nivel_espera(minutos_desde((string) $cita['creado_en']), 15) === 'prioridad'; ?>
        <div class="pq-card-borde<?= $citaDemorada ? ' pq-card-demorado' : '' ?>" style="display: flex; align-items: center; gap: 12px">
          <div class="pq-avatar pq-avatar-chico" style="background: var(--sello)"><?= e(mb_strtoupper(mb_substr($cita['cliente_nombre'], 0, 1))) ?></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600"><?= e($cita['cliente_nombre']) ?> · <?= e($cita['nombre_servicio']) ?></span>
            <span class="pq-ayuda"><?= e(date('d M, g:i a', strtotime((string) $cita['fecha_hora']))) ?></span>
          </div>
          <div class="pq-stack" style="align-items: flex-end">
            <span class="pq-mono pq-precio-suave" style="font-size: 13px"><?= pesos((int) $cita['precio']) ?></span>
            <span class="pq-chip <?= e(chip_estado($cita['estado'])) ?>"><?= e($cita['estado']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <a href="<?= e(base_url('/panel/citas')) ?>" class="pq-mono" style="display: block; margin-top: 12px; font-size: 12px; color: var(--sello)">ver toda la agenda →</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div style="margin-top: 28px">
  <span style="font-size: 13px; font-weight: 600; color: var(--gris-texto)">Últimos pedidos</span>

  <?php if ($ultimosPedidos === []): ?>
    <p class="pq-ayuda" style="margin-top: 10px">Todavía no te han hecho pedidos.</p>
  <?php else: ?>
    <div class="pq-stack" style="gap: 8px; margin-top: 10px">
      <?php foreach ($ultimosPedidos as $pedido): ?>
        <?php $pedidoDemorado = !in_array($pedido['estado'], ['entregado', 'cancelado'], true) && nivel_espera(minutos_desde((string) $pedido['creado_en']), 20) === 'prioridad'; ?>
        <div class="pq-card-borde<?= $pedidoDemorado ? ' pq-card-demorado' : '' ?>" style="display: flex; align-items: center; gap: 12px">
          <div class="pq-avatar pq-avatar-chico" style="background: var(--caja)"><?= e(mb_strtoupper(mb_substr($pedido['cliente_nombre'], 0, 1))) ?></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600"><?= e($pedido['cliente_nombre']) ?></span>
            <span class="pq-ayuda"><?= e(date('d M, g:i a', strtotime((string) $pedido['creado_en']))) ?></span>
          </div>
          <div class="pq-stack" style="align-items: flex-end">
            <span class="pq-mono pq-precio-suave" style="font-size: 13px"><?= pesos((int) $pedido['total']) ?></span>
            <span class="pq-chip <?= e(chip_estado($pedido['estado'])) ?>"><?= e(etiqueta_estado_pedido($pedido['estado'])) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <a href="<?= e(base_url('/panel/pedidos')) ?>" class="pq-mono" style="display: block; margin-top: 12px; font-size: 12px; color: var(--sello)">ver todos los pedidos →</a>
  <?php endif; ?>
</div>
<?php endif; ?>
