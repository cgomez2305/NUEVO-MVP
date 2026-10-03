<?php
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ver todos los servicios';
require __DIR__ . '/_cabecera_corta.php';
$primerNombre = explode(' ', trim((string) $empleado['nombre']))[0];
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <?php // La ficha como un carné de barrio: foto grande, nombre y lo que mejor hace. ?>
  <section class="pq-profesional-ficha">
    <?php if (!empty($empleado['foto'])): ?>
      <img class="pq-profesional-foto" src="<?= e(base_url($empleado['foto'])) ?>" alt="Foto de <?= e($empleado['nombre']) ?>" width="120" height="120">
    <?php else: ?>
      <span class="pq-profesional-inicial" aria-hidden="true"><?= e(inicial_persona((string) $empleado['nombre'])) ?></span>
    <?php endif; ?>
    <div>
      <h1 class="pq-pagina-titulo"><?= e($empleado['nombre']) ?></h1>
      <?php if (!empty($empleado['especialidad'])): ?><p class="pq-profesional-especialidad"><?= e($empleado['especialidad']) ?></p><?php endif; ?>
      <p class="pq-ayuda">en <?= e(nombre_publico_sede($negocio)) ?></p>
    </div>
  </section>
  <?php if (!empty($empleado['bio'])): ?>
    <p class="pq-profesional-bio"><?= e($empleado['bio']) ?></p>
  <?php endif; ?>

  <?php if ($fotos !== []): ?>
    <section aria-labelledby="pq-trabajos-titulo">
      <h2 class="pq-carta-titulo" id="pq-trabajos-titulo">Sus trabajos</h2>
      <ul class="pq-profesional-trabajos">
        <?php foreach ($fotos as $i => $foto): ?>
          <li><img src="<?= e(base_url($foto['ruta'])) ?>" alt="Trabajo <?= $i + 1 ?> de <?= e($primerNombre) ?>" loading="lazy" width="300" height="300"></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section aria-labelledby="pq-reserva-con-titulo">
    <h2 class="pq-carta-titulo" id="pq-reserva-con-titulo">Reserva con <?= e($primerNombre) ?></h2>
    <?php if ($servicios === []): ?>
      <p class="pq-ayuda">Por ahora no tiene servicios en su agenda.</p>
    <?php else: ?>
      <div class="pq-carta-lista">
        <?php foreach ($servicios as ['servicio' => $servicio, 'condiciones' => $condiciones]): ?>
          <?php $agotado = (int) $servicio['agotado'] === 1; ?>
          <?php if ($agotado): ?>
            <div class="pq-servicio pq-servicio-agotado">
          <?php else: ?>
            <a class="pq-servicio" href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']) . '?empleado=' . (int) $empleado['id']) ?>">
          <?php endif; ?>
            <span class="pq-servicio-cuerpo">
              <span class="pq-servicio-nombre"><?= e($servicio['nombre']) ?></span>
              <span class="pq-servicio-meta">
                <span><?= (int) $condiciones['duracion_min'] ?> min</span>
                <span aria-hidden="true">·</span>
                <span class="pq-servicio-precio"><?= e(precio_texto($condiciones)) ?></span>
              </span>
            </span>
            <?php if ($agotado): ?>
              <span class="pq-cupo pq-cupo-no">No disponible</span>
            </div>
            <?php else: ?>
              <svg class="pq-servicio-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
