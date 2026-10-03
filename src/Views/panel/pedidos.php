<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Pedidos</span>
    <h1 class="pq-h1">Tus pedidos</h1>
  </div>
  <?php // Tus datos son tuyos: exportar va en todos los planes. Con el historial de 30 días (Gratis) se exporta todo, no solo lo que se ve. ?>
  <?php if ($negocio['rol'] === 'dueno' && !$historialLimitado): ?>
    <a href="<?= e(base_url('/panel/pedidos/exportar.csv') . '?' . http_build_query(array_filter([
        'estado' => $filtro, 'q' => $busqueda, 'rango' => $rango,
        'desde' => $desdePersonalizado, 'hasta' => $hastaPersonalizado,
    ]))) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar <?= $historialTotal ?> pedido<?= $historialTotal === 1 ? '' : 's' ?></a>
  <?php elseif ($negocio['rol'] === 'dueno'): ?>
    <a href="<?= e(base_url('/panel/pedidos/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar todo (CSV)</a>
  <?php endif; ?>
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

// Tiempo que "debería" tomar resolver un pedido: las alertas de espera se
// calculan como % de este valor (ver nivel_espera() en helpers.php), no
// con un minuto fijo para todos los negocios.
$objetivoMin = 20;

// Tablero por columnas (solo desktop, ver .pq-kanban en app.css): agrupa
// los pedidos EN VUELO por etapa. El historial completo (abajo) es una
// cosa aparte — puede tener cientos de registros y no debe usar el mismo
// patrón de tarjetas grandes que el tablero operativo.
$columnasKanban = [
    ['clave' => 'confirmados', 'titulo' => 'Confirmados',    'estados' => ['pendiente', 'pagado'], 'color' => 'var(--sello)'],
    ['clave' => 'cocina',      'titulo' => 'En preparación', 'estados' => ['en_cocina'],            'color' => 'var(--aji)'],
    ['clave' => 'listo',       'titulo' => 'Listos',         'estados' => ['listo'],                'color' => 'var(--caja)'],
    ['clave' => 'camino',      'titulo' => 'En camino',      'estados' => ['en_camino'],            'color' => '#8a5a00'],
];
foreach ($columnasKanban as &$columna) {
    $columna['pedidos'] = array_values(array_filter($activos, fn ($p) => in_array($p['estado'], $columna['estados'], true)));
}
unset($columna);
?>

