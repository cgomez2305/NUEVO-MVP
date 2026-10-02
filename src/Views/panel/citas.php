<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Agenda</span>
    <h1 class="pq-h1">Tus próximas citas</h1>
  </div>
  <?php if (!empty($negocio['incluye_estadisticas_completas'])): ?>
    <a href="<?= e(base_url('/panel/citas/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar CSV</a>
  <?php else: ?>
    <a href="<?= e(base_url('/panel/plan')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico pq-btn-bloqueado">Exportar CSV · Pro</a>
  <?php endif; ?>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok pq-pagina-aviso"><?= e($ok) ?></div>
<?php endif; ?>

<?php if ($listaEspera !== []): ?>
  <section class="pq-agenda-espera" aria-labelledby="pq-titulo-espera">
    <h2 class="pq-seccion-titulo" id="pq-titulo-espera">Lista de espera <span class="pq-seccion-cuenta"><?= count($listaEspera) ?></span></h2>
    <div class="pq-agenda-espera-lista">
      <?php foreach ($listaEspera as $fila): ?>
        <?php
        $fechaEspera = (string) $fila['fecha'];
        $mensajeWa = "Hola {$fila['cliente_nombre']}, se liberó un cupo para {$fila['nombre_servicio']} el "
            . fecha_larga($fechaEspera) . '. ¿Te sirve que te lo reserve?';
        $telefonoWa = preg_replace('/\D+/', '', (string) $fila['cliente_telefono']);
        $enlaceWa = 'https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($mensajeWa);
        ?>
        <div class="pq-agenda-espera-fila">
          <div class="pq-avatar pq-avatar-chico pq-avatar-espera"><?= e(mb_strtoupper(mb_substr($fila['cliente_nombre'], 0, 1))) ?></div>
          <div class="pq-agenda-espera-texto">
            <span class="pq-agenda-nombre"><?= e($fila['cliente_nombre']) ?></span>
            <span class="pq-ayuda"><?= e($fila['nombre_servicio']) ?> · quiere el <?= e(fecha_larga($fechaEspera)) ?></span>
          </div>
          <a href="<?= e($enlaceWa) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-ghost pq-btn-chico">Avisar</a>
          <form method="post" action="<?= e(base_url('/panel/lista-espera/' . $fila['id'] . '/contactado')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-btn-icono" title="Ya la contacté" aria-label="Marcar a <?= e($fila['cliente_nombre']) ?> como contactada">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($citas === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
    <p><strong>Todavía no tienes citas.</strong><br>Comparte el enlace de tu tienda para que tus clientes reserven.</p>
  </div>
<?php else: ?>
  <?php
  // Agenda agrupada por día, con la hora en su propia columna: se lee como
  // la agenda de papel del salón, no como una lista de tarjetas iguales.
  $porDia = [];
  foreach ($citas as $cita) {
      $porDia[date('Y-m-d', strtotime((string) $cita['fecha_hora']) ?: 0)][] = $cita;
  }
  $hoy = date('Y-m-d');
  $manana = date('Y-m-d', strtotime('+1 day'));
  $ayer = date('Y-m-d', strtotime('-1 day'));
  ?>
  <?php foreach ($porDia as $dia => $citasDia): ?>
    <section class="pq-agenda-dia" aria-label="<?= e(fecha_larga($dia)) ?>">
      <h2 class="pq-agenda-dia-titulo">
        <?= e(match ($dia) { $hoy => 'Hoy', $manana => 'Mañana', $ayer => 'Ayer', default => ucfirst(fecha_larga($dia)) }) ?>
        <?php if (in_array($dia, [$hoy, $manana, $ayer], true)): ?><span><?= e(fecha_larga($dia)) ?></span><?php endif; ?>
      </h2>
      <div class="pq-agenda-lista">
        <?php foreach ($citasDia as $cita): ?>
          <?php
          $citaSinConfirmar = $cita['estado'] === 'pendiente';
          $minutosEspera = minutos_desde((string) $cita['creado_en']);
          $nivel = nivel_espera($minutosEspera, 15);
          $demorada = $citaSinConfirmar && $nivel === 'prioridad';
          $siguientePasoCita = \App\Models\Cita::siguientePaso($cita);
          ?>
          <article class="pq-agenda-cita<?= $demorada ? ' pq-card-demorado' : '' ?><?= $cita['estado'] === 'cancelada' ? ' pq-agenda-cita-cancelada' : '' ?>">
            <?php $tsCita = strtotime((string) $cita['fecha_hora']) ?: 0; ?>
            <div class="pq-agenda-hora">
              <strong><?= e(date('g:i', $tsCita)) ?></strong>
              <span><?= date('a', $tsCita) === 'am' ? 'a. m.' : 'p. m.' ?></span>
              <span class="pq-agenda-duracion"><?= (int) $cita['duracion_min'] ?> min</span>
            </div>
            <div class="pq-agenda-cuerpo">
              <div class="pq-agenda-fila">
                <span class="pq-agenda-nombre"><?= e($cita['cliente_nombre']) ?></span>
                <span class="pq-chip <?= e(chip_estado($cita['estado'])) ?>"><?= e(ucfirst($cita['estado'])) ?></span>
              </div>
              <span class="pq-ayuda">
                <?= e($cita['nombre_servicio']) ?> · <span class="pq-mono"><?= pesos((int) $cita['precio']) ?></span>
                <?php if (!empty($cita['empleado_nombre'])): ?> · con <?= e($cita['empleado_nombre']) ?><?php endif; ?>
              </span>
              <?php if ($citaSinConfirmar): ?>
                <span class="pq-tiempo-espera pq-tiempo-<?= e($nivel) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                  Sin confirmar · <?= e(texto_espera($minutosEspera)) ?>
                </span>
              <?php endif; ?>

              <?php if ((int) $cita['anticipo_monto'] > 0): ?>
                <div class="pq-agenda-anticipo">
                  <span class="pq-ayuda">Anticipo <?= pesos((int) $cita['anticipo_monto']) ?></span>
                  <?php if ($cita['anticipo_estado'] === 'pagado'): ?>
                    <span class="pq-chip pq-chip-caja">Pagado</span>
                  <?php else: ?>
                    <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/anticipo')) ?>">
                      <?= csrf_campo() ?>
                      <button type="submit" class="pq-chip pq-chip-pendiente pq-chip-boton">Marcar pagado</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <div class="pq-agenda-acciones">
                <?php if ($siguientePasoCita !== null): ?>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/estado')) ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="estado" value="<?= e($siguientePasoCita['estado']) ?>">
                    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico"><?= e($siguientePasoCita['texto']) ?> →</button>
                  </form>
                <?php endif; ?>
                <?php if ($cita['estado'] === 'completada'): ?>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/resena')) ?>" target="_blank">
                    <?= csrf_campo() ?>
                    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Pedir reseña</button>
                  </form>
                <?php endif; ?>
                <details class="pq-agenda-mas">
                  <summary>Cambiar estado</summary>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/estado')) ?>" class="pq-detalle-estado-fila">
                    <?= csrf_campo() ?>
                    <select class="pq-select" name="estado" aria-label="Nuevo estado de la cita">
                      <?php foreach (['pendiente', 'confirmada', 'completada', 'cancelada'] as $estado): ?>
                        <option value="<?= e($estado) ?>" <?= $cita['estado'] === $estado ? 'selected' : '' ?>><?= e(ucfirst($estado)) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Actualizar</button>
                  </form>
                </details>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
