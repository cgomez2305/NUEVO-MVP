<span class="pq-eyebrow">Pedidos</span>
<h1 class="pq-h1" style="font-size: 28px">Tus pedidos</h1>

<?php if ($pedidos === []): ?>
  <p class="pq-lead" style="margin-top: 16px">Todavía no te han hecho pedidos.</p>
<?php else: ?>
  <div class="pq-stack" style="gap: 10px; margin-top: 20px">
    <?php foreach ($pedidos as $pedido): ?>
      <div class="pq-card-borde">
        <div style="display: flex; align-items: center; justify-content: space-between">
          <div class="pq-stack">
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
