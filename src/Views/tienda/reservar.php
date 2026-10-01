<div class="pq-topbar" style="border-bottom: none; padding-top: 20px">
  <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-mono" style="font-size: 11px; color: var(--gris-suave); text-decoration: none">‹ ver servicios</a>
</div>

<?php
$diasCorto = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$sufijoEmpleado = $empleadoElegido !== null ? '&empleado=' . (int) $empleadoElegido['id'] : '';
$fechaEsHoy = $fecha === date('Y-m-d');
$nombreDia = static fn (string $f) => $f === date('Y-m-d') ? 'hoy' : $diasCorto[(int) date('w', strtotime($f))] . ' ' . date('d', strtotime($f));
?>
<div class="pq-content-tienda" style="padding-top: 0">
  <h1 class="pq-tienda-nombre" style="font-size: 24px"><?= e($servicio['nombre']) ?></h1>
  <span class="pq-tienda-desc"><?= e(nombre_publico_sede($negocio)) ?> · <?= (int) $servicio['duracion_min'] ?> min · <?= pesos((int) $servicio['precio']) ?></span>
  <?php if ($anticipo > 0): ?>
    <div class="pq-alerta pq-alerta-aviso" style="margin-top: 12px">
      Este servicio pide un anticipo de <strong><?= pesos($anticipo) ?></strong> para confirmar la reserva.
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($empleados)): ?>
    <div style="margin-top: 20px">
      <span class="pq-label">¿Con quién?</span>
      <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px">
        <?php foreach ($empleados as $emp): ?>
          <?php $activo = $empleadoElegido !== null && (int) $empleadoElegido['id'] === (int) $emp['id']; ?>
          <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']) . '?fecha=' . $fecha . '&empleado=' . (int) $emp['id']) ?>"
             class="pq-chip <?= $activo ? 'pq-chip-caja' : '' ?>" style="text-decoration: none"><?= e($emp['nombre']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div id="elige-dia" style="margin-top: 20px; scroll-margin-top: 16px">
    <span class="pq-label">Elige el día</span>
    <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; margin-top: 8px">
      <?php foreach ($fechasDisponibles as $opcion): ?>
        <?php $esHoy = $opcion === date('Y-m-d'); $activo = $opcion === $fecha; ?>
        <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']) . '?fecha=' . $opcion . $sufijoEmpleado) ?>"
           class="pq-chip <?= $activo ? 'pq-chip-caja' : '' ?>" style="text-decoration: none; white-space: nowrap; flex-shrink: 0; min-height: 32px; display: inline-flex; align-items: center">
          <?= $esHoy ? 'Hoy' : e($diasCorto[(int) date('w', strtotime($opcion))] . ' ' . date('d', strtotime($opcion))) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="margin-top: 20px">
    <span class="pq-label">Horarios disponibles</span>

    <?php if (!empty($faltaElegirEmpleado)): ?>
      <p class="pq-ayuda" style="margin-top: 10px">Elige con quién quieres agendar para ver los horarios.</p>
    <?php elseif (!empty($bloqueada)): ?>
      <p class="pq-ayuda" style="margin-top: 10px"><?= e(nombre_publico_sede($negocio)) ?> no atiende ese día. Elige otra fecha.</p>
    <?php elseif ($slots === []): ?>

      <?php if ($proximoDisponible !== null): ?>
        <div class="pq-card" style="margin-top: 10px; background: #FFFFFF; border: 1px solid #E7E0CF">
          <span class="pq-mono" style="font-size: 11px; color: var(--gris-texto)">NO HAY CUPOS PARA <?= mb_strtoupper(e($nombreDia($fecha))) ?></span>
          <p style="font-size: 14px; margin-top: 4px">El próximo horario disponible es <strong><?= e(fecha_larga($proximoDisponible['fecha'])) ?> a las <?= e($proximoDisponible['hora']) ?></strong>.</p>
          <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']) . '?fecha=' . $proximoDisponible['fecha'] . $sufijoEmpleado . '&hora=' . $proximoDisponible['hora']) ?>#confirmar"
             class="pq-btn pq-btn-oscuro pq-btn-chico" style="margin-top: 10px; width: auto">Ver ese horario</a>
        </div>
      <?php else: ?>
        <p class="pq-ayuda" style="margin-top: 10px">No hay horarios disponibles en los próximos días. Elige otro servicio o anótate en la lista de espera.</p>
      <?php endif; ?>

      <a href="#elige-dia" class="pq-mono" style="display: inline-block; margin-top: 10px; font-size: 12px; color: var(--gris-suave); text-decoration: underline">Elegir otro día</a>

      <?php if (!empty($ok)): ?>
        <div class="pq-card" style="margin-top: 14px; background: #FFFFFF; border: 1px solid #E7E0CF">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#16A36A" stroke-width="2.2"><path d="M5 13l5 5L20 7"/></svg>
          <span style="display: block; font-size: 14px; font-weight: 700; margin-top: 6px">Estás en la lista de espera</span>
          <p class="pq-ayuda" style="margin-top: 4px">Te escribiremos por WhatsApp si se libera un horario para <strong><?= e($servicio['nombre']) ?></strong> el <?= e(fecha_larga($fecha)) ?>.</p>
        </div>
      <?php else: ?>
        <details class="pq-lista-espera-detalle" style="margin-top: 14px">
          <summary>¿Quieres que te avisemos si alguien cancela?</summary>
          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/lista-espera')) ?>" id="pq-form-lista-espera" style="margin-top: 12px">
            <p class="pq-ayuda">Te escribiremos por WhatsApp si aparece disponibilidad para <strong><?= e(fecha_larga($fecha)) ?></strong>.</p>
            <?= csrf_campo() ?>
            <input type="hidden" name="servicio_id" value="<?= (int) $servicio['id'] ?>">
            <input type="hidden" name="fecha" value="<?= e($fecha) ?>">

            <div class="pq-campo" style="margin-top: 12px">
              <label class="pq-label" for="le-nombre">Tu nombre</label>
              <input class="pq-input" type="text" id="le-nombre" name="nombre" required maxlength="120" autocomplete="name">
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="le-telefono">Tu WhatsApp</label>
              <div class="pq-input-telefono">
                <span class="pq-input-telefono-prefijo pq-mono">🇨🇴 +57</span>
                <input class="pq-input pq-mono" type="tel" inputmode="numeric" id="le-telefono" name="telefono" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
              </div>
            </div>
            <label class="pq-consentimiento pq-consentimiento-requerido" style="margin-bottom: 12px">
              <input type="checkbox" name="autorizo_datos" value="1" required>
              <span>
                <span class="pq-consentimiento-titulo">Uso de datos para avisarte de este cupo</span>
                <span class="pq-ayuda" style="margin-top: 1px">Solo te escribimos si se libera un horario — nada de promociones.</span>
              </span>
            </label>
            <button type="submit" class="pq-btn pq-btn-oscuro pq-btn-chico" style="width: 100%">Anotarme en la lista</button>
          </form>
        </details>
      <?php endif; ?>
    <?php else: ?>
      <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px">
        <?php foreach ($slots as $slot): ?>
          <?php $activo = $slot === $horaElegida; ?>
          <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']) . '?fecha=' . $fecha . $sufijoEmpleado . '&hora=' . $slot) ?>#confirmar"
             class="pq-btn <?= $activo ? 'pq-btn-sello' : 'pq-btn-ghost-oscuro' ?> pq-btn-chico pq-mono"><?= e($slot) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($horaElegida !== null): ?>
    <div id="confirmar" class="pq-card" style="margin-top: 24px; border: 1px solid #E7E0CF">
      <span style="font-size: 14px; font-weight: 700">Confirmar reserva</span>
      <p class="pq-ayuda" style="margin-top: 4px">
        <?= e($servicio['nombre']) ?> el <?= e(date('d M', strtotime($fecha))) ?> a las <?= e($horaElegida) ?>
      </p>
      <?php if ($anticipo > 0): ?>
        <p class="pq-ayuda" style="margin-top: 4px; color: #8a5a00">Anticipo para confirmar: <strong><?= pesos($anticipo) ?></strong>. Te mostramos cómo pagarlo en la siguiente pantalla.</p>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/cita')) ?>" id="pq-form-reserva" style="margin-top: 16px">
        <?= csrf_campo() ?>
        <input type="hidden" name="servicio_id" value="<?= (int) $servicio['id'] ?>">
        <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
        <input type="hidden" name="hora" value="<?= e($horaElegida) ?>">
        <?php if ($empleadoElegido !== null): ?>
          <input type="hidden" name="empleado_id" value="<?= (int) $empleadoElegido['id'] ?>">
        <?php endif; ?>

        <div class="pq-campo">
          <label class="pq-label" for="nombre">Tu nombre</label>
          <input class="pq-input" type="text" id="nombre" name="nombre" required maxlength="120" autocomplete="name">
        </div>

        <div class="pq-campo">
          <label class="pq-label" for="telefono">Tu WhatsApp</label>
          <div class="pq-input-telefono">
            <span class="pq-input-telefono-prefijo pq-mono">🇨🇴 +57</span>
            <input class="pq-input pq-mono" type="tel" inputmode="numeric" id="telefono" name="telefono" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
          </div>
        </div>

        <label class="pq-consentimiento pq-consentimiento-requerido" style="margin-bottom: 20px">
          <input type="checkbox" name="autorizo_datos" value="1" required>
          <span>
            <span class="pq-consentimiento-titulo">Uso de datos para gestionar tu reserva</span>
            <span class="pq-ayuda" style="margin-top: 1px">Necesario para procesar la reserva y avisarte sobre cambios.</span>
          </span>
        </label>

        <button type="submit" class="pq-btn pq-btn-whatsapp">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
          Reservar y confirmar por WhatsApp
        </button>
        <p class="pq-ayuda pq-centro" style="margin-top: 10px">Revisarás la reserva en WhatsApp antes de enviarla.</p>
      </form>
    </div>
  <?php endif; ?>
</div>
