<?php $volverUrl = '/t/' . $negocio['slug']; $volverTexto = 'Volver al menú'; require __DIR__ . '/_cabecera_corta.php'; ?>

<div class="pq-content-tienda pq-flujo">
  <h1 class="pq-pagina-titulo">Tu pedido</h1>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($carrito['lineas'] === []): ?>
    <div class="pq-vacio-tienda">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16l-1.5 12.5a2 2 0 0 1-2 1.5h-9a2 2 0 0 1-2-1.5L4 7Z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg>
      <p><strong>Tu pedido está vacío.</strong><br>Mira el menú y agrega lo que se te antoje.</p>
      <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-btn pq-btn-oscuro pq-btn-chico pq-vacio-tienda-accion">Ver el menú</a>
    </div>
  <?php else: ?>

  <div class="pq-checkout">
    <?php
    // La comanda: el pedido escrito como el papelito que se pasa a la
    // cocina, con su borde rasgado abajo. Es el detalle propio de esta
    // pantalla; el resto del checkout es deliberadamente sobrio.
    ?>
    <aside class="pq-checkout-resumen" aria-label="Resumen del pedido">
      <div class="pq-comanda">
        <div class="pq-comanda-hoja" id="pq-carrito-lineas">
          <p class="pq-comanda-cabeza">
            <span>Comanda</span>
            <span id="pq-comanda-cuenta"><?= $carrito['cantidad'] ?> producto<?= $carrito['cantidad'] === 1 ? '' : 's' ?></span>
          </p>
          <?php foreach ($carrito['lineas'] as $linea): $producto = $linea['producto']; $id = (int) $producto['id']; ?>
            <div class="pq-comanda-linea" data-fila-producto="<?= $id ?>">
              <div class="pq-comanda-fila">
                <span class="pq-comanda-nombre"><?= e($producto['nombre']) ?></span>
                <span class="pq-plato-guia" aria-hidden="true"></span>
                <span class="pq-comanda-subtotal" id="pq-subtotal-<?= $id ?>"><?= pesos((int) $producto['precio'] * $linea['cantidad']) ?></span>
              </div>
              <?php if (!empty($producto['combo'])): ?>
                <p class="pq-comanda-combo"><?= e(\App\Models\Producto::textoCombo($producto)) ?></p>
              <?php endif; ?>
              <div class="pq-comanda-controles">
                <span class="pq-comanda-unitario"><?= pesos((int) $producto['precio']) ?> <?= ($producto['vende_por'] ?? '') === 'peso' ? 'el kilo' : 'c/u' ?></span>
                <div class="pq-stepper">
                  <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/restar')) ?>" data-carrito-form="restar">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="producto_id" value="<?= $id ?>">
                    <button type="submit" class="pq-stepper-boton" aria-label="Quitar una unidad de <?= e($producto['nombre']) ?>">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>
                    </button>
                  </form>
                  <span class="pq-stepper-cantidad" id="pq-cantidad-<?= $id ?>"><?= $linea['cantidad'] ?></span>
                  <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/agregar')) ?>" data-carrito-form="agregar">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="producto_id" value="<?= $id ?>">
                    <input type="hidden" name="volver" value="carrito">
                    <?php $enTope = $producto['stock'] !== null && $linea['cantidad'] >= (int) $producto['stock']; ?>
                    <button type="submit" class="pq-stepper-boton" aria-label="Agregar una unidad de <?= e($producto['nombre']) ?>"<?= $enTope ? ' disabled title="No hay más unidades"' : '' ?> data-tope="<?= $producto['stock'] !== null ? (int) $producto['stock'] : '' ?>">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                  </form>
                </div>
                <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/quitar')) ?>" data-carrito-form="quitar" class="pq-comanda-quitar">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="producto_id" value="<?= $id ?>">
                  <button type="submit" aria-label="Quitar <?= e($producto['nombre']) ?> del pedido">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>

          <div id="pq-comanda-ajustes"><?php require __DIR__ . '/_comanda_ajustes.php'; ?></div>
          <?php if ($zonas !== []): ?>
            <?php // La llena interacciones.js al elegir zona; sin JS el servidor la suma al pedir. ?>
            <div class="pq-comanda-ajuste" id="pq-comanda-domicilio" hidden>
              <span>Domicilio · <span data-zona-nombre></span></span>
              <span class="pq-plato-guia" aria-hidden="true"></span>
              <span data-zona-costo></span>
            </div>
          <?php endif; ?>

          <div class="pq-comanda-total">
            <span>Total</span>
            <span id="pq-carrito-total" data-total-base="<?= (int) $carrito['total'] ?>"><?= pesos($carrito['total']) ?></span>
          </div>
          <?php if ($zonas !== []): ?>
            <p class="pq-comanda-nota" data-mostrar-si="tipo_entrega=domicilio" data-nota-zona>El domicilio se suma según tu zona (la eliges abajo).</p>
          <?php else: ?>
            <p class="pq-comanda-nota" data-mostrar-si="tipo_entrega=domicilio">Si el domicilio tiene costo, el negocio te lo confirma por WhatsApp: no está sumado arriba.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($carrito['cupon'] === null): ?>
        <?php
        // El cupón se escribe en un recorte punteado bajo la comanda: está
        // a la mano para quien lo tiene y no estorba a quien no.
        ?>
        <details class="pq-cupon-entrada"<?= !empty($errorCupon) ? ' open' : '' ?>>
          <summary>¿Tienes un cupón de descuento?</summary>
          <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/carrito/cupon')) ?>" class="pq-cupon-form">
            <?= csrf_campo() ?>
            <label class="pq-sr-solo" for="cupon">Código del cupón</label>
            <input class="pq-input<?= !empty($errorCupon) ? ' pq-input-invalido' : '' ?>" type="text" id="cupon" name="cupon" value="<?= e($cuponEscrito ?? '') ?>" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="VECI10"<?= !empty($errorCupon) ? ' aria-describedby="cupon-error" aria-invalid="true"' : '' ?>>
            <button type="submit" class="pq-btn pq-btn-oscuro pq-btn-chico">Aplicar</button>
          </form>
          <?php if (!empty($errorCupon)): ?><p class="pq-campo-error" id="cupon-error"><?= e($errorCupon) ?></p><?php endif; ?>
        </details>
      <?php endif; ?>
      <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-comanda-seguir">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Agregar algo más
      </a>
    </aside>

    <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/pedido')) ?>" class="pq-checkout-form" id="pq-form-pedido">
      <?= csrf_campo() ?>

      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true">1</span>Tus datos</h2>

      <div class="pq-campo">
        <label class="pq-label" for="nombre">Tu nombre</label>
        <input class="pq-input" type="text" id="nombre" name="nombre" required maxlength="120" autocomplete="name">
      </div>

      <div class="pq-campo">
        <label class="pq-label" for="telefono">Tu WhatsApp</label>
        <div class="pq-input-telefono">
          <span class="pq-input-telefono-prefijo">🇨🇴 +57</span>
          <input class="pq-input" type="tel" inputmode="numeric" id="telefono" name="telefono" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
        </div>
        <span class="pq-ayuda">Lo usamos para identificar tu pedido y avisarte si algo cambia.</span>
      </div>

      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true">2</span>Entrega</h2>

      <div class="pq-campo">
        <label class="pq-label">¿Cómo lo recibes?</label>
        <div class="pq-opciones-entrega">
          <label class="pq-opcion-entrega">
            <input type="radio" name="tipo_entrega" value="domicilio" checked>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.4 7-11.5a7 7 0 1 0-14 0C5 14.6 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.6"/></svg>
            Domicilio
          </label>
          <label class="pq-opcion-entrega">
            <input type="radio" name="tipo_entrega" value="recoger">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>
            Recoger en tienda
          </label>
          <?php if (!empty($negocio['acepta_mesa'])): ?>
            <label class="pq-opcion-entrega">
              <input type="radio" name="tipo_entrega" value="mesa">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
              Comer aquí
            </label>
          <?php endif; ?>
        </div>
      </div>

      <div data-mostrar-si="tipo_entrega=domicilio">
        <?php if ($zonas !== []): ?>
          <?php
          // Cada zona como una fila de "tarifa de mensajero": nombre a la
          // izquierda, valor a la derecha. Se elige antes de la dirección
          // porque es lo que cambia el total.
          ?>
          <fieldset class="pq-campo pq-zonas">
            <legend class="pq-label">¿A qué zona te lo llevamos?</legend>
            <?php foreach ($zonas as $zona): ?>
              <?php $minZona = (int) $zona['minimo_pedido']; ?>
              <label class="pq-zona">
                <input type="radio" name="zona_id" value="<?= (int) $zona['id'] ?>" data-costo="<?= (int) $zona['costo'] ?>" data-nombre="<?= e($zona['nombre']) ?>" data-requerido-si-visible>
                <span class="pq-zona-nombre"><?= e($zona['nombre']) ?><?php if ($minZona > (int) $carrito['minimo']): ?><span class="pq-zona-min">Pedido mínimo <?= pesos($minZona) ?></span><?php endif; ?></span>
                <span class="pq-zona-costo"><?= e(\App\Models\ZonaDomicilio::etiquetaCosto((int) $zona['costo'])) ?></span>
              </label>
            <?php endforeach; ?>
            <span class="pq-ayuda">¿Tu barrio no está? Escríbele al negocio por WhatsApp antes de pedir.</span>
          </fieldset>
        <?php endif; ?>
        <div class="pq-campo">
          <label class="pq-label" for="direccion">Dirección</label>
          <input class="pq-input" type="text" id="direccion" name="direccion" placeholder="Calle 34 #20-15" maxlength="200" data-requerido-si-visible autocomplete="address-line1">
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="referencia">Barrio o referencia (opcional)</label>
          <input class="pq-input" type="text" id="referencia" name="referencia" placeholder="Edificio azul, apto 302" maxlength="150" autocomplete="address-line2">
        </div>
      </div>

      <div class="pq-campo" data-mostrar-si="tipo_entrega=recoger">
        <span class="pq-label">Recoges en</span>
        <span class="pq-recoger-nombre"><?= e(nombre_publico_sede($negocio)) ?></span>
        <?php if (!empty($negocio['direccion'])): ?>
          <span class="pq-recoger-direccion"><?= e($negocio['direccion']) ?></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($negocio['acepta_mesa'])): ?>
        <div class="pq-campo" data-mostrar-si="tipo_entrega=mesa">
          <label class="pq-label" for="mesa">Número de mesa</label>
          <input class="pq-input" type="text" id="mesa" name="mesa" placeholder="Ej. 4" maxlength="20" data-requerido-si-visible>
        </div>
      <?php endif; ?>

      <div class="pq-campo">
        <label class="pq-label" for="notas">Indicaciones adicionales (opcional)</label>
        <textarea class="pq-input" id="notas" name="notas" rows="2" placeholder="Instrucciones para tu pedido..." maxlength="255"></textarea>
      </div>

      <h2 class="pq-etapa"><span class="pq-etapa-numero" aria-hidden="true">3</span>Pago</h2>

      <div class="pq-campo">
        <label class="pq-label">Forma de pago</label>
        <div class="pq-opciones-entrega">
          <label class="pq-opcion-entrega">
            <input type="radio" name="metodo_pago" value="breb" checked>
            <svg class="pq-opcion-entrega-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/></svg>
            Bre-B
          </label>
          <label class="pq-opcion-entrega">
            <input type="radio" name="metodo_pago" value="nequi">
            <svg class="pq-opcion-entrega-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
            Nequi
          </label>
          <label class="pq-opcion-entrega">
            <input type="radio" name="metodo_pago" value="efectivo">
            <svg class="pq-opcion-entrega-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
            Efectivo
          </label>
        </div>
      </div>

      <div class="pq-consentimientos">
        <label class="pq-consentimiento pq-consentimiento-requerido">
          <input type="checkbox" name="autorizo_datos" value="1" required>
          <span>
            <span class="pq-consentimiento-titulo">Uso de datos para gestionar tu pedido</span>
            <span class="pq-ayuda">Necesario para procesar el pedido y avisarte sobre cambios.</span>
          </span>
        </label>
        <label class="pq-consentimiento">
          <input type="checkbox" name="acepta_marketing" value="1">
          <span>
            <span class="pq-consentimiento-titulo">Promociones por WhatsApp</span>
            <span class="pq-ayuda">Quiero recibir promociones y novedades. Opcional.</span>
          </span>
        </label>
        <span class="pq-ayuda">Al continuar aceptas nuestra <a href="https://tuveci.co/privacidad.html" target="_blank" rel="noopener" class="pq-enlace-suave">política de privacidad</a>.</span>
      </div>

      <div class="pq-checkout-sticky">
        <div class="pq-checkout-sticky-resumen">
          <span class="pq-checkout-sticky-etiqueta">Total</span>
          <span class="pq-checkout-sticky-total" id="pq-checkout-sticky-total"><?= pesos($carrito['total']) ?></span>
        </div>
        <button type="submit" class="pq-btn pq-btn-whatsapp pq-checkout-sticky-boton">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="#0b3d24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>
          Confirmar por WhatsApp
        </button>
      </div>
      <p class="pq-ayuda pq-checkout-pie">Se abre WhatsApp con el pedido escrito: lo revisas antes de enviarlo.</p>
    </form>
  </div>

  <?php endif; ?>
</div>
