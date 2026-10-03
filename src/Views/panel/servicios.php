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
          <span class="pq-servicio-panel-precio pq-mono"><?= e(precio_texto($servicio)) ?></span>
          <span class="pq-servicio-panel-editar">Editar</span>
        </summary>

        <div class="pq-servicio-panel-cuerpo">
          <?php
          // Cuánto dura de verdad (medido de Empezar a Terminar). Solo con
          // muestra suficiente y si difiere de verdad (5+ min) de lo anotado.
          $medida = $duraciones[(int) $servicio['id']] ?? null;
          $sugerida = $medida !== null ? \App\Models\Imprevisto::redondearDuracion($medida['promedio']) : null;
          ?>
          <?php if ($sugerida !== null && abs($sugerida - (int) $servicio['duracion_min']) >= 5): ?>
            <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/duracion-real')) ?>" class="pq-duracion-real">
              <?= csrf_campo() ?>
              <p>En tus últimas <?= (int) $medida['citas'] ?> citas, este servicio te tomó en promedio <strong><?= (int) $medida['promedio'] ?> min</strong>; tu agenda lo reparte en <?= (int) $servicio['duracion_min'] ?>.</p>
              <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Usar <?= $sugerida ?> min</button>
            </form>
          <?php endif; ?>
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
            <?php $tipoPrecio = (string) $servicio['precio_tipo']; ?>
            <div class="pq-servicio-panel-par">
              <div class="pq-campo">
                <label class="pq-label" for="<?= $idBase ?>-tipo">¿El precio es exacto?</label>
                <select class="pq-select" id="<?= $idBase ?>-tipo" name="precio_tipo">
                  <option value="fijo" <?= $tipoPrecio === 'fijo' ? 'selected' : '' ?>>Sí, precio fijo</option>
                  <option value="desde" <?= $tipoPrecio === 'desde' ? 'selected' : '' ?>>Desde ese valor</option>
                  <option value="rango" <?= $tipoPrecio === 'rango' ? 'selected' : '' ?>>Entre ese valor y otro</option>
                </select>
              </div>
              <div class="pq-campo" data-mostrar-si="precio_tipo=rango">
                <label class="pq-label" for="<?= $idBase ?>-max">Hasta</label>
                <div class="pq-campo-dinero">
                  <input class="pq-input pq-mono" id="<?= $idBase ?>-max" type="text" inputmode="numeric" name="precio_max" value="<?= $servicio['precio_max'] !== null ? number_format((int) $servicio['precio_max'], 0, ',', '.') : '' ?>" data-precio-cop data-requerido-si-visible>
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
        <label class="pq-label" for="srv-nuevo-tipo">¿Exacto?</label>
        <select class="pq-select" id="srv-nuevo-tipo" name="precio_tipo">
          <option value="fijo">Precio fijo</option>
          <option value="desde">Desde ese valor</option>
          <option value="rango">Entre dos valores</option>
        </select>
      </div>
      <div class="pq-campo" data-mostrar-si="precio_tipo=rango">
        <label class="pq-label" for="srv-nuevo-max">Hasta</label>
        <div class="pq-campo-dinero">
          <input class="pq-input pq-mono" id="srv-nuevo-max" type="text" inputmode="numeric" name="precio_max" placeholder="0" data-precio-cop data-requerido-si-visible>
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

