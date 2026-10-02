<?php
/**
 * Pie informativo de la tienda: horario de la semana (con el día de hoy
 * resaltado) y dirección. Solo se imprime lo que el negocio de verdad
 * cargó; si no hay ni horario ni dirección, no queda una sección vacía.
 *
 * Espera: $negocio; opcional $horario (de horario_resumen()) y
 * $fidelidad (Fidelidad::activa(), la tarjeta de sellos si está prendida).
 */
$horario = $horario ?? [];
$direccion = trim((string) ($negocio['direccion'] ?? ''));
$fidelidad = $fidelidad ?? null;
?>
<?php if ($horario !== [] || $direccion !== '' || $fidelidad !== null): ?>
  <div class="pq-info-grupo">
    <?php if ($fidelidad !== null): ?>
      <section class="pq-info pq-info-sellos" aria-labelledby="pq-info-sellos">
        <h2 class="pq-info-titulo" id="pq-info-sellos">Tarjeta de sellos</h2>
        <p class="pq-info-direccion">
          Cada <?= (int) $fidelidad['minimo_compra'] > 0 ? 'compra desde ' . pesos((int) $fidelidad['minimo_compra']) : 'compra' ?> suma un sello.
          Con <?= (int) $fidelidad['meta'] ?> te llevas <strong><?= e($fidelidad['premio']) ?></strong>.
        </p>
        <p class="pq-ayuda">Se cuenta solo con tu número de WhatsApp: no hay que guardar nada.</p>
      </section>
    <?php endif; ?>
    <?php if ($horario !== []): ?>
      <section class="pq-info" id="horario" aria-labelledby="pq-info-horario">
        <h2 class="pq-info-titulo" id="pq-info-horario">Horario</h2>
        <dl class="pq-horario">
          <?php foreach ($horario as $linea): ?>
            <div class="pq-horario-fila<?= !empty($linea['hoy']) ? ' pq-horario-hoy' : '' ?><?= $linea['rango'] === 'Cerrado' ? ' pq-horario-cerrado' : '' ?>">
              <dt><?= e($linea['dia']) ?><?php if (!empty($linea['hoy'])): ?> <span class="pq-horario-etiqueta">hoy</span><?php endif; ?></dt>
              <dd><?php foreach ($linea['franjas'] as $franja): ?><span class="pq-horario-franja"><?= e($franja) ?></span><?php endforeach; ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>
      </section>
    <?php endif; ?>

    <?php if ($direccion !== ''): ?>
      <section class="pq-info" aria-labelledby="pq-info-donde">
        <h2 class="pq-info-titulo" id="pq-info-donde">Dónde estamos</h2>
        <p class="pq-info-direccion"><?= e($direccion) ?></p>
        <a class="pq-info-enlace" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(urlencode($direccion)) ?>" target="_blank" rel="noopener">
          Abrir en el mapa
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
        </a>
      </section>
    <?php endif; ?>
  </div>
<?php endif; ?>
