<?php
use App\Models\ZonaDomicilio;

$pedidoMinimo = (int) ($negocio['pedido_minimo'] ?? 0);
$formatear = static fn (int $valor): string => $valor > 0 ? number_format($valor, 0, ',', '.') : '';
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Operación · <?= e($negocio['nombre']) ?></span>
    <h1 class="pq-h1">Domicilios</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">A dónde llevas y cuánto cobras. El cliente elige su zona en el carrito y el domicilio se suma solo al total.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="pq-admin-seccion pq-domicilios-seccion" aria-labelledby="pq-titulo-zonas">
  <h2 class="pq-seccion-titulo" id="pq-titulo-zonas">Zonas y tarifas</h2>
  <?php if ($zonas === []): ?>
    <p class="pq-ayuda">Sin zonas, el carrito sigue como antes: el costo del domicilio lo acuerdas con el cliente por WhatsApp.</p>
  <?php else: ?>
    <?php
    // El tarifario del mensajero: zona a la izquierda, valor a la derecha
    // con puntos guía, como la lista pegada en la nevera del negocio.
    ?>
    <ul class="pq-admin-tarjeta pq-admin-filas pq-tarifario">
      <?php foreach ($zonas as $zona): ?>
        <?php $activa = (int) $zona['activa'] === 1; ?>
        <li class="pq-admin-fila pq-tarifa<?= $activa ? '' : ' pq-tarifa-pausada' ?>">
          <span class="pq-tarifa-texto">
            <span class="pq-tarifa-linea">
              <strong class="pq-tarifa-nombre"><?= e($zona['nombre']) ?></strong>
              <span class="pq-tarifa-guia" aria-hidden="true"></span>
              <span class="pq-tarifa-costo pq-mono"><?= e(ZonaDomicilio::etiquetaCosto((int) $zona['costo'])) ?></span>
            </span>
            <span class="pq-ayuda">
              <?= (int) $zona['minimo_pedido'] > 0 ? 'Pedido mínimo ' . pesos((int) $zona['minimo_pedido']) : 'Sin mínimo propio' ?><?= $activa ? '' : ' · En pausa: no aparece en el carrito' ?>
            </span>
          </span>
          <details class="pq-menu-kebab">
            <summary aria-label="Acciones de la zona <?= e($zona['nombre']) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </summary>
            <div class="pq-menu-kebab-panel">
              <a href="<?= e(base_url('/panel/domicilios?editar=' . $zona['id'])) ?>#pq-form-zona">Editar</a>
              <form method="post" action="<?= e(base_url('/panel/domicilios/' . $zona['id'] . '/alternar')) ?>">
                <?= csrf_campo() ?>
                <button type="submit"><?= $activa ? 'Pausar' : 'Activar' ?></button>
              </form>
              <form method="post" action="<?= e(base_url('/panel/domicilios/' . $zona['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar la zona <?= e($zona['nombre']) ?>?">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-peligro">Eliminar</button>
              </form>
            </div>
          </details>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <details class="pq-agregar-panel" id="pq-form-zona"<?= $editar !== null || $zonas === [] ? ' open' : '' ?>>
    <summary class="pq-btn pq-btn-ghost"><?= $editar !== null ? 'Editar ' . e($editar['nombre']) : '+ Nueva zona' ?></summary>
    <form method="post" action="<?= e(base_url('/panel/domicilios' . ($editar !== null ? '/' . $editar['id'] : ''))) ?>" class="pq-agregar-panel-form">
      <?= csrf_campo() ?>
      <div class="pq-campo">
        <label class="pq-label" for="zona-nombre">Zona</label>
        <input class="pq-input" id="zona-nombre" type="text" name="nombre" value="<?= e($editar['nombre'] ?? '') ?>" required maxlength="80" placeholder="Ej.: La Floresta, Centro, Hasta 2 km">
      </div>
      <div class="pq-domicilios-cifras">
        <div class="pq-campo">
          <label class="pq-label" for="zona-costo">Costo del domicilio</label>
          <input class="pq-input" id="zona-costo" type="text" inputmode="numeric" name="costo" value="<?= $formatear((int) ($editar['costo'] ?? 0)) ?>" placeholder="0 = gratis" data-precio>
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="zona-minimo">Pedido mínimo <span class="pq-ayuda">(opcional)</span></label>
          <input class="pq-input" id="zona-minimo" type="text" inputmode="numeric" name="minimo_pedido" value="<?= $formatear((int) ($editar['minimo_pedido'] ?? 0)) ?>" placeholder="Sin mínimo" data-precio>
        </div>
      </div>
      <div class="pq-domicilios-botones">
        <button type="submit" class="pq-btn pq-btn-sello"><?= $editar !== null ? 'Guardar zona' : 'Agregar zona' ?></button>
        <?php if ($editar !== null): ?>
          <a class="pq-btn pq-btn-ghost" href="<?= e(base_url('/panel/domicilios')) ?>">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </details>
</section>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-minimo">
  <h2 class="pq-seccion-titulo" id="pq-titulo-minimo">Pedido mínimo de la sede</h2>
  <p class="pq-ayuda">Vale para domicilio, recoger y mesa. Una zona puede pedir uno mayor.</p>
  <form method="post" action="<?= e(base_url('/panel/domicilios/minimo')) ?>" class="pq-domicilios-minimo">
    <?= csrf_campo() ?>
    <label class="pq-sr-solo" for="pedido-minimo">Pedido mínimo</label>
    <input class="pq-input" id="pedido-minimo" type="text" inputmode="numeric" name="pedido_minimo" value="<?= $formatear($pedidoMinimo) ?>" placeholder="Sin mínimo" data-precio>
    <button type="submit" class="pq-btn pq-btn-ghost">Guardar</button>
  </form>
</section>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
