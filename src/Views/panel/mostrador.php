<?php
use App\Models\Producto;
use App\Models\Venta;

$esDueno = $negocio['rol'] === 'dueno';
$total = array_sum(array_column($lineas, 'subtotal'));
$form = $formAnterior ?? [];
$metodoElegido = isset(Venta::METODOS[$form['metodo'] ?? '']) ? $form['metodo'] : 'efectivo';
$clienteElegido = (string) ($form['cliente_id'] ?? '');
$cuentaTexto = static fn (int $n): string => $n === 1 ? '1 producto' : $n . ' productos';
// Atajos de la báscula: como se pide en la tienda ("una libra de queso").
$atajosPeso = [125 => '125 g', 250 => '250 g', 500 => '1 libra', 1000 => '1 kg'];
$iconoMas = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
$iconoMenos = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>';
$iconoQuitar = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Operación · <?= e($negocio['nombre']) ?></span>
    <h1 class="pq-h1">Mostrador</h1>
  </div>
  <?php if ($ventasHoy !== []): ?>
    <a class="pq-btn pq-btn-ghost pq-btn-chico" href="#ventas-hoy">Ventas de hoy · <?= count($ventasHoy) ?></a>
  <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert">
    <?= e($error) ?>
    <?php if (!empty($codigoNuevo)): ?>
      <a class="pq-mostrador-crear" href="<?= e(base_url('/panel/productos/nuevo?codigo=' . rawurlencode((string) $codigoNuevo))) ?>">Crear un producto con este código</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($ventaReciente !== null): ?>
  <?php // La venta que se acaba de cobrar: lo único que importa ya son las vueltas. ?>
  <section class="pq-mostrador-hecha" aria-live="polite">
    <div>
      <strong>Venta #<?= (int) $ventaReciente['id'] ?> · <?= e(Venta::METODOS[$ventaReciente['metodo']] ?? '') ?><?= $ventaReciente['metodo'] === 'fiado' && !empty($ventaReciente['cliente_nombre']) ? ' a ' . e($ventaReciente['cliente_nombre']) : '' ?></strong>
      <span class="pq-ayuda"><?= pesos((int) $ventaReciente['total']) ?><?= (int) $ventaReciente['anulada'] === 1 ? ' · anulada' : '' ?></span>
    </div>
    <?php if ($ventaReciente['metodo'] === 'efectivo'): ?>
      <p class="pq-mostrador-hecha-vueltas"><span>Vueltas</span> <strong class="pq-mono"><?= pesos((int) $ventaReciente['cambio']) ?></strong></p>
    <?php endif; ?>
    <a class="pq-btn pq-btn-ghost pq-btn-chico" href="<?= e(base_url('/panel/mostrador/ventas/' . (int) $ventaReciente['id'])) ?>">Ver tiquete</a>
  </section>
<?php endif; ?>

