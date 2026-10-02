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
    $enCarrito[(int) $linea['producto']['id']] = (int) $linea['cantidad'];
}
$contador = 0;
?>

<?php if ($mostrarTitulos): ?>
  <nav class="pq-categorias" aria-label="Secciones del menú" data-categorias>
    <?php $i = 0; foreach ($porCategoria as $categoria => $items): ?>
      <a href="#seccion-<?= $i ?>" class="pq-categoria<?= $i === 0 ? ' pq-categoria-activa' : '' ?>"><?= e($categoria) ?> <span class="pq-categoria-cuenta"><?= count($items) ?></span></a>
    <?php $i++; endforeach; ?>
  </nav>
<?php endif; ?>

<main class="pq-content-tienda pq-vitrina">
  <?php if ($productos === []): ?>
    <div class="pq-vacio-tienda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16l-1.5 12.5a2 2 0 0 1-2 1.5h-9a2 2 0 0 1-2-1.5L4 7Z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg>
      <p><strong>El menú está en preparación.</strong><br>Mientras tanto, puedes escribirle al negocio por WhatsApp.</p>
    </div>
  <?php else: ?>
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
            $cantidad = $enCarrito[$id] ?? 0;
            ?>
            <article class="pq-plato<?= $agotado ? ' pq-plato-agotado' : '' ?><?= !empty($producto['imagen']) ? ' pq-plato-con-foto' : '' ?>" style="--i: <?= $contador++ % 8 ?>">
              <div class="pq-plato-cuerpo">
                <div class="pq-plato-linea">
                  <h3 class="pq-plato-nombre"><?= e($producto['nombre']) ?></h3>
                  <span class="pq-plato-guia" aria-hidden="true"></span>
                  <span class="pq-plato-precio"><?= pesos((int) $producto['precio']) ?></span>
                </div>
                <?php if (!empty($producto['descripcion'])): ?>
                  <p class="pq-plato-desc"><?= e($producto['descripcion']) ?></p>
                <?php endif; ?>
                <?php if ($agotado): ?>
                  <?php // Solo "por hoy" si de verdad vuelve mañana; un agotado indefinido no promete fecha. ?>
                  <span class="pq-plato-agotado-etiqueta"><?= ($producto['motivo_agotado'] ?? '') === 'hoy' ? 'Agotado por hoy' : 'Agotado' ?></span>
                <?php elseif ($producto['stock'] !== null && (int) $producto['stock'] <= \App\Models\Producto::POCAS_UNIDADES): ?>
                  <span class="pq-plato-quedan"><?= (int) $producto['stock'] === 1 ? 'Queda 1' : 'Quedan ' . (int) $producto['stock'] ?></span>
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
