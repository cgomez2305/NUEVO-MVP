<?php
$abierta = (int) $negocio['fila_abierta'] === 1;
$esDueno = $negocio['rol'] === 'dueno';
$enlaceFila = url_publica('/t/' . $negocio['slug'] . '/fila');
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Operación</span>
    <h1 class="pq-h1">Fila de hoy</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Para quien llega sin cita: se anota desde su celular, espera donde quiera y tú lo llamas por WhatsApp cuando se acerca su turno.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="pq-cola-estado<?= $abierta ? ' pq-cola-estado-abierta' : '' ?>">
  <span class="pq-cola-estado-luz" aria-hidden="true"></span>
  <span>
    <strong><?= $abierta ? 'La fila está abierta' : 'La fila está cerrada' ?></strong>
    <span class="pq-ayuda"><?= $abierta ? 'Se pueden anotar mientras estés en horario. Pon el enlace o un QR en la puerta.' : 'Nadie se puede anotar.' ?></span>
  </span>
  <?php if ($esDueno): ?>
    <form method="post" action="<?= e(base_url('/panel/fila/abrir')) ?>">
      <?= csrf_campo() ?>
      <input type="hidden" name="abrir" value="<?= $abierta ? '0' : '1' ?>">
      <button type="submit" class="pq-btn <?= $abierta ? 'pq-btn-ghost' : 'pq-btn-sello' ?> pq-btn-chico"><?= $abierta ? 'Cerrar la fila' : 'Abrir la fila' ?></button>
    </form>
  <?php endif; ?>
</div>
<?php if ($abierta): ?>
  <p class="pq-ayuda pq-cola-enlace">Enlace para tus clientes: <a href="<?= e($enlaceFila) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $enlaceFila)) ?></a>
    <button type="button" class="pq-enlace-boton" data-copiar="<?= e($enlaceFila) ?>">Copiar</button></p>
<?php endif; ?>

<?php if ($turnos === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="7" r="2.5"/><path d="M3 20c0-2.5 1.8-4.5 4-4.5s4 2 4 4.5M13 20c0-2.5 1.8-4.5 4-4.5s4 2 4 4.5"/></svg>
    <p><strong>No hay nadie en la fila.</strong></p>
  </div>
<?php else: ?>
  <?php // Cada turno como una ficha de la panadería: el número a la izquierda, lo demás a la derecha. ?>
  <ol class="pq-cola-lista">
    <?php foreach ($turnos as $i => $turno): ?>
      <?php
      $llamado = $turno['estado'] === 'llamado';
      $espera = minutos_desde((string) $turno['creado_en']);
      ?>
      <li class="pq-cola-turno<?= $llamado ? ' pq-cola-turno-llamado' : '' ?>">
        <span class="pq-cola-numero" aria-hidden="true"><?= $i + 1 ?></span>
        <div class="pq-cola-cuerpo">
          <div class="pq-agenda-fila">
            <span class="pq-agenda-nombre"><?= e($turno['cliente_nombre']) ?></span>
            <span class="pq-chip <?= $llamado ? 'pq-chip-curso' : 'pq-chip-pendiente' ?>"><?= $llamado ? 'Llamado' : 'Esperando' ?></span>
          </div>
          <span class="pq-ayuda">
            <?= !empty($turno['servicio_nombre']) ? e($turno['servicio_nombre']) : 'Sin servicio elegido' ?>
            <?= !empty($turno['empleado_nombre']) ? ' · quiere con ' . e($turno['empleado_nombre']) : '' ?>
            · <?= e(texto_espera($espera)) ?>
          </span>
          <div class="pq-agenda-acciones">
            <?php if (!$llamado): ?>
              <form method="post" action="<?= e(base_url('/panel/fila/' . $turno['id'] . '/llamar')) ?>" target="_blank">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Llamarlo por WhatsApp</button>
              </form>
            <?php endif; ?>
            <details class="pq-agenda-mas">
              <summary>Atendido</summary>
              <form method="post" action="<?= e(base_url('/panel/fila/' . $turno['id'] . '/atender')) ?>" class="pq-ajuste-form">
                <?= csrf_campo() ?>
                <select class="pq-select" name="servicio_id" required aria-label="Qué servicio le hiciste">
                  <option value="">¿Qué le hiciste?</option>
                  <?php foreach ($servicios as $servicio): ?>
                    <option value="<?= (int) $servicio['id'] ?>" <?= (int) $turno['servicio_id'] === (int) $servicio['id'] ? 'selected' : '' ?>><?= e($servicio['nombre']) ?> · <?= e(precio_texto($servicio)) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($empleados !== []): ?>
                  <select class="pq-select" name="empleado_id" aria-label="Quién lo atendió">
                    <option value="0">¿Quién lo atendió?</option>
                    <?php foreach ($empleados as $persona): ?>
                      <option value="<?= (int) $persona['id'] ?>" <?= (int) $turno['empleado_id'] === (int) $persona['id'] ? 'selected' : '' ?>><?= e($persona['nombre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>
                <div class="pq-campo-dinero"><input class="pq-input pq-mono" type="text" inputmode="numeric" name="cobrado" placeholder="Cobrado (vacío = precio del servicio)" data-precio-cop aria-label="Lo que te pagó"></div>
                <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar como atendido</button>
              </form>
            </details>
            <form method="post" action="<?= e(base_url('/panel/fila/' . $turno['id'] . '/se-fue')) ?>" data-confirmar="¿<?= e($turno['cliente_nombre']) ?> se fue?">
              <?= csrf_campo() ?>
              <button type="submit" class="pq-enlace-boton">Se fue</button>
            </form>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
