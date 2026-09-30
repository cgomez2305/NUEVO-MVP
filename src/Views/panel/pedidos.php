<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px">
  <div>
    <span class="pq-eyebrow">Pedidos</span>
    <h1 class="pq-h1" style="font-size: 28px">Tus pedidos</h1>
  </div>
  <a href="<?= e(base_url('/panel/pedidos/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar CSV</a>
</div>

<?php
$etiquetasEstado = [
    'pendiente'  => 'Pendiente',
    'pagado'     => 'Pagado',
    'en_cocina'  => 'En cocina',
    'en_camino'  => 'En camino',
    'entregado'  => 'Entregado',
    'cancelado'  => 'Cancelado',
];
?>
<?php if ($total > 0): ?>
  <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 16px">
    <a href="<?= e(base_url('/panel/pedidos')) ?>" class="pq-chip <?= $filtro === '' ? 'pq-chip-caja' : '' ?>" style="text-decoration: none">Todos · <?= $total ?></a>
    <?php foreach ($etiquetasEstado as $clave => $texto): ?>
      <?php if (($conteos[$clave] ?? 0) > 0): ?>
        <a href="<?= e(base_url('/panel/pedidos') . '?estado=' . $clave) ?>" class="pq-chip <?= $filtro === $clave ? 'pq-chip-caja' : '' ?>" style="text-decoration: none"><?= e($texto) ?> · <?= $conteos[$clave] ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($pedidos === [] && $total === 0): ?>
  <p class="pq-lead" style="margin-top: 16px">Todavía no te han hecho pedidos.</p>
<?php elseif ($pedidos === []): ?>
  <p class="pq-lead" style="margin-top: 16px">No tienes pedidos en estado «<?= e($etiquetasEstado[$filtro] ?? $filtro) ?>».</p>
<?php else: ?>
  <div class="pq-stack" style="gap: 10px; margin-top: 20px">
    <?php foreach ($pedidos as $pedido): ?>
      <div class="pq-card-borde">
        <div style="display: flex; align-items: center; gap: 12px">
          <div class="pq-avatar pq-avatar-chico" style="background: var(--sello)"><?= e(mb_strtoupper(mb_substr($pedido['cliente_nombre'], 0, 1))) ?></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600">
              #<?= (int) $pedido['id'] ?> · <?= e($pedido['cliente_nombre']) ?>
            </span>
            <span class="pq-ayuda"><?= e(date('d M, g:i a', strtotime((string) $pedido['creado_en']))) ?> · <?= e(strtoupper($pedido['metodo_pago'])) ?></span>
          </div>
          <span class="pq-mono" style="font-size: 14px; font-weight: 600"><?= pesos((int) $pedido['total']) ?></span>
        </div>

        <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>"
              style="display: flex; gap: 8px; margin-top: 12px">
          <?= csrf_campo() ?>
          <select class="pq-select" name="estado" style="flex-grow: 1">
            <?php foreach (['pendiente', 'pagado', 'en_cocina', 'en_camino', 'entregado', 'cancelado'] as $estado): ?>
              <option value="<?= e($estado) ?>" <?= $pedido['estado'] === $estado ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $estado))) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Actualizar</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
