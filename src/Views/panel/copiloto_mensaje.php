<?php
$urlActual = base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje') . '?segmento=' . $segmento . '&descuento=' . $descuento;
?>
<a href="<?= e(base_url('/panel/copiloto') . '?segmento=' . $segmento) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Copiloto</a>

<div style="display: flex; align-items: center; gap: 12px; margin-top: 20px">
  <div class="pq-avatar" style="background: var(--aji); width: 44px; height: 44px">
    <?= e(mb_strtoupper(mb_substr($cliente['nombre'], 0, 1))) ?>
  </div>
  <div>
    <span class="pq-serif" style="font-size: 22px; display: block; line-height: 1"><?= e($cliente['nombre']) ?></span>
    <span class="pq-ayuda"><?= e($cliente['telefono']) ?></span>
  </div>
</div>

<?php if ($contexto !== null): ?>
  <div style="margin-top: 20px; background: #F4F1E9; border-radius: 14px; padding: 14px 16px">
    <span class="pq-eyebrow">Por qué te lo recomendamos</span>
    <p class="pq-lead" style="margin-top: 4px">
      <?php if ($segmento === 'inactivo' && $contexto['frecuencia_prom'] !== null): ?>
        Antes compraba cada <?= (int) $contexto['frecuencia_prom'] ?> días; lleva <?= (int) $contexto['dias_sin_pedir'] ?> días sin pedir.
      <?php else: ?>
        <?= e($contexto['motivo']) ?>
      <?php endif; ?>
    </p>
    <div style="display: flex; gap: 18px; flex-wrap: wrap; margin-top: 10px">
      <div>
        <span class="pq-mono" style="font-size: 11px; color: var(--gris-suave); display: block">Última compra</span>
        <span style="font-size: 13.5px; font-weight: 600"><?= pesos((int) $contexto['ultima_compra_monto']) ?> · hace <?= (int) $contexto['dias_sin_pedir'] ?> días</span>
      </div>
      <div>
        <span class="pq-mono" style="font-size: 11px; color: var(--gris-suave); display: block">Compras históricas</span>
        <span style="font-size: 13.5px; font-weight: 600"><?= (int) $contexto['total_compras'] ?> · <?= pesos((int) $contexto['gasto_total']) ?> en total</span>
      </div>
    </div>
    <?php if ($contacto !== null): ?>
      <p class="pq-ayuda" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--borde)">
        Contactado hace <?= (int) $contacto['dias'] ?> día<?= $contacto['dias'] === 1 ? '' : 's' ?>
        <?= $comproDespues ? ' · hizo un pedido después ✓' : '' ?>
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div style="margin-top: 20px">
  <span class="pq-eyebrow">Mensaje sugerido por Veci</span>

  <form method="get" action="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje')) ?>" style="margin-top: 10px; display: flex; gap: 8px; align-items: center">
    <input type="hidden" name="segmento" value="<?= e($segmento) ?>">
    <select class="pq-select" name="descuento" data-autoenviar style="width: auto; flex-grow: 0">
      <option value="0" <?= $descuento === 0 ? 'selected' : '' ?>>Sin descuento</option>
      <option value="5" <?= $descuento === 5 ? 'selected' : '' ?>>5% de descuento</option>
      <option value="10" <?= $descuento === 10 ? 'selected' : '' ?>>10% de descuento</option>
      <option value="15" <?= $descuento === 15 ? 'selected' : '' ?>>15% de descuento</option>
    </select>
    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Aplicar</button>
  </form>
  <p class="pq-ayuda" style="margin-top: 6px">Veci no ofrece descuentos por su cuenta: elige aquí si quieres incluir uno.</p>

  <form method="get" action="<?= e($cliente['telefono'] ? $waBase : '#') ?>" style="margin-top: 14px">
    <div style="position: relative">
      <textarea name="text" id="mensaje-copiloto" class="pq-input" rows="4" maxlength="500" data-contador
                style="font-family: inherit; resize: vertical"><?= e($mensaje) ?></textarea>
    </div>
    <div style="display: flex; gap: 8px; margin-top: 10px">
      <a href="<?= e($urlActual) ?>" class="pq-btn pq-btn-ghost pq-btn-chico" style="width: auto">↻ Regenerar mensaje</a>
      <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" style="width: auto" data-copiar-de="#mensaje-copiloto">Copiar</button>
    </div>

    <button type="submit" class="pq-btn pq-btn-whatsapp" style="margin-top: 14px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
      Enviar por WhatsApp
    </button>
  </form>
</div>

<form method="post" action="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/enviar')) ?>" style="margin-top: 10px">
  <?= csrf_campo() ?>
  <input type="hidden" name="segmento" value="<?= e($segmento) ?>">
  <input type="hidden" name="descuento" value="<?= (int) $descuento ?>">
  <button type="submit" class="pq-btn pq-btn-ghost">Marcar como contactado</button>
</form>
