<?php
$volverUrl = '/cita/' . $cita['token_gestion'];
$volverTexto = 'Volver a tu cita';
require __DIR__ . '/_cabecera_corta.php';

$tsActual = strtotime((string) $cita['fecha_hora']) ?: 0;
$mesesLargo = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$mesActual = ucfirst($mesesLargo[(int) date('n', strtotime($fecha)) - 1]) . ' ' . date('Y', strtotime($fecha));
$urlReprogramar = base_url('/cita/' . $cita['token_gestion'] . '/reprogramar');
// Mañana/tarde, igual que en reservar.php, para escanear la grilla rápido.
$grupos = array_filter([
    'Mañana' => array_values(array_filter($slots, static fn (string $s) => (int) substr($s, 0, 2) < 12)),
    'Tarde'  => array_values(array_filter($slots, static fn (string $s) => (int) substr($s, 0, 2) >= 12)),
]);
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Cambiar tu cita</h1>
  <p class="pq-pagina-meta">
    <span><?= e($cita['nombre_servicio']) ?></span>
    <span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
      <?= (int) $cita['duracion_min'] ?> min
    </span>
  </p>
  <div class="pq-reprogramar-actual">
    <span class="pq-reprogramar-actual-etiqueta">Turno actual</span>
    <strong><?= e(ucfirst(fecha_larga(date('Y-m-d', $tsActual)))) ?> · <?= e(\App\Models\Visita::esVisita($cita) ? \App\Models\Visita::textoFranja($cita) : hora_completa(date('H:i', $tsActual))) ?></strong>
    <span>Elige el nuevo turno abajo: el cambio se guarda al tocar la hora.</span>
  </div>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($profesionalEnPausa)): ?>
    <div class="pq-alerta pq-alerta-aviso"><?= e($cita['empleado_nombre'] ?? 'Quien te atiende') ?> no está atendiendo por estos días. Escríbele al negocio por WhatsApp para mover tu cita con otra persona.</div>
  <?php endif; ?>

  <section class="pq-reserva-bloque" id="elige-dia">
    <div class="pq-etapa-fila">
      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true">1</span>Elige el día</h2>
      <span class="pq-mes-actual"><?= e($mesActual) ?></span>
    </div>
    <div class="pq-dias-scroll">
      <?php foreach ($fechasDisponibles as $opcion): ?>
        <?= hoja_almanaque($opcion, $fecha, $urlReprogramar . '?fecha=' . $opcion) ?>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="pq-reserva-bloque" id="disponibilidad">
    <div class="pq-etapa-fila">
      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true">2</span>Elige la hora</h2>
      <?php if ($slots !== []): ?>
        <span class="pq-mes-actual"><?= count($slots) ?> libre<?= count($slots) === 1 ? '' : 's' ?></span>
      <?php endif; ?>
    </div>

    <?php if (!empty($bloqueada)): ?>
      <div class="pq-sin-cupos">
        <span class="pq-sin-cupos-titulo"><?= e(nombre_publico_sede($negocio)) ?> no atiende ese día</span>
        <p class="pq-ayuda">Elige otra fecha arriba.</p>
      </div>
    <?php elseif ($slots === []): ?>
      <div class="pq-sin-cupos">
        <span class="pq-sin-cupos-titulo">No quedan turnos ese día</span>
        <p class="pq-ayuda">Elige otra fecha arriba.</p>
      </div>
    <?php else: ?>
      <?php foreach ($grupos as $titulo => $slotsGrupo): ?>
        <div class="pq-slot-grupo">
          <?php if (count($grupos) > 1): ?>
            <span class="pq-slot-grupo-titulo"><?= e($titulo) ?></span>
          <?php endif; ?>
          <div class="pq-slots-grid">
            <?php foreach ($slotsGrupo as $slot): ?>
              <?php $esActual = $fecha === date('Y-m-d', $tsActual) && $slot === date('H:i', $tsActual); ?>
              <form method="post" action="<?= e($urlReprogramar) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                <input type="hidden" name="hora" value="<?= e($slot) ?>">
                <button type="submit" class="pq-slot<?= $esActual ? ' pq-slot-seleccionado' : '' ?>"<?= $esActual ? ' aria-current="true" disabled' : '' ?>>
                  <?= e(hora_completa($slot)) ?>
                  <?php if ($esActual): ?><span class="pq-slot-nota">actual</span><?php endif; ?>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>
