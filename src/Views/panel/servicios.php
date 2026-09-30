<?php
/**
 * Catálogo de solo lectura de servicios, mismo patrón que productos.php
 * (ver ese archivo para el porqué). Los servicios no tienen categoría en
 * el modelo, así que aquí no hay agrupación: es una sola grilla.
 */
$hayFiltro = $busqueda !== '' || $disponibilidad !== '';
?>
<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap">
  <div>
    <span class="pq-eyebrow">Tus servicios</span>
    <h1 class="pq-h1" style="font-size: 28px">Servicios</h1>
    <p class="pq-lead" style="margin-top: 2px">Lo que cambies aquí se ve de inmediato en tu tienda.</p>
  </div>
  <a href="<?= e(base_url('/panel/servicios/nuevo')) ?>" class="pq-btn pq-btn-sello pq-btn-chico">+ Nuevo servicio</a>
</div>

<?php if ($ok !== null): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>

<form method="get" action="<?= e(base_url('/panel/servicios')) ?>" class="pq-filtro-historial">
  <input class="pq-input" type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar servicio...">
  <select class="pq-select" name="disponibilidad" data-autoenviar>
    <option value="">Todos</option>
    <option value="disponibles" <?= $disponibilidad === 'disponibles' ? 'selected' : '' ?>>Disponibles</option>
    <option value="agotados" <?= $disponibilidad === 'agotados' ? 'selected' : '' ?>>No disponibles</option>
  </select>
  <select class="pq-select" name="orden" data-autoenviar>
    <option value="">Orden manual</option>
    <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre (A-Z)</option>
    <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio (menor a mayor)</option>
    <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio (mayor a menor)</option>
    <option value="duracion" <?= $orden === 'duracion' ? 'selected' : '' ?>>Duración</option>
  </select>
  <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico" style="width: auto">Buscar</button>
</form>

<?php if ($servicios === []): ?>
  <div class="pq-tabla-wrap" style="margin-top: 16px">
    <p class="pq-tabla-vacia">
      <?= $hayFiltro ? 'Nada coincide con esa búsqueda.' : 'Aún no tienes servicios en tu catálogo.' ?>
    </p>
  </div>
<?php else: ?>
  <div class="pq-catalogo-grid pq-catalogo-grid-servicios">
    <?php foreach ($servicios as $servicio): ?>
      <?php $agotado = (int) $servicio['agotado'] === 1; ?>
      <div class="pq-catalogo-card pq-catalogo-card-servicio<?= $agotado ? ' pq-catalogo-card-agotado' : '' ?>">
        <span class="pq-catalogo-card-color" style="background: <?= e($servicio['color']) ?>" aria-hidden="true"></span>
        <div class="pq-catalogo-card-body">
          <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px">
            <span class="pq-catalogo-card-nombre"><?= e($servicio['nombre']) ?></span>
            <details class="pq-menu">
              <summary aria-label="Más acciones para <?= e($servicio['nombre']) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
              </summary>
              <div class="pq-menu-panel">
                <a class="pq-menu-item" href="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/editar')) ?>">Editar</a>
                <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/agotado')) ?>">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="volver" value="/panel/servicios">
                  <button type="submit" class="pq-menu-item"><?= $agotado ? 'Marcar disponible' : 'Marcar no disponible' ?></button>
                </form>
                <form method="post" action="<?= e(base_url('/panel/servicios/' . $servicio['id'] . '/eliminar')) ?>"
                      data-confirmar="¿Eliminar «<?= e($servicio['nombre']) ?>» de tu catálogo? No se puede deshacer.">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="volver" value="/panel/servicios">
                  <button type="submit" class="pq-menu-item pq-menu-item-peligro">Eliminar</button>
                </form>
              </div>
            </details>
          </div>
          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px">
            <span class="pq-mono" style="font-size: 13px; font-weight: 600"><?= pesos((int) $servicio['precio']) ?></span>
            <span class="pq-chip"><?= (int) $servicio['duracion_min'] ?> min</span>
            <?php if ($agotado): ?><span class="pq-chip pq-chip-cancelado">No disponible</span><?php endif; ?>
          </div>
          <?php if ($servicio['deposito_tipo'] !== 'ninguno'): ?>
            <span class="pq-ayuda" style="display: block; margin-top: 4px">
              Anticipo: <?= pesos(\App\Models\Servicio::calcularAnticipo($servicio)) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