<?php if ($activos !== []): ?>
<h2 class="pq-seccion-titulo pq-kanban-titulo">En curso <span class="pq-seccion-cuenta"><?= count($activos) ?></span></h2>
<div class="pq-kanban">
  <?php foreach ($columnasKanban as $columna): ?>
    <?php // En celular las etapas vacías se ocultan: apiladas, solo serían ruido. ?>
    <div class="pq-kanban-col<?= $columna['pedidos'] === [] ? ' pq-kanban-col-vacia' : '' ?>">
      <div class="pq-kanban-col-header">
        <span class="pq-kanban-dot" style="background: <?= e($columna['color']) ?>"></span>
        <?= e($columna['titulo']) ?>
        <span class="pq-kanban-count"><?= count($columna['pedidos']) ?></span>
      </div>
      <div class="pq-kanban-cards">
        <?php if ($columna['pedidos'] === []): ?>
          <p class="pq-ayuda pq-kanban-nada">Nada por aquí.</p>
        <?php endif; ?>
        <?php foreach ($columna['pedidos'] as $pedido): ?>
          <?php
          $minutos = minutos_desde((string) $pedido['creado_en']);
          $nivel = nivel_espera($minutos, $objetivoMin);
          $siguiente = \App\Models\Pedido::siguientePaso($pedido);
          ?>
          <div class="pq-kanban-card<?= $nivel === 'prioridad' ? ' pq-card-demorado' : '' ?>">
            <a class="pq-kanban-card-link" href="<?= e(base_url('/panel/pedidos/' . $pedido['id'])) ?>">#<?= (int) $pedido['id'] ?> · <?= e($pedido['cliente_nombre']) ?></a>
            <div class="pq-kanban-card-fila">
              <span class="pq-tiempo-espera pq-kanban-entrega">
                <?= $iconoEntrega($pedido['tipo_entrega']) ?>
                <?= e($etiquetaEntregaCorta($pedido)) ?>
              </span>
              <span class="pq-mono pq-precio-suave pq-kanban-total"><?= pesos((int) $pedido['total']) ?></span>
            </div>
            <?php if (!empty($pedido['notas'])): ?>
              <span class="pq-ayuda pq-nota-corta pq-kanban-nota">"<?= e($pedido['notas']) ?>"</span>
            <?php endif; ?>
            <span class="pq-tiempo-espera pq-tiempo-<?= e($nivel) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
              <?= e(texto_espera($minutos)) ?>
            </span>
            <?php if ($siguiente !== null): ?>
              <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/estado')) ?>" class="pq-kanban-accion">
                <?= csrf_campo() ?>
                <input type="hidden" name="estado" value="<?= e($siguiente['estado']) ?>">
                <input type="hidden" name="volver" value="/panel/pedidos">
                <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico"><?= e($siguiente['texto']) ?> →</button>
              </form>
            <?php endif; ?>
            <?php if (\App\Services\AvisoEstado::pendiente('pedido', $pedido, $negocio)): ?>
              <?php // El cliente todavía no sabe del cambio: un toque y se abre WhatsApp con el aviso escrito. ?>
              <form method="post" action="<?= e(base_url('/panel/pedidos/' . $pedido['id'] . '/avisar')) ?>" target="_blank" class="pq-aviso-estado-form">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-aviso-estado"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.7 14.2c-.2.6-1.4 1.2-2 1.3-.5.1-1.2.2-3.6-.8-3-1.3-5-4.4-5.1-4.6-.2-.2-1.2-1.6-1.2-3 0-1.4.7-2.1 1-2.4.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.8.8 1.9.1.2.1.4 0 .6-.6 1.2-1.2 1.1-.7 1.9.9 1.6 1.9 2.2 3.4 3 .3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.6.8 1.9.9.3.2.5.2.6.4.1.2.1.9-.1 1.5Z"/></svg>Avisarle: <?= e(etiqueta_estado_pedido((string) $pedido['estado'])) ?></button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <div class="pq-kanban-vacio">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
    Todo al día. No tienes pedidos pendientes.
  </div>
<?php endif; ?>

<h2 class="pq-seccion-titulo pq-historial-titulo">Historial</h2>

<form method="get" action="<?= e(base_url('/panel/pedidos')) ?>" class="pq-filtro-historial">
  <input class="pq-input" type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar pedido o cliente...">
  <select class="pq-select" name="estado" data-autoenviar>
    <option value="">Todos los estados</option>
    <?php foreach ($etiquetasEstado as $clave => $texto): ?>
      <option value="<?= e($clave) ?>" <?= $filtro === $clave ? 'selected' : '' ?>><?= e($texto) ?> · <?= (int) ($conteosPorEstado[$clave] ?? 0) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="pq-select" name="rango" data-autoenviar>
    <option value="">Todo el tiempo</option>
    <option value="hoy" <?= $rango === 'hoy' ? 'selected' : '' ?>>Hoy</option>
    <option value="7dias" <?= $rango === '7dias' ? 'selected' : '' ?>>Últimos 7 días</option>
    <option value="mes" <?= $rango === 'mes' ? 'selected' : '' ?>>Este mes</option>
    <option value="mes_pasado" <?= $rango === 'mes_pasado' ? 'selected' : '' ?>>Mes pasado</option>
    <option value="personalizado" <?= $rango === 'personalizado' ? 'selected' : '' ?>>Personalizado...</option>
  </select>
  <span data-mostrar-si="rango=personalizado" class="pq-filtro-fecha-personalizada">
    <input class="pq-input" type="date" name="desde" value="<?= e($desdePersonalizado) ?>" aria-label="Desde">
    <input class="pq-input" type="date" name="hasta" value="<?= e($hastaPersonalizado) ?>" aria-label="Hasta">
  </span>
  <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico pq-filtro-boton">Buscar</button>
</form>