<div class="pq-mostrador" data-mostrador
     data-catalogo-url="<?= e(base_url('/panel/mostrador/catalogo')) ?>"
     data-sincronizar-url="<?= e(base_url('/panel/mostrador/carrito')) ?>"
     data-crear-url="<?= e(base_url('/panel/productos/nuevo?codigo=')) ?>"
     data-codigos-url="<?= e(base_url('/panel/codigos/')) ?>"
     data-csrf="<?= e(csrf_token()) ?>">
  <div class="pq-mostrador-principal">
    <?php // El campo del lector: el lector USB/Bluetooth "escribe" el código y manda Enter, que agrega. ?>
    <form method="post" action="<?= e(base_url('/panel/mostrador/escanear')) ?>" class="pq-mostrador-lector" data-mostrador-lector autocomplete="off">
      <?= csrf_campo() ?>
      <label class="pq-label" for="mostrador-codigo">Escanea o escribe el nombre</label>
      <div class="pq-mostrador-lector-fila">
        <span class="pq-mostrador-lector-campo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6v12M7 6v12M10 6v8M13 6v12M16 6v8M20 6v12"/></svg>
          <input class="pq-input" id="mostrador-codigo" name="codigo" type="text" maxlength="60" autofocus autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="enter" placeholder="Código o nombre" data-mostrador-codigo aria-describedby="mostrador-aviso">
        </span>
        <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Agregar</button>
      </div>
      <ul class="pq-mostrador-sugerencias" data-mostrador-sugerencias hidden></ul>
      <div class="pq-mostrador-camara-fila">
        <button type="button" class="pq-enlace-boton" data-mostrador-camara hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H18a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="12.5" r="3.4"/></svg>
          Escanear con la cámara
        </button>
      </div>
      <div class="pq-mostrador-camara" data-mostrador-video hidden>
        <video playsinline muted aria-label="Vista de la cámara para leer el código"></video>
        <span class="pq-mostrador-camara-mira" aria-hidden="true"></span>
        <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-mostrador-camara-cerrar>Cerrar cámara</button>
      </div>
      <p class="pq-mostrador-aviso" id="mostrador-aviso" data-mostrador-aviso role="status" aria-live="polite"></p>
    </form>

    <?php if ($busqueda !== ''): ?>
      <section class="pq-mostrador-resultados" aria-labelledby="pq-titulo-resultados">
        <h2 class="pq-seccion-titulo" id="pq-titulo-resultados">Resultados para «<?= e($busqueda) ?>» <span class="pq-seccion-cuenta"><?= count($resultados) ?></span></h2>
        <?php if ($resultados === []): ?>
          <p class="pq-ayuda">No hay productos con ese nombre. <a href="<?= e(base_url('/panel/mostrador')) ?>">Volver</a></p>
        <?php else: ?>
          <ul class="pq-admin-tarjeta pq-admin-filas">
            <?php foreach ($resultados as $producto): ?>
              <li class="pq-admin-fila">
                <span class="pq-admin-fila-texto">
                  <strong><?= e($producto['nombre']) ?></strong>
                  <span class="pq-ayuda pq-mono"><?= pesos((int) $producto['precio']) ?><?= $producto['vende_por'] === 'peso' ? ' el kilo' : '' ?><?= $producto['stock'] !== null ? ' · ' . e(Producto::stockLegible($producto)) : '' ?></span>
                </span>
                <?php if ($producto['vende_por'] === 'peso'): ?>
                  <a class="pq-btn pq-btn-ghost pq-btn-chico" href="<?= e(base_url('/panel/mostrador?pesar=' . (int) $producto['id'])) ?>#bascula">Pesar</a>
                <?php else: ?>
                  <form method="post" action="<?= e(base_url('/panel/mostrador/agregar')) ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                    <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Agregar</button>
                  </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($pesar !== null): ?>
      <?php // La báscula: el producto por peso se vende por gramos, con los atajos de siempre. ?>
      <section class="pq-mostrador-bascula" id="bascula" aria-labelledby="pq-titulo-bascula" data-bascula-servidor>
        <h2 class="pq-mostrador-bascula-titulo" id="pq-titulo-bascula">¿Cuánto lleva de <?= e($pesar['nombre']) ?>?</h2>
        <p class="pq-ayuda"><?= pesos((int) $pesar['precio']) ?> el kilo · se redondea a $50<?= $pesar['stock'] !== null ? ' · ' . e(Producto::stockLegible($pesar)) : '' ?></p>
        <form method="post" action="<?= e(base_url('/panel/mostrador/agregar')) ?>" class="pq-mostrador-pesos">
          <?= csrf_campo() ?>
          <input type="hidden" name="producto_id" value="<?= (int) $pesar['id'] ?>">
          <?php if ($reemplazar): ?><input type="hidden" name="reemplazar" value="1"><?php endif; ?>
          <?php foreach ($atajosPeso as $gramos => $etiqueta): ?>
            <button type="submit" name="gramos" value="<?= $gramos ?>" class="pq-mostrador-peso">
              <strong><?= e($etiqueta) ?></strong>
              <span class="pq-mono"><?= pesos(Producto::precioPorGramos((int) $pesar['precio'], $gramos)) ?></span>
            </button>
          <?php endforeach; ?>
        </form>
        <form method="post" action="<?= e(base_url('/panel/mostrador/agregar')) ?>" class="pq-mostrador-peso-otro">
          <?= csrf_campo() ?>
          <input type="hidden" name="producto_id" value="<?= (int) $pesar['id'] ?>">
          <?php if ($reemplazar): ?><input type="hidden" name="reemplazar" value="1"><?php endif; ?>
          <label class="pq-label" for="bascula-gramos">Otro peso</label>
          <span class="pq-campo-sufijo" data-sufijo="g">
            <input class="pq-input pq-mono" id="bascula-gramos" type="text" inputmode="numeric" name="gramos_otro" maxlength="5" placeholder="Ej.: 380" required>
          </span>
          <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Agregar</button>
        </form>
        <a class="pq-enlace-boton" href="<?= e(base_url('/panel/mostrador')) ?>">Cancelar</a>
      </section>
    <?php endif; ?>

    <?php // El tiquete en curso: el mismo papel de la comanda, como el rollo de la registradora. ?>
    <section class="pq-comanda pq-mostrador-tiquete" aria-labelledby="pq-titulo-tiquete">
      <article class="pq-comanda-hoja">
        <p class="pq-comanda-cabeza" id="pq-titulo-tiquete">
          <span>Venta en curso</span>
          <span data-mostrador-cuenta><?= e($cuentaTexto(count($lineas))) ?></span>
        </p>
        <p class="pq-mostrador-vacio" data-mostrador-vacio<?= $lineas !== [] ? ' hidden' : '' ?>>Escanea el primer producto o búscalo por su nombre.</p>
        <ol class="pq-mostrador-lineas" data-mostrador-lineas>
          <?php foreach ($lineas as $linea): ?>
            <?php $id = (int) $linea['producto_id']; ?>
            <li class="pq-comanda-linea" data-linea="<?= $id ?>">
              <div class="pq-comanda-fila">
                <span class="pq-comanda-nombre"><?= e($linea['nombre']) ?></span>
                <span class="pq-plato-guia" aria-hidden="true"></span>
                <span class="pq-comanda-subtotal"><?= pesos((int) $linea['subtotal']) ?></span>
              </div>
              <div class="pq-comanda-controles">
                <?php if ($linea['por_peso']): ?>
                  <span class="pq-comanda-unitario"><?= e(Producto::gramosLegibles((int) $linea['gramos'])) ?> · <?= pesos((int) $linea['precio_unitario']) ?>/kg</span>
                  <a class="pq-enlace-boton" href="<?= e(base_url('/panel/mostrador?pesar=' . $id . '&reemplazar=1')) ?>#bascula">Cambiar peso</a>
                <?php else: ?>
                  <span class="pq-comanda-unitario"><?= pesos((int) $linea['precio_unitario']) ?> c/u</span>
                  <div class="pq-stepper">
                    <form method="post" action="<?= e(base_url('/panel/mostrador/linea')) ?>">
                      <?= csrf_campo() ?><input type="hidden" name="producto_id" value="<?= $id ?>"><input type="hidden" name="accion" value="menos">
                      <button type="submit" class="pq-stepper-boton" aria-label="Quitar una unidad de <?= e($linea['nombre']) ?>"><?= $iconoMenos ?></button>
                    </form>
                    <span class="pq-stepper-cantidad"><?= (int) $linea['cantidad'] ?></span>
                    <form method="post" action="<?= e(base_url('/panel/mostrador/linea')) ?>">
                      <?= csrf_campo() ?><input type="hidden" name="producto_id" value="<?= $id ?>"><input type="hidden" name="accion" value="mas">
                      <button type="submit" class="pq-stepper-boton" aria-label="Sumar una unidad de <?= e($linea['nombre']) ?>"><?= $iconoMas ?></button>
                    </form>
                  </div>
                <?php endif; ?>
                <form method="post" action="<?= e(base_url('/panel/mostrador/linea')) ?>" class="pq-comanda-quitar">
                  <?= csrf_campo() ?><input type="hidden" name="producto_id" value="<?= $id ?>"><input type="hidden" name="accion" value="quitar">
                  <button type="submit" aria-label="Quitar <?= e($linea['nombre']) ?> del tiquete"><?= $iconoQuitar ?></button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
        <div class="pq-comanda-total">
          <span>Total</span>
          <span data-mostrador-total><?= pesos($total) ?></span>
        </div>
      </article>
    </section>
    <?php if ($lineas !== []): ?>
      <form method="post" action="<?= e(base_url('/panel/mostrador/vaciar')) ?>" class="pq-mostrador-vaciar" data-mostrador-vaciar data-confirmar="¿Borrar todo el tiquete en curso?">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Empezar de nuevo</button>
      </form>
    <?php endif; ?>
  </div>

  <?php // El cobro, con el visor de la registradora: total y vueltas en grande. ?>
  <form method="post" action="<?= e(base_url('/panel/mostrador/cobrar')) ?>" class="pq-mostrador-cobro" id="cobrar" data-mostrador-cobro>
    <?= csrf_campo() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="items_presentes" value="1">
    <div data-mostrador-items>
      <?php foreach ($lineas as $linea): ?>
        <input type="hidden" name="items[<?= (int) $linea['producto_id'] ?>]" value="<?= e(rtrim(rtrim(number_format((float) $linea['cantidad'], 3, '.', ''), '0'), '.')) ?>">
      <?php endforeach; ?>
    </div>

    <div class="pq-mostrador-visor" aria-live="polite">
      <div class="pq-mostrador-visor-fila"><span>Total</span><strong data-mostrador-total-visor><?= pesos($total) ?></strong></div>
      <div class="pq-mostrador-visor-fila pq-mostrador-visor-vueltas" data-mostrador-visor-vueltas hidden><span>Vueltas</span><strong data-mostrador-vueltas>$0</strong></div>
    </div>

    <fieldset class="pq-mostrador-metodos">
      <legend class="pq-label">¿Cómo paga?</legend>
      <?php foreach (Venta::METODOS as $clave => $etiqueta): ?>
        <label class="pq-mostrador-metodo">
          <input type="radio" name="metodo" value="<?= e($clave) ?>"<?= $clave === $metodoElegido ? ' checked' : '' ?>>
          <span><?= e($etiqueta) ?></span>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <div class="pq-campo pq-mostrador-recibido" data-mostrar-si="metodo=efectivo">
      <label class="pq-label" for="mostrador-recibido">¿Con cuánto paga? <span class="pq-ayuda">(vacío = exacto)</span></label>
      <input class="pq-input pq-mono pq-mostrador-recibido-campo" id="mostrador-recibido" type="text" inputmode="numeric" name="recibido" maxlength="24" value="<?= e((string) ($form['recibido'] ?? '')) ?>" placeholder="Exacto" data-precio data-mostrador-recibido>
      <div class="pq-mostrador-billetes" data-mostrador-billetes hidden>
        <?php foreach ([2000, 5000, 10000, 20000, 50000, 100000] as $billete): ?>
          <button type="button" class="pq-mostrador-billete" data-billete="<?= $billete ?>"><?= pesos($billete) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="pq-mostrador-fiado" data-mostrar-si="metodo=fiado">
      <div class="pq-campo">
        <label class="pq-label" for="mostrador-cliente">¿A quién le fías?</label>
        <?php // Con muchos clientes, la lista se filtra escribiendo (JS); sin JS queda la lista entera. ?>
        <input class="pq-input pq-mostrador-cliente-buscar" type="search" placeholder="Buscar por nombre o WhatsApp" aria-label="Buscar cliente para fiar" data-filtro-clientes="#mostrador-cliente" hidden>
        <select class="pq-select" id="mostrador-cliente" name="cliente_id">
          <option value="">Elige el cliente…</option>
          <option value="nuevo"<?= $clienteElegido === 'nuevo' ? ' selected' : '' ?>>+ Cliente nuevo</option>
          <?php foreach ($clientes as $cliente): ?>
            <?php
            $saldoCliente = (int) $cliente['saldo'];
            $detalle = $saldoCliente > 0 ? ' · debe ' . pesos($saldoCliente) : ($saldoCliente < 0 ? ' · a favor ' . pesos(-$saldoCliente) : '');
            if ($cliente['fiado_limite'] !== null) {
                $detalle .= ' · límite ' . pesos((int) $cliente['fiado_limite']);
            }
            ?>
            <option value="<?= (int) $cliente['id'] ?>"<?= $clienteElegido === (string) $cliente['id'] ? ' selected' : '' ?> data-buscar="<?= e($cliente['nombre'] . ' ' . $cliente['telefono']) ?>"><?= e($cliente['nombre'] . $detalle) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="pq-mostrador-cliente-nuevo" data-mostrar-si="cliente_id=nuevo">
        <p class="pq-label">Si es un cliente nuevo</p>
        <p class="pq-ayuda">Si ese WhatsApp ya está guardado, se usa esa cuenta (no se duplica).</p>
        <?php if (!empty($form['cliente_confirmar'])): ?>
          <?php // El WhatsApp ya era de otra persona: se pregunta antes de cargarle la cuenta. ?>
          <label class="pq-consentimiento pq-consentimiento-requerido pq-mostrador-confirmar">
            <input type="checkbox" name="cliente_confirmado" value="<?= (int) $form['cliente_confirmar']['id'] ?>">
            <span>
              <span class="pq-consentimiento-titulo">Sí, es <?= e((string) $form['cliente_confirmar']['nombre']) ?>: fiarle a su cuenta</span>
              <span class="pq-ayuda">Ese WhatsApp ya está guardado con ese nombre. Si no es la misma persona, corrige el número.</span>
            </span>
          </label>
        <?php endif; ?>
        <div class="pq-campo">
          <label class="pq-label" for="mostrador-cliente-nombre">Nombre</label>
          <input class="pq-input" id="mostrador-cliente-nombre" type="text" name="cliente_nombre" maxlength="120" value="<?= e((string) ($form['cliente_nombre'] ?? '')) ?>">
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="mostrador-cliente-telefono">WhatsApp</label>
          <input class="pq-input pq-mono" id="mostrador-cliente-telefono" type="tel" inputmode="numeric" name="cliente_telefono" maxlength="20" value="<?= e((string) ($form['cliente_telefono'] ?? '')) ?>" placeholder="300 123 4567">
        </div>
        <label class="pq-consentimiento pq-consentimiento-requerido">
          <input type="checkbox" name="cliente_autorizo" value="1">
          <span>
            <span class="pq-consentimiento-titulo">El cliente autorizó guardar su nombre y número</span>
            <span class="pq-ayuda">Para llevar su cuenta de fiado y recordarle lo que debe (Ley 1581 de 2012). Pregúntaselo antes de marcar.</span>
          </span>
        </label>
      </div>
    </div>

    <button type="submit" class="pq-btn pq-btn-caja pq-mostrador-cobrar" data-mostrador-cobrar<?= $lineas === [] ? ' disabled' : '' ?>>
      Cobrar <span data-mostrador-total-boton><?= pesos($total) ?></span>
    </button>
  </form>
