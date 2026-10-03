<?php
use App\Models\Cupon;

$etiquetaEstado = ['activo' => 'Activo', 'pausado' => 'Pausado', 'vencido' => 'Vencido', 'agotado' => 'Agotado'];
$chipEstado = ['activo' => 'pq-chip-caja', 'pausado' => 'pq-chip-pendiente', 'vencido' => 'pq-chip-cancelado', 'agotado' => 'pq-chip-cancelado'];
$urlTienda = (int) ($negocio['publicada'] ?? 0) === 1 ? url_publica('/t/' . $negocio['slug']) : null;
$nombreNegocio = (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']);

/** Las condiciones en una línea que el dueño entiende de un vistazo. */
$condiciones = static function (array $cupon): string {
    $partes = [];
    if ((int) $cupon['minimo_compra'] > 0) {
        $partes[] = 'desde ' . pesos((int) $cupon['minimo_compra']);
    }
    if ((int) $cupon['una_vez_por_cliente'] === 1) {
        $partes[] = '1 vez por cliente';
    }
    if ($cupon['usos_maximos'] !== null) {
        $partes[] = (int) $cupon['usos_maximos'] === 1 ? 'un solo uso en total' : 'máximo ' . (int) $cupon['usos_maximos'] . ' usos';
    }
    $partes[] = !empty($cupon['vence_en']) ? 'hasta el ' . fecha_larga((string) $cupon['vence_en']) : 'sin vencimiento';

    return ucfirst(implode(' · ', $partes));
};
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Crecimiento</span>
    <h1 class="pq-h1">Cupones</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Un código que tus clientes escriben en el carrito o al reservar. Sirve en todas tus sedes.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<details class="pq-agregar-panel pq-cupon-nuevo"<?= $abierto || $cupones === [] ? ' open' : '' ?>>
  <summary class="pq-btn pq-btn-ghost">+ Nuevo cupón</summary>
  <form method="post" action="<?= e(base_url('/panel/cupones')) ?>" class="pq-agregar-panel-form">
    <?= csrf_campo() ?>
    <div class="pq-campo">
      <label class="pq-label" for="cupon-codigo">Código</label>
      <input class="pq-input pq-cupon-codigo-input" id="cupon-codigo" type="text" name="codigo" value="<?= e($sugerido) ?>" required minlength="3" maxlength="20" autocapitalize="characters" autocomplete="off" spellcheck="false">
      <span class="pq-ayuda">Corto y fácil de dictar. Letras, números y guiones.</span>
    </div>

    <fieldset class="pq-campo pq-cupon-valor">
      <legend class="pq-label">Descuento</legend>
      <div class="pq-cupon-tipo" role="radiogroup" aria-label="Tipo de descuento">
        <label><input type="radio" name="tipo" value="porcentaje" checked><span>%</span></label>
        <label><input type="radio" name="tipo" value="monto"><span>$</span></label>
      </div>
      <input class="pq-input" type="text" inputmode="numeric" name="valor" value="10" required aria-label="Valor del descuento" data-precio>
    </fieldset>
    <span class="pq-ayuda pq-cupon-valor-ayuda">Con % el descuento se calcula sobre el pedido (redondeado a $100). Con $ es un valor fijo.</span>

    <details class="pq-cupon-reglas">
      <summary>Condiciones <span class="pq-ayuda">(opcional)</span></summary>
      <div class="pq-cupon-reglas-campos">
        <div class="pq-campo">
          <label class="pq-label" for="cupon-minimo">Compra mínima</label>
          <input class="pq-input" id="cupon-minimo" type="text" inputmode="numeric" name="minimo_compra" placeholder="Sin mínimo" data-precio>
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="cupon-vence">Vence</label>
          <input class="pq-input" id="cupon-vence" type="date" name="vence_en" min="<?= e(date('Y-m-d')) ?>">
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="cupon-usos">Máximo de usos en total</label>
          <input class="pq-input" id="cupon-usos" type="number" inputmode="numeric" name="usos_maximos" min="1" placeholder="Sin límite">
        </div>
        <label class="pq-interruptor pq-cupon-una-vez">
          <input type="checkbox" name="una_vez_por_cliente" value="1" checked>
          <span class="pq-interruptor-pista" aria-hidden="true"></span>
          <span>Una vez por cliente <span class="pq-ayuda">(por número de WhatsApp)</span></span>
        </label>
      </div>
    </details>

    <button type="submit" class="pq-btn pq-btn-sello">Crear cupón</button>
  </form>
</details>

<?php if ($cupones === []): ?>
  <p class="pq-ayuda pq-cupones-vacio">Todavía no tienes cupones. Un buen primero: 10% para quien te escriba por el estado de WhatsApp esta semana.</p>
<?php else: ?>
  <?php
  // Cada cupón se dibuja como un tiquete recortable: el talón con el valor
  // grande (lo primero que se busca) y la línea punteada donde se "corta".
  ?>
  <ul class="pq-cupones" role="list">
    <?php foreach ($cupones as $cupon): ?>
      <?php
      $estado = Cupon::estado($cupon);
      $usos = (int) $cupon['usos'];
      $mensajeCompartir = 'Usa el código ' . $cupon['codigo'] . ' y te damos ' . Cupon::etiqueta($cupon) . ' de descuento en ' . $nombreNegocio
          . ((int) $cupon['minimo_compra'] > 0 ? ' (compras desde ' . pesos((int) $cupon['minimo_compra']) . ')' : '')
          . (!empty($cupon['vence_en']) ? '. Vale hasta el ' . fecha_larga((string) $cupon['vence_en']) : '')
          . '.' . ($urlTienda !== null ? ' Pide aquí: ' . $urlTienda : '');
      ?>
      <li class="pq-cupon pq-cupon-<?= e($estado) ?>">
        <div class="pq-cupon-talon" aria-hidden="true">
          <span class="pq-cupon-cifra<?= $cupon['tipo'] === 'monto' ? ' pq-cupon-cifra-monto' : '' ?>"><?= e(Cupon::etiqueta($cupon)) ?></span>
          <span class="pq-cupon-off">de descuento</span>
        </div>
        <div class="pq-cupon-cuerpo">
          <div class="pq-cupon-fila">
            <strong class="pq-cupon-codigo"><span class="pq-sr-solo"><?= e(Cupon::etiqueta($cupon)) ?> de descuento con el código </span><?= e($cupon['codigo']) ?></strong>
            <span class="pq-chip <?= $chipEstado[$estado] ?>"><?= $etiquetaEstado[$estado] ?></span>
          </div>
          <p class="pq-cupon-condiciones"><?= e($condiciones($cupon)) ?></p>
          <p class="pq-cupon-uso pq-mono">
            <?php if ($usos === 0): ?>
              Sin usar todavía
            <?php else: ?>
              Usado <?= $usos ?> <?= $usos === 1 ? 'vez' : 'veces' ?> · <?= pesos((int) $cupon['descontado']) ?> descontados
            <?php endif; ?>
          </p>
          <div class="pq-cupon-acciones">
            <?php if ($estado === 'activo'): ?>
              <a class="pq-btn pq-btn-ghost pq-btn-chico" href="https://wa.me/?text=<?= rawurlencode($mensajeCompartir) ?>" target="_blank" rel="noopener">Compartir</a>
              <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-copiar="<?= e($cupon['codigo']) ?>" aria-label="Copiar el código <?= e($cupon['codigo']) ?>">Copiar</button>
            <?php endif; ?>
            <details class="pq-menu-kebab pq-cupon-kebab">
              <summary aria-label="Más acciones del cupón <?= e($cupon['codigo']) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
              </summary>
              <div class="pq-menu-kebab-panel">
                <form method="post" action="<?= e(base_url('/panel/cupones/' . $cupon['id'] . '/alternar')) ?>">
                  <?= csrf_campo() ?>
                  <button type="submit"><?= (int) $cupon['activo'] === 1 ? 'Pausar' : 'Activar de nuevo' ?></button>
                </form>
                <?php if ($usos === 0): ?>
                  <form method="post" action="<?= e(base_url('/panel/cupones/' . $cupon['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar el cupón <?= e($cupon['codigo']) ?>?">
                    <?= csrf_campo() ?>
                    <button type="submit" class="pq-peligro">Eliminar</button>
                  </form>
                <?php endif; ?>
              </div>
            </details>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($personales !== []): ?>
  <section class="pq-cupones-personales" aria-labelledby="pq-titulo-personales">
    <h2 class="pq-seccion-titulo" id="pq-titulo-personales">Cupones personales del Copiloto</h2>
    <p class="pq-ayuda">Los que se crearon al mandarle un descuento a un cliente. Cada uno sirve solo con su WhatsApp y una vez.</p>
    <ul class="pq-cupones-personales-lista" role="list">
      <?php foreach ($personales as $cupon): ?>
        <?php $estado = Cupon::estado($cupon); $usado = (int) $cupon['usos'] > 0; ?>
        <li>
          <span class="pq-cupones-personales-quien">
            <strong><?= e((string) ($cupon['cliente_nombre'] ?? 'Cliente')) ?></strong>
            <span class="pq-ayuda pq-mono"><?= e($cupon['codigo']) ?> · <?= e(Cupon::etiqueta($cupon)) ?></span>
          </span>
          <span class="pq-chip <?= $usado ? 'pq-chip-caja' : ($estado === 'activo' ? 'pq-chip-pendiente' : $chipEstado[$estado]) ?>"><?= $usado ? 'Lo usó' : ($estado === 'activo' ? 'Sin usar' : $etiquetaEstado[$estado]) ?></span>
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
