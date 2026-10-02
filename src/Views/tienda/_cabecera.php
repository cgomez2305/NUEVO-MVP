<?php
/**
 * Cabecera de la tienda pública ("fachada"): toldo rayado con el color del
 * negocio, letrero con su nombre, estado abierto/cerrado real y accesos
 * rápidos (WhatsApp, cómo llegar, horario). Compartida por mostrar.php
 * (pedidos) y servicios.php (reservas).
 *
 * Espera: $negocio; opcionales $abiertoAhora, $proximaApertura, $horario, $resenas.
 */
$abiertoAhora = $abiertoAhora ?? null;
$proximaApertura = $proximaApertura ?? null;
$horario = $horario ?? [];
$whatsappNegocio = preg_replace('/\D+/', '', (string) ($negocio['whatsapp'] ?? '')) ?? '';
$direccion = trim((string) ($negocio['direccion'] ?? ''));
?>
<header class="pq-fachada">
  <div class="pq-toldo" aria-hidden="true"></div>

  <div class="pq-letrero">
    <div class="pq-letrero-insignia" aria-hidden="true">
      <?= e($negocio['inicial'] ?? mb_strtoupper(mb_substr((string) $negocio['negocio_nombre'], 0, 1))) ?>
    </div>

    <h1 class="pq-letrero-nombre"><?= e(nombre_publico_sede($negocio)) ?></h1>
    <?php if (!empty($negocio['descripcion'])): ?>
      <p class="pq-letrero-desc"><?= e($negocio['descripcion']) ?></p>
    <?php endif; ?>

    <?php if (!empty($resenas)): ?>
      <a class="pq-letrero-resenas" href="#resenas">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.1l-5.7 3.2 1.2-6.4-4.7-4.4 6.4-.8L12 2.8Z"/></svg>
        <strong><?= e(number_format((float) $resenas['resumen']['promedio'], 1, ',', '')) ?></strong>
        <span><?= (int) $resenas['resumen']['total'] ?> reseñas</span>
      </a>
    <?php endif; ?>

    <?php if ($abiertoAhora !== null): ?>
      <p class="pq-letrero-estado <?= $abiertoAhora['abierto'] ? 'pq-letrero-estado-abierto' : (!empty($abiertoAhora['pausa']) ? 'pq-letrero-estado-pausa' : '') ?>">
        <span class="pq-letrero-estado-punto" aria-hidden="true"></span>
        <span>
          <?php if ($abiertoAhora['abierto']): ?>
            <strong>Abierto</strong> · hasta las <?= e(hora_legible($abiertoAhora['hasta'])) ?>
          <?php elseif (!empty($abiertoAhora['pausa'])): ?>
            <?php // El almuerzo no es "cerrado": hoy vuelven a abrir. ?>
            <strong>En pausa</strong> · vuelve a las <?= e(hora_legible($abiertoAhora['vuelve'])) ?>
          <?php elseif ($proximaApertura !== null): ?>
            <strong>Cerrado</strong> · abre <?= e($proximaApertura['dia']) ?> a las <?= e($proximaApertura['hora']) ?>
          <?php else: ?>
            <strong>Cerrado ahora</strong>
          <?php endif; ?>
        </span>
      </p>
    <?php endif; ?>

    <?php if ($whatsappNegocio !== '' || $direccion !== '' || $horario !== []): ?>
      <nav class="pq-accesos" aria-label="Contacto del negocio">
        <?php if ($whatsappNegocio !== ''): ?>
          <a class="pq-acceso" href="https://wa.me/57<?= e($whatsappNegocio) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
            Escribir
          </a>
        <?php endif; ?>
        <?php if ($direccion !== ''): ?>
          <a class="pq-acceso" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(urlencode($direccion)) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
            Cómo llegar
          </a>
        <?php endif; ?>
        <?php if ($horario !== []): ?>
          <a class="pq-acceso" href="#horario">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            Horario
          </a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</header>
