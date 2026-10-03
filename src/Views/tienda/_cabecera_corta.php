<?php
/**
 * Cabecera de las pantallas internas de la tienda (carrito, reservar,
 * reprogramar...): una franja delgada del mismo toldo de la portada (o el
 * filete del membrete en consultorios y despachos) y un
 * único enlace de vuelta con la insignia y el nombre del negocio, para que
 * el cliente sepa en todo momento en qué tienda está y cómo volver.
 *
 * Espera: $negocio, $volverUrl (ruta relativa), $volverTexto.
 */
?>
<?php $conMembrete = fachada_tienda($negocio) !== 'barrio'; ?>
<header class="pq-cabecera-corta">
  <?php if ($conMembrete): ?>
    <div class="pq-membrete-filete pq-membrete-filete-corto" aria-hidden="true"></div>
  <?php else: ?>
    <div class="pq-toldo pq-toldo-corto" aria-hidden="true"></div>
  <?php endif; ?>
  <a class="pq-cabecera-corta-enlace" href="<?= e(base_url($volverUrl)) ?>">
    <span class="pq-cabecera-corta-flecha" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
    </span>
    <span class="<?= $conMembrete ? 'pq-membrete-sello pq-membrete-sello-chico' : 'pq-letrero-insignia pq-letrero-insignia-chica' ?>" aria-hidden="true"><?= e($negocio['inicial'] ?? mb_strtoupper(mb_substr((string) $negocio['negocio_nombre'], 0, 1))) ?></span>
    <span class="pq-cabecera-corta-texto">
      <span class="pq-cabecera-corta-nombre"><?= e(nombre_publico_sede($negocio)) ?></span>
      <span class="pq-cabecera-corta-volver"><?= e($volverTexto) ?></span>
    </span>
  </a>
</header>
