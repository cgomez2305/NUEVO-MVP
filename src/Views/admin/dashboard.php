<?php
$filtros = \App\Models\Negocio::FILTROS_ADMIN;
$conteos = $resumen['conteos'];
$hayPendientes = $pagosPendientes !== [];
$enlaceFiltro = fn (string $clave) => base_url('/admin') . '?' . http_build_query(array_filter(['filtro' => $clave === 'todos' ? null : $clave, 'q' => $busqueda !== '' ? $busqueda : null]));
$vacio = [
    'todos'        => $busqueda !== '' ? 'Ningún negocio coincide con «' . $busqueda . '».' : 'Todavía no se ha registrado ningún negocio.',
    'por_cobrar'   => 'No hay pagos de plan esperando confirmación.',
    'pagan'        => 'Ningún negocio tiene un plan pago activo.',
    'sin_publicar' => 'Todos los negocios ya abrieron su tienda.',
    'suspendidos'  => 'No hay cuentas suspendidas.',
];
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Panel interno</span>
    <h1 class="pq-h1">Negocios</h1>
  </div>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" role="status"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="pq-caja-dia" role="group" aria-label="Resumen de negocios">
  <div class="pq-caja-casilla">
    <span class="pq-caja-valor"><?= (int) $resumen['total'] ?></span>
    <span class="pq-caja-etiqueta">Negocios</span>
  </div>
  <div class="pq-caja-casilla">
    <span class="pq-caja-valor"><?= (int) $resumen['abiertos'] ?></span>
    <span class="pq-caja-etiqueta">Con tienda abierta</span>
  </div>
  <div class="pq-caja-casilla">
    <span class="pq-caja-valor"><?= (int) $resumen['pagan'] ?></span>
    <span class="pq-caja-etiqueta">Pagan plan</span>
  </div>
  <div class="pq-caja-casilla">
    <span class="pq-caja-valor"><?= (int) $resumen['nuevos_semana'] ?></span>
    <span class="pq-caja-etiqueta">Nuevos en 7 días</span>
  </div>
</div>

<?php if ($hayPendientes): ?>
  <section class="pq-admin-seccion" aria-labelledby="pq-titulo-por-confirmar">
    <h2 class="pq-seccion-titulo" id="pq-titulo-por-confirmar">Por confirmar <span class="pq-admin-cuenta"><?= count($pagosPendientes) ?></span></h2>
    <p class="pq-ayuda">Mira la cuenta Bre-B de Veci y escribe lo que llegó: el plan se activa solo si coincide.</p>
    <div class="pq-consignaciones">
      <?php foreach ($pagosPendientes as $pago): ?>
        <?php $mostrarNegocio = true; $volver = '/admin'; require __DIR__ . '/_consignacion.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-lista">
  <h2 class="pq-sr-solo" id="pq-titulo-lista">Lista de negocios</h2>
  <form method="get" action="<?= e(base_url('/admin')) ?>" class="pq-admin-buscar" role="search">
    <?php if ($filtro !== 'todos'): ?><input type="hidden" name="filtro" value="<?= e($filtro) ?>"><?php endif; ?>
    <label class="pq-sr-solo" for="q">Buscar negocio</label>
    <input class="pq-input" type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre o WhatsApp (3001234567)" maxlength="80">
    <button type="submit" class="pq-btn pq-btn-ghost">Buscar</button>
  </form>

  <nav class="pq-segmentos" aria-label="Filtrar negocios">
    <?php foreach ($filtros as $clave => $texto): ?>
      <a href="<?= e($enlaceFiltro($clave)) ?>" class="pq-segmento<?= $filtro === $clave ? ' pq-segmento-activo' : '' ?>"<?= $filtro === $clave ? ' aria-current="page"' : '' ?>>
        <?= e($texto) ?> <span class="pq-segmento-cuenta"><?= (int) $conteos[$clave] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($negocios === []): ?>
    <div class="pq-vacio-panel">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9h18l-1.5-5h-15Z"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/></svg>
      <p><?= e($vacio[$filtro]) ?></p>
    </div>
  <?php else: ?>
    <ul class="pq-admin-lista">
      <?php foreach ($negocios as $fila): ?>
        <?php
        $color = color_seguro($fila['color_marca'] ?? null);
        $inicial = mb_strtoupper(mb_substr((string) $fila['nombre'], 0, 1));
        $diasCreado = dias_desde((string) $fila['creado_en']);
        $publicadas = (int) $fila['sedes_publicadas'];
        $totalSedes = (int) $fila['total_sedes'];
        ?>
        <li>
          <a href="<?= e(base_url('/admin/negocios/' . (int) $fila['id'])) ?>" class="pq-admin-negocio<?= (int) $fila['suspendido'] === 1 ? ' pq-admin-negocio-suspendido' : '' ?>">
            <span class="pq-letrero-insignia pq-letrero-insignia-chica pq-admin-insignia" style="--marca: <?= e($color) ?>; --marca-sobre: <?= e(color_texto_sobre($color)) ?>" aria-hidden="true"><?= e($inicial) ?></span>
            <span class="pq-admin-negocio-texto">
              <span class="pq-admin-negocio-nombre"><?= e($fila['nombre']) ?></span>
              <span class="pq-ayuda">
                <?= $fila['tipo_negocio'] === 'reservas' ? 'Reservas' : 'Pedidos' ?>
                · Plan <?= e(ucfirst((string) $fila['plan_nombre'])) ?>
                · <?= $publicadas ?>/<?= $totalSedes ?> sede<?= $totalSedes === 1 ? '' : 's' ?> abierta<?= $publicadas === 1 ? '' : 's' ?>
              </span>
            </span>
            <span class="pq-admin-negocio-lado">
              <?php if ((int) $fila['suspendido'] === 1): ?>
                <span class="pq-chip pq-chip-cancelado">Suspendido</span>
              <?php elseif ((int) $fila['pagos_pendientes'] > 0): ?>
                <span class="pq-chip pq-chip-pendiente">Pago por confirmar</span>
              <?php elseif ($publicadas === 0): ?>
                <span class="pq-chip">Sin abrir</span>
              <?php endif; ?>
              <span class="pq-ayuda">Se unió <?= e(hace_dias($diasCreado)) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