<?php if ($historialLimitado): ?>
  <div class="pq-alerta pq-alerta-aviso pq-historial-aviso">
    Tu plan muestra hasta 30 días de historial. <a href="<?= e(base_url('/panel/plan')) ?>">Sube a Pro</a> para ver el histórico completo y exportarlo a CSV.
  </div>
<?php endif; ?>

<?php
$desdeIdx = $historial === [] ? 0 : (($pagina - 1) * $porPagina) + 1;
$hastaIdx = min($pagina * $porPagina, $historialTotal);
?>
<p class="pq-historial-resumen">
  <strong><?= $historialTotal ?></strong> pedido<?= $historialTotal === 1 ? '' : 's' ?> · <strong><?= pesos($historialSuma) ?></strong> vendidos
  <?= $filtro !== '' || $busqueda !== '' || $rango !== '' ? ' con este filtro' : '' ?>
  <?php if ($historialTotal > $porPagina): ?>
    · mostrando <?= $desdeIdx ?>–<?= $hastaIdx ?>
  <?php endif; ?>
</p>

<?php if ($historial === []): ?>
  <div class="pq-tabla-wrap">
    <p class="pq-tabla-vacia">No hay pedidos que coincidan con la búsqueda.</p>
  </div>
<?php else: ?>
  <div class="pq-tabla-wrap">
    <table class="pq-tabla">
      <thead>
        <tr>
          <th>Pedido</th><th>Cliente</th><th>Entrega</th><th>Fecha</th><th>Estado</th><th>Total</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($historial as $pedido): ?>
          <tr data-href="<?= e(base_url('/panel/pedidos/' . $pedido['id'])) ?>">
            <td class="pq-t-id pq-mono">#<?= (int) $pedido['id'] ?></td>
            <td class="pq-t-cliente"><?= e($pedido['cliente_nombre']) ?></td>
            <td class="pq-t-entrega">
              <span class="pq-t-entrega-contenido">
                <span class="pq-t-icono"><?= $iconoEntrega($pedido['tipo_entrega']) ?></span>
                <?= e($etiquetaEntregaCorta($pedido)) ?>
              </span>
            </td>
            <td class="pq-t-fecha"><?= e(fecha_corta((string) $pedido['creado_en'])) ?></td>
            <td class="pq-t-estado"><span class="pq-chip <?= e(chip_estado($pedido['estado'])) ?>"><?= e($etiquetasEstado[$pedido['estado']]) ?></span></td>
            <td class="pq-t-total pq-mono"><?= pesos((int) $pedido['total']) ?></td>
            <td class="pq-t-ver"><a class="pq-fila-ver" href="<?= e(base_url('/panel/pedidos/' . $pedido['id'])) ?>" aria-label="Ver pedido #<?= (int) $pedido['id'] ?>">›</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPaginas > 1): ?>
    <?php
    $parametrosBase = array_filter(['estado' => $filtro, 'q' => $busqueda, 'rango' => $rango, 'desde' => $desdePersonalizado, 'hasta' => $hastaPersonalizado]);
    $enlacePagina = fn (int $p) => e(base_url('/panel/pedidos') . '?' . http_build_query($parametrosBase + ['pagina' => $p]));
    ?>
    <div class="pq-paginacion">
      <a class="<?= $pagina <= 1 ? 'pq-pagina-deshabilitada' : '' ?>" href="<?= $enlacePagina(max(1, $pagina - 1)) ?>" aria-label="Anterior">‹</a>
      <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
        <?php if ($p === 1 || $p === $totalPaginas || abs($p - $pagina) <= 1): ?>
          <?php if ($p === $pagina): ?>
            <span class="pq-pagina-activa"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= $enlacePagina($p) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php elseif ($p === 2 || $p === $totalPaginas - 1): ?>
          <span class="pq-pagina-puntos">···</span>
        <?php endif; ?>
      <?php endfor; ?>
      <a class="<?= $pagina >= $totalPaginas ? 'pq-pagina-deshabilitada' : '' ?>" href="<?= $enlacePagina(min($totalPaginas, $pagina + 1)) ?>" aria-label="Siguiente">›</a>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
