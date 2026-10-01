<div class="pq-tienda-header">
  <div class="pq-tienda-logo" style="background: <?= e($negocio['color_marca']) ?>">
    <?= e($negocio['inicial'] ?? mb_strtoupper(mb_substr($negocio['negocio_nombre'], 0, 1))) ?>
  </div>
  <div>
    <h1 class="pq-tienda-nombre"><?= e(nombre_publico_sede($negocio)) ?></h1>
    <?php if (!empty($negocio['descripcion'])): ?>
      <span class="pq-tienda-desc"><?= e($negocio['descripcion']) ?></span>
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
        <div class="pq-fila-carrito" style="align-items: center<?= $agotado ? '; opacity: .55' : '' ?>">
          <div class="pq-fila-carrito-icono" style="background: <?= e($servicio['color']) ?>"></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600"><?= e($servicio['nombre']) ?></span>
            <span class="pq-mono" style="font-size: 12px; color: var(--gris-texto)"><?= (int) $servicio['duracion_min'] ?> min</span>
          </div>
          <span class="pq-mono" style="font-size: 13px"><?= pesos((int) $servicio['precio']) ?></span>
          <?php if ($agotado): ?>
            <span class="pq-chip pq-chip-cancelado">No disponible</span>
          <?php else: ?>
            <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id'])) ?>" class="pq-btn pq-btn-oscuro pq-btn-chico">Reservar</a>
          <?php endif; ?>
        </div>
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
    <a href="https://wa.me/57<?= e(preg_replace('/\D+/', '', (string) $negocio['whatsapp']) ?? '') ?>" target="_blank" rel="noopener" class="pq-mono pq-centro" style="display: block; margin-top: 18px; font-size: 12px; color: var(--gris-suave); text-decoration: none">¿Dudas? Escríbele a <?= e(nombre_publico_sede($negocio)) ?> por WhatsApp</a>
  <?php endif; ?>
</div>
