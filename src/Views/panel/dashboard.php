<?php
$sustantivo = $esReservas ? 'citas' : 'pedidos';
$sustantivoSingular = $esReservas ? 'cita' : 'pedido';
?>
<span class="pq-eyebrow"><?= e(strftime_es()) ?></span>
<h1 class="pq-h1">Hola, <?= e($negocio['nombre']) ?> 👋</h1>
<p class="pq-lead" style="margin-top: 2px">Así va tu <?= $esReservas ? 'agenda' : 'tienda' ?> hoy</p>

<div class="pq-stats" style="margin-top: 18px">
  <div class="pq-stat">
    <span class="pq-stat-icono" style="background: rgba(59,76,202,.16); color: var(--sello)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/></svg></span>
    <span class="pq-stat-valor" style="color: var(--sello)"><?= $pedidosHoy ?></span>
    <span class="pq-stat-label"><?= e($sustantivo) ?> hoy</span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-icono" style="background: rgba(22,163,106,.18); color: var(--caja)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/></svg></span>
    <span class="pq-stat-valor" style="color: var(--caja)"><?= pesos($ventasHoy) ?></span>
    <span class="pq-stat-label">ventas de hoy</span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-icono" style="background: rgba(59,76,202,.16); color: var(--sello)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 2.1 21 6l-4 4"/><path d="M3 12v-1a4 4 0 0 1 4-4h14"/><path d="M7 21.9 3 18l4-4"/><path d="M21 12v1a4 4 0 0 1-4 4H3"/></svg></span>
    <span class="pq-stat-valor" style="color: var(--sello)"><?= $recompraPct ?>%</span>
    <span class="pq-stat-label">recurrentes · este mes</span>
  </div>
  <?php if ($aReactivar > 0): ?>
    <a href="<?= e(base_url('/panel/copiloto') . '?segmento=inactivo') ?>" class="pq-stat">
      <span class="pq-stat-icono" style="background: rgba(232,69,44,.16); color: var(--aji)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5"/><path d="M12 16.5h.01"/><circle cx="12" cy="12" r="9"/></svg></span>
      <span class="pq-stat-valor" style="color: var(--aji)"><?= $aReactivar ?></span>
      <span class="pq-stat-label">por reactivar</span>
    </a>
  <?php else: ?>
    <div class="pq-stat">
      <span class="pq-stat-icono" style="background: rgba(232,69,44,.16); color: var(--aji)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5"/><path d="M12 16.5h.01"/><circle cx="12" cy="12" r="9"/></svg></span>
      <span class="pq-stat-valor" style="color: var(--aji)">0</span>
      <span class="pq-stat-label">por reactivar</span>
    </div>
  <?php endif; ?>
</div>

<?php if ($pedidosHoy === 0 && $ventasHoy === 0): ?>
  <p class="pq-ayuda" style="margin-top: 12px">
    Tu <?= $esReservas ? 'agenda' : 'tienda' ?> está tranquila por ahora. Comparte tu enlace para empezar a recibir <?= e($sustantivo) ?>.
  </p>
<?php endif; ?>

<?php if ($aReactivar > 0): ?>
  <div class="pq-recomendacion">
    <div class="pq-recomendacion-texto">
      <strong><?= $aReactivar === 1 ? 'Hay 1 cliente que podrías recuperar hoy' : "Hay {$aReactivar} clientes que podrías recuperar hoy" ?></strong>
      <span>Hace más de 30 días que no compran</span>
    </div>
    <a href="<?= e(base_url('/panel/copiloto') . '?segmento=inactivo') ?>" class="pq-btn pq-btn-sello pq-btn-chico" style="width: auto">Ver clientes →</a>
  </div>
<?php endif; ?>

<?php if ($listaEsperaCount > 0): ?>
  <div class="pq-recomendacion" style="background: var(--tinte-aji); border-color: var(--aji)">
    <div class="pq-recomendacion-texto">
      <strong><?= $listaEsperaCount === 1 ? '1 persona espera un cupo' : "{$listaEsperaCount} personas esperan un cupo" ?></strong>
      <span>Avísales si se libera un horario</span>
    </div>
    <a href="<?= e(base_url('/panel/citas')) ?>" class="pq-btn pq-btn-oscuro pq-btn-chico" style="width: auto">Ver lista →</a>
  </div>
