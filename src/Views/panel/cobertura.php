<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Visitas</span>
    <h1 class="pq-h1">Zonas que cubres</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Los barrios o municipios a los que vas. El cliente elige el suyo al pedir la visita y el transporte se suma solo. Sin zonas, cualquiera puede pedir y el transporte lo cuadras por WhatsApp.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($zonas !== []): ?>
  <ul class="pq-admin-tarjeta pq-admin-filas pq-cobertura-lista">
    <?php foreach ($zonas as $zona): ?>
      <?php $activa = (int) $zona['activa'] === 1; ?>
      <li class="pq-admin-fila<?= $activa ? '' : ' pq-adicional-pausado' ?>">
        <details class="pq-cobertura-editar">
          <summary class="pq-admin-fila-texto">
            <strong><?= e($zona['nombre']) ?></strong>
            <span class="pq-ayuda"><?= (int) $zona['costo'] > 0 ? 'Transporte <span class="pq-mono">+' . pesos((int) $zona['costo']) . '</span>' : 'Sin recargo' ?><?= $activa ? '' : ' · pausada' ?></span>
          </summary>
          <form method="post" action="<?= e(base_url('/panel/cobertura/' . (int) $zona['id'])) ?>" class="pq-servicio-panel-par pq-cobertura-form">
            <?= csrf_campo() ?>
            <div class="pq-campo">
              <label class="pq-label" for="zona-<?= (int) $zona['id'] ?>-nombre">Nombre</label>
              <input class="pq-input" id="zona-<?= (int) $zona['id'] ?>-nombre" type="text" name="nombre" value="<?= e($zona['nombre']) ?>" maxlength="80" required>
            </div>
            <div class="pq-campo">
              <label class="pq-label" for="zona-<?= (int) $zona['id'] ?>-costo">Transporte</label>
              <div class="pq-campo-dinero"><input class="pq-input pq-mono" id="zona-<?= (int) $zona['id'] ?>-costo" type="text" inputmode="numeric" name="costo" value="<?= number_format((int) $zona['costo'], 0, ',', '.') ?>" data-precio-cop></div>
            </div>
            <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar</button>
          </form>
        </details>
        <span class="pq-adicional-acciones">
          <form method="post" action="<?= e(base_url('/panel/cobertura/' . (int) $zona['id'] . '/alternar')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-enlace-boton"><?= $activa ? 'Pausar' : 'Activar' ?></button>
          </form>
          <form method="post" action="<?= e(base_url('/panel/cobertura/' . (int) $zona['id'] . '/eliminar')) ?>" data-confirmar="¿Quitar «<?= e($zona['nombre']) ?>»? Las visitas ya pedidas conservan su zona.">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Quitar</button>
          </form>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<details class="pq-agregar-panel pq-adicional-nuevo"<?= $zonas === [] ? ' open' : '' ?>>
  <summary>Agregar zona</summary>
  <form method="post" action="<?= e(base_url('/panel/cobertura')) ?>" class="pq-servicio-panel-form">
    <?= csrf_campo() ?>
    <div class="pq-servicio-panel-par">
      <div class="pq-campo">
        <label class="pq-label" for="zona-nombre">Barrio o municipio</label>
        <input class="pq-input" id="zona-nombre" type="text" name="nombre" maxlength="80" placeholder="Ej.: Cabecera, Floridablanca" required>
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="zona-costo">Transporte</label>
        <div class="pq-campo-dinero"><input class="pq-input pq-mono" id="zona-costo" type="text" inputmode="numeric" name="costo" placeholder="0" data-precio-cop></div>
      </div>
    </div>
    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Agregar zona</button>
  </form>
</details>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
