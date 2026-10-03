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
                <span class="pq-servicio-precio"><?= e(precio_texto($servicio)) ?></span>
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

  <?php if (!empty($paquetes)): ?>
    <?php
    // Paquetes: el ahorro solo si es real; se piden por WhatsApp (se pagan
    // en el local) y después cada reserva con ese número usa una sesión.
    $whatsappPaquetes = preg_replace('/\D+/', '', (string) ($negocio['whatsapp'] ?? '')) ?? '';
    ?>
    <section class="pq-carta pq-paquetes" aria-labelledby="titulo-paquetes">
      <h2 class="pq-carta-titulo" id="titulo-paquetes">Paquetes</h2>
      <ul class="pq-paquetes-lista">
        <?php foreach ($paquetes as $paquete): ?>
          <?php
          $ahorro = (int) $paquete['precio_suelto'] - (int) $paquete['precio'];
          $mensaje = 'Hola, quiero el paquete de ' . (int) $paquete['sesiones'] . ' ' . mb_strtolower((string) $paquete['servicio_nombre']) . ' por ' . pesos((int) $paquete['precio']) . '.';
          ?>
          <li class="pq-paquete">
            <span class="pq-paquete-cuantas" aria-hidden="true"><?= (int) $paquete['sesiones'] ?><small>×</small></span>
            <span class="pq-paquete-texto">
              <strong><?= (int) $paquete['sesiones'] ?> <?= e(mb_strtolower((string) $paquete['servicio_nombre'])) ?></strong>
              <span class="pq-ayuda"><?= pesos((int) $paquete['precio']) ?><?= $ahorro > 0 ? ' · ahorras ' . pesos($ahorro) : '' ?><?= $paquete['vigencia_dias'] !== null ? ' · se usan en ' . (int) $paquete['vigencia_dias'] . ' días' : '' ?></span>
            </span>
            <?php if ($whatsappPaquetes !== ''): ?>
              <a class="pq-paquete-pedir" href="https://wa.me/57<?= e($whatsappPaquetes) ?>?text=<?= rawurlencode($mensaje) ?>" target="_blank" rel="noopener">Lo quiero</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="pq-ayuda pq-paquetes-nota">Lo pagas en el local y luego, al reservar con tu WhatsApp, cada cita usa una sesión sola.</p>
    </section>
  <?php endif; ?>
  <?php require __DIR__ . '/_resenas.php'; ?>
  <?php require __DIR__ . '/_informacion.php'; ?>
</main>
