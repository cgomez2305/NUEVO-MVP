<?php
$etiquetasEstado = array_combine(\App\Models\Pedido::ESTADOS, array_map('etiqueta_estado_pedido', \App\Models\Pedido::ESTADOS));

$etiquetaEntregaLarga = match ($pedido['tipo_entrega']) {
    'recoger' => 'Recoge en el local',
    'mesa'    => 'Come en el local · Mesa ' . $pedido['mesa'],
    default   => 'Domicilio · ' . ($pedido['direccion'] ?? ''),
};

// El texto va dentro de una URL (rawurlencode): sin e(), que convertiría un
// "&" del nombre del negocio en "&amp;" literal dentro del mensaje.
$telefonoWa = preg_replace('/\D+/', '', (string) $pedido['cliente_telefono']);
$mensajeWa = "Hola {$pedido['cliente_nombre']}, te escribo por tu pedido #{$pedido['id']} en {$negocio['nombre']}.";
$enlaceWa = 'https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($mensajeWa);

$pedidoActivo = !in_array($pedido['estado'], ['entregado', 'cancelado'], true);
$minutosEspera = minutos_desde((string) $pedido['creado_en']);
$nivel = nivel_espera($minutosEspera, 20);
?>
<a href="<?= e(base_url('/panel/pedidos')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Pedidos
</a>

<div class="pq-detalle-cabeza">
  <div class="pq-detalle-quien">
    <div class="pq-avatar pq-detalle-avatar"><?= e(mb_strtoupper(mb_substr($pedido['cliente_nombre'], 0, 1))) ?></div>
    <div>
      <h1 class="pq-detalle-titulo">#<?= (int) $pedido['id'] ?> · <?= e($pedido['cliente_nombre']) ?></h1>
      <span class="pq-ayuda"><?= e($pedido['cliente_telefono']) ?> · <?= e(fecha_corta((string) $pedido['creado_en'], ', ')) ?></span>
    </div>
  </div>
  <span class="pq-chip <?= e(chip_estado($pedido['estado'])) ?>"><?= e($etiquetasEstado[$pedido['estado']] ?? $pedido['estado']) ?></span>
</div>

<?php if ($pedidoActivo): ?>
  <span class="pq-tiempo-espera pq-tiempo-<?= e($nivel) ?> pq-detalle-espera">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
    <?= e(texto_espera($minutosEspera)) ?>
  </span>
<?php endif; ?>

<?php
// La comanda: el pedido como tiquete de impresora térmica. Es lo que se ve
// en pantalla y lo ÚNICO que sale al imprimir (ver @media print en app.css),
// con el ancho de un rollo de 80 mm, lista para pegar en la cocina.
?>
<article class="pq-comanda-panel" aria-label="Comanda del pedido #<?= (int) $pedido['id'] ?>">
  <header class="pq-comanda-panel-cabeza">
    <strong><?= e(nombre_publico_sede($negocio)) ?></strong>
    <span>Pedido #<?= (int) $pedido['id'] ?> · <?= e(fecha_corta((string) $pedido['creado_en'], ', ')) ?></span>
  </header>
  <p class="pq-comanda-panel-cliente"><?= e($pedido['cliente_nombre']) ?> · <?= e($pedido['cliente_telefono']) ?></p>
  <ul class="pq-comanda-panel-items">
    <?php foreach ($items as $item): ?>
      <li>
        <span class="pq-comanda-panel-cant"><?= (int) $item['cantidad'] ?>×</span>
        <span class="pq-comanda-panel-nombre"><?= e($item['nombre_producto']) ?></span>
        <span class="pq-comanda-panel-precio"><?= pesos((int) $item['precio_unitario'] * (int) $item['cantidad']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="pq-comanda-panel-total"><span>Total</span><span><?= pesos((int) $pedido['total']) ?></span></p>
  <dl class="pq-comanda-panel-datos">
    <div><dt>Entrega</dt><dd><?= e($etiquetaEntregaLarga) ?></dd></div>
    <div><dt>Pago</dt><dd><?= e(metodo_pago_legible((string) $pedido['metodo_pago'])) ?></dd></div>
    <?php if (!empty($pedido['notas'])): ?>
      <div class="pq-comanda-panel-nota"><dt>Nota</dt><dd>"<?= e($pedido['notas']) ?>"</dd></div>
    <?php endif; ?>
  </dl>
</article>

<?php if ($siguientePaso !== null): ?>
  <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>" class="pq-detalle-accion">
    <?= csrf_campo() ?>
    <input type="hidden" name="estado" value="<?= e($siguientePaso['estado']) ?>">
    <input type="hidden" name="volver" value="/panel/pedidos/<?= (int) $pedido['id'] ?>">
    <button type="submit" class="pq-btn pq-btn-sello"><?= e($siguientePaso['texto']) ?> →</button>
  </form>
<?php endif; ?>

<div class="pq-detalle-secundarias">
  <a href="<?= e($enlaceWa) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-ghost pq-btn-chico" aria-label="Escribirle al cliente por WhatsApp">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
    WhatsApp
  </a>
  <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-imprimir aria-label="Imprimir la comanda">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7"/></svg>
    Imprimir
  </button>
</div>

<details class="pq-detalle-mas">
  <summary>Más opciones</summary>
  <div class="pq-detalle-mas-cuerpo">
    <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>" class="pq-detalle-estado">
      <?= csrf_campo() ?>
      <input type="hidden" name="volver" value="/panel/pedidos/<?= (int) $pedido['id'] ?>">
      <label class="pq-label" for="pq-estado-manual">Cambiar el estado a mano</label>
      <div class="pq-detalle-estado-fila">
        <select class="pq-select" name="estado" id="pq-estado-manual">
          <?php foreach ($etiquetasEstado as $estado => $texto): ?>
            <option value="<?= e($estado) ?>" <?= $pedido['estado'] === $estado ? 'selected' : '' ?>><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Actualizar</button>
      </div>
    </form>

    <?php if ($pedidoActivo): ?>
      <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>"
            data-confirmar="¿Cancelar el pedido #<?= (int) $pedido['id'] ?>? El cliente no será notificado automáticamente.">
        <?= csrf_campo() ?>
        <input type="hidden" name="estado" value="cancelado">
        <input type="hidden" name="volver" value="/panel/pedidos">
        <button type="submit" class="pq-boton-peligro">Cancelar este pedido</button>
      </form>
    <?php endif; ?>
  </div>
</details>
