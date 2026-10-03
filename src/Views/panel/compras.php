<?php
use App\Models\Producto;

$totalBorrador = 0;
foreach ($lineas as $linea) {
    $cantidad = \App\Models\Compra::cantidadDesdeTexto($linea['cantidad'], $linea['producto']['vende_por'] === 'peso');
    $totalBorrador += (int) round(dinero_desde_texto($linea['costo']) * $cantidad);
}
$proveedores = array_values(array_unique(array_column($historial, 'proveedor')));
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Operación · <?= e($negocio['nombre']) ?></span>
    <h1 class="pq-h1">Compras</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Anota lo que te llegó del proveedor: sube el inventario y queda el costo real, para saber cuánto te deja cada producto.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($codigoNuevo !== null || $crearNuevo): ?>
  <?php // Llegó algo que la tienda no tenía: se crea ahí mismo y entra a la compra. ?>
  <section class="pq-cierre-form pq-compra-nuevo" id="compra-nuevo" aria-labelledby="pq-titulo-nuevo">
    <h2 class="pq-seccion-titulo" id="pq-titulo-nuevo">Producto nuevo</h2>
    <?php if ($codigoNuevo !== null): ?>
      <p class="pq-ayuda">No tienes ningún producto con el código <strong class="pq-mono"><?= e($codigoNuevo) ?></strong>. Créalo y entra a la compra.</p>
      <?php if ($sugerencia !== null): ?>
        <p class="pq-compra-sugerencia">Otras tiendas Veci lo llaman: <strong>«<?= e($sugerencia) ?>»</strong></p>
      <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="<?= e(base_url('/panel/compras/producto')) ?>" class="pq-compra-nuevo-form">
      <?= csrf_campo() ?>
      <div class="pq-campo">
        <label class="pq-label" for="nuevo-codigo">Código de barras <span class="pq-ayuda">(opcional)</span></label>
        <input class="pq-input pq-mono" id="nuevo-codigo" type="text" name="codigo" maxlength="32" value="<?= e((string) $codigoNuevo) ?>" autocomplete="off" data-campo-lector>
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="nuevo-nombre">Nombre</label>
        <input class="pq-input" id="nuevo-nombre" type="text" name="nombre" maxlength="120" required value="<?= e((string) ($sugerencia ?? '')) ?>">
      </div>
      <fieldset class="pq-mostrador-metodos pq-mostrador-metodos-2">
        <legend class="pq-label">¿Cómo lo vendes?</legend>
        <label class="pq-mostrador-metodo"><input type="radio" name="vende_por" value="unidad" checked><span>Por unidad</span></label>
        <label class="pq-mostrador-metodo"><input type="radio" name="vende_por" value="peso"><span>Por peso (kilo)</span></label>
      </fieldset>
      <div class="pq-domicilios-cifras">
        <div class="pq-campo">
          <label class="pq-label" for="nuevo-precio">Precio de venta</label>
          <input class="pq-input pq-mono" id="nuevo-precio" type="text" inputmode="numeric" name="precio" maxlength="12" required data-precio>
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="nuevo-costo">Te cuesta</label>
          <input class="pq-input pq-mono" id="nuevo-costo" type="text" inputmode="numeric" name="costo" maxlength="12" data-precio>
        </div>
      </div>
      <p class="pq-ayuda">Si va por peso, los dos son por kilo.</p>
      <div class="pq-form-panel-botones">
        <a class="pq-btn pq-btn-ghost" href="<?= e(base_url('/panel/compras')) ?>">Cancelar</a>
        <button type="submit" class="pq-btn pq-btn-sello">Crear y agregar</button>
      </div>
    </form>
  </section>
<?php endif; ?>