</div>

<?php if ($ventasHoy !== []): ?>
  <section class="pq-admin-seccion" id="ventas-hoy" aria-labelledby="pq-titulo-ventas-hoy">
    <h2 class="pq-seccion-titulo" id="pq-titulo-ventas-hoy">Ventas de hoy <span class="pq-seccion-cuenta"><?= count($ventasHoy) ?></span></h2>
    <ul class="pq-admin-tarjeta pq-admin-filas">
      <?php foreach ($ventasHoy as $venta): ?>
        <?php $anulada = (int) $venta['anulada'] === 1; ?>
        <li class="pq-admin-fila<?= $anulada ? ' pq-mostrador-venta-anulada' : '' ?>">
          <a class="pq-admin-fila-texto pq-mostrador-venta-enlace" href="<?= e(base_url('/panel/mostrador/ventas/' . (int) $venta['id'])) ?>">
            <strong>#<?= (int) $venta['id'] ?> · <?= e(hora_legible(substr((string) $venta['creado_en'], 11, 5))) ?></strong>
            <span class="pq-ayuda"><?= e(Venta::METODOS[$venta['metodo']] ?? '') ?><?= $venta['metodo'] === 'fiado' && !empty($venta['cliente_nombre']) ? ' · ' . e($venta['cliente_nombre']) : '' ?> · <?= e($cuentaTexto((int) $venta['lineas'])) ?></span>
          </a>
          <span class="pq-mostrador-venta-total">
            <?php if ($anulada): ?><span class="pq-chip pq-chip-cancelado">Anulada</span><?php endif; ?>
            <span class="pq-mono"><?= pesos((int) $venta['total']) ?></span>
          </span>
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
