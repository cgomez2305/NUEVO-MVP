<?php
/**
 * Cabecera de las fachadas Consultorio y Despacho: un membrete en vez del
 * toldo. Arriba un filete con el color del negocio (una banda en el
 * consultorio; doble línea de papelería en el despacho), el sello con la
 * inicial, el nombre, a qué se dedica y la credencial que escribió el
 * dueño. Debajo, lo mismo que en cualquier fachada: estado y accesos.
 *
 * La incluye _cabecera.php, que ya preparó las variables.
 */
$credencial = trim((string) ($negocio['credencial'] ?? ''));
$inicialMembrete = (string) ($negocio['inicial'] ?? '') !== ''
    ? (string) $negocio['inicial']
    : mb_strtoupper(mb_substr((string) $negocio['negocio_nombre'], 0, 1));
?>
<header class="pq-membrete">
  <div class="pq-membrete-filete" aria-hidden="true"></div>

  <div class="pq-membrete-cuerpo">
    <div class="pq-membrete-encabezado">
      <div class="pq-membrete-sello" aria-hidden="true"><?= e($inicialMembrete) ?></div>
      <div class="pq-membrete-titulos">
        <h1 class="pq-membrete-nombre"><?= e(nombre_publico_sede($negocio)) ?></h1>
        <?php if (!empty($negocio['descripcion'])): ?>
          <p class="pq-membrete-desc"><?= e($negocio['descripcion']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($credencial !== ''): ?>
      <p class="pq-membrete-credencial">
        <?php // Ícono de documento, no de "verificado": la credencial la escribe el dueño y Veci no la comprueba. ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16c.6-1.4 1.7-2 3-2s2.4.6 3 2M14 10h4M14 13.5h3"/></svg>
        <span><?= e($credencial) ?></span>
      </p>
    <?php endif; ?>

    <?php require __DIR__ . '/_letrero_datos.php'; ?>
  </div>
</header>
