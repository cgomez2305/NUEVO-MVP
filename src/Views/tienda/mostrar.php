<?php require __DIR__ . '/_cabecera.php'; ?>

<?php
// Se agrupa por categoría preservando el orden en que aparece cada
// producto (para no reordenar el catálogo del dueño). Si todo el menú
// sigue en la categoría "General" de siempre, no tiene sentido imprimir
// ese título una sola vez, así que solo se muestran cuando hay 2+.
$porCategoria = [];
foreach ($productos as $producto) {
    $porCategoria[$producto['categoria']][] = $producto;
}
$mostrarTitulos = count($porCategoria) > 1;

// Cuántas unidades de cada producto ya van en el carrito: el botón "+" de
// ese producto lo muestra, así el cliente ve lo que lleva sin abrir el carrito.
$enCarrito = [];
foreach ($carrito['lineas'] as $linea) {
    $enCarrito[(int) $linea['producto']['id']] = \App\Models\Producto::cantidadEnLinea($linea['producto'], (int) $linea['cantidad'], true);
}
$contador = 0;

// "Lo más pedido": solo con pedidos reales de los últimos 30 días (ver
// Producto::masPedidos). Sin datos suficientes no se muestra nada.
$masPedidos = $masPedidos ?? [];
$porId = array_column($productos, null, 'id');
$destacados = array_values(array_filter(array_keys($masPedidos), fn ($id) => isset($porId[$id]) && (int) $porId[$id]['agotado'] === 0));
?>

<?php if ($mostrarTitulos): ?>
  <nav class="pq-categorias" aria-label="Secciones del menú" data-categorias>
    <?php $i = 0; foreach ($porCategoria as $categoria => $items): ?>
      <a href="#seccion-<?= $i ?>" class="pq-categoria<?= $i === 0 ? ' pq-categoria-activa' : '' ?>"><?= e($categoria) ?> <span class="pq-categoria-cuenta"><?= count($items) ?></span></a>
    <?php $i++; endforeach; ?>
  </nav>
<?php endif; ?>

