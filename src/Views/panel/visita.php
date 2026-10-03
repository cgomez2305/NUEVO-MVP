<?php
use App\Models\Cita;
use App\Models\Cotizacion;
use App\Models\Visita;

$id = (int) $cita['id'];
$base = '/panel/visitas/' . $id;
$abierta = in_array($cita['estado'], ['pendiente', 'confirmada', 'en_curso'], true);
$fotosCliente = array_values(array_filter($fotos, fn ($f) => $f['momento'] === 'cliente'));
$fotosAntes = array_values(array_filter($fotos, fn ($f) => $f['momento'] === 'antes'));
$fotosDespues = array_values(array_filter($fotos, fn ($f) => $f['momento'] === 'despues'));
$siguientePaso = Cita::siguientePaso($cita);
$telefono = preg_replace('/\D+/', '', (string) $cita['cliente_telefono']);
$anticipoMateriales = $cotizacion !== null && $cotizacion['estado'] === 'aprobada' ? (int) $cotizacion['anticipo'] : 0;
// Primera línea de una cotización nueva: el servicio a su precio, y el transporte de la zona si lo hay.
$filasIniciales = [['tipo' => 'mano_obra', 'descripcion' => (string) $cita['nombre_servicio'], 'cantidad' => 1, 'valor' => (int) $cita['precio'] - (int) $cita['recargo_zona']]];
if ((int) $cita['recargo_zona'] > 0) {
    $filasIniciales[] = ['tipo' => 'otro', 'descripcion' => 'Transporte · ' . $cita['zona_nombre'], 'cantidad' => 1, 'valor' => (int) $cita['recargo_zona']];
}
$filasCotizacion = 6;
$renderFotos = static function (array $lista, bool $sePuedeBorrar) use ($base): void {
    if ($lista === []) {
        return;
    }
    echo '<ul class="pq-ficha-trabajos">';
    foreach ($lista as $foto) {
        $url = e(base_url($base . '/fotos/' . (int) $foto['id']));
        echo '<li><a href="' . $url . '" target="_blank" rel="noopener"><img src="' . $url . '" alt="" width="120" height="120" loading="lazy"></a>';
        if ($sePuedeBorrar) {
            echo '<form method="post" action="' . e(base_url($base . '/fotos/' . (int) $foto['id'] . '/eliminar')) . '" data-confirmar="¿Quitar esta foto?">'
                . csrf_campo() . '<button type="submit" class="pq-ficha-trabajo-quitar" aria-label="Quitar esta foto">×</button></form>';
        }
        echo '</li>';
    }
    echo '</ul>';
};
?>
<div class="pq-pagina-cabeza">
  <div>
    <a class="pq-volver-panel" href="<?= e(base_url('/panel/citas')) ?>">← Agenda</a>
    <h1 class="pq-h1"><?= e($cita['cliente_nombre']) ?></h1>
  </div>
  <span class="pq-chip <?= e(chip_estado($cita['estado'])) ?>"><?= e(Cita::ETIQUETAS[$cita['estado']] ?? ucfirst((string) $cita['estado'])) ?></span>
</div>
<p class="pq-lead pq-pagina-bajada-panel">
  <?= e($cita['nombre_servicio']) ?> · <?= e(fecha_larga(date('Y-m-d', strtotime((string) $cita['fecha_hora'])))) ?>, <?= e(Visita::textoFranja($cita)) ?>
  <?= !empty($cita['empleado_nombre']) ? ' · va ' . e($cita['empleado_nombre']) : '' ?>
</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if (!empty($whatsapp)): ?>
  <?php // El mensaje queda listo pero lo manda el técnico: así ve exactamente qué le llega al cliente. ?>
  <div class="pq-visita-wa">
    <span><strong>Mensaje listo.</strong> Ábrelo en WhatsApp y dale Enviar.</span>
    <a class="pq-btn pq-btn-sello pq-btn-chico" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">Abrir WhatsApp</a>
  </div>
<?php endif; ?>

