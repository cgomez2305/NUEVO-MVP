<?php
$esDueno = $negocio['rol'] === 'dueno';
$resumenAnticipo = static function (array $servicio): string {
    return match ($servicio['deposito_tipo']) {
        'porcentaje' => 'Anticipo ' . (int) $servicio['deposito_valor'] . '%',
        'monto_fijo' => 'Anticipo ' . pesos((int) $servicio['deposito_valor']),
        default      => '',
    };
};
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Tus servicios</span>
    <h1 class="pq-h1">Servicios</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Lo que cambies aquí se ve de inmediato en tu agenda online.</p>

<?php
// Cada servicio es una fila que se lee como la carta de la tienda (nombre,
// duración, precio). Tocarla abre UN formulario con todo — nombre, precio,
// duración y anticipo — y un solo "Guardar"; antes cada tarjeta tenía dos
// formularios con su propio botón y no quedaba claro qué guardaba cuál.
?>
<?php if ($servicios === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 16l9 5 9-5M3 12l9 5 9-5"/></svg>
    <p><strong>Todavía no tienes servicios.</strong><br>Agrega el primero para que tus clientes puedan reservar.</p>
  </div>
<?php else: ?>
  <div class="pq-servicios-lista">
    <?php foreach ($servicios as $servicio): ?>
      <?php
      $agotado = (int) $servicio['agotado'] === 1;
      $anticipo = $resumenAnticipo($servicio);
      $idBase = 'srv-' . (int) $servicio['id'];
      ?>
      <details class="pq-servicio-panel<?= $agotado ? ' pq-servicio-panel-agotado' : '' ?>" style="--color-servicio: <?= e(color_seguro($servicio['color'] ?? null, '#3B4CCA')) ?>">
        <summary class="pq-servicio-panel-fila">
          <span class="pq-servicio-panel-punto" aria-hidden="true"></span>
          <span class="pq-servicio-panel-texto">
            <span class="pq-servicio-panel-nombre"><?= e($servicio['nombre']) ?></span>
            <span class="pq-servicio-panel-meta">
              <span class="pq-mono"><?= (int) $servicio['duracion_min'] ?> min</span>
              <?php if ($anticipo !== ''): ?><span aria-hidden="true">·</span><span><?= e($anticipo) ?></span><?php endif; ?>
              <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado">No disponible</span><?php endif; ?>
            </span>
          </span>
          <span class="pq-servicio-panel-precio pq-mono"><?= pesos((int) $servicio['precio']) ?></span>
          <span class="pq-servicio-panel-editar">Editar</span>
        </summary>

        <div class="pq-servicio-panel-cuerpo">
          <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/actualizar')) ?>" class="pq-servicio-panel-form">
            <?= csrf_campo() ?>
            <input type="hidden" name="volver" value="/panel/servicios">
            <div class="pq-campo">
              <label class="pq-label" for="<?= $idBase ?>-nombre">Nombre</label>
              <input class="pq-input" id="<?= $idBase ?>-nombre" type="text" name="nombre" value="<?= e($servicio['nombre']) ?>" required maxlength="120">
            </div>
            <div class="pq-servicio-panel-par">
              <div class="pq-campo">
                <label class="pq-label" for="<?= $idBase ?>-precio">Precio</label>
                <div class="pq-campo-dinero">
                  <input class="pq-input pq-mono" id="<?= $idBase ?>-precio" type="text" inputmode="numeric" name="precio" value="<?= number_format((int) $servicio['precio'], 0, ',', '.') ?>" data-precio-cop required>
                </div>
              </div>
              <div class="pq-campo">
                <label class="pq-label" for="<?= $idBase ?>-duracion">Duración</label>
                <div class="pq-campo-sufijo" data-sufijo="min">
                  <input class="pq-input pq-mono" id="<?= $idBase ?>-duracion" type="number" name="duracion_min" value="<?= (int) $servicio['duracion_min'] ?>" min="5" step="5" required>
                </div>
              </div>
            </div>
            <?php if ($esDueno): ?>
              <fieldset class="pq-servicio-panel-anticipo">
                <legend class="pq-label">Anticipo para confirmar la cita <span class="pq-ayuda">(opcional)</span></legend>
                <div class="pq-servicio-panel-par">
                  <select class="pq-select" name="deposito_tipo" aria-label="Tipo de anticipo">
                    <option value="ninguno" <?= $servicio['deposito_tipo'] === 'ninguno' ? 'selected' : '' ?>>Sin anticipo</option>
                    <option value="porcentaje" <?= $servicio['deposito_tipo'] === 'porcentaje' ? 'selected' : '' ?>>% del precio</option>
                    <option value="monto_fijo" <?= $servicio['deposito_tipo'] === 'monto_fijo' ? 'selected' : '' ?>>Monto fijo</option>
                  </select>
                  <input class="pq-input pq-mono" type="text" inputmode="numeric" name="deposito_valor" value="<?= (int) $servicio['deposito_valor'] ?: '' ?>" placeholder="0" aria-label="Valor del anticipo">
                </div>
                <?php if ($servicio['deposito_tipo'] !== 'ninguno'): ?>
                  <p class="pq-ayuda">Hoy se pide <?= pesos(\App\Models\Servicio::calcularAnticipo($servicio)) ?> al reservar.</p>
                <?php endif; ?>
              </fieldset>
            <?php endif; ?>
            <button type="submit" class="pq-btn pq-btn-sello">Guardar cambios</button>
          </form>

          <div class="pq-servicio-panel-secundarias">
            <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/agotado')) ?>">
              <?= csrf_campo() ?>
              <input type="hidden" name="volver" value="/panel/servicios">
              <button type="submit" class="pq-enlace-boton"><?= $agotado ? 'Volver a ofrecerlo' : 'Pausar (no disponible)' ?></button>
            </form>
            <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar «<?= e($servicio['nombre']) ?>» de tu catálogo? No se puede deshacer.">
              <?= csrf_campo() ?>
              <input type="hidden" name="volver" value="/panel/servicios">
              <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Eliminar</button>
            </form>
          </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<details class="pq-agregar-panel"<?= $servicios === [] ? ' open' : '' ?>>
  <summary class="pq-btn pq-btn-ghost">+ Nuevo servicio</summary>
  <form method="post" action="<?= e(base_url('/panel/servicios')) ?>" class="pq-agregar-panel-form">
    <?= csrf_campo() ?>
    <input type="hidden" name="volver" value="/panel/servicios">
    <div class="pq-campo">
      <label class="pq-label" for="srv-nuevo-nombre">Nombre</label>
      <input class="pq-input" id="srv-nuevo-nombre" type="text" name="nombre" placeholder="Ej.: Corte y cepillado" required maxlength="120">
    </div>
    <div class="pq-servicio-panel-par">
      <div class="pq-campo">
        <label class="pq-label" for="srv-nuevo-precio">Precio</label>
        <div class="pq-campo-dinero">
          <input class="pq-input pq-mono" id="srv-nuevo-precio" type="text" inputmode="numeric" name="precio" placeholder="0" data-precio-cop required>
        </div>
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="srv-nuevo-duracion">Duración</label>
        <div class="pq-campo-sufijo" data-sufijo="min">
          <input class="pq-input pq-mono" id="srv-nuevo-duracion" type="number" name="duracion_min" min="5" step="5" value="30" required>
        </div>
      </div>
    </div>
    <button type="submit" class="pq-btn pq-btn-sello">Agregar servicio</button>
  </form>
</details>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