<?php if ($busqueda !== ''): ?>
  <section class="pq-mostrador-resultados" id="compra-buscar" aria-labelledby="pq-titulo-buscar">
    <h2 class="pq-seccion-titulo" id="pq-titulo-buscar">Resultados para «<?= e($busqueda) ?>»</h2>
    <?php if ($resultados === []): ?>
      <p class="pq-ayuda">No hay productos con ese nombre. <a href="<?= e(base_url('/panel/compras?crear=1')) ?>#compra-nuevo">Crear uno</a></p>
    <?php else: ?>
      <ul class="pq-admin-tarjeta pq-admin-filas">
        <?php foreach ($resultados as $producto): ?>
          <li class="pq-admin-fila">
            <span class="pq-admin-fila-texto">
              <strong><?= e($producto['nombre']) ?></strong>
              <span class="pq-ayuda"><?= $producto['costo'] !== null ? 'Último costo ' . pesos((int) $producto['costo']) : 'Sin costo anotado' ?></span>
            </span>
            <form method="post" action="<?= e(base_url('/panel/compras/agregar')) ?>">
              <?= csrf_campo() ?>
              <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
              <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Agregar</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php
// Toda la compra en curso es un solo formulario. El primer botón es
// "Agregar": el Enter del lector agrega el código y guarda lo corregido.
?>
<form method="post" action="<?= e(base_url('/panel/compras')) ?>" class="pq-compra" id="compra-form">
  <?= csrf_campo() ?>
  <input type="hidden" name="token" value="<?= e($borrador['token']) ?>">
  <div class="pq-campo">
    <label class="pq-label" for="compra-proveedor">Proveedor</label>
    <input class="pq-input" id="compra-proveedor" type="text" name="proveedor" maxlength="120" value="<?= e($borrador['proveedor']) ?>" placeholder="Ej.: Distribuidora El Surtidor" list="compra-proveedores" autocomplete="off">
    <?php if ($proveedores !== []): ?>
      <datalist id="compra-proveedores">
        <?php foreach ($proveedores as $proveedor): ?><option value="<?= e($proveedor) ?>"></option><?php endforeach; ?>
      </datalist>
    <?php endif; ?>
  </div>

  <div class="pq-mostrador-lector" id="compra-escanear">
    <label class="pq-label" for="compra-codigo">Escanea o escribe el nombre</label>
    <div class="pq-mostrador-lector-fila">
      <span class="pq-mostrador-lector-campo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6v12M7 6v12M10 6v8M13 6v12M16 6v8M20 6v12"/></svg>
        <input class="pq-input" id="compra-codigo" type="text" name="codigo" maxlength="60" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Código o nombre" data-compra-codigo<?= $codigoNuevo === null && !$crearNuevo && $busqueda === '' ? ' autofocus' : '' ?>>
      </span>
      <button type="submit" name="accion" value="agregar" class="pq-btn pq-btn-sello pq-btn-chico">Agregar</button>
    </div>
    <p class="pq-ayuda">Si un producto no está en tu catálogo, lo creas ahí mismo con su precio de venta.</p>
  </div>

  <section id="compra-lineas" aria-labelledby="pq-titulo-lineas">
    <h2 class="pq-seccion-titulo" id="pq-titulo-lineas">Lo que llegó <span class="pq-seccion-cuenta"><?= count($lineas) ?></span></h2>
    <?php if ($lineas === []): ?>
      <p class="pq-ayuda">Todavía no has agregado productos a esta compra.</p>
    <?php else: ?>
      <ul class="pq-compra-lineas">
        <?php foreach ($lineas as $i => $linea): ?>
          <?php
          $p = $linea['producto'];
          $porPeso = $p['vende_por'] === 'peso';
          $id = (int) $p['id'];
          ?>
          <li class="pq-compra-linea">
            <input type="hidden" name="lineas[<?= $i ?>][producto_id]" value="<?= $id ?>">
            <div class="pq-compra-linea-cabeza">
              <strong class="pq-compra-linea-nombre"><?= e($p['nombre']) ?></strong>
              <button type="submit" name="accion" value="quitar-<?= $id ?>" class="pq-enlace-boton pq-enlace-boton-peligro" formnovalidate aria-label="Quitar <?= e($p['nombre']) ?> de la compra">Quitar</button>
            </div>
            <span class="pq-ayuda">
              <?php if ($p['stock'] === null): ?>
                <span class="pq-compra-sin-control">No llevabas la cuenta de este producto: desde esta compra se cuenta, empezando con lo que llegó.</span>
              <?php else: ?>
                Hay <?= e(Producto::existencias($p)) ?> · precio de venta <?= pesos((int) $p['precio']) ?><?= $porPeso ? ' el kilo' : '' ?>
              <?php endif; ?>
            </span>
            <div class="pq-compra-linea-cifras">
              <div class="pq-campo">
                <label class="pq-label" for="linea-cantidad-<?= $id ?>"><?= $porPeso ? 'Kilos' : 'Unidades' ?></label>
                <input class="pq-input pq-mono" id="linea-cantidad-<?= $id ?>" type="text" inputmode="decimal" name="lineas[<?= $i ?>][cantidad]" maxlength="10" value="<?= e($linea['cantidad']) ?>" placeholder="<?= $porPeso ? 'Ej.: 2,5' : '0' ?>">
              </div>
              <div class="pq-campo">
                <label class="pq-label" for="linea-costo-<?= $id ?>">Costo <?= $porPeso ? 'por kilo' : 'c/u' ?></label>
                <input class="pq-input pq-mono" id="linea-costo-<?= $id ?>" type="text" inputmode="numeric" name="lineas[<?= $i ?>][costo]" maxlength="12" value="<?= e($linea['costo']) ?>" data-precio placeholder="0">
              </div>
            </div>
            <?php $margen = Producto::margen(['precio' => $p['precio'], 'costo' => dinero_desde_texto($linea['costo']) ?: null]); ?>
            <?php if ($margen !== null): ?>
              <span class="pq-ayuda<?= $margen['ganancia'] < 0 ? ' pq-compra-perdida' : '' ?>">
                <?= $margen['ganancia'] < 0 ? 'Ojo: lo vendes por debajo de este costo.' : 'Te deja ' . pesos($margen['ganancia']) . ($porPeso ? ' por kilo' : ' por unidad') . ' (' . $margen['porcentaje'] . ' %).' ?>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="pq-compra-total">Total de la compra <strong class="pq-mono"><?= pesos($totalBorrador) ?></strong></p>
      <div class="pq-compra-botones">
        <button type="submit" name="accion" value="vaciar" class="pq-enlace-boton pq-enlace-boton-peligro" formnovalidate>Vaciar</button>
        <button type="submit" name="accion" value="guardar" class="pq-btn pq-btn-sello">Guardar compra</button>
      </div>
      <p class="pq-ayuda">Al guardar, cada cantidad se suma al inventario y el costo queda como el último que pagaste.</p>
    <?php endif; ?>
  </section>
</form>

<?php if ($historial !== []): ?>
  <section class="pq-admin-seccion" aria-labelledby="pq-titulo-historial-compras">
    <h2 class="pq-seccion-titulo" id="pq-titulo-historial-compras">Compras anteriores</h2>
    <ul class="pq-admin-tarjeta pq-admin-filas">
      <?php foreach ($historial as $compra): ?>
        <li class="pq-admin-fila">
          <a class="pq-admin-fila-texto pq-cierre-historial-enlace" href="<?= e(base_url('/panel/compras/' . (int) $compra['id'])) ?>">
            <strong><?= e($compra['proveedor']) ?></strong>
            <span class="pq-ayuda"><?= e(fecha_corta((string) $compra['creado_en'])) ?> · <?= (int) $compra['lineas'] === 1 ? '1 producto' : (int) $compra['lineas'] . ' productos' ?></span>
          </a>
          <span class="pq-mono"><?= pesos((int) $compra['total']) ?></span>
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
