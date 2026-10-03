<?php
$esReservas = $negocio['tipo_negocio'] === 'reservas';
$urlTienda = url_publica('/t/' . $negocio['slug']);
$nombreNegocio = (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']);
$inicial = mb_strtoupper(mb_substr((string) ($negocio['inicial'] ?? $nombreNegocio), 0, 1));
$queEs = $esReservas ? 'agenda' : 'tienda';
?>
<?php
// El cierre del alta es la cabecera real de la tienda: el toldo del color
// elegido se despliega (la misma animación que ve el cliente al entrar) y
// la insignia se monta encima. Es "abrir la persiana" del negocio.
?>
<?php $fachadaAlta = fachada_tienda($negocio); ?>
<main class="pq-onb-abierta pq-estilo-<?= e($fachadaAlta) ?>">
  <?php if ($fachadaAlta === 'barrio'): ?>
    <div class="pq-toldo" aria-hidden="true"></div>
  <?php else: ?>
    <?php // Consultorio o despacho: su membrete, no el toldo de barrio. ?>
    <div class="pq-membrete-filete" aria-hidden="true"></div>
  <?php endif; ?>
  <div class="pq-letrero pq-onb-abierta-letrero<?= $fachadaAlta !== 'barrio' ? ' pq-onb-abierta-membrete' : '' ?>">
    <div class="<?= $fachadaAlta === 'barrio' ? 'pq-letrero-insignia' : 'pq-membrete-sello' ?>" aria-hidden="true"><?= e($inicial) ?></div>
    <p class="pq-onb-abierta-eyebrow">¡Ya abriste!</p>
    <h1 class="pq-letrero-nombre"><?= e(nombre_publico_sede($negocio)) ?></h1>
    <p class="pq-onb-abierta-texto">Tu <?= $queEs ?> ya recibe <?= $esReservas ? 'reservas' : 'pedidos' ?>. Compártela en tu estado de WhatsApp o en Instagram para que lleguen los primeros.</p>
  </div>

  <div class="pq-onb-abierta-cuerpo">
    <div class="pq-onb-abierta-enlace">
      <span class="pq-mono"><?= e(preg_replace('#^https?://#', '', $urlTienda)) ?></span>
      <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-copiar="<?= e($urlTienda) ?>">Copiar</button>
    </div>

    <div class="pq-onb-abierta-botones">
      <a href="https://wa.me/?text=<?= rawurlencode('¡Ya abrí mi ' . $queEs . ' en línea! Pide aquí: ' . $urlTienda) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-whatsapp">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
        Contarles por WhatsApp
      </a>
      <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-ghost">Ver mi <?= $queEs ?></a>
      <a href="<?= e(base_url('/panel')) ?>" class="pq-btn pq-btn-sello">Ir a mi panel</a>
    </div>

    <section class="pq-onb-lista-hecho" aria-labelledby="pq-titulo-hecho">
      <h2 class="pq-seccion-titulo" id="pq-titulo-hecho">Lo que ya quedó listo</h2>
      <ul>
        <li class="pq-publicada-check">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
          <?= (int) $totalCatalogo ?> <?= $esReservas ? 'servicio' : 'producto' ?><?= $totalCatalogo === 1 ? '' : 's' ?> en tu <?= $esReservas ? 'lista' : 'carta' ?>
        </li>
        <?php if ($esReservas): ?>
          <li class="pq-publicada-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
            Horario de atención
          </li>
        <?php endif; ?>
        <li class="pq-publicada-check">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
          <?php
          $llave = (string) ($negocio['llave_breb_valor'] ?? '');
          $llaveVisible = ($negocio['llave_breb_tipo'] ?? '') === 'celular' && strlen($llave) === 10
              ? substr($llave, 0, 3) . ' ' . substr($llave, 3, 3) . ' ' . substr($llave, 6)
              : $llave;
          ?>
          <span>Cobras a tu llave Bre-B <span class="pq-mono"><?= e($llaveVisible) ?></span></span>
        </li>
        <?php if (!$esReservas && empty($negocio['horario_atencion'])): ?>
          <li>
            <a href="<?= e(base_url('/panel/horario')) ?>" class="pq-publicada-check pq-publicada-check-pendiente">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>
              Pon tu horario (y si cierras a almorzar) →
            </a>
          </li>
        <?php endif; ?>
        <?php if (empty($negocio['direccion'])): ?>
          <li>
            <a href="<?= e(base_url('/panel/sedes/' . $negocio['id'] . '/editar')) ?>" class="pq-publicada-check pq-publicada-check-pendiente">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>
              Agrega tu dirección (opcional) →
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </section>
  </div>
</main>
