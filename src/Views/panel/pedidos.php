<?php
$hoyStr = date('Y-m-d');
$entregadosHoy = count(array_filter($todos, fn ($p) => $p['estado'] === 'entregado' && substr((string) $p['creado_en'], 0, 10) === $hoyStr));
?>
<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap">
  <div>
    <span class="pq-eyebrow">Pedidos</span>
    <div style="display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap">
      <h1 class="pq-h1" style="font-size: 28px">Tus pedidos</h1>
      <?php if ($entregadosHoy > 0): ?>
        <span class="pq-ayuda" style="font-size: 12.5px"><?= $entregadosHoy ?> entregados hoy</span>
      <?php endif; ?>
    </div>
  </div>
  <a href="<?= e(base_url('/panel/pedidos/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar CSV</a>
</div>

<?php
$etiquetasEstado = array_combine(\App\Models\Pedido::ESTADOS, array_map('etiqueta_estado_pedido', \App\Models\Pedido::ESTADOS));

$iconoEntrega = static function (string $tipo): string {
    return match ($tipo) {
        'recoger' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/></svg>',
        'mesa'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>',
        default   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.4 7-11.5a7 7 0 1 0-14 0C5 14.6 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.6"/></svg>',
    };
};

$etiquetaEntregaCorta = static function (array $pedido): string {
    return match ($pedido['tipo_entrega']) {
        'recoger' => 'Recoge',
        'mesa'    => 'Mesa ' . $pedido['mesa'],
        default   => 'Domicilio',
    };
};

$etiquetaEntregaLarga = static function (array $pedido): string {
    return match ($pedido['tipo_entrega']) {
        'recoger' => 'Recoge en el local',
        'mesa'    => 'Come en el local · Mesa ' . $pedido['mesa'],
        default   => $pedido['direccion'] ?? 'Domicilio',
    };
};

// Tiempo que "debería" tomar resolver un pedido: las alertas de espera se
// calculan como % de este valor (ver nivel_espera() en helpers.php), no
// con un minuto fijo para todos los negocios.
$objetivoMin = 20;

// Tablero por columnas (solo desktop, ver .pq-kanban en app.css): agrupa
// los pedidos activos por etapa para verlos todos de un vistazo, como una
// cocina real. "Entregados" no vive aquí — ya no requiere ninguna acción,
// así que solo aparece como contador arriba del título; verlo en detalle
// completo es cosa del historial de abajo.
$columnasKanban = [
    ['clave' => 'confirmados', 'titulo' => 'Confirmados',    'estados' => ['pendiente', 'pagado'], 'color' => 'var(--sello)'],
    ['clave' => 'cocina',      'titulo' => 'Preparación',    'estados' => ['en_cocina'],            'color' => 'var(--aji)'],
    ['clave' => 'listo',       'titulo' => 'Listos',         'estados' => ['listo'],                'color' => 'var(--caja)'],
    ['clave' => 'camino',      'titulo' => 'En camino',      'estados' => ['en_camino'],            'color' => '#8a5a00'],
];
foreach ($columnasKanban as &$columna) {
    $columna['pedidos'] = array_values(array_filter($todos, fn ($p) => in_array($p['estado'], $columna['estados'], true)));
}
unset($columna);
?>

<?php if ($total > 0): ?>
  <div class="pq-kanban">
    <?php foreach ($columnasKanban as $columna): ?>
      <div class="pq-kanban-col">
        <div class="pq-kanban-col-header">
          <span class="pq-kanban-dot" style="background: <?= e($columna['color']) ?>"></span>
          <?= e($columna['titulo']) ?>
          <span class="pq-kanban-count"><?= count($columna['pedidos']) ?></span>
        </div>
        <div class="pq-kanban-cards">
          <?php if ($columna['pedidos'] === []): ?>
            <p class="pq-ayuda" style="padding: 4px 2px">Nada por aquí.</p>
          <?php endif; ?>
          <?php foreach ($columna['pedidos'] as $pedido): ?>
            <?php
            $minutos = minutos_desde((string) $pedido['creado_en']);
            $nivel = nivel_espera($minutos, $objetivoMin);
            $siguiente = \App\Models\Pedido::siguientePaso($pedido);
            ?>
            <div class="pq-kanban-card<?= $nivel === 'prioridad' ? ' pq-card-demorado' : '' ?>">
              <a class="pq-kanban-card-link" href="<?= e(base_url('/panel/pedidos/' . $pedido['id'])) ?>">#<?= (int) $pedido['id'] ?> · <?= e($pedido['cliente_nombre']) ?></a>
              <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px">
                <span class="pq-tiempo-espera" style="color: var(--gris-texto)">
                  <?= $iconoEntrega($pedido['tipo_entrega']) ?>
                  <?= e($etiquetaEntregaCorta($pedido)) ?>
                </span>
                <span class="pq-mono pq-precio-suave" style="font-size: 12px; flex-shrink: 0"><?= pesos((int) $pedido['total']) ?></span>
              </div>
              <?php if (!empty($pedido['notas'])): ?>
                <span class="pq-ayuda pq-nota-corta" style="font-size: 11px">"<?= e($pedido['notas']) ?>"</span>
              <?php endif; ?>
              <span class="pq-tiempo-espera pq-tiempo-<?= e($nivel) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                <?= e(texto_espera($minutos)) ?>
              </span>
              <?php if ($siguiente !== null): ?>
                <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>" style="margin-top: 4px">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="estado" value="<?= e($siguiente['estado']) ?>">
                  <input type="hidden" name="volver" value="/panel/pedidos">
                  <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico" style="width: 100%; font-size: 12px; padding: 8px 10px"><?= e($siguiente['texto']) ?> →</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <p class="pq-eyebrow pq-kanban-label">Historial completo</p>
