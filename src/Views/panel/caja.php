<?php
use App\Models\CierreCaja;

$cerrada = $cierre !== null;
// Tiendas (fase 4): el efectivo del mostrador y de los abonos de fiado
// también está en el cajón (ver CierreCaja::efectivoDeTienda).
$efectivoVendido = (int) ($resumen['por_metodo']['efectivo']['total'] ?? 0) + CierreCaja::efectivoDeTienda($resumen);
$mostradorZ = $resumen['mostrador'] ?? null;
$abonosZ = array_filter($resumen['abonos_fiado'] ?? [], fn ($a) => (int) $a['total'] > 0);
$hayMostrador = $mostradorZ !== null && ((int) $mostradorZ['ventas'] > 0 || (int) $mostradorZ['anuladas'] > 0);
$esReservas = ($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas';
// Citas: se sugiere lo que falta por cobrar (precio − anticipos ya pagados).
$servicioSugerido = $cerrada ? (int) ($resumen['efectivo_servicios'] ?? 0) : max(0, (int) $resumen['total_citas'] - (int) $resumen['anticipos']);
$anterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$siguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
$formatear = static fn (int $valor): string => $valor > 0 ? number_format($valor, 0, ',', '.') : '';
$textoDiferencia = static fn (int $d): string => $d === 0 ? 'Cuadra' : ($d > 0 ? 'Sobran ' . pesos($d) : 'Faltan ' . pesos(-$d));
$tonoDiferencia = static fn (int $d): string => $d === 0 ? 'pq-chip-caja' : ($d > 0 ? 'pq-chip-pendiente' : 'pq-chip-cancelado');
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Operación · <?= e($negocio['nombre']) ?></span>
    <h1 class="pq-h1">Cierre de caja</h1>
  </div>
</div>

<?php // El día se recorre como en un talonario: ayer ‹ hoy › (mañana no existe todavía). ?>
<nav class="pq-cierre-dias" aria-label="Elegir día">
  <a class="pq-cierre-dias-flecha" href="<?= e(base_url('/panel/caja?fecha=' . $anterior)) ?>" aria-label="Día anterior">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  </a>
  <span class="pq-cierre-dias-hoy"><?= $esHoy ? 'Hoy, ' : '' ?><?= e(fecha_larga($fecha)) ?></span>
  <?php if (!$esHoy): ?>
    <a class="pq-cierre-dias-flecha" href="<?= e(base_url('/panel/caja?fecha=' . $siguiente)) ?>" aria-label="Día siguiente">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
    </a>
  <?php else: ?>
    <span class="pq-cierre-dias-flecha pq-cierre-dias-flecha-off" aria-hidden="true"></span>
  <?php endif; ?>
</nav>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="pq-cierre">
  <?php
  // El tiquete Z de la registradora: lo vendido por forma de pago, en el
  // mismo papel de la comanda. Se imprime en rollo de 80 mm igual que ella.
  ?>
  <div class="pq-comanda pq-comanda-final pq-comanda-imprimible pq-cierre-z">
    <article class="pq-comanda-hoja" aria-label="Cierre de caja del <?= e(fecha_larga($fecha)) ?>">
      <span class="pq-sello<?= $cerrada ? ' pq-sello-ok' : '' ?>" aria-hidden="true"><?= $cerrada ? 'Cerrada' : ($esHoy ? 'Día abierto' : 'Sin cerrar') ?></span>
      <p class="pq-comanda-negocio"><?= e(nombre_publico_sede($negocio)) ?></p>
      <p class="pq-comanda-cabeza">
        <span>Cierre Z</span>
        <span><?= e(date('d/m/Y', strtotime($fecha))) ?></span>
      </p>
      <?php foreach ($esReservas && (int) $resumen['pedidos'] === 0 ? [] : CierreCaja::METODOS as $clave => $etiqueta): ?>
        <?php $linea = $resumen['por_metodo'][$clave] ?? ['pedidos' => 0, 'total' => 0]; ?>
        <?php $deMostrador = $mostradorZ['por_metodo'][$clave] ?? ['ventas' => 0, 'total' => 0]; ?>
        <div class="pq-comanda-linea">
          <div class="pq-comanda-fila">
            <span class="pq-comanda-nombre"><?= e($etiqueta) ?> <span class="pq-cierre-z-cuenta"><?= (int) $linea['pedidos'] ?> ped.<?= $hayMostrador ? ' · ' . (int) $deMostrador['ventas'] . ' mostr.' : '' ?></span></span>
            <span class="pq-plato-guia" aria-hidden="true"></span>
            <span class="pq-comanda-subtotal"><?= pesos((int) $linea['total'] + (int) $deMostrador['total']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if ((int) $resumen['citas'] > 0): ?>
        <div class="pq-comanda-linea">
          <div class="pq-comanda-fila">
            <span class="pq-comanda-nombre">Servicios <span class="pq-cierre-z-cuenta"><?= (int) $resumen['citas'] ?> cita<?= (int) $resumen['citas'] === 1 ? '' : 's' ?></span></span>
            <span class="pq-plato-guia" aria-hidden="true"></span>
            <span class="pq-comanda-subtotal"><?= pesos((int) $resumen['total_citas']) ?></span>
          </div>
        </div>
      <?php endif; ?>
      <?php if ((int) ($resumen['bonos'] ?? 0) > 0): ?>
        <div class="pq-comanda-linea">
          <div class="pq-comanda-fila">
            <span class="pq-comanda-nombre">Bonos de sesiones <span class="pq-cierre-z-cuenta"><?= (int) $resumen['bonos'] ?> vendido<?= (int) $resumen['bonos'] === 1 ? '' : 's' ?></span></span>
            <span class="pq-plato-guia" aria-hidden="true"></span>
            <span class="pq-comanda-subtotal"><?= pesos((int) $resumen['total_bonos']) ?></span>
          </div>
        </div>
      <?php endif; ?>
      <?php if ((int) $resumen['descuentos'] > 0): ?>
        <div class="pq-comanda-ajuste"><span>Descuentos (ya restados)</span><span class="pq-plato-guia" aria-hidden="true"></span><span>−<?= pesos((int) $resumen['descuentos']) ?></span></div>
      <?php endif; ?>
      <?php if ((int) $resumen['domicilios'] > 0): ?>
        <div class="pq-comanda-ajuste"><span>Domicilios (incluidos)</span><span class="pq-plato-guia" aria-hidden="true"></span><span><?= pesos((int) $resumen['domicilios']) ?></span></div>
      <?php endif; ?>
      <?php foreach (array_filter($resumen['abonos_planes'] ?? [], fn ($a) => (int) $a['total'] > 0) as $metodoAbono => $abono): ?>
        <div class="pq-comanda-ajuste"><span>Abonos a planes · <?= e(\App\Models\PlanTratamiento::METODOS_ABONO[$metodoAbono] ?? $metodoAbono) ?> <span class="pq-cierre-z-cuenta"><?= (int) $abono['abonos'] === 1 ? '1 abono' : (int) $abono['abonos'] . ' abonos' ?></span></span><span class="pq-plato-guia" aria-hidden="true"></span><span><?= pesos((int) $abono['total']) ?></span></div>
      <?php endforeach; ?>
      <div class="pq-comanda-total">
        <span>Vendido</span>
        <span><?= pesos((int) $resumen['ventas_total']) ?></span>
      </div>
      <?php if ((int) ($resumen['fiado_hoy'] ?? 0) > 0 || $abonosZ !== []): ?>
        <?php // Fiado: se vendió pero no entró plata, por eso va debajo del total y no suma. ?>
        <div class="pq-cierre-fiado">
          <?php if ((int) ($resumen['fiado_hoy'] ?? 0) > 0): ?>
            <div class="pq-comanda-ajuste"><span>Fiado hoy <span class="pq-cierre-z-cuenta"><?= (int) $mostradorZ['por_metodo']['fiado']['ventas'] ?> vent. · no entró plata</span></span><span class="pq-plato-guia" aria-hidden="true"></span><span><?= pesos((int) $resumen['fiado_hoy']) ?></span></div>
          <?php endif; ?>
          <?php foreach ($abonosZ as $metodoAbono => $abono): ?>
            <div class="pq-comanda-ajuste"><span>Abonos de fiado · <?= e(\App\Models\Fiado::METODOS_ABONO[$metodoAbono] ?? $metodoAbono) ?> <span class="pq-cierre-z-cuenta"><?= (int) $abono['abonos'] === 1 ? '1 abono' : (int) $abono['abonos'] . ' abonos' ?></span></span><span class="pq-plato-guia" aria-hidden="true"></span><span><?= pesos((int) $abono['total']) ?></span></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <dl class="pq-comanda-datos">
        <?php if ($cerrada): ?>
          <div><dt>Base</dt><dd><?= pesos((int) $cierre['base']) ?></dd></div>
          <?php if ((int) ($resumen['efectivo_servicios'] ?? 0) > 0): ?>
            <div><dt>Efectivo servicios</dt><dd><?= pesos((int) $resumen['efectivo_servicios']) ?></dd></div>
          <?php endif; ?>
          <div><dt>Efectivo esperado</dt><dd><?= pesos((int) $cierre['efectivo_esperado']) ?></dd></div>
          <div><dt>Contado</dt><dd><?= pesos((int) $cierre['efectivo_contado']) ?></dd></div>
          <div><dt>Diferencia</dt><dd><strong><?= e($textoDiferencia((int) $cierre['diferencia'])) ?></strong></dd></div>
          <div><dt>Cerró</dt><dd><?= e((string) ($cierre['usuario_nombre'] ?? '—')) ?> · <?= e(date('g:i a', strtotime((string) $cierre['creado_en']))) ?></dd></div>
          <?php if (!empty($cierre['notas'])): ?>
            <div class="pq-comanda-dato-nota"><dt>Nota</dt><dd>"<?= e($cierre['notas']) ?>"</dd></div>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ((int) $resumen['cancelados'] > 0): ?>
          <div><dt>Cancelados</dt><dd><?= (int) $resumen['cancelados'] ?> (no suman)</dd></div>
        <?php endif; ?>
        <?php if ((int) ($mostradorZ['anuladas'] ?? 0) > 0): ?>
          <div><dt>Anuladas</dt><dd><?= (int) $mostradorZ['anuladas'] ?> de mostrador (no suman)</dd></div>
        <?php endif; ?>
      </dl>
    </article>
  </div>

  <div class="pq-cierre-lado">
    <?php if ((int) $resumen['sin_entregar'] > 0): ?>
      <p class="pq-cierre-aviso">
        <?= (int) $resumen['sin_entregar'] === 1 ? 'Un pedido de este día sigue' : (int) $resumen['sin_entregar'] . ' pedidos de este día siguen' ?> sin marcar como entregado: ya suman en lo vendido.
        <a href="<?= e(base_url('/panel/pedidos')) ?>">Ver pedidos</a>
      </p>
    <?php endif; ?>
    <?php if (!$cerrada && (int) ($resumen['citas_por_venir'] ?? 0) > 0): ?>
      <p class="pq-ayuda">
        <?= (int) $resumen['citas_por_venir'] === 1 ? 'Una cita de este día todavía no llega' : (int) $resumen['citas_por_venir'] . ' citas de este día todavía no llegan' ?>: no suman hasta su hora o hasta que las marques como atendidas.
      </p>
    <?php endif; ?>
    <?php if ((int) ($resumen['bonos'] ?? 0) > 0): ?>
      <p class="pq-ayuda">Los bonos no dicen cómo te los pagaron: si fue en efectivo, súmalo a lo que debería haber en el cajón.</p>
    <?php endif; ?>
    <?php if ((int) $resumen['anticipos'] > 0): ?>
      <p class="pq-ayuda">Anticipos ya pagados de las citas del día: <?= pesos((int) $resumen['anticipos']) ?> (incluidos en servicios).</p>
    <?php endif; ?>

    <?php if ($cerrada): ?>
      <div class="pq-cierre-cerrada">
        <span class="pq-chip <?= $tonoDiferencia((int) $cierre['diferencia']) ?>"><?= e($textoDiferencia((int) $cierre['diferencia'])) ?></span>
        <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-imprimir>Imprimir cierre</button>
      </div>
    <?php endif; ?>

    <details class="pq-cierre-form-envoltura"<?= $cerrada ? '' : ' open' ?>>
      <summary class="pq-btn pq-btn-ghost"><?= $cerrada ? 'Volver a cerrar este día' : 'Cerrar caja' ?></summary>
      <form method="post" action="<?= e(base_url('/panel/caja')) ?>" class="pq-cierre-form" data-caja-form data-efectivo-vendido="<?= $efectivoVendido ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
        <div class="pq-campo">
          <label class="pq-label" for="caja-base">Base con la que abriste</label>
          <input class="pq-input pq-mono" id="caja-base" type="text" inputmode="numeric" name="base" value="<?= $formatear((int) $baseSugerida) ?>" placeholder="0" data-precio data-caja-base>
          <span class="pq-ayuda">El sencillo para dar vueltas. Se recuerda para el próximo cierre.</span>
        </div>
        <?php if ((int) $resumen['total_citas'] > 0): ?>
          <div class="pq-campo">
            <label class="pq-label" for="caja-servicios">De los servicios, ¿cuánto te pagaron en efectivo?</label>
            <input class="pq-input pq-mono" id="caja-servicios" type="text" inputmode="numeric" name="efectivo_servicios" value="<?= $formatear($servicioSugerido) ?>" placeholder="0" data-precio data-caja-servicios>
            <span class="pq-ayuda">Vendiste <?= pesos((int) $resumen['total_citas']) ?> en servicios<?= (int) $resumen['anticipos'] > 0 ? ', ' . pesos((int) $resumen['anticipos']) . ' ya llegaron como anticipo' : '' ?>. Lo que entró por transferencia no va aquí.</span>
          </div>
        <?php endif; ?>
        <div class="pq-campo">
          <label class="pq-label" for="caja-contado">Efectivo que hay en la caja</label>
          <input class="pq-input pq-mono pq-cierre-contado" id="caja-contado" type="text" inputmode="numeric" name="efectivo_contado" value="<?= $cerrada ? $formatear((int) $cierre['efectivo_contado']) : '' ?>" placeholder="Cuenta billetes y monedas" required data-precio data-caja-contado>
        </div>
        <p class="pq-cierre-cuadre" data-caja-cuadre aria-live="polite">
          Debería haber <strong class="pq-mono" data-caja-esperado><?= pesos((int) $baseSugerida + $efectivoVendido + ((int) $resumen['total_citas'] > 0 ? $servicioSugerido : 0)) ?></strong>
          <span class="pq-ayuda">(base + efectivo de <?= (int) $resumen['total_citas'] > 0 && (int) $resumen['pedidos'] > 0 ? 'pedidos y servicios' : ((int) $resumen['total_citas'] > 0 ? 'servicios' : 'pedidos') ?><?= $hayMostrador || $abonosZ !== [] ? ', mostrador y abonos de fiado' : '' ?>)</span>
          <span class="pq-chip" data-caja-resultado hidden></span>
        </p>
        <div class="pq-campo">
          <label class="pq-label" for="caja-notas">Nota <span class="pq-ayuda">(opcional)</span></label>
          <input class="pq-input" id="caja-notas" type="text" name="notas" maxlength="255" value="<?= e((string) ($cierre['notas'] ?? '')) ?>" placeholder="Ej.: se pagaron $20.000 de hielo de la caja">
        </div>
        <button type="submit" class="pq-btn pq-btn-sello"><?= $cerrada ? 'Guardar el nuevo cierre' : 'Cerrar caja del día' ?></button>
      </form>
    </details>
  </div>
</div>

<?php if ($historial !== []): ?>
  <section class="pq-admin-seccion" aria-labelledby="pq-titulo-cierres">
    <h2 class="pq-seccion-titulo" id="pq-titulo-cierres">Cierres anteriores</h2>
    <ul class="pq-admin-tarjeta pq-admin-filas">
      <?php foreach ($historial as $fila): ?>
        <li class="pq-admin-fila">
          <a class="pq-admin-fila-texto pq-cierre-historial-enlace" href="<?= e(base_url('/panel/caja?fecha=' . $fila['fecha'])) ?>">
            <strong><?= e(ucfirst(fecha_larga((string) $fila['fecha']))) ?></strong>
            <span class="pq-ayuda pq-mono">Vendido <?= pesos((int) $fila['ventas_total']) ?></span>
          </a>
          <span class="pq-chip <?= $tonoDiferencia((int) $fila['diferencia']) ?>"><?= e($textoDiferencia((int) $fila['diferencia'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
