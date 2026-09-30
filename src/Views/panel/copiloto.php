<?php $ok = flash_obtener('ok'); ?>

<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px">
  <div>
    <span class="pq-eyebrow">Copiloto</span>
    <h1 class="pq-h1">Buenos días, <?= e($negocio['nombre']) ?></h1>
    <p class="pq-lead">Encontramos clientes que podrías recuperar hoy.</p>
  </div>
  <a href="<?= e(base_url('/panel/clientes/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar clientes CSV</a>
</div>

<?php if ($ok): ?>
  <div class="pq-toast">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>

<div class="pq-stats">
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--aji)"><?= (int) $aReactivarCount ?></span>
    <span class="pq-stat-label">por reactivar</span>
  </div>
  <div class="pq-stat">
    <span class="pq-stat-valor" style="color: var(--caja)"><?= (int) $vipCount ?></span>
    <span class="pq-stat-label">clientes VIP</span>
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
$ayudaSegmento = [
    'inactivo'   => 'Compraban seguido y llevan más tiempo del habitual sin volver.',
    'vip'        => 'Tus clientes que más te compran (mínimo 3 compras).',
    'nuevo'      => 'Hicieron su primera compra hace menos de 30 días.',
    'recurrente' => 'Compran seguido y no necesitan nada especial ahora mismo.',
    'todos'      => 'Todos los clientes con al menos una compra o reserva.',
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
         class="pq-chip <?= $filtro === $clave ? 'pq-chip-caja' : '' ?>" style="text-decoration: none"
         title="<?= e($ayudaSegmento[$clave]) ?>">
        <?= e($texto) ?><?php if ($clave !== 'todos'): ?> · <?= (int) $conteos[$clave] ?><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($lista === []): ?>
    <p class="pq-ayuda" style="margin-top: 16px"><?= e($vacio[$filtro]) ?></p>
  <?php else: ?>
    <div style="margin-top: 16px">
      <?php foreach ($lista as $fila): $cliente = $fila['cliente']; $segmentoEfectivo = $filtro === 'todos' ? $fila['tags'][0] : $filtro; ?>
        <div class="pq-fila-cliente" style="align-items: flex-start">
          <div class="pq-avatar" style="background: var(--aji)">
            <?= e(mb_strtoupper(mb_substr($cliente['nombre'], 0, 1))) ?>
          </div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600">
              <?= e($cliente['nombre']) ?>
              <?php foreach ($fila['tags'] as $tag): ?>
                <span class="pq-chip <?= $colorTag[$tag] ?? 'pq-chip' ?>" style="font-size: 11px; padding: 2px 8px; margin-left: 4px"><?= e($etiquetas[$tag]) ?></span>
              <?php endforeach; ?>
            </span>
            <span class="pq-ayuda">
              <?php if ($segmentoEfectivo === 'inactivo' && $fila['frecuencia_prom'] !== null): ?>
                Compraba cada <?= (int) $fila['frecuencia_prom'] ?> días · lleva <?= (int) $fila['dias_sin_pedir'] ?> días sin pedir
              <?php else: ?>
                <?= e($fila['motivo']) ?>
              <?php endif; ?>
            </span>
          </div>
          <a href="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje') . '?segmento=' . $segmentoEfectivo) ?>" class="pq-btn pq-btn-sello pq-btn-chico">Contactar</a>
          <details class="pq-menu-kebab">
            <summary aria-label="Más acciones">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </summary>
            <div class="pq-menu-kebab-panel">
              <form method="post" action="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar todos los datos de <?= e($cliente['nombre']) ?> (incluye su historial de pedidos/citas)? Esta acción no se puede deshacer.">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-peligro" title="Borra permanentemente sus datos personales (habeas data)">Eliminar datos</button>
              </form>
            </div>
          </details>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
