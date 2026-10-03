<?php
$ok = flash_obtener('ok');
$errorCopiloto = flash_obtener('error');
$etiquetas = [
    'inactivo'   => 'Inactivos',
    'vip'        => 'VIP',
    'nuevo'      => 'Nuevos',
    'recurrente' => 'Recurrentes',
    'todos'      => 'Todos',
];
$ayudaSegmento = [
    'inactivo'   => 'Compraban seguido y llevan más tiempo del habitual sin volver.',
    'vip'        => 'Tus clientes que más te compran (mínimo 3 compras).',
    'nuevo'      => 'Hicieron su primera compra hace menos de 30 días.',
    'recurrente' => 'Compran seguido y no necesitan nada especial ahora mismo.',
    'todos'      => 'Todos los clientes con al menos una compra o reserva.',
];
$vacio = [
    'inactivo'   => 'Nadie se está quedando atrás por ahora. Vuelve a revisar mañana.',
    'vip'        => 'Todavía no tienes clientes VIP (se necesitan al menos 3 compras).',
    'nuevo'      => 'No tienes clientes con una primera compra en los últimos 30 días.',
    'recurrente' => 'No tienes clientes en este grupo por ahora.',
    'todos'      => 'Todavía no tienes clientes con compras registradas.',
];
$colorTag = ['inactivo' => 'pq-chip-pendiente', 'vip' => 'pq-chip-caja', 'nuevo' => 'pq-chip-cancelado', 'recurrente' => ''];
?>
<?php if (!empty($errorCopiloto)): ?>
  <div class="pq-alerta" role="alert"><?= e($errorCopiloto) ?></div>
<?php endif; ?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Copiloto</span>
    <h1 class="pq-h1">Clientes para recuperar</h1>
  </div>
  <?php // Tus datos son tuyos: exportar va en todos los planes (solo el dueño descarga). ?>
  <?php if ($negocio['rol'] === 'dueno'): ?>
    <a href="<?= e(base_url('/panel/clientes/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar CSV</a>
  <?php endif; ?>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Veci revisa tus ventas y te dice a quién vale la pena escribirle hoy.</p>

