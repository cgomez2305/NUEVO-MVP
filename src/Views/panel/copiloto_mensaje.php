<?php
$urlActual = base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje') . '?segmento=' . $segmento . '&descuento=' . $descuento;
// "Antes compraba cada N días" solo es verdad si de verdad ya se pasó de su
// ritmo; si no, se muestra el motivo general (no inventar una ausencia).
$estaAtrasado = $contexto !== null && $segmento === 'inactivo' && $contexto['frecuencia_prom'] !== null
    && (int) $contexto['dias_sin_pedir'] > (int) $contexto['frecuencia_prom'];
?>
<a href="<?= e(base_url('/panel/copiloto') . '?segmento=' . $segmento) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Copiloto
</a>

<div class="pq-detalle-cabeza">
  <div class="pq-detalle-quien">
    <div class="pq-avatar pq-detalle-avatar"><?= e(mb_strtoupper(mb_substr($cliente['nombre'], 0, 1))) ?></div>
    <div>
      <h1 class="pq-detalle-titulo"><?= e($cliente['nombre']) ?></h1>
      <span class="pq-ayuda"><?= e($cliente['telefono']) ?></span>
    </div>
  </div>
</div>

<?php if ($contexto !== null): ?>
  <section class="pq-contexto-cliente" aria-labelledby="pq-titulo-porque">
    <h2 class="pq-contexto-titulo" id="pq-titulo-porque">Por qué escribirle</h2>
    <p class="pq-contexto-motivo">
      <?php if ($estaAtrasado): ?>
        Compraba cada <?= (int) $contexto['frecuencia_prom'] ?> días y ya lleva <?= (int) $contexto['dias_sin_pedir'] ?> sin pedir.
      <?php else: ?>
        <?= e(match ($segmento) {
            'vip'        => 'Es de los clientes que más te compran: un mensaje a tiempo lo mantiene cerca.',
            'nuevo'      => 'Compró por primera vez hace poco: un saludo ayuda a que vuelva.',
            'recurrente' => 'Compra seguido. Un mensaje amable mantiene la costumbre.',
            default      => 'Todavía está dentro de su ritmo de compra: no hace falta insistir.',
        }) ?>
      <?php endif; ?>
    </p>
    <dl class="pq-contexto-datos">
      <div>
        <dt>Última compra</dt>
        <dd><span class="pq-mono"><?= pesos((int) $contexto['ultima_compra_monto']) ?></span> · <?= e(hace_dias((int) $contexto['dias_sin_pedir'])) ?></dd>
      </div>
      <div>
        <dt>En total</dt>
        <dd><?= (int) $contexto['total_compras'] ?> compras · <span class="pq-mono"><?= pesos((int) $contexto['gasto_total']) ?></span></dd>
      </div>
    </dl>
    <?php if ($contacto !== null): ?>
      <p class="pq-ayuda pq-contexto-contacto">
        Le escribiste <?= e(hace_dias((int) $contacto['dias'])) ?><?= $comproDespues ? ' · y volvió a comprar' : '' ?>
      </p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php
// El mensaje se edita dentro de la misma burbuja verde que va a recibir el
// cliente: lo que se ve es exactamente lo que se manda.
?>
<section class="pq-wa-vista" aria-labelledby="pq-titulo-mensaje">
  <div class="pq-wa-cabeza">
    <h2 class="pq-seccion-titulo" id="pq-titulo-mensaje">Mensaje</h2>
    <form method="get" action="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje')) ?>" class="pq-wa-descuento">
      <input type="hidden" name="segmento" value="<?= e($segmento) ?>">
      <label class="pq-sr-solo" for="pq-descuento">Descuento</label>
      <select class="pq-select" id="pq-descuento" name="descuento" data-autoenviar>
        <option value="0" <?= $descuento === 0 ? 'selected' : '' ?>>Sin descuento</option>
        <option value="5" <?= $descuento === 5 ? 'selected' : '' ?>>Con 5% off</option>
        <option value="10" <?= $descuento === 10 ? 'selected' : '' ?>>Con 10% off</option>
        <option value="15" <?= $descuento === 15 ? 'selected' : '' ?>>Con 15% off</option>
      </select>
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico pq-sin-js">Aplicar</button>
    </form>
  </div>

  <form method="get" action="<?= e($cliente['telefono'] ? $waBase : '#') ?>" class="pq-wa-form">
    <div class="pq-wa-fondo">
      <label class="pq-sr-solo" for="mensaje-copiloto">Texto del mensaje</label>
      <textarea name="text" id="mensaje-copiloto" class="pq-burbuja-out pq-wa-editable" rows="4" maxlength="500" data-contador><?= e($mensaje) ?></textarea>
    </div>
    <p class="pq-ayuda pq-wa-nota">Puedes editarlo. Veci nunca ofrece descuentos por su cuenta: solo si los eliges arriba.</p>
    <div class="pq-wa-secundarias">
      <a href="<?= e($urlActual) ?>" class="pq-enlace-boton">Otra versión</a>
      <button type="button" class="pq-enlace-boton" data-copiar-de="#mensaje-copiloto">Copiar texto</button>
    </div>
    <button type="submit" class="pq-btn pq-btn-whatsapp">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
      Abrir WhatsApp con el mensaje
    </button>
  </form>
</section>

<form method="post" action="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/enviar')) ?>" class="pq-recordatorio-listo">
  <?= csrf_campo() ?>
  <input type="hidden" name="segmento" value="<?= e($segmento) ?>">
  <input type="hidden" name="descuento" value="<?= (int) $descuento ?>">
  <button type="submit" class="pq-btn pq-btn-ghost">Ya le escribí</button>
  <p class="pq-ayuda">Así Veci sabe si volvió a comprar después de tu mensaje.</p>
</form>
