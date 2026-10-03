<?php require __DIR__ . '/_cabecera.php'; ?>

<main class="pq-content-tienda pq-vitrina">
  <?php if (!empty($filaAbierta)): ?>
    <?php // Para quien pasa por la puerta sin cita: la fila se hace desde el celular. ?>
    <a class="pq-cola-aviso" href="<?= e(base_url('/t/' . $negocio['slug'] . '/fila')) ?>">
      <span class="pq-cola-aviso-punto" aria-hidden="true"></span>
      <span><strong>¿Sin cita?</strong> Haz la fila desde aquí y te avisamos por WhatsApp.</span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </a>
  <?php endif; ?>
  <?php if ($servicios === []): ?>
    <div class="pq-vacio-tienda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
      <p><strong>La agenda está en preparación.</strong><br>Mientras tanto, puedes escribirle al negocio por WhatsApp.</p>
    </div>
  <?php else: ?>
    <?php if (!empty($ultimoServicio)): ?>
      <?php
      // El cliente ya conocido en este celular reserva lo de siempre, con la
      // misma persona si sigue en el equipo: dos toques en vez de buscarlo.
      $urlRepetir = base_url('/t/' . $negocio['slug'] . '/reservar/' . (int) $ultimoServicio['servicio']['id'])
          . ($ultimoServicio['empleado'] !== null ? '?empleado=' . (int) $ultimoServicio['empleado']['id'] : '');
      ?>
      <section class="pq-repetir" aria-labelledby="pq-repetir-titulo">
        <h2 class="pq-repetir-titulo" id="pq-repetir-titulo"><?= $ultimoServicio['nombre'] !== null ? 'Hola, ' . e($ultimoServicio['nombre']) . '. ' : '' ?>¿Reservas lo de siempre?</h2>
        <p class="pq-repetir-servicio">
          <?= e($ultimoServicio['servicio']['nombre']) ?><?= $ultimoServicio['empleado'] !== null ? ' con ' . e($ultimoServicio['empleado']['nombre']) : '' ?>
        </p>
        <div class="pq-repetir-acciones">
          <a href="<?= e($urlRepetir) ?>" class="pq-btn pq-btn-oscuro pq-btn-chico pq-btn-alto">Reservar de nuevo</a>
          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/olvidarme')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-repetir-olvidar"><?= $ultimoServicio['nombre'] !== null ? 'No soy ' . e($ultimoServicio['nombre']) : 'Olvidar este celular' ?></button>
          </form>
        </div>
      </section>
    <?php endif; ?>
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

  <?php
  // El equipo, solo si hay alguien que mostrar con cara o especialidad: una
  // lista de nombres sueltos no le dice nada al cliente.
  $equipoVisible = array_values(array_filter($equipo ?? [], fn ($e) => !empty($e['foto']) || !empty($e['especialidad'])));
  ?>
  <?php if ($equipoVisible !== []): ?>
    <section class="pq-carta pq-equipo-tienda" aria-labelledby="titulo-equipo">
      <h2 class="pq-carta-titulo" id="titulo-equipo">Nuestro equipo</h2>
      <ul class="pq-equipo-tienda-lista">
        <?php foreach ($equipoVisible as $persona): ?>
          <li>
            <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/equipo/' . (int) $persona['id'])) ?>" class="pq-equipo-tienda-ficha">
              <?php if (!empty($persona['foto'])): ?>
                <img src="<?= e(base_url($persona['foto'])) ?>" alt="" width="72" height="72" loading="lazy">
              <?php else: ?>
                <span class="pq-equipo-tienda-inicial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $persona['nombre'], 0, 1))) ?></span>
              <?php endif; ?>
              <span class="pq-equipo-tienda-nombre"><?= e($persona['nombre']) ?></span>
              <?php if (!empty($persona['especialidad'])): ?><span class="pq-equipo-tienda-esp"><?= e($persona['especialidad']) ?></span><?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
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
      <p class="pq-ayuda pq-paquetes-nota">Lo pagas en el local y te llega un enlace: reservando desde ahí, cada cita usa una sesión sola.</p>
    </section>
  <?php endif; ?>
  <?php require __DIR__ . '/_resenas.php'; ?>
  <?php require __DIR__ . '/_informacion.php'; ?>
</main>