<?php if ($esDueno): ?>
  <?php // Lo que se suma al servicio al reservar (cejas, hidratación…): suma precio y tiempo al turno. ?>
  <section class="pq-adicionales-panel" id="adicionales" aria-labelledby="pq-titulo-adicionales">
    <h2 class="pq-seccion-titulo" id="pq-titulo-adicionales">Adicionales</h2>
    <p class="pq-ayuda">Lo que el cliente le puede sumar a su servicio al reservar. Suma al precio y, si pones minutos, al tiempo del turno.</p>
    <?php if (!empty($adicionales)): ?>
      <ul class="pq-admin-tarjeta pq-admin-filas">
        <?php foreach ($adicionales as $adicional): ?>
          <?php $activo = (int) $adicional['activo'] === 1; ?>
          <li class="pq-admin-fila<?= $activo ? '' : ' pq-adicional-pausado' ?>">
            <span class="pq-admin-fila-texto">
              <strong><?= e($adicional['nombre']) ?></strong>
              <span class="pq-ayuda">
                <span class="pq-mono">+<?= pesos((int) $adicional['precio']) ?></span><?= (int) $adicional['duracion_min'] > 0 ? ' · +' . (int) $adicional['duracion_min'] . ' min' : '' ?>
                · <?= !empty($adicional['servicio_nombre']) ? 'con ' . e($adicional['servicio_nombre']) : 'con cualquier servicio' ?>
                <?= $activo ? '' : ' · en pausa' ?>
              </span>
            </span>
            <span class="pq-adicional-acciones">
              <form method="post" action="<?= e(base_url('/panel/adicionales/' . $adicional['id'] . '/alternar')) ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-enlace-boton"><?= $activo ? 'Pausar' : 'Ofrecer' ?></button>
              </form>
              <form method="post" action="<?= e(base_url('/panel/adicionales/' . $adicional['id'] . '/eliminar')) ?>" data-confirmar="¿Quitar «<?= e($adicional['nombre']) ?>»? Las citas que ya lo tienen lo conservan.">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Quitar</button>
              </form>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <details class="pq-agregar-panel pq-adicional-nuevo"<?= empty($adicionales) ? ' open' : '' ?>>
      <summary>Agregar adicional</summary>
      <form method="post" action="<?= e(base_url('/panel/adicionales')) ?>" class="pq-servicio-panel-form">
        <?= csrf_campo() ?>
        <div class="pq-campo">
          <label class="pq-label" for="ad-nombre">Nombre</label>
          <input class="pq-input" id="ad-nombre" type="text" name="nombre" maxlength="80" placeholder="Ej: Diseño de cejas" required>
        </div>
        <div class="pq-servicio-panel-par">
          <div class="pq-campo">
            <label class="pq-label" for="ad-precio">Precio</label>
            <div class="pq-campo-dinero"><input class="pq-input pq-mono" id="ad-precio" type="text" inputmode="numeric" name="precio" placeholder="0" data-precio-cop required></div>
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="ad-duracion">Tiempo extra</label>
            <div class="pq-campo-sufijo" data-sufijo="min"><input class="pq-input pq-mono" id="ad-duracion" type="number" name="duracion_min" min="0" max="240" step="5" value="0"></div>
          </div>
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="ad-servicio">Se ofrece con</label>
          <select class="pq-select" id="ad-servicio" name="servicio_id">
            <option value="0">Cualquier servicio</option>
            <?php foreach ($servicios as $servicio): ?>
              <option value="<?= (int) $servicio['id'] ?>"><?= e($servicio['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Agregar adicional</button>
      </form>
    </details>
  </section>

  <?php // Las reglas que el cliente ve antes de reservar (ver reservar.php). ?>
  <section class="pq-reglas-agenda" aria-labelledby="pq-titulo-reglas">
    <h2 class="pq-seccion-titulo" id="pq-titulo-reglas">Reglas de tu agenda</h2>
    <p class="pq-ayuda">Para cuando las cosas no salen exactas. Tus clientes las ven antes de reservar.</p>
    <form method="post" action="<?= e(base_url('/panel/agenda/reglas')) ?>" class="pq-reglas-agenda-form">
      <?= csrf_campo() ?>
      <div class="pq-servicio-panel-par">
        <div class="pq-campo">
          <label class="pq-label" for="regla-colchon">Colchón entre citas</label>
          <select class="pq-select" id="regla-colchon" name="colchon_min">
            <?php foreach (\App\Models\Imprevisto::COLCHONES as $minutos): ?>
              <option value="<?= $minutos ?>" <?= (int) $negocio['colchon_min'] === $minutos ? 'selected' : '' ?>><?= $minutos === 0 ? 'Sin colchón' : $minutos . ' min' ?></option>
            <?php endforeach; ?>
          </select>
          <span class="pq-ayuda">Si un servicio se alarga, no se come el siguiente turno.</span>
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="regla-tolerancia">Esperas a quien llega tarde</label>
          <select class="pq-select" id="regla-tolerancia" name="tolerancia_min">
            <?php foreach (\App\Models\Imprevisto::TOLERANCIAS as $minutos): ?>
              <option value="<?= $minutos ?>" <?= (int) $negocio['tolerancia_min'] === $minutos ? 'selected' : '' ?>>Hasta <?= $minutos ?> min</option>
            <?php endforeach; ?>
          </select>
          <span class="pq-ayuda">Después de eso aparece «No vino» en la agenda.</span>
        </div>
      </div>
      <fieldset class="pq-campo">
        <legend class="pq-label">Si alguien no llega y pagó anticipo</legend>
        <label class="pq-reglas-opcion"><input type="radio" name="anticipo_no_asiste" value="se_pierde" <?= $negocio['anticipo_no_asiste'] !== 'se_abona' ? 'checked' : '' ?>><span>El anticipo no se devuelve</span></label>
        <label class="pq-reglas-opcion"><input type="radio" name="anticipo_no_asiste" value="se_abona" <?= $negocio['anticipo_no_asiste'] === 'se_abona' ? 'checked' : '' ?>><span>Se le abona para su próxima cita <span class="pq-ayuda">(le queda un cupón por ese valor)</span></span></label>
      </fieldset>
      <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar reglas</button>
    </form>
  </section>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
