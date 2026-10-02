<?php require __DIR__ . '/_cabecera.php'; ?>

<main class="pq-content-tienda pq-vitrina">
  <?php if ($servicios === []): ?>
    <div class="pq-vacio-tienda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
      <p><strong>La agenda está en preparación.</strong><br>Mientras tanto, puedes escribirle al negocio por WhatsApp.</p>
    </div>
  <?php else: ?>
    <section class="pq-carta" aria-labelledby="titulo-servicios">
      <h2 class="pq-carta-titulo" id="titulo-servicios">Reserva tu turno</h2>
      <?php
      // Un solo aviso arriba cuando hoy ya no queda ningún turno, en vez de
      // repetir "sin cupos" en cada servicio (ruido que no ayuda a elegir).
      $sinTurnosHoy = $disponibilidadHoy !== [] && array_filter($disponibilidadHoy, fn ($cupo) => $cupo !== null) === [];
      ?>
      <?php if ($sinTurnosHoy): ?>
        <p class="pq-carta-nota">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          Hoy ya no quedan turnos. Elige un servicio para ver los próximos días.
        </p>
      <?php endif; ?>
      <div class="pq-carta-lista">
        <?php $contador = 0; foreach ($servicios as $servicio): ?>
          <?php
          $agotado = (int) $servicio['agotado'] === 1;
          $cupoHoy = $disponibilidadHoy[$servicio['id']] ?? null;
          $etiquetaTag = $agotado ? 'div' : 'a';
          ?>
          <<?= $etiquetaTag ?> class="pq-servicio<?= $agotado ? ' pq-servicio-agotado' : '' ?>" style="--i: <?= $contador++ % 8 ?>"<?php if (!$agotado): ?> href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id'])) ?>"<?php endif; ?>>
            <span class="pq-servicio-cuerpo">
              <span class="pq-servicio-nombre"><?= e($servicio['nombre']) ?></span>
              <span class="pq-servicio-meta">
                <span><?= (int) $servicio['duracion_min'] ?> min</span>
                <span aria-hidden="true">·</span>
                <span class="pq-servicio-precio"><?= pesos((int) $servicio['precio']) ?></span>
              </span>
            </span>

            <?php if ($agotado): ?>
              <span class="pq-cupo pq-cupo-no">No disponible</span>
            <?php elseif ($cupoHoy !== null): ?>
              <span class="pq-cupo pq-cupo-hoy"><span class="pq-cupo-sub">Hoy</span><?= e(hora_legible($cupoHoy)) ?></span>
            <?php endif; ?>

            <?php if (!$agotado): ?>
              <svg class="pq-servicio-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
            <?php endif; ?>
          </<?= $etiquetaTag ?>>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php require __DIR__ . '/_resenas.php'; ?>
  <?php require __DIR__ . '/_informacion.php'; ?>
</main>
