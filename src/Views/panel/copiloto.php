<?php $ok = flash_obtener('ok'); ?>

<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px">
  <div>
    <span class="pq-eyebrow">Copiloto</span>
    <h1 class="pq-h1">Buenos días, <?= e($negocio['nombre']) ?></h1>
  </div>
  <a href="<?= e(base_url('/panel/clientes/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar clientes CSV</a>
</div>

<?php if ($ok): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>

<div class="pq-stats">
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--caja)"><?= $pedidosHoy ?></span>
    <span class="pq-stat-label"><?= $negocio['tipo_negocio'] === 'reservas' ? 'citas hoy' : 'pedidos hoy' ?></span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--sello)"><?= $recompraPct ?>%</span>
    <span class="pq-stat-label">recompra del mes</span>
  </div>
</div>

<?php
$etiquetas = [
    'inactivo'   => 'Inactivos',
    'vip'        => 'VIP',
    'nuevo'      => 'Nuevos',
    'recurrente' => 'Recurrentes',
    'todos'      => 'Todos',
];
$vacio = [
    'inactivo'   => 'Nadie se está quedando atrás por ahora. Vuelve a revisar mañana.',
    'vip'        => 'Todavía no tienes clientes VIP (se necesitan al menos 3 compras).',
    'nuevo'      => 'No tienes clientes con una primera compra en los últimos 30 días.',
    'recurrente' => 'No tienes clientes en este grupo por ahora.',
    'todos'      => 'Todavía no tienes clientes con compras registradas.',
];
$colorTag = ['inactivo' => 'pq-chip-pendiente', 'vip' => 'pq-chip-caja', 'nuevo' => 'pq-chip-cancelado', 'recurrente' => 'pq-chip'];
?>
<div style="margin-top: 24px">
  <div style="display: flex; gap: 8px; flex-wrap: wrap">
    <?php foreach ($etiquetas as $clave => $texto): ?>
      <a href="<?= e(base_url('/panel/copiloto') . '?segmento=' . $clave) ?>"
         class="pq-chip <?= $filtro === $clave ? 'pq-chip-caja' : '' ?>" style="text-decoration: none">
        <?= e($texto) ?><?php if ($clave !== 'todos'): ?> · <?= (int) $conteos[$clave] ?><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($lista === []): ?>
    <p class="pq-ayuda" style="margin-top: 16px"><?= e($vacio[$filtro]) ?></p>
  <?php else: ?>
    <div style="margin-top: 16px">
      <?php foreach ($lista as $fila): $cliente = $fila['cliente']; $segmentoEfectivo = $filtro === 'todos' ? $fila['tags'][0] : $filtro; ?>
        <div class="pq-lead">
          <div class="pq-avatar" style="background: var(--aji)">
            <?= e(mb_strtoupper(mb_substr($cliente['nombre'], 0, 1))) ?>
          </div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600">
              <?= e($cliente['nombre']) ?>
              <?php foreach ($fila['tags'] as $tag): ?>
                <span class="pq-chip <?= $colorTag[$tag] ?? 'pq-chip' ?>" style="font-size: 10px; padding: 2px 7px; margin-left: 4px"><?= e($etiquetas[$tag]) ?></span>
              <?php endforeach; ?>
            </span>
            <span class="pq-ayuda"><?= e($fila['motivo']) ?></span>
          </div>
          <a href="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje') . '?segmento=' . $segmentoEfectivo) ?>" class="pq-btn pq-btn-sello pq-btn-chico">Enviar</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
