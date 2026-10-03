<?php
use App\Models\Referido;

$nombreNegocio = (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']);
$mensaje = '¡Hola! Yo recibo mis ' . (($negocio['tipo_negocio'] ?? '') === 'reservas' ? 'reservas' : 'pedidos') . ' por WhatsApp con Veci y me ha servido mucho. '
    . 'Crea la tienda de tu negocio gratis aquí: ' . $enlace;
$ganados = count(array_filter($invitados, fn ($i) => $i['premiado_en'] !== null));
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Crecimiento</span>
    <h1 class="pq-h1">Invita y gana</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">¿Conoces otro negocio del barrio que venda por WhatsApp? Invítalo: cuando pague su primer plan, a ti te regalamos <?= Referido::DIAS_PREMIO ?> días de Veci.</p>

<?php // El enlace como una tarjeta de presentación que se pasa de mano en mano. ?>
<section class="pq-referido-tarjeta" aria-labelledby="pq-titulo-enlace">
  <h2 class="pq-referido-tarjeta-titulo" id="pq-titulo-enlace">Tu enlace de invitación</h2>
  <input class="pq-input pq-mono pq-referido-enlace" type="text" readonly value="<?= e($enlace) ?>" aria-label="Tu enlace de invitación" data-seleccionar-al-tocar>
  <div class="pq-referido-botones">
    <a class="pq-btn pq-btn-whatsapp" href="https://wa.me/?text=<?= rawurlencode($mensaje) ?>" target="_blank" rel="noopener">Compartir por WhatsApp</a>
    <button type="button" class="pq-btn pq-btn-ghost" data-copiar="<?= e($enlace) ?>">Copiar enlace</button>
  </div>
</section>

<ol class="pq-referido-pasos">
  <li><strong>Comparte tu enlace</strong><span class="pq-ayuda">Al vecino de la panadería, a la peluquería de la esquina…</span></li>
  <li><strong>Crea su tienda gratis</strong><span class="pq-ayuda">Queda anotado que llegó por ti.</span></li>
  <li><strong>Cuando pague su primer plan, ganas <?= Referido::DIAS_PREMIO ?> días</strong><span class="pq-ayuda">Se suman a tu plan; si estás en Gratis, te damos Barrio esos días.</span></li>
</ol>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-invitados">
  <h2 class="pq-seccion-titulo" id="pq-titulo-invitados">Tus invitados<?= $invitados !== [] ? ' <span class="pq-admin-cuenta">' . count($invitados) . '</span>' : '' ?></h2>
  <?php if ($invitados === []): ?>
    <p class="pq-ayuda">Todavía nadie se ha registrado con tu enlace.</p>
  <?php else: ?>
    <?php if ($ganados > 0): ?>
      <p class="pq-ayuda">Llevas <?= $ganados * Referido::DIAS_PREMIO ?> días ganados. ¡Gracias por recomendarnos!</p>
    <?php endif; ?>
    <ul class="pq-admin-tarjeta pq-admin-filas">
      <?php foreach ($invitados as $invitado): ?>
        <li class="pq-admin-fila">
          <span class="pq-admin-fila-texto">
            <strong><?= e($invitado['nombre']) ?></strong>
            <span class="pq-ayuda">Se registró <?= e(hace_dias(dias_desde((string) $invitado['registrado_en']))) ?></span>
          </span>
          <?php if ($invitado['premiado_en'] !== null): ?>
            <span class="pq-chip pq-chip-caja">Ganaste <?= (int) $invitado['dias_premio'] ?> días</span>
          <?php else: ?>
            <span class="pq-chip pq-chip-pendiente">Falta su primer pago</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
