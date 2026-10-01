<div class="pq-tienda-header">
  <div class="pq-tienda-logo" style="background: <?= e($negocio['color_marca']) ?>">
    <?= e($negocio['inicial'] ?? mb_strtoupper(mb_substr($negocio['negocio_nombre'], 0, 1))) ?>
  </div>
  <div>
    <h1 class="pq-tienda-nombre"><?= e(nombre_publico_sede($negocio)) ?></h1>
    <?php if (!empty($negocio['descripcion'])): ?>
      <span class="pq-tienda-desc"><?= e($negocio['descripcion']) ?></span>
    <?php endif; ?>
    <?php if ($abiertoAhora !== null): ?>
      <span class="pq-estado-abierto <?= $abiertoAhora['abierto'] ? 'pq-estado-abierto-si' : 'pq-estado-abierto-no' ?>">
        <span class="pq-estado-abierto-punto"></span>
        <?= $abiertoAhora['abierto'] ? 'Abierto ahora · hasta las ' . e(hora_legible($abiertoAhora['hasta'])) : 'Cerrado ahora' ?>
      </span>
    <?php endif; ?>
  </div>
</div>

<div class="pq-content-tienda">
  <?php if ($servicios === []): ?>
    <p class="pq-ayuda" style="margin-top: 24px">Este negocio todavía no tiene servicios publicados.</p>
  <?php else: ?>
    <div class="pq-stack" style="gap: 10px; margin-top: 8px">
      <?php foreach ($servicios as $servicio): ?>
        <?php $agotado = (int) $servicio['agotado'] === 1; ?>
        <?php $urlReservar = base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']); ?>
        <?php if ($agotado): ?>
          <div class="pq-fila-carrito" style="align-items: center; opacity: .55">
        <?php else: ?>
          <a href="<?= e($urlReservar) ?>" class="pq-fila-carrito pq-fila-servicio" style="align-items: center">
        <?php endif; ?>
          <div class="pq-fila-carrito-icono" style="background: <?= e($servicio['color']) ?>"></div>
          <div class="pq-stack" style="flex-grow: 1; gap: 2px">
            <span style="font-size: 14px; font-weight: 600"><?= e($servicio['nombre']) ?></span>
            <span class="pq-mono" style="font-size: 12px; color: var(--gris-texto)"><?= (int) $servicio['duracion_min'] ?> min · <?= pesos((int) $servicio['precio']) ?></span>
          </div>
          <?php if ($agotado): ?>
            <span class="pq-chip pq-chip-cancelado">No disponible</span>
          <?php else: ?>
            <span class="pq-btn pq-btn-oscuro pq-btn-chico" style="pointer-events: none">Reservar</span>
          <?php endif; ?>
        <?= $agotado ? '</div>' : '</a>' ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($horario !== []): ?>
    <div class="pq-card" style="margin-top: 28px; display: flex; flex-direction: column; gap: 8px">
      <span class="pq-mono" style="font-size: 11px; color: var(--gris-texto)">HORARIO DE ATENCIÓN</span>
      <?php foreach ($horario as $linea): ?>
        <div style="display: flex; justify-content: space-between; font-size: 14px">
          <span><?= e($linea['dia']) ?></span>
          <span class="pq-mono"><?= e($linea['rango']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($negocio['whatsapp'])): ?>
    <a href="https://wa.me/57<?= e(preg_replace('/\D+/', '', (string) $negocio['whatsapp']) ?? '') ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-ghost-oscuro pq-btn-chico" style="margin-top: 20px; width: 100%">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
      WhatsApp · Hacer una pregunta
    </a>
  <?php endif; ?>
</div>