<?php endif; ?>

<?php if ((int) $negocio['publicada'] === 1): ?>
  <?php $urlTienda = url_publica('/t/' . $negocio['slug']); ?>
  <div style="margin-top: 20px">
    <span class="pq-seccion-titulo">Tu <?= $esReservas ? 'agenda' : 'tienda' ?> online</span>
    <div class="pq-compartir pq-compartir-secundario" style="margin-top: 8px">
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
  </div>
<?php else: ?>
  <div class="pq-alerta" style="margin-top: 16px">
    Tu <?= $esReservas ? 'agenda' : 'tienda' ?> todavía no está publicada.
    <a href="<?= e(base_url('/panel/onboarding/foto')) ?>" style="font-weight: 700">Termina el alta →</a>
  </div>
<?php endif; ?>

<?php if ($esReservas): ?>
<div style="margin-top: 28px">
  <span class="pq-seccion-titulo">Próximas citas</span>

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
            <span class="pq-chip <?= e(chip_estado($cita['estado'])) ?>"><?= e(ucfirst($cita['estado'])) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <a href="<?= e(base_url('/panel/citas')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico" style="margin-top: 14px">Ver toda la agenda</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div style="margin-top: 28px">
  <span class="pq-seccion-titulo">Últimos pedidos</span>

  <?php if ($ultimosPedidos === []): ?>
    <p class="pq-ayuda" style="margin-top: 10px">Todavía no te han hecho pedidos.</p>
  <?php else: ?>
    <div class="pq-stack" style="gap: 8px; margin-top: 10px">
      <?php foreach ($ultimosPedidos as $pedido): ?>
        <?php
        $pedidoDemorado = !in_array($pedido['estado'], ['entregado', 'cancelado'], true) && nivel_espera(minutos_desde((string) $pedido['creado_en']), 20) === 'prioridad';
        $etiquetaEntrega = match ($pedido['tipo_entrega']) {
            'recoger' => 'Recoge',
            'mesa'    => 'Mesa ' . $pedido['mesa'],
            default   => 'Domicilio',
        };
        ?>
        <a href="<?= e(base_url('/panel/pedidos/' . $pedido['id'])) ?>" class="pq-fila-pedido<?= $pedidoDemorado ? ' pq-card-demorado' : '' ?>">
          <div class="pq-avatar pq-avatar-chico" style="background: var(--caja)"><?= e(mb_strtoupper(mb_substr($pedido['cliente_nombre'], 0, 1))) ?></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600"><?= e($pedido['cliente_nombre']) ?></span>
            <span class="pq-ayuda">Pedido #<?= (int) $pedido['id'] ?> · <?= e($etiquetaEntrega) ?> · <?= e(date('d M, g:i a', strtotime((string) $pedido['creado_en']))) ?></span>
          </div>
          <div class="pq-stack" style="align-items: flex-end">
            <span class="pq-mono pq-precio-suave" style="font-size: 13px"><?= pesos((int) $pedido['total']) ?></span>
            <span class="pq-chip <?= e(chip_estado($pedido['estado'])) ?>"><?= e(etiqueta_estado_pedido($pedido['estado'])) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <a href="<?= e(base_url('/panel/pedidos')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico" style="margin-top: 14px">Ver todos los pedidos</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div style="margin-top: 28px">
  <span class="pq-seccion-titulo">Acciones rápidas</span>
  <div class="pq-acciones-rapidas">
    <a href="<?= e(base_url($esReservas ? '/panel/citas' : '/panel/pedidos')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Ver <?= e($sustantivo) ?></a>
    <a href="<?= e(base_url($esReservas ? '/panel/servicios' : '/panel/productos')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Agregar <?= $esReservas ? 'servicio' : 'producto' ?></a>
    <?php if ((int) $negocio['publicada'] === 1): ?>
      <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" style="width: auto" data-copiar="<?= e(url_publica('/t/' . $negocio['slug'])) ?>">Compartir <?= $esReservas ? 'agenda' : 'tienda' ?></button>
    <?php endif; ?>
    <?php if ($negocio['rol'] === 'dueno'): ?>
      <a href="<?= e(base_url('/panel/copiloto')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Contactar clientes</a>
    <?php endif; ?>
  </div>
</div>
