<?php
use App\Models\Bono;

$esDueno = $negocio['rol'] === 'dueno';
$activos = array_values(array_filter($paquetes, fn ($p) => (int) $p['activo'] === 1));
$etiquetaEstado = ['activo' => 'Con sesiones', 'agotado' => 'Usado completo', 'vencido' => 'Vencido'];
$chipEstado = ['activo' => 'pq-chip-caja', 'agotado' => '', 'vencido' => 'pq-chip-cancelado'];
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Operación · <?= e($negocio['nombre']) ?></span>
    <h1 class="pq-h1">Paquetes y bonos</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Vende varias sesiones por adelantado. Cuando el cliente reserva con su WhatsApp, cada cita usa una sesión sola; si la cancela, la sesión vuelve.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($vendido !== null): ?>
  <?php
  $enlaceBono = url_publica('/bono/' . $vendido['token']);
  $mensajeBono = 'Hola ' . explode(' ', trim((string) $vendido['cliente_nombre']))[0] . ', este es tu bono de '
      . (int) $vendido['sesiones_total'] . ' ' . mb_strtolower((string) $vendido['nombre_servicio']) . ' en ' . nombre_publico_sede($negocio)
      . '. Aquí ves cuántas sesiones te quedan y reservas: ' . $enlaceBono;
  ?>
  <section class="pq-bono-vendido" aria-live="polite">
    <strong>Bono vendido a <?= e($vendido['cliente_nombre']) ?></strong>
    <span class="pq-ayuda"><?= (int) $vendido['sesiones_total'] ?> × <?= e($vendido['nombre_servicio']) ?> · <?= pesos((int) $vendido['precio_pagado']) ?><?= !empty($vendido['vence_en']) ? ' · vence el ' . e(fecha_larga((string) $vendido['vence_en'])) : '' ?></span>
    <a class="pq-btn pq-btn-whatsapp pq-btn-chico" href="https://wa.me/57<?= e(preg_replace('/\D+/', '', (string) $vendido['cliente_telefono'])) ?>?text=<?= rawurlencode($mensajeBono) ?>" target="_blank" rel="noopener">Mandarle su bono por WhatsApp</a>
  </section>
<?php endif; ?>

