<?php $volverUrl = '/t/' . $negocio['slug']; $volverTexto = 'Volver a los servicios'; require __DIR__ . '/_cabecera_corta.php'; ?>

<?php
$diasLargo = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$diasPlural = ['domingos', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábados'];
$mesesLargo = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
// Lo elegido viaja en la URL (sin JS ni sesión): profesional y adicionales.
$idsAdicionales = implode(',', array_map(fn ($a) => (int) $a['id'], $adicionalesElegidos));
$sufijoAdicionales = $idsAdicionales !== '' ? '&ad=' . $idsAdicionales : '';
$sufijoEmpleado = ($empleadoElegido !== null ? '&empleado=' . (int) $empleadoElegido['id'] : '') . $sufijoAdicionales;
$urlReservar = base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']);
$precioMostrado = ['precio' => $condiciones['precio'] + $precioAdicionales, 'precio_tipo' => $condiciones['precio_tipo'], 'precio_max' => $condiciones['precio_max'] !== null ? $condiciones['precio_max'] + $precioAdicionales : null];
$fechaEsHoy = $fecha === date('Y-m-d');
$aDomicilio = !empty($aDomicilio);
$franjas = $franjas ?? [];
$hayFranjaLibre = array_filter(array_column($franjas, 'hora')) !== [];
$horaCompleta = 'hora_completa';
$mesActual = ucfirst($mesesLargo[(int) date('n', strtotime($fecha)) - 1]) . ' ' . date('Y', strtotime($fecha));
// Mañana/tarde para escanear la grilla más rápido cuando hay muchos cupos
// — el corte es mediodía, no la hora de cierre del negocio.
$slotsManana = array_values(array_filter($slots, static fn (string $s) => (int) substr($s, 0, 2) < 12));
$slotsTarde = array_values(array_filter($slots, static fn (string $s) => (int) substr($s, 0, 2) >= 12));
// Los pasos se numeran según lo que de verdad hay que hacer: "¿Con quién?"
// solo existe si el negocio tiene empleados.
$paso = 0;
// Cada fecha es una hojita de almanaque (ver hoja_almanaque()): mismo
// marcado en el carrusel, en "Ver todas las fechas" y en reprogramar.
$hojaDia = static fn (string $opcion): string => hoja_almanaque(
    $opcion,
    $fecha,
    base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']) . '?fecha=' . $opcion . $sufijoEmpleado
);
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo"><?= e($servicio['nombre']) ?></h1>
  <p class="pq-pagina-meta">
    <span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
      <?= (int) $duracionTotal ?> min
    </span>
    <span class="pq-pagina-meta-precio"><?= e(precio_texto($precioMostrado)) ?></span>
  </p>
  <?php if (precio_es_estimado($precioMostrado)): ?>
    <p class="pq-ayuda pq-precio-estimado-nota">El valor final se confirma al ver el trabajo. Si cambia, te lo mandamos para que lo apruebes antes de seguir.</p>
  <?php endif; ?>
  <?php if ($anticipo > 0): ?>
    <div class="pq-alerta pq-alerta-aviso">
      Este servicio pide un anticipo de <strong><?= pesos($anticipo) ?></strong> para confirmar la reserva.
    </div>
  <?php endif; ?>

  <?php if (!empty($ok)): ?>
    <div class="pq-alerta pq-alerta-ok" role="status"><?= e($ok) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="pq-alerta"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($sinProfesional)): ?>
    <div class="pq-alerta pq-alerta-aviso">Por ahora nadie del equipo tiene este servicio en su agenda. Escríbele al negocio por WhatsApp para agendarlo.</div>
  <?php endif; ?>

  <?php if (!empty($empleados)): ?>
    <section class="pq-reserva-bloque">
      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span>¿Con quién?</h2>
      <?php // Cada profesional con su cara y lo que mejor hace: así se elige en una barbería de verdad. ?>
      <div class="pq-opciones-persona">
        <?php foreach ($empleados as $emp): ?>
          <?php
          $activo = $empleadoElegido !== null && (int) $empleadoElegido['id'] === (int) $emp['id'];
          $condicionesEmp = \App\Models\Empleado::condiciones($emp, $servicio);
          ?>
          <a href="<?= e($urlReservar . '?fecha=' . $fecha . '&empleado=' . (int) $emp['id'] . $sufijoAdicionales) ?>"
             class="pq-persona<?= !empty($emp['foto']) || !empty($emp['especialidad']) ? ' pq-persona-ficha' : '' ?><?= $activo ? ' pq-persona-activa' : '' ?>"<?= $activo ? ' aria-current="true"' : '' ?>>
            <?php if (!empty($emp['foto'])): ?>
              <img class="pq-persona-foto" src="<?= e(base_url($emp['foto'])) ?>" alt="" width="44" height="44" loading="lazy">
            <?php else: ?>
              <span class="pq-persona-inicial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $emp['nombre'], 0, 1))) ?></span>
            <?php endif; ?>
            <span class="pq-persona-texto">
              <span class="pq-persona-nombre"><?= e($emp['nombre']) ?></span>
              <?php if (!empty($emp['especialidad'])): ?><span class="pq-persona-especialidad"><?= e($emp['especialidad']) ?></span><?php endif; ?>
              <?php if ($condicionesEmp['precio'] !== (int) $servicio['precio']): ?><span class="pq-persona-precio"><?= e(precio_texto($condicionesEmp)) ?></span><?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($empleadoElegido !== null): ?>
        <a class="pq-persona-perfil" href="<?= e(base_url('/t/' . $negocio['slug'] . '/equipo/' . (int) $empleadoElegido['id'])) ?>">Ver trabajos de <?= e(explode(' ', trim((string) $empleadoElegido['nombre']))[0]) ?> →</a>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (!empty($adicionalesDisponibles)): ?>
    <section class="pq-reserva-bloque">
      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span>¿Le sumas algo?</h2>
      <?php // Cada adicional es un enlace que lo agrega o lo quita: funciona sin JS y los cupos se recalculan con el tiempo total. ?>
      <div class="pq-adicionales">
        <?php foreach ($adicionalesDisponibles as $adicional): ?>
          <?php
          $idsActuales = array_map(fn ($a) => (int) $a['id'], $adicionalesElegidos);
          $marcado = in_array((int) $adicional['id'], $idsActuales, true);
          $idsNuevos = $marcado ? array_diff($idsActuales, [(int) $adicional['id']]) : array_merge($idsActuales, [(int) $adicional['id']]);
          $href = $urlReservar . '?fecha=' . $fecha . ($empleadoElegido !== null ? '&empleado=' . (int) $empleadoElegido['id'] : '') . ($idsNuevos !== [] ? '&ad=' . implode(',', $idsNuevos) : '');
          ?>
          <a href="<?= e($href) ?>" class="pq-adicional<?= $marcado ? ' pq-adicional-activo' : '' ?>">
            <span class="pq-adicional-marca" aria-hidden="true"><?= $marcado ? '✓' : '+' ?></span>
            <span class="pq-sr-solo"><?= $marcado ? 'Incluido, toca para quitar:' : 'Sumar:' ?></span>
            <span class="pq-adicional-nombre"><?= e($adicional['nombre']) ?></span>
            <span class="pq-adicional-extra">+<?= pesos((int) $adicional['precio']) ?><?= (int) $adicional['duracion_min'] > 0 ? ' · ' . (int) $adicional['duracion_min'] . ' min' : '' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="pq-reserva-bloque" id="elige-dia">
    <div class="pq-etapa-fila">
      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span>Elige el día</h2>
      <span class="pq-mes-actual"><?= e($mesActual) ?></span>
    </div>
    <div class="pq-dias-scroll">
      <?php foreach ($fechasDisponibles as $opcion): ?>
        <?= $hojaDia($opcion) ?>
      <?php endforeach; ?>
    </div>

    <details class="pq-calendario-detalle">
      <summary>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
        Ver todas las fechas
      </summary>
      <div class="pq-calendario-grid">
        <?php foreach ($fechasDisponibles as $opcion): ?>
          <?= $hojaDia($opcion) ?>
        <?php endforeach; ?>
      </div>
    </details>
  </section>

  <?php if ($aDomicilio): ?>
  <?php // Visita a domicilio: se promete una franja de llegada, no una hora exacta (con tráfico, nadie la cumple). ?>
  <section class="pq-reserva-bloque" id="disponibilidad">
    <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span>¿Cuándo te visitamos?</h2>
    <?php if (!empty($disponibilidadError)): ?>
      <div class="pq-disponibilidad-error">
        <span class="pq-disponibilidad-error-titulo">No pudimos cargar la disponibilidad</span>
        <p class="pq-ayuda">Puede ser algo pasajero. Intenta de nuevo o escribe directo por WhatsApp.</p>
        <a href="<?= e($urlReservar . '?fecha=' . $fecha . $sufijoAdicionales) ?>" class="pq-btn pq-btn-oscuro pq-btn-chico pq-btn-alto">Reintentar</a>
      </div>
    <?php elseif (empty($sinProfesional)): ?>
      <?php if ($franjas !== []): ?>
        <p class="pq-ayuda pq-reserva-nota">Te damos una franja de llegada. Cuando el técnico salga para tu casa, te escribe por WhatsApp con la hora.</p>
        <div class="pq-franjas">
          <?php foreach ($franjas as $franja): ?>
            <?php
            $rango = hora_completa($franja['inicio']) . ' – ' . hora_completa($franja['fin']);
            $activa = $franjaElegida !== null && $franjaElegida['clave'] === $franja['clave'];
            ?>
            <?php if ($franja['hora'] === null): ?>
              <div class="pq-franja pq-franja-llena">
                <span class="pq-franja-nombre"><?= e($franja['etiqueta']) ?></span>
                <span class="pq-franja-rango"><?= e($rango) ?></span>
                <span class="pq-franja-estado">Ya no hay cupo</span>
              </div>
            <?php else: ?>
              <a class="pq-franja<?= $activa ? ' pq-franja-activa' : '' ?>"<?= $activa ? ' aria-current="true"' : '' ?> href="<?= e($urlReservar . '?fecha=' . $fecha . '&franja=' . $franja['clave'] . $sufijoAdicionales) ?>#confirmar">
                <span class="pq-franja-nombre"><?= e($franja['etiqueta']) ?></span>
                <span class="pq-franja-rango"><?= e($rango) ?></span>
                <span class="pq-franja-estado"><?= $activa ? 'Elegida' : 'Hay cupo' ?></span>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!$hayFranjaLibre): ?>
        <div class="pq-sin-cupos">
          <span class="pq-sin-cupos-titulo"><?= !empty($bloqueada) || $franjas === [] ? 'Ese día no hacemos visitas' : 'Ese día ya está lleno' ?></span>
          <?php if ($proximoDisponible !== null): ?>
            <div class="pq-sin-cupos-proximo">
              <span class="pq-ayuda">Próxima visita libre</span>
              <span class="pq-sin-cupos-fecha"><?= e(ucfirst($diasLargo[(int) date('w', strtotime($proximoDisponible['fecha']))])) ?> <?= (int) date('j', strtotime($proximoDisponible['fecha'])) ?> de <?= e($mesesLargo[(int) date('n', strtotime($proximoDisponible['fecha'])) - 1]) ?> · en la <?= e(mb_strtolower($proximoDisponible['etiqueta'])) ?></span>
            </div>
            <a href="<?= e($urlReservar . '?fecha=' . $proximoDisponible['fecha'] . '&franja=' . $proximoDisponible['franja'] . $sufijoAdicionales) ?>#confirmar" class="pq-btn pq-btn-oscuro pq-btn-chico pq-btn-alto">Pedir esa visita</a>
          <?php else: ?>
            <p class="pq-ayuda">No encontramos cupo en los próximos días. Escríbenos por WhatsApp y lo cuadramos.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php else: ?>
  <section class="pq-reserva-bloque" id="disponibilidad">
    <div class="pq-etapa-fila">
      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span>Elige la hora</h2>
      <?php if ($slots !== []): ?>
        <span class="pq-mes-actual"><?= count($slots) ?> libre<?= count($slots) === 1 ? '' : 's' ?></span>
      <?php endif; ?>
    </div>

    <?php if (!empty($disponibilidadError)): ?>
      <div class="pq-disponibilidad-error">
        <span class="pq-disponibilidad-error-titulo">No pudimos cargar la disponibilidad</span>
        <p class="pq-ayuda">Puede ser algo pasajero. Intenta de nuevo o escribe directo por WhatsApp.</p>
        <a href="<?= e($urlReservar . '?fecha=' . $fecha . $sufijoEmpleado) ?>" class="pq-btn pq-btn-oscuro pq-btn-chico pq-btn-alto">Reintentar</a>
      </div>
    <?php elseif (!empty($faltaElegirEmpleado)): ?>
      <p class="pq-ayuda pq-reserva-nota">Elige con quién quieres agendar para ver los horarios.</p>
    <?php elseif (!empty($bloqueada)): ?>
      <p class="pq-ayuda pq-reserva-nota"><?= e(nombre_publico_sede($negocio)) ?> no atiende ese día. Elige otra fecha.</p>
    <?php elseif ($slots === []): ?>

      <?php
        $diaSemanaIdx = (int) date('w', strtotime($fecha));
        if ($cerradoEseDia) {
            $tituloSinCupos = 'No atiende los ' . $diasPlural[$diaSemanaIdx];
        } elseif ($fechaEsHoy) {
            $tituloSinCupos = 'Sin cupos para hoy';
        } else {
            $tituloSinCupos = 'Sin cupos para ' . $diasLargo[$diaSemanaIdx] . ' ' . (int) date('j', strtotime($fecha));
        }
      ?>
      <div class="pq-sin-cupos">
        <span class="pq-sin-cupos-titulo"><?= e($tituloSinCupos) ?></span>
        <?php if ($proximoDisponible !== null): ?>
          <div class="pq-sin-cupos-proximo">
            <span class="pq-ayuda">Próximo turno libre</span>
            <span class="pq-sin-cupos-fecha"><?= e(ucfirst($diasLargo[(int) date('w', strtotime($proximoDisponible['fecha']))])) ?> <?= (int) date('j', strtotime($proximoDisponible['fecha'])) ?> de <?= e($mesesLargo[(int) date('n', strtotime($proximoDisponible['fecha'])) - 1]) ?> · <?= e($horaCompleta($proximoDisponible['hora'])) ?></span>
          </div>
          <a href="<?= e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']) . '?fecha=' . $proximoDisponible['fecha'] . $sufijoEmpleado . '&hora=' . $proximoDisponible['hora']) ?>#confirmar"
             class="pq-btn pq-btn-oscuro pq-btn-chico pq-btn-alto">Reservar ese turno</a>
        <?php else: ?>
          <p class="pq-ayuda">No encontramos disponibilidad en los próximos días. Elige otro servicio o anótate en la lista de espera.</p>
        <?php endif; ?>
      </div>

      <?php if ($cerradoEseDia): ?>
        <?php /* Ese día no se atiende: no hay cupo que se pueda liberar. */ ?>
      <?php elseif ($listaEsperaId !== null): ?>
        <div class="pq-card pq-card-exito pq-reserva-espera">
          <div class="pq-reserva-espera-titulo">
            <span class="pq-card-exito-check">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l5 5L20 7"/></svg>
            </span>
            <span>Estás en la lista de espera</span>
          </div>
          <p class="pq-reserva-espera-servicio"><?= e($servicio['nombre']) ?></p>
          <p class="pq-reserva-espera-fecha"><?= e(ucfirst($fechaEsHoy ? 'hoy, ' . fecha_larga($fecha) : fecha_larga($fecha))) ?></p>
          <p class="pq-ayuda">Te escribiremos por WhatsApp si se libera un cupo.</p>

          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/lista-espera/salir')) ?>" id="pq-form-salir-lista">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= (int) $listaEsperaId ?>">
            <input type="hidden" name="servicio_id" value="<?= (int) $servicio['id'] ?>">
            <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
            <input type="hidden" name="empleado" value="<?= $empleadoElegido !== null ? (int) $empleadoElegido['id'] : '' ?>">
            <input type="hidden" name="ad" value="<?= e($idsAdicionales) ?>">
          </form>
          <details class="pq-salir-confirm">
            <summary>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
              Salir de la lista
            </summary>
            <div class="pq-salir-confirm-caja">
              <p class="pq-ayuda">¿Seguro que quieres salir de la lista de espera para <?= $fechaEsHoy ? 'hoy, ' : '' ?><?= e(fecha_larga($fecha)) ?>?</p>
              <div class="pq-botones-par">
                <button type="button" data-cerrar-details class="pq-btn pq-btn-ghost-oscuro pq-btn-chico">Cancelar</button>
                <button type="submit" form="pq-form-salir-lista" class="pq-btn pq-btn-oscuro pq-btn-chico">Sí, salir</button>
              </div>
            </div>
          </details>
        </div>
      <?php else: ?>
        <details class="pq-lista-espera-detalle">
          <summary>Avísame si se libera un cupo</summary>
          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/lista-espera')) ?>" id="pq-form-lista-espera">
            <p class="pq-ayuda">Para <?= $fechaEsHoy ? 'hoy, ' : '' ?><?= e(fecha_larga($fecha)) ?>.</p>
            <?= csrf_campo() ?>
            <input type="hidden" name="servicio_id" value="<?= (int) $servicio['id'] ?>">
            <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
            <input type="hidden" name="empleado" value="<?= $empleadoElegido !== null ? (int) $empleadoElegido['id'] : '' ?>">
            <input type="hidden" name="ad" value="<?= e($idsAdicionales) ?>">

            <div class="pq-campo">
              <label class="pq-label" for="le-nombre">Tu nombre</label>
              <input class="pq-input" type="text" id="le-nombre" name="nombre" required maxlength="120" autocomplete="name">
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="le-telefono">Tu WhatsApp</label>
              <div class="pq-input-telefono">
                <span class="pq-input-telefono-prefijo">🇨🇴 +57</span>
                <input class="pq-input" type="tel" inputmode="numeric" id="le-telefono" name="telefono" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
              </div>
            </div>
            <label class="pq-consentimiento">
              <input type="checkbox" name="autorizo_datos" value="1" required>
              <span>
                <span class="pq-consentimiento-titulo">Aviso por WhatsApp si se libera un cupo</span>
                <span class="pq-ayuda">Solo te escribimos si se libera un cupo, nada de promociones.</span>
              </span>
            </label>
            <button type="submit" class="pq-btn pq-btn-oscuro pq-btn-chico pq-btn-ancho">Avisarme si se libera un cupo</button>
          </form>
        </details>
      <?php endif; ?>
    <?php else: ?>

      <?php
        $renderGrupo = static function (array $slotsGrupo, ?string $titulo) use ($negocio, $servicio, $fecha, $sufijoEmpleado, $horaElegida, $horaCompleta): void {
            if ($slotsGrupo === []) {
                return;
            }
            echo '<div class="pq-slot-grupo">';
            if ($titulo !== null) {
                echo '<span class="pq-slot-grupo-titulo">' . e($titulo) . '</span>';
            }
            echo '<div class="pq-slots-grid">';
            foreach ($slotsGrupo as $slot) {
                $activo = $slot === $horaElegida;
                $href = e(base_url('/t/' . $negocio['slug'] . '/reservar/' . $servicio['id']) . '?fecha=' . $fecha . $sufijoEmpleado . '&hora=' . $slot) . '#confirmar';
                echo '<a href="' . $href . '" class="pq-slot' . ($activo ? ' pq-slot-seleccionado' : '') . '">';
                if ($activo) {
                    echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l5 5L20 7"/></svg>';
                }
                echo e($horaCompleta($slot)) . '</a>';
            }
            echo '</div></div>';
        };
      ?>
      <div>
        <?php if ($slotsManana !== [] && $slotsTarde !== []): ?>
          <?php $renderGrupo($slotsManana, 'Mañana'); ?>
          <?php $renderGrupo($slotsTarde, 'Tarde'); ?>
        <?php else: ?>
          <?php $renderGrupo($slots, null); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($horaElegida !== null): ?>
    <section id="confirmar" class="pq-reserva-bloque pq-confirmar">
      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true"><?= ++$paso ?></span><?= $aDomicilio ? 'Confirma tu visita' : 'Confirma tu turno' ?></h2>
      <div class="pq-turno">
        <?php if ($aDomicilio && $franjaElegida !== null): ?>
          <span class="pq-turno-hora"><?= e($franjaElegida['etiqueta']) ?></span>
          <span class="pq-turno-franja">Llegamos entre <?= e(hora_completa($franjaElegida['inicio'])) ?> y <?= e(hora_completa($franjaElegida['fin'])) ?></span>
        <?php else: ?>
        <span class="pq-turno-hora"><?= e($horaCompleta($horaElegida)) ?></span>
        <?php endif; ?>
        <span class="pq-turno-fecha"><?= e(ucfirst($fechaEsHoy ? 'hoy, ' . fecha_larga($fecha) : fecha_larga($fecha))) ?></span>
        <span class="pq-turno-servicio"><?= e($servicio['nombre']) ?><?= $adicionalesElegidos !== [] ? ' + ' . e(implode(' + ', array_column($adicionalesElegidos, 'nombre'))) : '' ?><?= $empleadoElegido !== null ? ' · con ' . e($empleadoElegido['nombre']) : '' ?></span>
        <span class="pq-turno-total"><?= e(precio_texto($precioMostrado)) ?> · <?= (int) $duracionTotal ?> min</span>
      </div>
      <?php if ($anticipo > 0): ?>
        <p class="pq-turno-anticipo">Anticipo para confirmar: <strong><?= pesos($anticipo) ?></strong>. Te mostramos cómo pagarlo en la siguiente pantalla.</p>
      <?php endif; ?>
      <?php // Las reglas se dicen antes de reservar, no después: nadie se entera de una política cuando ya la incumplió. ?>
      <?php if ($aDomicilio): ?>
      <p class="pq-turno-reglas">
        Cuando el técnico salga para tu casa te escribe por WhatsApp con la hora de llegada y su foto, para que sepas quién va.
        <?php if ($anticipo > 0): ?>
          Si no estás en casa, <?= $negocio['anticipo_no_asiste'] === 'se_abona' ? 'el anticipo queda abonado para otra visita' : 'el anticipo no se devuelve' ?>.
        <?php endif; ?>
      </p>
      <?php else: ?>
      <p class="pq-turno-reglas">
        Te esperamos hasta <?= (int) $negocio['tolerancia_min'] ?> minutos; si se te hace tarde, avísanos desde el enlace de tu cita.
        <?php if ($anticipo > 0): ?>
          Si no llegas, <?= $negocio['anticipo_no_asiste'] === 'se_abona' ? 'el anticipo queda abonado para tu próxima cita' : 'el anticipo no se devuelve' ?>.
        <?php endif; ?>
      </p>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/cita')) ?>" id="pq-form-reserva" class="pq-confirmar-form"<?= $aDomicilio ? ' enctype="multipart/form-data"' : '' ?>>
        <?= csrf_campo() ?>
        <input type="hidden" name="servicio_id" value="<?= (int) $servicio['id'] ?>">
        <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
        <input type="hidden" name="hora" value="<?= e($horaElegida) ?>">
        <?php if ($empleadoElegido !== null): ?>
          <input type="hidden" name="empleado_id" value="<?= (int) $empleadoElegido['id'] ?>">
        <?php endif; ?>
        <?php if ($idsAdicionales !== ''): ?>
          <input type="hidden" name="adicionales" value="<?= e($idsAdicionales) ?>">
        <?php endif; ?>
        <?php if ($aDomicilio && $franjaElegida !== null): ?>
          <input type="hidden" name="franja" value="<?= e($franjaElegida['clave']) ?>">
        <?php endif; ?>

        <div class="pq-campo">
          <label class="pq-label" for="nombre">Tu nombre</label>
          <input class="pq-input" type="text" id="nombre" name="nombre" required maxlength="120" autocomplete="name">
        </div>

        <div class="pq-campo">
          <label class="pq-label" for="telefono">Tu WhatsApp</label>
          <div class="pq-input-telefono">
            <span class="pq-input-telefono-prefijo">🇨🇴 +57</span>
            <input class="pq-input" type="tel" inputmode="numeric" id="telefono" name="telefono" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
          </div>
        </div>

        <?php if ($aDomicilio): ?>
          <?php // Lo que el técnico necesita para llegar y para llevar lo correcto. ?>
          <fieldset class="pq-visita-datos">
            <legend class="pq-visita-datos-titulo">La visita</legend>
            <?php if (!empty($zonas)): ?>
              <div class="pq-campo">
                <label class="pq-label" for="zona_id">Barrio o zona</label>
                <select class="pq-select pq-input" id="zona_id" name="zona_id" required>
                  <option value="">Elige tu zona</option>
                  <?php foreach ($zonas as $zona): ?>
                    <option value="<?= (int) $zona['id'] ?>"><?= e($zona['nombre']) ?><?= (int) $zona['costo'] > 0 ? ' · +' . pesos((int) $zona['costo']) . ' de transporte' : '' ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="pq-ayuda">¿No está tu zona? Escríbenos por WhatsApp antes de pedir la visita.</span>
              </div>
            <?php endif; ?>
            <div class="pq-campo">
              <label class="pq-label" for="direccion">Dirección</label>
              <input class="pq-input" type="text" id="direccion" name="direccion" required minlength="5" maxlength="200" autocomplete="street-address" placeholder="Calle 45 # 12-30">
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="referencia">Apto, torre o cómo llegar <span class="pq-ayuda">(opcional)</span></label>
              <input class="pq-input" type="text" id="referencia" name="referencia" maxlength="160" placeholder="Torre 2, apto 504 · portería por la 46">
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="problema">¿Qué pasa?</label>
              <textarea class="pq-input" id="problema" name="problema" rows="3" required minlength="3" maxlength="1000" placeholder="Ej.: el aire gotea agua por dentro y no enfría"></textarea>
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="fotos">Fotos del daño <span class="pq-ayuda">(opcional, hasta <?= \App\Models\Visita::MAX_FOTOS_CLIENTE ?>)</span></label>
              <input class="pq-input" type="file" id="fotos" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple data-max-total-mb="12">
              <span class="pq-ayuda">Ayudan a llevar el repuesto correcto. Solo las ven el negocio y tú.</span>
            </div>
            <?php if (!empty($servicio['repetir_cada_meses'])): ?>
              <label class="pq-consentimiento">
                <input type="checkbox" name="recordar_repetir" value="1">
                <span>
                  <span class="pq-consentimiento-titulo">Recuérdame el próximo en <?= (int) $servicio['repetir_cada_meses'] ?> <?= (int) $servicio['repetir_cada_meses'] === 1 ? 'mes' : 'meses' ?></span>
                  <span class="pq-ayuda">Un solo mensaje por WhatsApp cuando toque, nada más.</span>
                </span>
              </label>
            <?php endif; ?>
          </fieldset>
        <?php endif; ?>

        <?php if (!$aDomicilio && !empty($servicio['repetir_cada_meses'])): ?>
          <label class="pq-consentimiento">
            <input type="checkbox" name="recordar_repetir" value="1">
            <span>
              <span class="pq-consentimiento-titulo">Recuérdame el próximo en <?= (int) $servicio['repetir_cada_meses'] ?> <?= (int) $servicio['repetir_cada_meses'] === 1 ? 'mes' : 'meses' ?></span>
              <span class="pq-ayuda">Un solo mensaje por WhatsApp cuando toque, nada más. Lo puedes quitar desde el enlace de tu cita.</span>
            </span>
          </label>
        <?php endif; ?>
        <?php if (\App\Models\PlanTratamiento::esSalud($negocio)): ?>
          <?php // Dato de salud = dato sensible: opcional y con su propia autorización, separada de la general. ?>
          <fieldset class="pq-visita-datos pq-motivo-consulta">
            <div class="pq-campo">
              <label class="pq-label" for="motivo_consulta">Motivo de la consulta <span class="pq-ayuda">(opcional)</span></label>
              <textarea class="pq-input" id="motivo_consulta" name="motivo_consulta" rows="2" maxlength="500" placeholder="Ej.: dolor en una muela al tomar frío"></textarea>
              <span class="pq-ayuda">Si prefieres, déjalo en blanco y lo cuentas en la consulta.</span>
            </div>
            <label class="pq-consentimiento">
              <input type="checkbox" name="autorizo_sensibles" value="1">
              <span>
                <span class="pq-consentimiento-titulo">Autorizo el tratamiento de este dato de salud</span>
                <span class="pq-ayuda">Es un dato sensible (Ley 1581): solo lo ve <?= e(nombre_publico_sede($negocio)) ?> para preparar tu cita, no se comparte y no es obligatorio darlo. Solo hace falta si escribiste el motivo.</span>
              </span>
            </label>
          </fieldset>
        <?php endif; ?>

        <details class="pq-cupon-entrada pq-cupon-entrada-reserva"<?= !empty($error) && str_starts_with((string) $error, 'Cupón') ? ' open' : '' ?>>
          <summary>¿Tienes un cupón de descuento?</summary>
          <div class="pq-cupon-form">
            <label class="pq-sr-solo" for="cupon">Código del cupón</label>
            <input class="pq-input" type="text" id="cupon" name="cupon" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="VECI10">
          </div>
          <p class="pq-ayuda pq-cupon-nota">Se descuenta del valor del servicio al confirmar.</p>
        </details>

        <label class="pq-consentimiento pq-consentimiento-requerido">
          <input type="checkbox" name="autorizo_datos" value="1" required>
          <span>
            <span class="pq-consentimiento-titulo">Uso de datos para gestionar tu reserva</span>
            <span class="pq-ayuda">Necesario para procesar la reserva y avisarte sobre cambios.</span>
          </span>
        </label>

        <button type="submit" class="pq-btn pq-btn-whatsapp pq-confirmar-boton">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
          Confirmar por WhatsApp
        </button>
        <p class="pq-ayuda pq-checkout-pie">Se abre WhatsApp con la reserva escrita: la revisas antes de enviarla.</p>
      </form>
    </section>
  <?php endif; ?>
</div>