<?php
// Lo recuperado va primero: es el resultado de usar el copiloto, en plata.
$mesNombre = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][(int) date('n') - 1];
?>
<section class="pq-recuperado" aria-labelledby="pq-recuperado-titulo">
  <div class="pq-recuperado-cifra">
    <span class="pq-recuperado-etiqueta" id="pq-recuperado-titulo">Recuperado con Veci en <?= e($mesNombre) ?></span>
    <span class="pq-recuperado-valor pq-mono"><?= pesos((int) $recuperado['total']) ?></span>
  </div>
  <p class="pq-ayuda pq-recuperado-explica">
    <?php if ((int) $recuperado['ventas'] > 0): ?>
      <?= (int) $recuperado['clientes'] === 1 ? '1 cliente volvió' : (int) $recuperado['clientes'] . ' clientes volvieron' ?>
      a <?= ($negocio['tipo_negocio'] ?? '') === 'reservas' ? 'reservar' : 'comprar' ?> en los <?= \App\Models\Copiloto::DIAS_ATRIBUCION ?> días siguientes a tu mensaje<?= (int) $recuperado['contactados'] > 0 ? ' · este mes le escribiste a ' . (int) $recuperado['contactados'] : '' ?>.
    <?php elseif ((int) $recuperado['contactados'] > 0): ?>
      Este mes le escribiste a <?= (int) $recuperado['contactados'] ?>. Si vuelven en los <?= \App\Models\Copiloto::DIAS_ATRIBUCION ?> días siguientes, lo verás sumado aquí.
    <?php else: ?>
      Escríbele a alguien desde aquí: si vuelve en los <?= \App\Models\Copiloto::DIAS_ATRIBUCION ?> días siguientes, su compra se suma aquí.
    <?php endif; ?>
  </p>
  <?php if ($recuperado['detalle'] !== []): ?>
    <ul class="pq-recuperado-lista">
      <?php foreach (array_slice($recuperado['detalle'], 0, 5) as $venta): ?>
        <li><span><?= e($venta['nombre']) ?></span><span class="pq-plato-guia" aria-hidden="true"></span><span class="pq-mono"><?= pesos((int) $venta['monto']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php if (($negocio['tipo_negocio'] ?? '') === 'reservas' && !\App\Models\Visita::esDomicilio($negocio)): ?>
  <a href="<?= e(base_url('/panel/copiloto/huecos')) ?>" class="pq-copiloto-huecos">
    <span><strong>Llena los huecos de tu agenda</strong><span class="pq-ayuda">A quién ya le toca volver y en qué espacio libre cabe.</span></span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
  </a>
<?php endif; ?>
<div class="pq-caja-dia pq-caja-dia-3" role="group" aria-label="Resumen de clientes">
  <div class="pq-caja-casilla<?= $aReactivarCount > 0 ? ' pq-caja-casilla-alerta' : '' ?>">
    <span class="pq-caja-valor"><?= (int) $aReactivarCount ?></span>
    <span class="pq-caja-etiqueta">Por reactivar</span>
  </div>
  <div class="pq-caja-casilla">
    <span class="pq-caja-valor"><?= (int) $vipCount ?></span>
    <span class="pq-caja-etiqueta">Clientes VIP</span>
  </div>
  <div class="pq-caja-casilla">
    <span class="pq-caja-valor"><?= (int) $recompraPct ?>%</span>
    <span class="pq-caja-etiqueta">Recompra del mes</span>
  </div>
</div>

<?php
// Los grupos como pestañas de una sola fila (con scroll lateral en
// celular) y la explicación del grupo elegido a la vista: antes vivía en un
// title="" que en el celular nunca se ve.
?>
<nav class="pq-segmentos" aria-label="Grupos de clientes">
  <?php foreach ($etiquetas as $clave => $texto): ?>
    <a href="<?= e(base_url('/panel/copiloto') . '?segmento=' . $clave) ?>" class="pq-segmento<?= $filtro === $clave ? ' pq-segmento-activo' : '' ?>"<?= $filtro === $clave ? ' aria-current="page"' : '' ?>>
      <?= e($texto) ?><?php if ($clave !== 'todos'): ?> <span class="pq-segmento-cuenta"><?= (int) $conteos[$clave] ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>
<p class="pq-ayuda pq-segmento-ayuda"><?= e($ayudaSegmento[$filtro]) ?></p>
<?php if (!empty($sinPermiso)): ?>
  <p class="pq-ayuda pq-copiloto-permiso-nota">
    <?= (int) $sinPermiso === 1 ? '1 cliente de este grupo no ha' : (int) $sinPermiso . ' clientes de este grupo no han' ?> autorizado promociones por WhatsApp:
    no se les escribe con ofertas (Ley 1581). Puedes pedirles permiso una sola vez.
  </p>
<?php endif; ?>

<?php if ($lista === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/></svg>
    <p><?= e($vacio[$filtro]) ?></p>
  </div>
<?php else: ?>
  <div class="pq-clientes-lista">
    <?php foreach ($lista as $fila): $cliente = $fila['cliente']; $segmentoEfectivo = $filtro === 'todos' ? $fila['tags'][0] : $filtro; ?>
      <div class="pq-cliente-fila">
        <span class="pq-avatar pq-avatar-chico pq-cliente-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($cliente['nombre'], 0, 1))) ?></span>
        <div class="pq-cliente-texto">
          <span class="pq-cliente-nombre">
            <?= e($cliente['nombre']) ?>
            <?php foreach ($fila['tags'] as $tag): ?>
              <span class="pq-chip pq-chip-mini <?= $colorTag[$tag] ?? '' ?>"><?= e($etiquetas[$tag]) ?></span>
            <?php endforeach; ?>
            <?php if (!$fila['contactable']): ?>
              <span class="pq-chip pq-chip-mini pq-chip-sin-permiso">Sin permiso</span>
            <?php endif; ?>
          </span>
          <span class="pq-ayuda">
            <?php if ($segmentoEfectivo === 'inactivo' && $fila['frecuencia_prom'] !== null): ?>
              Compraba cada <?= (int) $fila['frecuencia_prom'] ?> días · lleva <?= (int) $fila['dias_sin_pedir'] ?> sin pedir
            <?php else: ?>
              <?= e($fila['motivo']) ?>
            <?php endif; ?>
          </span>
        </div>
        <div class="pq-cliente-acciones">
          <?php if ($fila['contactable']): ?>
            <a href="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje') . '?segmento=' . $segmentoEfectivo) ?>" class="pq-btn pq-btn-sello pq-btn-chico">Escribir</a>
          <?php elseif (!empty($cliente['permiso_pedido_en'])): ?>
            <span class="pq-ayuda pq-copiloto-permiso-pedido">Permiso pedido el <?= e(explode(' · ', fecha_corta((string) $cliente['permiso_pedido_en']))[0]) ?></span>
          <?php elseif (!empty($cliente['telefono'])): ?>
            <form method="post" action="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/permiso')) ?>" data-confirmar="Se abrirá WhatsApp con un mensaje para preguntarle a <?= e($cliente['nombre']) ?> si quiere recibir promociones, con su enlace para activarlo. Solo se puede pedir una vez.">
              <?= csrf_campo() ?>
              <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Pedir permiso</button>
            </form>
          <?php endif; ?>
          <details class="pq-menu-kebab">
            <summary aria-label="Más acciones para <?= e($cliente['nombre']) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </summary>
            <div class="pq-menu-kebab-panel">
              <?php if ($fila['contactable']): ?>
                <form method="post" action="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/sin-promociones')) ?>" data-confirmar="¿<?= e($cliente['nombre']) ?> te pidió no recibir más promociones? Dejará de aparecer para mensajes; sus pedidos y su historial quedan igual.">
                  <?= csrf_campo() ?>
                  <button type="submit">Me pidió no escribirle más</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar todos los datos de <?= e($cliente['nombre']) ?> (incluye su historial de pedidos/citas)? Esta acción no se puede deshacer.">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-peligro">Eliminar sus datos</button>
              </form>
            </div>
          </details>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($ok): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
