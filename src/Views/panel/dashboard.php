<?php
$sustantivo = $esReservas ? 'citas' : 'pedidos';
$queEs = $esReservas ? 'agenda' : 'tienda';
// El saludo es para el negocio, no para la sede ("Hola, Sede Norte" sonaba a
// saludarle a un local); la sede aparece en la bajada cuando hay varias.
$nombreNegocio = (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']);
$variasSedes = !empty($negocio['multi_sede']) && trim((string) $negocio['nombre']) !== '' && $negocio['nombre'] !== $nombreNegocio;
$inicialNegocio = mb_strtoupper(mb_substr((string) ($negocio['inicial'] ?? $nombreNegocio), 0, 1));
?>
<span class="pq-eyebrow"><?= e(strftime_es()) ?></span>
<h1 class="pq-h1">Hola, <?= e($nombreNegocio) ?></h1>
<p class="pq-lead pq-inicio-bajada">
  <?= $variasSedes ? 'Así va ' . e($negocio['nombre']) . ' hoy' : 'Así va tu ' . $queEs . ' hoy' ?>
</p>

<?php if ($limitePedidosMes !== null && $usadosEsteMes >= $limitePedidosMes * 0.8): ?>
  <?php $limiteAlcanzado = $usadosEsteMes >= $limitePedidosMes; ?>
  <div class="pq-alerta pq-inicio-limite<?= $limiteAlcanzado ? '' : ' pq-alerta-aviso' ?>">
    <span>
      <?php if ($limiteAlcanzado): ?>
        Llegaste al límite de <?= (int) $limitePedidosMes ?> <?= $sustantivo ?> de este mes del plan Gratis: no se pueden recibir más hasta el próximo mes.
      <?php else: ?>
        Vas en <?= (int) $usadosEsteMes ?> de <?= (int) $limitePedidosMes ?> <?= $sustantivo ?> de este mes del plan Gratis.
      <?php endif; ?>
    </span>
    <a href="<?= e(base_url('/panel/plan')) ?>" class="pq-btn pq-btn-sello pq-btn-chico">Subir de plan →</a>
  </div>
<?php endif; ?>

<?php
// Acción primero, información después: lo que pide una decisión ahora va
// arriba, en una sola lista ordenada por urgencia (rojo: algo se demora;
// sello: falta un paso; verde: plata por ganar). Si no hay nada, se dice.
?>
<section class="pq-hoy" aria-labelledby="pq-titulo-hoy">
  <h2 class="pq-seccion-titulo" id="pq-titulo-hoy">
    <?= $tareas === [] ? 'Todo al día' : (count($tareas) === 1 ? '1 cosa necesita tu atención' : count($tareas) . ' cosas necesitan tu atención') ?>
  </h2>
  <?php if ($tareas === []): ?>
    <p class="pq-hoy-vacio">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
      Nada pendiente ahora mismo. Veci te avisa aquí cuando algo se demore o alguien deba volver.
    </p>
  <?php else: ?>
    <ul class="pq-hoy-lista">
      <?php foreach ($tareas as $tarea): ?>
        <li>
          <a href="<?= e(base_url($tarea['url'])) ?>" class="pq-hoy-tarea pq-hoy-<?= e($tarea['tono']) ?>">
            <span class="pq-hoy-punto" aria-hidden="true"></span>
            <span class="pq-hoy-texto">
              <span class="pq-hoy-titulo"><?= e($tarea['texto']) ?></span>
              <?php if (!empty($tarea['detalle'])): ?><span class="pq-ayuda"><?= e($tarea['detalle']) ?></span><?php endif; ?>
            </span>
            <svg class="pq-hoy-flecha" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php
// Las cifras del día como el cierre de caja de una registradora: una sola
// tira de papel con casillas. Lo recuperado con el copiloto (si lo tiene)
// reemplaza a "recurrentes": es el mismo tema, dicho en plata.
?>
<h2 class="pq-seccion-titulo pq-hoy-cifras-titulo">Tu negocio hoy</h2>
<div class="pq-caja-dia pq-caja-dia-3 pq-caja-dia-hoy" role="group" aria-label="Cifras de hoy">
  <div class="pq-caja-casilla">
    <span class="pq-caja-valor"><?= pesos($ventasHoy) ?></span>
    <span class="pq-caja-etiqueta">Vendido hoy</span>
  </div>
  <div class="pq-caja-casilla">
    <span class="pq-caja-valor"><?= (int) $pedidosHoy ?></span>
    <span class="pq-caja-etiqueta"><?= e(ucfirst($sustantivo)) ?> hoy</span>
  </div>
  <?php if ($recuperado !== null): ?>
    <a href="<?= e(base_url('/panel/copiloto')) ?>" class="pq-caja-casilla">
      <span class="pq-caja-valor"><?= pesos((int) $recuperado['total']) ?></span>
      <span class="pq-caja-etiqueta">Recuperado este mes →</span>
    </a>
  <?php else: ?>
    <div class="pq-caja-casilla">
      <span class="pq-caja-valor"><?= (int) $recompraPct ?>%</span>
      <span class="pq-caja-etiqueta">Recurrentes este mes</span>
    </div>
  <?php endif; ?>
</div>

<?php
// La vitrina: la tienda tal como la ve el cliente (mismo toldo, misma
// insignia y la misma letra de la tienda), dentro del panel. Es el puente
// entre los dos lados y el detalle propio de esta pantalla.
?>
<?php if ((int) $negocio['publicada'] === 1): ?>
  <?php $urlTienda = url_publica('/t/' . $negocio['slug']); ?>
  <section class="pq-escaparate" id="tienda-online" aria-labelledby="pq-escaparate-nombre">
    <div class="pq-toldo pq-toldo-corto" aria-hidden="true"></div>
    <div class="pq-escaparate-cuerpo">
      <div class="pq-escaparate-letrero">
        <span class="pq-letrero-insignia pq-letrero-insignia-chica" aria-hidden="true"><?= e($inicialNegocio) ?></span>
        <div class="pq-escaparate-texto">
          <span class="pq-escaparate-eyebrow">Tu <?= $queEs ?> online · así la ven tus clientes</span>
          <strong class="pq-escaparate-nombre" id="pq-escaparate-nombre"><?= e(nombre_publico_sede($negocio)) ?></strong>
        </div>
      </div>
      <a href="<?= e($urlTienda) ?>" target="_blank" rel="noopener" class="pq-escaparate-url"><?= e(preg_replace('#^https?://#', '', $urlTienda)) ?></a>
      <?php if ($pedidosHoy === 0 && $ventasHoy === 0): ?>
        <p class="pq-ayuda pq-escaparate-nota">Hoy está tranquilo: compártela para empezar a recibir <?= e($sustantivo) ?>.</p>
      <?php endif; ?>
      <div class="pq-escaparate-botones">
        <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-copiar="<?= e($urlTienda) ?>">Copiar enlace</button>
        <a class="pq-btn pq-btn-ghost pq-btn-chico" href="https://wa.me/?text=<?= rawurlencode('Mira mi ' . $queEs . ': ' . $urlTienda) ?>" target="_blank" rel="noopener">Compartir por WhatsApp</a>
      </div>
    </div>
  </section>
<?php else: ?>
  <div class="pq-alerta pq-inicio-bloque">
    Tu <?= $queEs ?> todavía no está publicada.
    <?php if (($negocio['rol'] ?? '') === 'dueno'): ?>
      <a href="<?= e(base_url('/panel/onboarding')) ?>" class="pq-enlace-sello">Termina el alta →</a>
    <?php else: ?>
      El dueño la abre cuando termine de configurarla.
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($esReservas): ?>
  <section class="pq-inicio-bloque" aria-labelledby="pq-titulo-proximas">
    <h2 class="pq-seccion-titulo" id="pq-titulo-proximas">Próximas citas</h2>
    <?php if ($proximasCitas === []): ?>
      <p class="pq-ayuda pq-inicio-vacio">Todavía no tienes citas reservadas.</p>
    <?php else: ?>
      <div class="pq-inicio-lista">
        <?php foreach ($proximasCitas as $cita): ?>
          <?php $citaDemorada = $cita['estado'] === 'pendiente' && nivel_espera(minutos_desde((string) $cita['creado_en']), 15) === 'prioridad'; ?>
          <a href="<?= e(base_url('/panel/citas')) ?>" class="pq-fila-pedido<?= $citaDemorada ? ' pq-card-demorado' : '' ?>">
            <span class="pq-fila-hora"><?= e(date('g:i', strtotime((string) $cita['fecha_hora']) ?: 0)) ?></span>
            <span class="pq-fila-texto">
              <span class="pq-fila-nombre"><?= e($cita['cliente_nombre']) ?></span>
              <span class="pq-ayuda"><?= e($cita['nombre_servicio']) ?> · <span class="pq-fila-fecha"><?= e(fecha_corta((string) $cita['fecha_hora'], ', ')) ?></span></span>
            </span>
            <span class="pq-fila-lado">
              <span class="pq-mono pq-precio-suave"><?= pesos((int) $cita['precio']) ?></span>
              <span class="pq-chip <?= e(chip_estado($cita['estado'])) ?>"><?= e(ucfirst($cita['estado'])) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
      <a href="<?= e(base_url('/panel/citas')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico pq-inicio-ver-todo">Ver toda la agenda</a>
    <?php endif; ?>
  </section>
<?php else: ?>
  <section class="pq-inicio-bloque" aria-labelledby="pq-titulo-ultimos">
    <h2 class="pq-seccion-titulo" id="pq-titulo-ultimos">Últimos pedidos</h2>
    <?php if ($ultimosPedidos === []): ?>
      <p class="pq-ayuda pq-inicio-vacio">Todavía no te han hecho pedidos.</p>
    <?php else: ?>
      <div class="pq-inicio-lista">
        <?php foreach ($ultimosPedidos as $pedido): ?>
          <?php
          $pedidoDemorado = !in_array($pedido['estado'], ['entregado', 'cancelado'], true) && nivel_espera(minutos_desde((string) $pedido['creado_en']), 20) === 'prioridad';
          $etiquetaEntrega = match ($pedido['tipo_entrega']) {
              'recoger' => 'Recoge',
              'mesa'    => 'Mesa ' . $pedido['mesa'],
              default   => 'Domicilio',
          };
          ?>
          <a href="<?= e(base_url('/panel/pedidos/' . $pedido['id'])) ?>" class="pq-fila-pedido<?= $pedidoDemorado ? ' pq-card-demorado' : '' ?>">
            <span class="pq-fila-hora">#<?= (int) $pedido['id'] ?></span>
            <span class="pq-fila-texto">
              <span class="pq-fila-nombre"><?= e($pedido['cliente_nombre']) ?></span>
              <span class="pq-ayuda"><?= e($etiquetaEntrega) ?> · <span class="pq-fila-fecha"><?= e(fecha_corta((string) $pedido['creado_en'], ', ')) ?></span></span>
            </span>
            <span class="pq-fila-lado">
              <span class="pq-mono pq-precio-suave"><?= pesos((int) $pedido['total']) ?></span>
              <span class="pq-chip <?= e(chip_estado($pedido['estado'])) ?>"><?= e(etiqueta_estado_pedido($pedido['estado'])) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
      <a href="<?= e(base_url('/panel/pedidos')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico pq-inicio-ver-todo">Ver todos los pedidos</a>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="pq-inicio-bloque" aria-labelledby="pq-titulo-acciones">
  <h2 class="pq-seccion-titulo" id="pq-titulo-acciones">Acciones rápidas</h2>
  <div class="pq-acciones-rapidas">
    <a href="<?= e(base_url($esReservas ? '/panel/servicios' : '/panel/productos/nuevo')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Agregar <?= $esReservas ? 'servicio' : 'producto' ?>
    </a>
    <a href="<?= e(base_url($esReservas ? '/panel/citas' : '/panel/pedidos')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
      Ver <?= e($sustantivo) ?>
    </a>
    <?php if ($negocio['rol'] === 'dueno'): ?>
      <a href="<?= e(base_url('/panel/copiloto')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/></svg>
        Contactar clientes
      </a>
    <?php endif; ?>
  </div>
</section>

<section class="pq-resumen-semana" aria-labelledby="pq-titulo-semana">
  <h2 class="pq-seccion-titulo" id="pq-titulo-semana">Resumen de esta semana</h2>
  <div class="pq-resumen-semana-fila">
    <span class="pq-resumen-semana-dato"><strong><?= (int) $resumenSemana['pedidos'] ?></strong> <span><?= e($sustantivo) ?></span></span>
    <span class="pq-resumen-semana-dato"><strong><?= pesos($resumenSemana['ventas']) ?></strong> <span>vendidos</span></span>
    <span class="pq-resumen-semana-dato"><strong><?= (int) $resumenSemana['recurrentes'] ?></strong> <span>clientes recurrentes</span></span>
  </div>
</section>