<div class="pq-ficha-panel pq-visita-hoja">
  <?php // La dirección primero: es lo que el técnico mira con el carro andando. ?>
  <section class="pq-ficha-bloque pq-visita-donde" aria-labelledby="pq-donde-titulo">
    <h2 class="pq-seccion-titulo" id="pq-donde-titulo">Dónde</h2>
    <p class="pq-visita-direccion"><?= e((string) $cita['direccion']) ?></p>
    <p class="pq-ayuda">
      <?= !empty($cita['zona_nombre']) ? e($cita['zona_nombre']) : '' ?>
      <?= !empty($cita['direccion_referencia']) ? (!empty($cita['zona_nombre']) ? ' · ' : '') . e($cita['direccion_referencia']) : '' ?>
    </p>
    <div class="pq-visita-acciones">
      <a class="pq-btn pq-btn-ghost pq-btn-chico" href="<?= e(Visita::urlMapa($cita, $negocio)) ?>" target="_blank" rel="noopener">Abrir en el mapa</a>
      <a class="pq-btn pq-btn-ghost pq-btn-chico" href="<?= e('https://wa.me/57' . $telefono) ?>" target="_blank" rel="noopener">Escribirle</a>
      <a class="pq-btn pq-btn-ghost pq-btn-chico" href="<?= e('tel:+57' . $telefono) ?>">Llamar</a>
    </div>
  </section>

  <section class="pq-ficha-bloque" aria-labelledby="pq-pasa-titulo">
    <h2 class="pq-seccion-titulo" id="pq-pasa-titulo">Qué pasa</h2>
    <p class="pq-visita-problema"><?= nl2br(e((string) $cita['problema'])) ?></p>
    <?php if ($fotosCliente !== []): ?>
      <p class="pq-ayuda">Fotos que mandó <?= e(explode(' ', trim((string) $cita['cliente_nombre']))[0]) ?>:</p>
      <?php $renderFotos($fotosCliente, false); ?>
    <?php endif; ?>
  </section>

  <?php if ($abierta): ?>
    <section class="pq-ficha-bloque" aria-labelledby="pq-paso-titulo">
      <h2 class="pq-seccion-titulo" id="pq-paso-titulo">Ahora</h2>
      <?php if (in_array($cita['estado'], ['pendiente', 'confirmada'], true)): ?>
        <form method="post" action="<?= e(base_url($base . '/en-camino')) ?>" class="pq-visita-camino">
          <?= csrf_campo() ?>
          <label class="pq-label" for="pq-minutos">Voy en camino, llego en</label>
          <div class="pq-visita-camino-fila">
            <select class="pq-select" id="pq-minutos" name="minutos">
              <?php foreach (Visita::MINUTOS_EN_CAMINO as $minutos): ?>
                <option value="<?= $minutos ?>" <?= $minutos === 30 ? 'selected' : '' ?>><?= $minutos ?> min</option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Avisarle</button>
          </div>
          <?php if (!empty($cita['en_camino_en'])): ?>
            <span class="pq-ayuda">Saliste a las <?= e(hora_completa(date('H:i', strtotime((string) $cita['en_camino_en'])))) ?>; le dijiste que llegabas hacia las <?= e(hora_completa(date('H:i', strtotime((string) $cita['llegada_estimada'])))) ?>.</span>
          <?php endif; ?>
        </form>
      <?php endif; ?>
      <?php if ($siguientePaso !== null && $siguientePaso['estado'] === 'completada'): ?>
        <form method="post" action="<?= e(base_url('/panel/citas/' . $id . '/terminar')) ?>" class="pq-terminar-form">
          <?= csrf_campo() ?>
          <input type="hidden" name="volver" value="<?= e($base) ?>">
          <label class="pq-terminar-cobrado">
            <span class="pq-ayuda">Cobrado en total</span>
            <span class="pq-campo-dinero"><input class="pq-input pq-mono" type="text" inputmode="numeric" name="cobrado" value="<?= number_format(Cita::valor($cita), 0, ',', '.') ?>" data-precio-cop required></span>
          </label>
          <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico"><?= e($siguientePaso['texto']) ?> →</button>
          <?php if ($anticipoMateriales > 0 && (int) $cotizacion['anticipo_pagado'] === 1): ?>
            <span class="pq-ayuda">Incluye el anticipo de materiales que ya te pagó (<?= pesos($anticipoMateriales) ?>).</span>
          <?php endif; ?>
        </form>
      <?php elseif ($siguientePaso !== null): ?>
        <form method="post" action="<?= e(base_url('/panel/citas/' . $id . '/estado')) ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="volver" value="<?= e($base) ?>">
          <input type="hidden" name="estado" value="<?= e($siguientePaso['estado']) ?>">
          <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico"><?= e(match ($siguientePaso['estado']) { 'en_curso' => 'Llegué, empezar', 'confirmada' => 'Confirmar visita', default => $siguientePaso['texto'] }) ?> →</button>
        </form>
      <?php endif; ?>
      <p class="pq-ayuda">Si el trabajo queda para otro día, no la termines: muévela desde la agenda y la cotización sigue valiendo.</p>
    </section>
  <?php endif; ?>

  <section class="pq-ficha-bloque" id="cotizacion" aria-labelledby="pq-cot-titulo">
    <h2 class="pq-seccion-titulo" id="pq-cot-titulo">Cotización</h2>
    <?php if ($cotizacion !== null): ?>
      <?php $estadoCot = Cotizacion::vencida($cotizacion) ? 'vencida' : $cotizacion['estado']; ?>
      <div class="pq-cotizacion-resumen">
        <span>
          <strong class="pq-mono"><?= pesos((int) $cotizacion['total']) ?></strong>
          <span class="pq-chip <?= $estadoCot === 'aprobada' ? 'pq-chip-caja' : ($estadoCot === 'enviada' ? 'pq-chip-pendiente' : 'pq-chip-cancelado') ?>"><?= e(['enviada' => 'Esperando respuesta', 'aprobada' => 'Aprobada', 'rechazada' => 'No aprobada', 'vencida' => 'Vencida'][$estadoCot] ?? $estadoCot) ?></span>
        </span>
        <span class="pq-ayuda">
          <?= count($itemsCotizacion) ?> ítem<?= count($itemsCotizacion) === 1 ? '' : 's' ?>
          <?= (int) $cotizacion['garantia_dias'] > 0 ? ' · garantía ' . (int) $cotizacion['garantia_dias'] . ' días' : '' ?>
          <?= (int) $cotizacion['anticipo'] > 0 ? ' · anticipo ' . pesos((int) $cotizacion['anticipo']) . ((int) $cotizacion['anticipo_pagado'] === 1 ? ' (recibido)' : '') : '' ?>
        </span>
        <a href="<?= e(url_publica('/cotizacion/' . $cotizacion['token'])) ?>" target="_blank" rel="noopener">Verla como el cliente</a>
      </div>
      <?php if ($cotizacion['estado'] === 'aprobada' && (int) $cotizacion['anticipo'] > 0 && (int) $cotizacion['anticipo_pagado'] === 0): ?>
        <form method="post" action="<?= e(base_url($base . '/anticipo')) ?>">
          <?= csrf_campo() ?>
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Ya me pagó el anticipo de materiales</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (Cotizacion::sePuedeCotizar($cita)): ?>
      <details class="pq-agregar-panel pq-cotizar"<?= $cotizacion === null ? ' open' : '' ?>>
        <summary><?= $cotizacion === null ? 'Armar cotización' : 'Hacer una nueva (reemplaza la anterior si no la han respondido)' ?></summary>
        <form method="post" action="<?= e(base_url($base . '/cotizar')) ?>" class="pq-cotizar-form">
          <?= csrf_campo() ?>
          <?php // Renglones fijos (sin JS): los vacíos no se guardan. ?>
          <div class="pq-cotizar-filas">
            <?php for ($i = 0; $i < $filasCotizacion; $i++): ?>
              <?php $fila = $filasIniciales[$i] ?? ['tipo' => $i === count($filasIniciales) ? 'material' : 'otro', 'descripcion' => '', 'cantidad' => 1, 'valor' => '']; ?>
              <div class="pq-cotizar-fila">
                <select class="pq-select" name="items[<?= $i ?>][tipo]" aria-label="Tipo del ítem <?= $i + 1 ?>">
                  <?php foreach (Cotizacion::TIPOS as $clave => $etiqueta): ?>
                    <option value="<?= $clave ?>" <?= $fila['tipo'] === $clave ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                  <?php endforeach; ?>
                </select>
                <input class="pq-input" type="text" name="items[<?= $i ?>][descripcion]" value="<?= e((string) $fila['descripcion']) ?>" maxlength="160" placeholder="<?= $i === 0 ? 'Mano de obra' : 'Ej.: Capacitor 35 µF' ?>" aria-label="Descripción del ítem <?= $i + 1 ?>">
                <input class="pq-input pq-mono pq-cotizar-cantidad" type="number" name="items[<?= $i ?>][cantidad]" value="<?= (int) $fila['cantidad'] ?>" min="1" max="999" aria-label="Cantidad del ítem <?= $i + 1 ?>">
                <div class="pq-campo-dinero"><input class="pq-input pq-mono" type="text" inputmode="numeric" name="items[<?= $i ?>][valor]" value="<?= $fila['valor'] !== '' ? number_format((int) $fila['valor'], 0, ',', '.') : '' ?>" placeholder="Valor c/u" data-precio-cop aria-label="Valor unitario del ítem <?= $i + 1 ?>"></div>
              </div>
            <?php endfor; ?>
          </div>
          <div class="pq-servicio-panel-par">
            <div class="pq-campo">
              <label class="pq-label" for="cot-garantia">Garantía del trabajo</label>
              <select class="pq-select" id="cot-garantia" name="garantia_dias">
                <?php foreach (Cotizacion::GARANTIAS as $dias): ?>
                  <option value="<?= $dias ?>" <?= $dias === 90 ? 'selected' : '' ?>><?= $dias === 0 ? 'Sin garantía' : $dias . ' días' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="cot-validez">Válida por</label>
              <select class="pq-select" id="cot-validez" name="validez_dias">
                <?php foreach (Cotizacion::VALIDEZ as $dias): ?>
                  <option value="<?= $dias ?>" <?= $dias === 8 ? 'selected' : '' ?>><?= $dias ?> días</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="cot-anticipo">Anticipo para materiales <span class="pq-ayuda">(opcional)</span></label>
            <div class="pq-campo-dinero"><input class="pq-input pq-mono" id="cot-anticipo" type="text" inputmode="numeric" name="anticipo" placeholder="0" data-precio-cop></div>
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="cot-nota">Nota para el cliente <span class="pq-ayuda">(opcional)</span></label>
            <textarea class="pq-input" id="cot-nota" name="nota" rows="2" maxlength="500" placeholder="Ej.: el repuesto llega en 2 días; la garantía no cubre golpes."></textarea>
          </div>
          <button type="submit" class="pq-btn pq-btn-sello">Guardar y mandar</button>
        </form>
      </details>
    <?php elseif ($cotizacion === null): ?>
      <p class="pq-ayuda">Esta visita ya se cerró: no tiene cotización.</p>
    <?php endif; ?>
  </section>

  <section class="pq-ficha-bloque" id="evidencia" aria-labelledby="pq-evidencia-titulo">
    <h2 class="pq-seccion-titulo" id="pq-evidencia-titulo">Antes y después</h2>
    <p class="pq-ayuda">Fotos del trabajo: le sirven al cliente para ver qué se hizo y a ti si hay un reclamo por garantía. Las ve solo el cliente, con su enlace.</p>
    <?php foreach (['antes' => ['Antes', $fotosAntes], 'despues' => ['Después', $fotosDespues]] as $momento => [$etiqueta, $lista]): ?>
      <div class="pq-visita-momento">
        <p class="pq-evidencia-momento"><?= $etiqueta ?> <span class="pq-seccion-cuenta"><?= count($lista) ?>/<?= Visita::MAX_FOTOS_EVIDENCIA ?></span></p>
        <?php $renderFotos($lista, true); ?>
        <?php if (count($lista) < Visita::MAX_FOTOS_EVIDENCIA): ?>
          <form method="post" action="<?= e(base_url($base . '/fotos')) ?>" enctype="multipart/form-data" class="pq-ficha-subir">
            <?= csrf_campo() ?>
            <input type="hidden" name="momento" value="<?= $momento ?>">
            <input class="pq-input" type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required aria-label="Fotos de <?= mb_strtolower($etiqueta) ?>">
            <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Subir</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
