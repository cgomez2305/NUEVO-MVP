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
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" role="status"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!empty($premio)): ?>
  <div class="pq-premio-aviso" role="status">
    <span class="pq-premio-aviso-texto">
      <strong>Le toca premio:</strong> <?= e($premio['premio']) ?>
      <span class="pq-ayuda">Completó su tarjeta de <?= (int) $premio['meta'] ?> sellos.</span>
    </span>
    <form method="post" action="<?= e(base_url('/panel/fidelidad/' . $pedido['cliente_id'] . '/premio')) ?>" data-confirmar="¿Le entregaste <?= e($premio['premio']) ?>? Su tarjeta vuelve a empezar.">
      <?= csrf_campo() ?>
      <input type="hidden" name="volver" value="/panel/pedidos/<?= (int) $pedido['id'] ?>">
      <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Entregar premio</button>
    </form>
  </div>
<?php endif; ?>

<?php if ($pedidoActivo): ?>
  <span class="pq-tiempo-espera pq-tiempo-<?= e($nivel) ?> pq-detalle-espera">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
    <?= e(texto_espera($minutosEspera)) ?>
  </span>
<?php endif; ?>

<?php
// La comanda: el MISMO papel que el cliente vio al pedir (pedido_confirmado
// en la tienda), con un sello de caucho que dice en qué va el pedido. Al
// imprimir sale solo la comanda, sin sello, en el ancho de un rollo de
// 80 mm (ver @media print en app.css), lista para pegar en la cocina.
$selloTono = match ($pedido['estado']) {
    'entregado' => 'ok',
    'cancelado' => 'no',
    default     => '',
};
?>
<div class="pq-comanda pq-comanda-final pq-comanda-imprimible">
  <article class="pq-comanda-hoja" aria-label="Comanda del pedido #<?= (int) $pedido['id'] ?>">
    <span class="pq-sello<?= $selloTono !== '' ? ' pq-sello-' . $selloTono : '' ?>" aria-hidden="true"><?= e($etiquetasEstado[$pedido['estado']] ?? $pedido['estado']) ?></span>
    <p class="pq-comanda-negocio"><?= e(nombre_publico_sede($negocio)) ?></p>
    <p class="pq-comanda-cabeza">
      <span>Pedido #<?= (int) $pedido['id'] ?></span>
      <span><?= e(fecha_corta((string) $pedido['creado_en'], ', ')) ?></span>
    </p>
    <?php foreach ($items as $item): ?>
      <div class="pq-comanda-linea">
        <div class="pq-comanda-fila">
          <span class="pq-comanda-nombre"><span class="pq-comanda-cantidad"><?= (int) $item['cantidad'] ?>×</span> <?= e($item['nombre_producto']) ?></span>
          <span class="pq-plato-guia" aria-hidden="true"></span>
          <span class="pq-comanda-subtotal"><?= pesos((int) $item['precio_unitario'] * (int) $item['cantidad']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php require __DIR__ . '/../tienda/_pedido_ajustes.php'; ?>
    <div class="pq-comanda-total">
      <span>Total</span>
      <span><?= pesos((int) $pedido['total']) ?></span>
    </div>
    <dl class="pq-comanda-datos">
      <div><dt>Cliente</dt><dd><?= e($pedido['cliente_nombre']) ?> · <?= e($pedido['cliente_telefono']) ?></dd></div>
      <div><dt>Entrega</dt><dd><?= e($etiquetaEntregaLarga) ?></dd></div>
      <div><dt>Pago</dt><dd><?= e(metodo_pago_legible((string) $pedido['metodo_pago'])) ?></dd></div>
      <?php if (!empty($pedido['notas'])): ?>
        <div class="pq-comanda-dato-nota"><dt>Nota</dt><dd>"<?= e($pedido['notas']) ?>"</dd></div>
      <?php endif; ?>
    </dl>
  </article>
</div>

<?php $textoAviso = \App\Services\AvisoEstado::pendiente('pedido', $pedido, $negocio) ? \App\Services\AvisoEstado::texto('pedido', $pedido, $negocio) : null; ?>
<?php if ($textoAviso !== null): ?>
  <?php // El aviso se ve tal cual le va a llegar, en burbuja de WhatsApp. ?>
  <section class="pq-aviso-detalle" aria-label="Avisarle al cliente">
    <p class="pq-burbuja-out pq-aviso-burbuja"><?= e($textoAviso) ?></p>
    <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/avisar')) ?>" target="_blank">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-whatsapp pq-btn-chico"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>Avisarle por WhatsApp</button>
    </form>
  </section>
<?php endif; ?>

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
  <?php if ($pedido['estado'] === 'entregado'): ?>
    <?php // Abre WhatsApp con el enlace de un solo uso para calificar este pedido. ?>
    <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/resena')) ?>" target="_blank">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5l2.5 5.2 5.7.7-4.2 3.9 1.1 5.6L12 16.1l-5.1 2.8 1.1-5.6-4.2-3.9 5.7-.7L12 3.5Z"/></svg>
        Pedir reseña
      </button>
    </form>
  <?php endif; ?>
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