<?php endif; ?>

<?php if ($total > 0): ?>
  <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 16px">
    <a href="<?= e(base_url('/panel/pedidos')) ?>" class="pq-chip <?= $filtro === '' ? 'pq-chip-caja' : '' ?>" style="text-decoration: none">Todos · <?= $total ?></a>
    <?php foreach ($etiquetasEstado as $clave => $texto): ?>
      <?php if (($conteos[$clave] ?? 0) > 0): ?>
        <a href="<?= e(base_url('/panel/pedidos') . '?estado=' . $clave) ?>" class="pq-chip <?= $filtro === $clave ? 'pq-chip-caja' : '' ?>" style="text-decoration: none"><?= e($texto) ?> · <?= $conteos[$clave] ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($pedidos === [] && $total === 0): ?>
  <p class="pq-lead" style="margin-top: 16px">Todavía no te han hecho pedidos.</p>
<?php elseif ($pedidos === []): ?>
  <p class="pq-lead" style="margin-top: 16px">No tienes pedidos en estado «<?= e($etiquetasEstado[$filtro] ?? $filtro) ?>».</p>
<?php else: ?>
  <div class="pq-stack" style="gap: 10px; margin-top: 20px">
    <?php foreach ($pedidos as $pedido): ?>
      <?php
      $pedidoActivo = !in_array($pedido['estado'], ['entregado', 'cancelado'], true);
      $minutosEspera = minutos_desde((string) $pedido['creado_en']);
      $nivelFila = nivel_espera($minutosEspera, $objetivoMin);
      $siguienteFila = \App\Models\Pedido::siguientePaso($pedido);
      ?>
      <div class="pq-card-borde<?= $nivelFila === 'prioridad' && $pedidoActivo ? ' pq-card-demorado' : '' ?>">
        <div style="display: flex; align-items: center; gap: 12px">
          <div class="pq-avatar pq-avatar-chico" style="background: var(--sello)"><?= e(mb_strtoupper(mb_substr($pedido['cliente_nombre'], 0, 1))) ?></div>
          <div class="pq-stack" style="flex-grow: 1">
            <a href="<?= e(base_url('/panel/pedidos/' . $pedido['id'])) ?>" style="font-size: 14px; font-weight: 600; color: inherit; text-decoration: none">
              #<?= (int) $pedido['id'] ?> · <?= e($pedido['cliente_nombre']) ?>
            </a>
            <span class="pq-ayuda"><?= e(date('d M, g:i a', strtotime((string) $pedido['creado_en']))) ?> · <?= e(strtoupper($pedido['metodo_pago'])) ?></span>
            <span class="pq-tiempo-espera" style="color: var(--gris-texto); white-space: normal; align-items: flex-start">
              <?= $iconoEntrega($pedido['tipo_entrega']) ?>
              <?= e($etiquetaEntregaLarga($pedido)) ?>
            </span>
            <?php if (!empty($pedido['notas'])): ?>
              <span class="pq-ayuda pq-nota-corta">"<?= e($pedido['notas']) ?>"</span>
            <?php endif; ?>
          </div>
          <div class="pq-stack" style="align-items: flex-end; gap: 3px">
            <span class="pq-mono pq-precio-suave" style="font-size: 14px"><?= pesos((int) $pedido['total']) ?></span>
            <?php if ($pedidoActivo): ?>
              <span class="pq-tiempo-espera pq-tiempo-<?= e($nivelFila) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                <?= e(texto_espera($minutosEspera)) ?>
              </span>
            <?php else: ?>
              <span class="pq-chip <?= e(chip_estado($pedido['estado'])) ?>"><?= e($etiquetasEstado[$pedido['estado']]) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div style="display: flex; gap: 8px; margin-top: 12px; align-items: center">
          <?php if ($siguienteFila !== null): ?>
            <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>">
              <?= csrf_campo() ?>
              <input type="hidden" name="estado" value="<?= e($siguienteFila['estado']) ?>">
              <input type="hidden" name="volver" value="/panel/pedidos<?= $filtro !== '' ? '?estado=' . e($filtro) : '' ?>">
              <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico"><?= e($siguienteFila['texto']) ?> →</button>
            </form>
          <?php endif; ?>
          <a href="<?= e(base_url('/panel/pedidos/' . $pedido['id'])) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Ver detalle</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
