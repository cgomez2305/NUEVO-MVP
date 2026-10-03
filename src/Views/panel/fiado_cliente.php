<?php
use App\Models\Fiado;

$esDueno = $negocio['rol'] === 'dueno';
$limite = $cliente['fiado_limite'] !== null ? (int) $cliente['fiado_limite'] : null;
$movimientos = $libreta['movimientos'];
$debeDesde = $pendientes !== [] ? $pendientes[0]['creado_en'] : null;
$telefono = Fiado::telefonoValido((string) $cliente['telefono']);
$iconoWhatsapp = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>';
$conceptoDe = static function (array $mov): string {
    if ($mov['tipo'] === 'abono') {
        return 'Abono · ' . (Fiado::METODOS_ABONO[$mov['metodo']] ?? '') . (!empty($mov['nota']) ? ' · ' . $mov['nota'] : '');
    }
    if ($mov['venta_id'] !== null) {
        return 'Compra #' . (int) $mov['venta_id'];
    }

    return (string) ($mov['nota'] ?? 'Cargo');
};
?>
<a href="<?= e(base_url('/panel/fiado')) ?>" class="pq-volver-panel">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
  Fiado
</a>
<span class="pq-eyebrow pq-eyebrow-tras-volver">Cuenta de fiado</span>
<h1 class="pq-h1 pq-fiado-titulo"><?= e($cliente['nombre']) ?></h1>
<p class="pq-ayuda pq-mono"><?= $telefono !== null ? e(substr($telefono, 0, 3) . ' ' . substr($telefono, 3, 3) . ' ' . substr($telefono, 6)) : 'Sin WhatsApp válido' ?></p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="pq-fiado-detalle">
  <div class="pq-fiado-columna">
    <section class="pq-fiado-resumen" aria-label="Saldo">
      <span class="pq-fiado-resumen-etiqueta"><?= $saldo > 0 ? 'Debe' : ($saldo < 0 ? 'Saldo a favor' : 'A paz y salvo') ?></span>
      <strong class="pq-fiado-resumen-cifra<?= $saldo < 0 ? ' pq-fiado-a-favor' : '' ?>"><?= pesos(abs($saldo)) ?></strong>
      <?php if ($saldo < 0): ?>
        <span class="pq-ayuda">Le debes tú: ya había abonado y se anuló una venta. Su próxima compra fiada lo usa primero, o devuélveselo y anota un cargo a mano.</span>
      <?php endif; ?>
      <span class="pq-ayuda">
        <?= $debeDesde !== null ? 'Desde ' . e(hace_dias(dias_desde((string) $debeDesde))) . ' (' . e(fecha_larga((string) $debeDesde)) . ')' : ($saldo < 0 ? '' : 'No tiene nada pendiente') ?>
        <?= $limite !== null ? ' · límite ' . pesos($limite) : '' ?>
      </span>
      <?php if ($limite !== null && $limite > 0): ?>
        <?php $uso = min(1, max(0, $saldo) / $limite); ?>
        <span class="pq-fiado-limite<?= $uso >= .9 ? ' pq-fiado-limite-lleno' : '' ?>" style="--uso: <?= round($uso, 3) ?>" role="img" aria-label="Usa <?= (int) round($uso * 100) ?>% de su límite"></span>
      <?php endif; ?>
    </section>

    <?php if ($saldo > 0): ?>
      <form method="post" action="<?= e(base_url('/panel/fiado/' . (int) $cliente['id'] . '/abono')) ?>" class="pq-cierre-form pq-fiado-abono">
        <?= csrf_campo() ?>
        <h2 class="pq-seccion-titulo">Anotar un abono</h2>
        <div class="pq-campo">
          <label class="pq-label" for="abono-monto">¿Cuánto abonó?</label>
          <input class="pq-input pq-mono pq-cierre-contado" id="abono-monto" type="text" inputmode="numeric" name="monto" maxlength="12" required data-precio placeholder="<?= e(number_format($saldo, 0, '', '.')) ?>">
          <span class="pq-ayuda">Todo lo que debe: <?= pesos($saldo) ?>.</span>
        </div>
        <fieldset class="pq-mostrador-metodos pq-mostrador-metodos-3">
          <legend class="pq-label">¿Cómo pagó?</legend>
          <?php foreach (Fiado::METODOS_ABONO as $clave => $etiqueta): ?>
            <label class="pq-mostrador-metodo">
              <input type="radio" name="metodo" value="<?= e($clave) ?>"<?= $clave === 'efectivo' ? ' checked' : '' ?>>
              <span><?= e($etiqueta) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <div class="pq-campo">
          <label class="pq-label" for="abono-nota">Nota <span class="pq-ayuda">(opcional)</span></label>
          <input class="pq-input" id="abono-nota" type="text" name="nota" maxlength="160">
        </div>
        <button type="submit" class="pq-btn pq-btn-caja">Anotar abono</button>
        <p class="pq-ayuda">El abono en efectivo suma a la caja de hoy de esta sede.</p>
      </form>

      <?php // El recordatorio: el texto exacto que le va a llegar, como burbuja de WhatsApp. ?>
      <section class="pq-wa-vista pq-fiado-recordatorio" aria-labelledby="pq-titulo-recordatorio">
        <h2 class="pq-seccion-titulo" id="pq-titulo-recordatorio">Recordatorio de pago</h2>
        <div class="pq-wa-fondo">
          <p class="pq-burbuja-out pq-fiado-burbuja"><?= e($mensaje) ?></p>
        </div>
        <?php if (!$esDueno): ?>
          <p class="pq-ayuda">El recordatorio de cobro lo envía el dueño del negocio.</p>
        <?php elseif ($recordar['permitido']): ?>
          <form method="post" action="<?= e(base_url('/panel/fiado/' . (int) $cliente['id'] . '/recordatorio')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-btn pq-btn-whatsapp"><?= $iconoWhatsapp ?> Enviar recordatorio por WhatsApp</button>
          </form>
          <p class="pq-ayuda">Puedes enviar uno por semana a cada cliente, de lunes a viernes de 7 a. m. a 7 p. m. y los sábados de 8 a. m. a 3 p. m. (Ley 2300 de 2023).</p>
        <?php else: ?>
          <button type="button" class="pq-btn pq-btn-whatsapp" disabled aria-describedby="recordatorio-razon"><?= $iconoWhatsapp ?> Enviar recordatorio por WhatsApp</button>
          <p class="pq-fiado-razon" id="recordatorio-razon"><?= e((string) $recordar['razon']) ?></p>
        <?php endif; ?>
        <?php if ($ultimoRecordatorio !== null): ?>
          <p class="pq-ayuda">Último recordatorio: <?= e(fecha_corta($ultimoRecordatorio)) ?>.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <details class="pq-detalle-mas">
      <summary>Cargar a mano</summary>
      <form method="post" action="<?= e(base_url('/panel/fiado/' . (int) $cliente['id'] . '/cargo')) ?>" class="pq-detalle-mas-cuerpo">
        <?= csrf_campo() ?>
        <p class="pq-ayuda">Para pasar lo que estaba en el cuaderno de papel. No mueve la caja: lo que se fía en el mostrador se carga solo.</p>
        <div class="pq-campo">
          <label class="pq-label" for="cargo-monto">Monto</label>
          <input class="pq-input pq-mono" id="cargo-monto" type="text" inputmode="numeric" name="monto" maxlength="12" required data-precio>
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="cargo-nota">¿Qué es?</label>
          <input class="pq-input" id="cargo-nota" type="text" name="nota" maxlength="160" required placeholder="Ej.: lo del cuaderno de septiembre">
        </div>
        <button type="submit" class="pq-btn pq-btn-ghost">Cargar a la cuenta</button>
      </form>
    </details>

    <?php if ($esDueno): ?>
      <details class="pq-detalle-mas">
        <summary>Límite de fiado<?= $limite !== null ? ' · ' . pesos($limite) : ' · sin límite' ?></summary>
        <form method="post" action="<?= e(base_url('/panel/fiado/' . (int) $cliente['id'] . '/limite')) ?>" class="pq-detalle-mas-cuerpo">
          <?= csrf_campo() ?>
          <div class="pq-campo">
            <label class="pq-label" for="fiado-limite">Hasta cuánto le fías</label>
            <input class="pq-input pq-mono" id="fiado-limite" type="text" inputmode="numeric" name="limite" maxlength="12" data-precio placeholder="Sin límite" value="<?= $limite !== null ? e(number_format($limite, 0, '', '.')) : '' ?>">
            <span class="pq-ayuda">Vacío = sin límite. El mostrador avisa y no deja fiarle por encima.</span>
          </div>
          <button type="submit" class="pq-btn pq-btn-ghost">Guardar límite</button>
        </form>
      </details>
    <?php endif; ?>
  </div>

  <?php // El cuaderno del fiado: renglones, margen rojo y el saldo que va quedando. ?>
  <section class="pq-libreta" aria-labelledby="pq-titulo-libreta">
    <h2 class="pq-libreta-titulo" id="pq-titulo-libreta">Cuaderno</h2>
    <?php if ($movimientos === []): ?>
      <p class="pq-libreta-vacia">Todavía no hay nada anotado.</p>
    <?php else: ?>
      <ol class="pq-libreta-renglones">
        <?php if ($libreta['saldo_anterior'] !== 0): ?>
          <li class="pq-libreta-renglon pq-libreta-anterior">
            <span class="pq-libreta-fecha">—</span>
            <span class="pq-libreta-concepto">Viene de antes</span>
            <span class="pq-libreta-monto"></span>
            <span class="pq-libreta-saldo pq-mono"><?= pesos($libreta['saldo_anterior']) ?></span>
          </li>
        <?php endif; ?>
        <?php foreach ($movimientos as $mov): ?>
          <?php $anulado = (int) $mov['anulado'] === 1; ?>
          <li class="pq-libreta-renglon<?= $mov['tipo'] === 'abono' ? ' pq-libreta-abono' : '' ?><?= $anulado ? ' pq-libreta-anulado' : '' ?>">
            <span class="pq-libreta-fecha"><?= e(Fiado::diaCorto((string) $mov['creado_en'])) ?></span>
            <span class="pq-libreta-concepto">
              <?php if ($mov['venta_id'] !== null): ?>
                <a class="pq-libreta-texto" href="<?= e(base_url('/panel/mostrador/ventas/' . (int) $mov['venta_id'])) ?>"><?= e($conceptoDe($mov)) ?></a>
              <?php else: ?>
                <span class="pq-libreta-texto"><?= e($conceptoDe($mov)) ?></span>
              <?php endif; ?>
              <?= $anulado ? '<span class="pq-libreta-nota">anulado, no cuenta</span>' : '' ?>
              <?php if ($esDueno && Fiado::anulable($mov)): ?>
                <form method="post" action="<?= e(base_url('/panel/fiado/' . (int) $cliente['id'] . '/movimientos/' . (int) $mov['id'] . '/anular')) ?>" class="pq-libreta-anular"
                      data-confirmar="¿Anular este <?= $mov['tipo'] === 'abono' ? 'abono' : 'cargo' ?> de <?= e(pesos((int) $mov['monto'])) ?>? Queda tachado en el cuaderno.">
                  <?= csrf_campo() ?>
                  <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Anular</button>
                </form>
              <?php endif; ?>
            </span>
            <span class="pq-libreta-monto pq-mono"><?= $mov['tipo'] === 'abono' ? '−' : '+' ?><?= pesos((int) $mov['monto']) ?></span>
            <span class="pq-libreta-saldo pq-mono"><?= (int) $mov['saldo_despues'] < 0 ? 'a favor ' . pesos(-(int) $mov['saldo_despues']) : pesos((int) $mov['saldo_despues']) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