<div class="pq-paquetes-panel">
  <section aria-labelledby="pq-titulo-vender">
    <h2 class="pq-seccion-titulo" id="pq-titulo-vender">Vender un bono</h2>
    <?php if ($activos === []): ?>
      <p class="pq-ayuda"><?= $esDueno ? 'Primero crea un paquete (abajo).' : 'El dueño todavía no ha creado paquetes.' ?></p>
    <?php else: ?>
      <form method="post" action="<?= e(base_url('/panel/paquetes/vender')) ?>" class="pq-agregar-panel-form pq-paquetes-form">
        <?= csrf_campo() ?>
        <fieldset class="pq-campo pq-paquetes-opciones">
          <legend class="pq-label">Paquete</legend>
          <?php foreach ($activos as $i => $paquete): ?>
            <label class="pq-paquetes-opcion">
              <input type="radio" name="paquete_id" value="<?= (int) $paquete['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <span><strong><?= (int) $paquete['sesiones'] ?> × <?= e($paquete['servicio_nombre']) ?></strong> <span class="pq-mono"><?= pesos((int) $paquete['precio']) ?></span></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <div class="pq-campo">
          <label class="pq-label" for="bono-nombre">Nombre del cliente</label>
          <input class="pq-input" id="bono-nombre" type="text" name="nombre" required maxlength="120">
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="bono-telefono">Su WhatsApp</label>
          <input class="pq-input pq-mono" id="bono-telefono" type="tel" inputmode="numeric" name="telefono" required maxlength="20" placeholder="300 123 4567">
          <span class="pq-ayuda">Con este número se le descuentan las sesiones al reservar. Pídele permiso para guardar sus datos.</span>
        </div>
        <button type="submit" class="pq-btn pq-btn-sello">Registrar venta (ya pagó)</button>
      </form>
    <?php endif; ?>
  </section>

  <section aria-labelledby="pq-titulo-paquetes">
    <h2 class="pq-seccion-titulo" id="pq-titulo-paquetes">Tus paquetes</h2>
    <?php if ($paquetes === []): ?>
      <p class="pq-ayuda">Todavía no tienes paquetes. Uno típico: 5 sesiones con un 15% menos que sueltas.</p>
    <?php else: ?>
      <ul class="pq-admin-tarjeta pq-admin-filas">
        <?php foreach ($paquetes as $paquete): ?>
          <?php $ahorro = (int) $paquete['precio_suelto'] - (int) $paquete['precio']; ?>
          <li class="pq-admin-fila<?= (int) $paquete['activo'] === 1 ? '' : ' pq-tarifa-pausada' ?>">
            <span class="pq-admin-fila-texto">
              <strong><?= (int) $paquete['sesiones'] ?> × <?= e($paquete['servicio_nombre']) ?> · <span class="pq-mono"><?= pesos((int) $paquete['precio']) ?></span></strong>
              <span class="pq-ayuda">
                <?= $ahorro > 0 ? 'El cliente ahorra ' . pesos($ahorro) : 'Sin ahorro frente a sueltas' ?>
                · <?= $paquete['vigencia_dias'] !== null ? (int) $paquete['vigencia_dias'] . ' días para usarlo' : 'sin vencimiento' ?>
                · <?= (int) $paquete['vendidos'] ?> vendido<?= (int) $paquete['vendidos'] === 1 ? '' : 's' ?>
                <?= (int) $paquete['activo'] === 1 ? '' : ' · en pausa' ?>
              </span>
            </span>
            <?php if ($esDueno): ?>
              <details class="pq-menu-kebab">
                <summary aria-label="Acciones del paquete">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                </summary>
                <div class="pq-menu-kebab-panel">
                  <form method="post" action="<?= e(base_url('/panel/paquetes/' . $paquete['id'] . '/alternar')) ?>">
                    <?= csrf_campo() ?>
                    <button type="submit"><?= (int) $paquete['activo'] === 1 ? 'Pausar (no se ofrece)' : 'Volver a ofrecer' ?></button>
                  </form>
                  <form method="post" action="<?= e(base_url('/panel/paquetes/' . $paquete['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar este paquete? Los bonos ya vendidos siguen valiendo.">
                    <?= csrf_campo() ?>
                    <button type="submit" class="pq-peligro">Eliminar</button>
                  </form>
                </div>
              </details>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($esDueno && $servicios !== []): ?>
      <details class="pq-agregar-panel"<?= $paquetes === [] ? ' open' : '' ?>>
        <summary class="pq-btn pq-btn-ghost">+ Nuevo paquete</summary>
        <form method="post" action="<?= e(base_url('/panel/paquetes')) ?>" class="pq-agregar-panel-form">
          <?= csrf_campo() ?>
          <div class="pq-campo">
            <label class="pq-label" for="paquete-servicio">Servicio</label>
            <select class="pq-select" id="paquete-servicio" name="servicio_id" required>
              <?php foreach ($servicios as $servicio): ?>
                <option value="<?= (int) $servicio['id'] ?>"><?= e($servicio['nombre']) ?> · <?= pesos((int) $servicio['precio']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="pq-domicilios-cifras">
            <div class="pq-campo">
              <label class="pq-label" for="paquete-sesiones">Sesiones</label>
              <input class="pq-input pq-mono" id="paquete-sesiones" type="number" inputmode="numeric" name="sesiones" min="2" max="30" value="5" required>
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="paquete-precio">Precio del paquete</label>
              <input class="pq-input pq-mono" id="paquete-precio" type="text" inputmode="numeric" name="precio" required data-precio>
            </div>
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="paquete-vigencia">Días para usarlo <span class="pq-ayuda">(opcional)</span></label>
            <input class="pq-input pq-mono pq-campo-unidades" id="paquete-vigencia" type="number" inputmode="numeric" name="vigencia_dias" min="1" max="730" placeholder="Sin límite">
          </div>
          <button type="submit" class="pq-btn pq-btn-sello">Crear paquete</button>
        </form>
      </details>
    <?php endif; ?>
  </section>
</div>

<?php if ($bonos !== []): ?>
  <section class="pq-admin-seccion" aria-labelledby="pq-titulo-bonos">
    <h2 class="pq-seccion-titulo" id="pq-titulo-bonos">Bonos vendidos</h2>
    <ul class="pq-admin-tarjeta pq-admin-filas">
      <?php foreach ($bonos as $bono): ?>
        <?php $estado = Bono::estado($bono); ?>
        <li class="pq-admin-fila">
          <span class="pq-admin-fila-texto">
            <strong><?= e($bono['cliente_nombre']) ?></strong>
            <span class="pq-ayuda"><?= e($bono['nombre_servicio']) ?><?= !empty($bono['vence_en']) ? ' · vence el ' . e(fecha_larga((string) $bono['vence_en'])) : '' ?></span>
            <?php // Las sesiones como puntos: llenos los usados. Se lee de un vistazo cuánto le queda. ?>
            <span class="pq-bono-puntos" aria-label="<?= (int) $bono['usadas'] ?> de <?= (int) $bono['sesiones_total'] ?> sesiones usadas">
              <?php for ($i = 1; $i <= (int) $bono['sesiones_total']; $i++): ?><span class="<?= $i <= (int) $bono['usadas'] ? 'pq-bono-punto-usado' : '' ?>"></span><?php endfor; ?>
              <span class="pq-ayuda pq-mono"><?= (int) $bono['usadas'] ?>/<?= (int) $bono['sesiones_total'] ?></span>
            </span>
          </span>
          <span class="pq-chip <?= $chipEstado[$estado] ?>"><?= $etiquetaEstado[$estado] ?></span>
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