<main class="pq-content-tienda pq-vitrina">
  <?php if (!empty($error)): ?>
    <div class="pq-alerta pq-confirmacion-aviso" role="alert"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($productos === []): ?>
    <div class="pq-vacio-tienda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16l-1.5 12.5a2 2 0 0 1-2 1.5h-9a2 2 0 0 1-2-1.5L4 7Z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg>
      <p><strong>El menú está en preparación.</strong><br>Mientras tanto, puedes escribirle al negocio por WhatsApp.</p>
    </div>
  <?php else: ?>
    <?php if (!empty($repetir)): ?>
      <?php
      // "Me manda lo mismo de la vez pasada": el cliente ya conocido en este
      // celular repite su último pedido de un toque (como lo pediría por
      // WhatsApp). Solo con lo que hoy se puede pedir; el carrito ajusta el resto.
      ?>
      <section class="pq-repetir" aria-labelledby="pq-repetir-titulo">
        <h2 class="pq-repetir-titulo" id="pq-repetir-titulo">Hola, <?= e($repetir['nombre']) ?>. ¿Lo mismo de la vez pasada?</h2>
        <ul class="pq-repetir-lista">
          <?php foreach (array_slice($repetir['lineas'], 0, 4) as $linea): ?>
            <li><span class="pq-repetir-cantidad"><?= e(\App\Models\Producto::esPorPeso($linea['producto']) ? \App\Models\Producto::cantidadEnLinea($linea['producto'], (int) $linea['cantidad'], true) : (int) $linea['cantidad'] . ' ×') ?></span> <?= e($linea['producto']['nombre']) ?></li>
          <?php endforeach; ?>
          <?php if (count($repetir['lineas']) > 4): ?>
            <li class="pq-repetir-mas">y <?= count($repetir['lineas']) - 4 ?> más</li>
          <?php endif; ?>
        </ul>
        <?php if ($repetir['faltan'] > 0): ?>
          <p class="pq-ayuda pq-repetir-nota"><?= $repetir['faltan'] === 1 ? '1 producto ya no está disponible.' : $repetir['faltan'] . ' productos ya no están disponibles.' ?></p>
        <?php endif; ?>
        <div class="pq-repetir-acciones">
          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/repetir')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-btn pq-btn-oscuro pq-btn-chico pq-btn-alto">Pedir lo mismo</button>
          </form>
          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/olvidarme')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-repetir-olvidar">No soy <?= e($repetir['nombre']) ?></button>
          </form>
        </div>
      </section>
    <?php endif; ?>
    <?php if ($destacados !== []): ?>
      <?php
      // El cliente nuevo no sabe qué pedir: esto se lo dice con lo que de
      // verdad piden los demás. Son enlaces a la carta, no una copia de los
      // productos (un solo botón "+" por producto en toda la página).
      ?>
      <section class="pq-mas-pedido" aria-labelledby="pq-mas-pedido-titulo">
        <h2 class="pq-mas-pedido-titulo" id="pq-mas-pedido-titulo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c1 3.5 5 5 5 10a5 5 0 0 1-10 0c0-2 1-3.5 2-4.5.5 2 1.5 3 2.5 3 0-3-1-5.5.5-8.5Z"/></svg>
          Lo más pedido
        </h2>
        <ol class="pq-mas-pedido-lista">
          <?php foreach ($destacados as $posicion => $idDestacado): ?>
            <li>
              <a href="#producto-<?= (int) $idDestacado ?>" class="pq-mas-pedido-item">
                <span class="pq-mas-pedido-puesto"><?= $posicion + 1 ?></span>
                <span class="pq-mas-pedido-nombre"><?= e($porId[$idDestacado]['nombre']) ?></span>
                <span class="pq-mas-pedido-precio"><?= pesos((int) $porId[$idDestacado]['precio']) ?><?= ($porId[$idDestacado]['vende_por'] ?? '') === 'peso' ? ' el kilo' : '' ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>
    <?php $i = 0; foreach ($porCategoria as $categoria => $items): ?>
      <section class="pq-carta" id="seccion-<?= $i ?>" <?= $mostrarTitulos ? 'aria-labelledby="titulo-seccion-' . $i . '"' : 'aria-label="Menú"' ?>>
        <?php if ($mostrarTitulos): ?>
          <h2 class="pq-carta-titulo" id="titulo-seccion-<?= $i ?>"><?= e($categoria) ?></h2>
        <?php endif; ?>
        <div class="pq-carta-lista">
          <?php foreach ($items as $producto): ?>
            <?php
            $id = (int) $producto['id'];
            $agotado = (int) $producto['agotado'] === 1;
            $cantidad = $enCarrito[$id] ?? 0; // "3" o, por peso, "1½ lb"
            ?>
            <article class="pq-plato<?= $agotado ? ' pq-plato-agotado' : '' ?><?= !empty($producto['imagen']) ? ' pq-plato-con-foto' : '' ?>" id="producto-<?= $id ?>" style="--i: <?= $contador++ % 8 ?>">
              <div class="pq-plato-cuerpo">
                <div class="pq-plato-linea">
                  <h3 class="pq-plato-nombre"><?= e($producto['nombre']) ?></h3>
                  <span class="pq-plato-guia" aria-hidden="true"></span>
                  <span class="pq-plato-precio"><?= pesos((int) $producto['precio']) ?><?= ($producto['vende_por'] ?? '') === 'peso' ? ' el kilo' : '' ?></span>
                </div>
                <?php if (\App\Models\Producto::esPorPeso($producto)): ?>
                  <?php // Se pide de a media libra; el precio de la libra se dice de una vez, así nadie hace cuentas. ?>
                  <span class="pq-plato-libra">La libra, <?= pesos(\App\Models\Producto::precioPorGramos((int) $producto['precio'], 500)) ?> · se pide de a media libra</span>
                <?php endif; ?>
                <?php if (!empty($producto['combo'])): ?>
                  <?php // El combo dice qué trae y, si es verdad, cuánto se ahorra frente a pedirlo suelto. ?>
                  <p class="pq-plato-combo">
                    <span class="pq-plato-combo-sello">Combo</span>
                    <?= e(\App\Models\Producto::textoCombo($producto)) ?>
                  </p>
                  <?php if ((int) $producto['precio_separado'] > (int) $producto['precio']): ?>
                    <span class="pq-plato-ahorro">Ahorras <?= pesos((int) $producto['precio_separado'] - (int) $producto['precio']) ?></span>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($producto['descripcion'])): ?>
                  <p class="pq-plato-desc"><?= e($producto['descripcion']) ?></p>
                <?php endif; ?>
                <?php if (isset($masPedidos[$id]) && !$agotado): ?>
                  <span class="pq-plato-favorito">Lo más pedido</span>
                <?php endif; ?>
                <?php if ($agotado): ?>
                  <?php // Solo "por hoy" si de verdad vuelve mañana; un agotado indefinido no promete fecha. ?>
                  <span class="pq-plato-agotado-etiqueta"><?= ($producto['motivo_agotado'] ?? '') === 'hoy' ? 'Agotado por hoy' : 'Agotado' ?></span>
                <?php elseif (($hay = \App\Models\Producto::unidadesDisponibles($producto)) !== null && $hay <= \App\Models\Producto::POCAS_UNIDADES): ?>
                  <span class="pq-plato-quedan"><?= $hay === 1 && !\App\Models\Producto::esPorPeso($producto) ? 'Queda 1' : 'Quedan ' . e(\App\Models\Producto::cantidadEnLinea($producto, $hay)) ?></span>
                <?php endif; ?>
              </div>

              <?php if (!empty($producto['imagen']) || !$agotado): ?>
                <div class="pq-plato-lado">
                  <?php if (!empty($producto['imagen'])): ?>
                    <img class="pq-plato-foto" src="<?= e(base_url($producto['imagen'])) ?>" alt="" loading="lazy" width="88" height="88">
                  <?php endif; ?>
                  <?php if (!$agotado): ?>
                    <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/agregar')) ?>" data-carrito-form="agregar" class="pq-plato-form">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="producto_id" value="<?= $id ?>">
                      <button type="submit" class="pq-agregar<?= $cantidad > 0 ? ' pq-agregar-lleva' : '' ?>" data-agregar-producto="<?= $id ?>" aria-label="Agregar <?= e($producto['nombre']) ?> al pedido">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        <span class="pq-agregar-cuenta" data-cuenta-producto="<?= $id ?>"<?= $cantidad > 0 ? '' : ' hidden' ?>><?= $cantidad ?></span>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php $i++; endforeach; ?>
  <?php endif; ?>

  <?php require __DIR__ . '/_resenas.php'; ?>
  <?php require __DIR__ . '/_informacion.php'; ?>
</main>

<a href="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito')) ?>" class="pq-barra-carrito<?= $carrito['cantidad'] > 0 ? '' : ' pq-barra-carrito-oculta' ?>" id="pq-barra-carrito">
  <span class="pq-barra-carrito-bolsa" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16l-1.5 12.5a2 2 0 0 1-2 1.5h-9a2 2 0 0 1-2-1.5L4 7Z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg>
    <span class="pq-barra-carrito-cuenta" id="pq-barra-carrito-cuenta"><?= $carrito['cantidad'] ?></span>
  </span>
  <span class="pq-barra-carrito-texto">
    <span class="pq-barra-carrito-etiqueta">Ver mi pedido</span>
    <span class="pq-barra-carrito-sub" id="pq-barra-carrito-resumen"><?= $carrito['cantidad'] ?> producto<?= $carrito['cantidad'] === 1 ? '' : 's' ?></span>
  </span>
  <span class="pq-barra-carrito-total" id="pq-barra-carrito-total"><?= pesos($carrito['total']) ?></span>
</a>
