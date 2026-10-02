<?php
$ok = flash_obtener('ok');
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
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Copiloto</span>
    <h1 class="pq-h1">Clientes para recuperar</h1>
  </div>
  <?php if (!empty($negocio['incluye_estadisticas_completas'])): ?>
    <a href="<?= e(base_url('/panel/clientes/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar CSV</a>
  <?php else: ?>
    <a href="<?= e(base_url('/panel/plan')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico pq-btn-bloqueado">Exportar CSV · Pro</a>
  <?php endif; ?>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Veci revisa tus ventas y te dice a quién vale la pena escribirle hoy.</p>

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
          <a href="<?= e(base_url('/panel/copiloto/' . $cliente['id'] . '/mensaje') . '?segmento=' . $segmentoEfectivo) ?>" class="pq-btn pq-btn-sello pq-btn-chico">Escribir</a>
          <details class="pq-menu-kebab">
            <summary aria-label="Más acciones para <?= e($cliente['nombre']) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </summary>
            <div class="pq-menu-kebab-panel">
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
