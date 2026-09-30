<?php
$etiquetasEstado = array_combine(\App\Models\Pedido::ESTADOS, array_map('etiqueta_estado_pedido', \App\Models\Pedido::ESTADOS));

$etiquetaEntregaLarga = match ($pedido['tipo_entrega']) {
    'recoger' => 'Recoge en el local',
    'mesa'    => 'Come en el local · Mesa ' . $pedido['mesa'],
    default   => $pedido['direccion'] ?? 'Domicilio',
};

$telefonoWa = preg_replace('/\D+/', '', (string) $pedido['cliente_telefono']);
$mensajeWa = "Hola {$pedido['cliente_nombre']}, te escribo por tu pedido #{$pedido['id']} en " . e($negocio['nombre']) . '.';
$enlaceWa = 'https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($mensajeWa);

$pedidoActivo = !in_array($pedido['estado'], ['entregado', 'cancelado'], true);
$minutosEspera = minutos_desde((string) $pedido['creado_en']);
$nivel = nivel_espera($minutosEspera, 20);
?>
<a href="<?= e(base_url('/panel/pedidos')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Pedidos</a>

<div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 20px; flex-wrap: wrap">
  <div style="display: flex; align-items: center; gap: 12px">
    <div class="pq-avatar" style="background: var(--sello); width: 44px; height: 44px">
      <?= e(mb_strtoupper(mb_substr($pedido['cliente_nombre'], 0, 1))) ?>
    </div>
    <div>
      <span class="pq-serif" style="font-size: 22px; display: block; line-height: 1">#<?= (int) $pedido['id'] ?> · <?= e($pedido['cliente_nombre']) ?></span>
      <span class="pq-ayuda"><?= e($pedido['cliente_telefono']) ?> · <?= e(date('d M, g:i a', strtotime((string) $pedido['creado_en']))) ?></span>
    </div>
  </div>
  <span class="pq-chip <?= e(chip_estado($pedido['estado'])) ?>"><?= e($etiquetasEstado[$pedido['estado']] ?? $pedido['estado']) ?></span>
</div>

<?php if ($pedidoActivo): ?>
  <span class="pq-tiempo-espera pq-tiempo-<?= e($nivel) ?>" style="margin-top: 10px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
    <?= e(texto_espera($minutosEspera)) ?>
  </span>
<?php endif; ?>

<div class="pq-card-borde" style="margin-top: 16px">
  <span class="pq-eyebrow">Entrega</span>
  <p class="pq-lead" style="margin-top: 6px"><?= e($etiquetaEntregaLarga) ?></p>
  <span class="pq-ayuda">Pago por <?= e(strtoupper($pedido['metodo_pago'])) ?></span>
  <?php if (!empty($pedido['notas'])): ?>
    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--borde)">
      <span class="pq-eyebrow">Nota del cliente</span>
      <p class="pq-lead" style="margin-top: 6px; font-style: italic">"<?= e($pedido['notas']) ?>"</p>
    </div>
  <?php endif; ?>
</div>

<div class="pq-card-borde" style="margin-top: 12px">
  <span class="pq-eyebrow">Pedido</span>
  <div class="pq-stack" style="gap: 8px; margin-top: 10px">
    <?php foreach ($items as $item): ?>
      <div style="display: flex; justify-content: space-between; gap: 10px">
        <span style="font-size: 14px"><?= (int) $item['cantidad'] ?>× <?= e($item['nombre_producto']) ?></span>
        <span class="pq-mono pq-precio-suave" style="flex-shrink: 0"><?= pesos((int) $item['precio_unitario'] * (int) $item['cantidad']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="display: flex; justify-content: space-between; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--borde)">
    <span style="font-size: 14px; font-weight: 700">Total</span>
    <span class="pq-mono" style="font-size: 15px; font-weight: 700"><?= pesos((int) $pedido['total']) ?></span>
  </div>
</div>

<?php if ($siguientePaso !== null): ?>
  <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>" style="margin-top: 20px">
    <?= csrf_campo() ?>
    <input type="hidden" name="estado" value="<?= e($siguientePaso['estado']) ?>">
    <input type="hidden" name="volver" value="/panel/pedidos/<?= (int) $pedido['id'] ?>">
    <button type="submit" class="pq-btn pq-btn-sello"><?= e($siguientePaso['texto']) ?> →</button>
  </form>
<?php endif; ?>

<div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap">
  <a href="<?= e($enlaceWa) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-ghost pq-btn-chico" style="width: auto; flex-grow: 1">Contactar cliente</a>
  <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" style="width: auto; flex-grow: 1" data-imprimir>Imprimir comanda</button>
</div>

<details style="margin-top: 16px">
  <summary class="pq-mono" style="font-size: 12px; color: var(--gris-suave); cursor: pointer">Cambiar estado manualmente</summary>
  <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>" style="display: flex; gap: 8px; margin-top: 10px">
    <?= csrf_campo() ?>
    <input type="hidden" name="volver" value="/panel/pedidos/<?= (int) $pedido['id'] ?>">
    <select class="pq-select" name="estado" style="flex-grow: 1">
      <?php foreach ($etiquetasEstado as $estado => $texto): ?>
        <option value="<?= e($estado) ?>" <?= $pedido['estado'] === $estado ? 'selected' : '' ?>><?= e($texto) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Actualizar</button>
  </form>
</details>

<?php if ($pedidoActivo): ?>
  <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>"
        data-confirmar="¿Cancelar el pedido #<?= (int) $pedido['id'] ?>? El cliente no será notificado automáticamente." style="margin-top: 10px">
    <?= csrf_campo() ?>
    <input type="hidden" name="estado" value="cancelado">
    <input type="hidden" name="volver" value="/panel/pedidos">
    <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 12px; cursor: pointer; padding: 0">cancelar pedido</button>
  </form>
<?php endif; ?>
