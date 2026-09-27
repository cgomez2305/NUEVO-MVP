<div class="pq-tienda-header">
  <div class="pq-tienda-logo" style="background: <?= e($negocio['color_marca']) ?>">
    <?= e($negocio['inicial'] ?? mb_strtoupper(mb_substr($negocio['nombre'], 0, 1))) ?>
  </div>
  <div>
    <h1 class="pq-tienda-nombre"><?= e($negocio['nombre']) ?></h1>
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
        <div class="pq-fila-carrito" style="align-items: center">
          <div class="pq-fila-carrito-icono" style="background: <?= e($servicio['color']) ?>"></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600"><?= e($servicio['nombre']) ?></span>
            <span class="pq-mono" style="font-size: 12px; color: var(--gris-texto)"><?= (int) $servicio['duracion_min'] ?> min</span>
          </div>
          <span class="pq-mono" style="font-size: 13px"><?= pesos((int) $servicio['precio']) ?></span>
          <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id'])) ?>" class="pq-btn pq-btn-oscuro pq-btn-chico">Reservar</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
