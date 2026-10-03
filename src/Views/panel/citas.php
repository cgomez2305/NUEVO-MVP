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
<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php
// Imprevistos del día: dos botones que se abren en el sitio. No compiten con
// la agenda: van en una franja discreta arriba, como la libreta al lado de
// la caja donde se anota "llegamos tarde hoy".
$etiquetasMotivo = ['lluvia' => 'Aguacero fuerte', 'luz' => 'Se fue la luz', 'salud' => 'Enfermedad', 'calamidad' => 'Calamidad familiar', 'transporte' => 'Problema de transporte', 'otro' => 'Otro imprevisto'];
?>
<div class="pq-imprevistos-barra">
  <?php if ($retrasoHoy > 0): ?>
    <div class="pq-imprevistos-estado">
      <span>Hoy vas con <strong>~<?= (int) $retrasoHoy ?> min</strong> de retraso</span>
      <form method="post" action="<?= e(base_url('/panel/agenda/retraso')) ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="minutos" value="0">
        <button type="submit" class="pq-enlace-boton">Ya voy al día</button>
      </form>
    </div>
  <?php endif; ?>
  <details class="pq-imprevisto-menu">
    <summary class="pq-btn pq-btn-ghost pq-btn-chico">Voy retrasado</summary>
    <form method="post" action="<?= e(base_url('/panel/agenda/retraso')) ?>" class="pq-imprevisto-menu-panel">
      <?= csrf_campo() ?>
      <p class="pq-ayuda">Avisamos a los clientes que faltan hoy y cada uno elige si espera o cambia la hora.</p>
      <?php if (!empty($equipo)): ?>
        <div class="pq-campo">
          <label class="pq-label" for="retraso-quien">¿Quién va retrasado?</label>
          <select class="pq-select" id="retraso-quien" name="empleado_id">
            <option value="0">Todo el negocio</option>
            <?php foreach ($equipo as $persona): ?><option value="<?= (int) $persona['id'] ?>"><?= e($persona['nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <fieldset class="pq-imprevisto-minutos">
        <legend class="pq-label">¿Cuánto más o menos?</legend>
        <?php foreach (\App\Models\Imprevisto::MINUTOS_RETRASO as $i => $minutos): ?>
          <label><input type="radio" name="minutos" value="<?= $minutos ?>" <?= $i === 2 ? 'checked' : '' ?>><span><?= $minutos ?> min</span></label>
        <?php endforeach; ?>
      </fieldset>
      <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Avisar a mis clientes</button>
    </form>
  </details>
  <details class="pq-imprevisto-menu">
    <summary class="pq-btn pq-btn-ghost pq-btn-chico">Se me complicó el día</summary>
    <form method="post" action="<?= e(base_url('/panel/agenda/dia-complicado')) ?>" class="pq-imprevisto-menu-panel" data-confirmar="Les vamos a pedir a los clientes de ese día que elijan otra hora. ¿Seguimos?">
      <?= csrf_campo() ?>
      <p class="pq-ayuda">Las citas no se cancelan: cada cliente elige otra hora desde su enlace y su anticipo sigue valiendo.</p>
      <div class="pq-servicio-panel-par">
        <div class="pq-campo">
          <label class="pq-label" for="imprevisto-fecha">Día</label>
          <input class="pq-input" id="imprevisto-fecha" type="date" name="fecha" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="imprevisto-motivo">Qué pasó</label>
          <select class="pq-select" id="imprevisto-motivo" name="motivo" required>
            <?php foreach ($etiquetasMotivo as $clave => $etiqueta): ?>
              <option value="<?= e($clave) ?>"><?= e($etiqueta) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php if (!empty($equipo)): ?>
        <div class="pq-campo">
          <label class="pq-label" for="imprevisto-quien">¿De quién son las citas?</label>
          <select class="pq-select" id="imprevisto-quien" name="empleado_id">
            <option value="0">De todo el negocio</option>
            <?php foreach ($equipo as $persona): ?><option value="<?= (int) $persona['id'] ?>"><?= e($persona['nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <?php if ($negocio['rol'] === 'dueno'): ?>
        <label class="pq-imprevisto-check"><input type="checkbox" name="bloquear" value="1" checked> Cerrar ese día para nuevas reservas <span class="pq-ayuda">(solo si es de todo el negocio)</span></label>
      <?php endif; ?>
      <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Pedir que muevan su cita</button>
    </form>
  </details>
</div>

<?php if ($porAvisar !== []): ?>
  <section class="pq-avisos-imprevisto" id="pq-avisos-imprevisto" aria-labelledby="pq-titulo-avisos">
    <h2 class="pq-seccion-titulo" id="pq-titulo-avisos">Por avisar <span class="pq-seccion-cuenta"><?= count($porAvisar) ?></span></h2>
    <ul class="pq-admin-tarjeta pq-admin-filas">
      <?php foreach ($porAvisar as $fila): ?>
        <?php
        $tsAviso = strtotime((string) $fila['fecha_hora']) ?: 0;
        $queAviso = match ((string) $fila['aviso_imprevisto']) {
            'retraso'     => 'Retraso de ~' . (int) $fila['retraso_negocio_min'] . ' min',
            'reprogramar' => 'Mover su cita: ' . ($etiquetasMotivo[(string) $fila['imprevisto_motivo']] ?? 'imprevisto'),
            'ajuste'      => 'Nuevo valor: ' . pesos((int) $fila['ajuste_precio']),
            'abono'       => 'Anticipo abonado: cupón ' . ($fila['cupon_abono_codigo'] ?? ''),
            default       => '',
        };
        ?>
        <li class="pq-admin-fila">
          <span class="pq-admin-fila-texto">
            <strong><?= e($fila['cliente_nombre']) ?></strong>
            <span class="pq-ayuda"><?= e($queAviso) ?> · <?= e(fecha_corta((string) $fila['fecha_hora'])) ?></span>
          </span>
          <form method="post" action="<?= e(base_url('/panel/citas/' . $fila['id'] . '/aviso-imprevisto')) ?>" target="_blank" class="pq-aviso-estado-form">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-aviso-estado"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>Avisarle</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
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
                <span class="pq-chip <?= e(chip_estado($cita['estado'])) ?>"><?= e(\App\Models\Cita::ETIQUETAS[$cita['estado']] ?? ucfirst((string) $cita['estado'])) ?></span>
              </div>
              <span class="pq-ayuda">
                <?= e($cita['nombre_servicio']) ?><?php if (!empty($cita['adicionales_texto'])): ?> <strong class="pq-agenda-adicionales">+ <?= e($cita['adicionales_texto']) ?></strong><?php endif; ?> · <span class="pq-mono"><?= e(precio_texto($cita)) ?></span>
                <?php if ($cita['precio_final'] !== null): ?> · <?= $cita['estado'] === 'completada' ? 'cobrado' : 'valor aprobado' ?> <span class="pq-mono"><?= pesos(\App\Models\Cita::valor($cita)) ?></span><?php endif; ?>
                <?php if (!empty($cita['empleado_nombre'])): ?> · <?= \App\Models\Visita::esVisita($cita) ? 'va' : 'con' ?> <?= e($cita['empleado_nombre']) ?><?php endif; ?>
              </span>
              <?php if (\App\Models\Visita::esVisita($cita)): ?>
                <?php // La visita en una línea: franja prometida, dirección y la hoja con todo lo demás. ?>
                <a class="pq-agenda-visita" href="<?= e(base_url('/panel/visitas/' . $cita['id'])) ?>">
                  <span><?= e(\App\Models\Visita::textoFranja($cita)) ?> · <?= e((string) $cita['direccion']) ?><?= !empty($cita['zona_nombre']) ? ', ' . e($cita['zona_nombre']) : '' ?></span>
                  <span class="pq-agenda-visita-ver">Ver visita →</span>
                </a>
              <?php endif; ?>
              <?php if (\App\Models\PlanTratamiento::esSalud($negocio)): ?>
                <?php // Salud: el motivo (dato sensible, solo lo ve el equipo) y el plan de tratamiento. ?>
                <?php if (!empty($cita['motivo_consulta'])): ?>
                  <p class="pq-agenda-motivo"><span class="pq-sr-solo">Motivo de consulta: </span><?= e($cita['motivo_consulta']) ?></p>
                <?php endif; ?>
                <?php if (!empty($cita['plan_id'])): ?>
                  <a class="pq-agenda-visita" href="<?= e(base_url('/panel/planes/' . (int) $cita['plan_id'])) ?>"><span>Sesión de un plan de tratamiento · va por cuenta de los abonos</span><span class="pq-agenda-visita-ver">Ver plan →</span></a>
                <?php elseif (!in_array($cita['estado'], ['cancelada', 'no_asistio'], true)): ?>
                  <a class="pq-agenda-plan-nuevo" href="<?= e(base_url('/panel/planes/nuevo?cita=' . (int) $cita['id'])) ?>">Armar plan de tratamiento</a>
                <?php endif; ?>
              <?php endif; ?>
              <?php
              // Lo que se movió en esta cita, en una sola línea de marcas.
              $marcas = [];
              if ((int) $cita['retraso_cliente_min'] > 0) {
                  $marcas[] = ['Llega ' . (int) $cita['retraso_cliente_min'] . ' min tarde', 'pq-marca-aviso'];
              }
              if (!empty($cita['imprevisto_motivo'])) {
                  $marcas[] = ['Por reprogramar', 'pq-marca-aviso'];
              } elseif ((int) $cita['retraso_negocio_min'] > 0 && in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
                  $marcas[] = match (true) {
                      $cita['aviso_imprevisto'] === 'retraso' => ['Retraso por avisar', 'pq-marca-aviso'],
                      (int) $cita['cliente_espera'] === 1     => ['Espera tu retraso', 'pq-marca-ok'],
                      default                                 => ['Sabe del retraso', ''],
                  };
              }
              if ($cita['ajuste_estado'] === 'pendiente') {
                  $marcas[] = ['Nuevo valor por aprobar', 'pq-marca-aviso'];
              } elseif ($cita['ajuste_estado'] === 'aprobado') {
                  $marcas[] = ['Aprobó ' . pesos((int) $cita['ajuste_precio']), 'pq-marca-ok'];
              } elseif ($cita['ajuste_estado'] === 'rechazado') {
                  $marcas[] = ['No aprobó el nuevo valor', 'pq-marca-no'];
              }
              ?>
              <?php if ($marcas !== []): ?>
                <span class="pq-agenda-marcas">
                  <?php foreach ($marcas as [$textoMarca, $claseMarca]): ?><span class="pq-agenda-marca <?= e($claseMarca) ?>"><?= e($textoMarca) ?></span><?php endforeach; ?>
                </span>
              <?php endif; ?>
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
                <?php if ($siguientePasoCita !== null && $siguientePasoCita['estado'] === 'completada'): ?>
                  <?php
                  // Terminar pregunta lo cobrado solo si el precio no era exacto
                  // o hubo un ajuste; con precio fijo, un toque basta.
                  $preguntarCobrado = precio_es_estimado($cita) || $cita['ajuste_estado'] !== null;
                  // Neto: lo que el cliente pagó, ya con cupón o bono descontados (ver Cita::terminar).
                  $cobradoSugerido = \App\Models\Cita::valor($cita);
                  ?>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/terminar')) ?>" class="pq-terminar-form">
                    <?= csrf_campo() ?>
                    <?php if ($preguntarCobrado): ?>
                      <label class="pq-terminar-cobrado">
                        <span class="pq-ayuda">Cobrado</span>
                        <span class="pq-campo-dinero"><input class="pq-input pq-mono" type="text" inputmode="numeric" name="cobrado" value="<?= number_format($cobradoSugerido, 0, ',', '.') ?>" data-precio-cop required aria-label="Lo que te pagó <?= e($cita['cliente_nombre']) ?>, ya con descuentos"></span>
                      </label>
                    <?php endif; ?>
                    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico"><?= e($siguientePasoCita['texto']) ?> →</button>
                  </form>
                <?php elseif ($siguientePasoCita !== null): ?>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/estado')) ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="estado" value="<?= e($siguientePasoCita['estado']) ?>">
                    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico"><?= e($siguientePasoCita['texto']) ?> →</button>
                  </form>
                <?php endif; ?>
                <?php // "No vino" aparece solo cuando ya pasó la tolerancia: antes sería apresurado. ?>
                <?php if (\App\Models\Imprevisto::puedeMarcarNoVino($cita, $negocio)): ?>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/no-vino')) ?>" data-confirmar="¿Marcar que <?= e($cita['cliente_nombre']) ?> no vino?<?= (int) $cita['anticipo_monto'] > 0 && $cita['anticipo_estado'] === 'pagado' ? ($negocio['anticipo_no_asiste'] === 'se_abona' ? ' Su anticipo queda como cupón para su próxima cita.' : ' Su anticipo no se devuelve (es tu regla).') : '' ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">No vino</button>
                  </form>
                <?php endif; ?>
                <?php if (\App\Services\AvisoEstado::pendiente('cita', $cita, $negocio)): ?>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/avisar')) ?>" target="_blank" class="pq-aviso-estado-form">
                    <?= csrf_campo() ?>
                    <button type="submit" class="pq-aviso-estado"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>Avisarle: <?= e(ucfirst((string) $cita['estado'])) ?></button>
                  </form>
                <?php endif; ?>
                <?php if ($cita['estado'] === 'completada'): ?>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/resena')) ?>" target="_blank">
                    <?= csrf_campo() ?>
                    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Pedir reseña</button>
                  </form>
                <?php endif; ?>
                <details class="pq-agenda-mas">
                  <summary>Más</summary>
                  <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/estado')) ?>" class="pq-detalle-estado-fila">
                    <?= csrf_campo() ?>
                    <select class="pq-select" name="estado" aria-label="Nuevo estado de la cita">
                      <?php foreach (\App\Models\Cita::ETIQUETAS as $estado => $etiquetaEstado): ?>
                        <option value="<?= e($estado) ?>" <?= $cita['estado'] === $estado ? 'selected' : '' ?>><?= e($etiquetaEstado) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Cambiar estado</button>
                  </form>
                  <?php if (in_array($cita['estado'], ['pendiente', 'confirmada', 'en_curso'], true) && $cita['ajuste_estado'] !== 'pendiente'): ?>
                    <?php // El trabajo cuesta más (o menos) de lo pensado: se le pregunta al cliente antes de seguir. ?>
                    <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/ajuste')) ?>" class="pq-ajuste-form">
                      <?= csrf_campo() ?>
                      <span class="pq-label">Cambió el valor</span>
                      <div class="pq-campo-dinero"><input class="pq-input pq-mono" type="text" inputmode="numeric" name="precio" placeholder="Nuevo valor" data-precio-cop required aria-label="Nuevo valor para <?= e($cita['cliente_nombre']) ?>"></div>
                      <input class="pq-input" type="text" name="motivo" maxlength="200" required placeholder="Por qué cambia (p. ej. el cabello es más largo)" aria-label="Por qué cambia el valor">
                      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Mandar para que lo apruebe</button>
                    </form>
                  <?php endif; ?>
                </details>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($atendidas !== []): ?>
  <?php // Retoques y garantías: el cliente vuelve días después, cuando la cita ya salió de la agenda. ?>
  <details class="pq-atendidas">
    <summary class="pq-seccion-titulo">Atendidas en los últimos 30 días <span class="pq-seccion-cuenta"><?= count($atendidas) ?></span></summary>
    <p class="pq-ayuda">¿Hay que repetir o retocar un trabajo? Dale garantía: un turno a $0 del mismo servicio que el cliente reserva con su WhatsApp.</p>
    <ul class="pq-admin-tarjeta pq-admin-filas">
      <?php foreach ($atendidas as $fila): ?>
        <li class="pq-admin-fila">
          <span class="pq-admin-fila-texto">
            <strong><?= e($fila['cliente_nombre']) ?></strong>
            <span class="pq-ayuda"><?= e($fila['nombre_servicio']) ?> · <?= e(fecha_corta((string) $fila['fecha_hora'])) ?></span>
          </span>
          <?php if (!empty($fila['garantia_token'])): ?>
            <span class="pq-chip pq-chip-caja">Garantía dada</span>
          <?php elseif (!empty($fila['servicio_id']) && $negocio['rol'] === 'dueno'): ?>
            <form method="post" action="<?= e(base_url('/panel/citas/' . $fila['id'] . '/garantia')) ?>" target="_blank" class="pq-garantia-form">
              <?= csrf_campo() ?>
              <select class="pq-select" name="dias" aria-label="Días para usar la garantía de <?= e($fila['cliente_nombre']) ?>">
                <?php foreach (\App\Models\Imprevisto::DIAS_GARANTIA as $dias): ?>
                  <option value="<?= $dias ?>" <?= $dias === 15 ? 'selected' : '' ?>><?= $dias ?> días</option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Dar garantía</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </details>
<?php endif; ?>
