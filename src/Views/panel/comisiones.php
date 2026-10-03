<?php
$conComision = array_values(array_filter($liquidacion, fn ($f) => $f['pct'] !== null));
$totalComisiones = array_sum(array_map(fn ($f) => (int) $f['comision'], $conComision));
$urlPeriodo = fn (string $clave, ?int $empleadoId = null) => base_url('/panel/comisiones') . '?periodo=' . $clave . ($empleadoId !== null ? '&empleado=' . $empleadoId : '');
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Equipo</span>
    <h1 class="pq-h1">Comisiones</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Lo que vendió cada persona y lo que le toca, sobre lo cobrado de verdad en citas atendidas (sin canceladas ni «no vino»). Las sesiones de un bono cuentan por el valor del servicio. Veci calcula; el pago lo haces tú.</p>

<nav class="pq-segmentos" aria-label="Período">
  <?php foreach ($periodos as $claveP => $p): ?>
    <a href="<?= e($urlPeriodo($claveP, $empleado !== null ? (int) $empleado['id'] : null)) ?>" class="pq-segmento<?= $claveP === $clave ? ' pq-segmento-activo' : '' ?>"<?= $claveP === $clave ? ' aria-current="page"' : '' ?>><?= e($p['etiqueta']) ?></a>
  <?php endforeach; ?>
</nav>
<p class="pq-ayuda"><?= e(fecha_larga($periodo['desde'])) ?> a <?= e(fecha_larga($periodo['hasta'])) ?></p>

<?php if ($liquidacion === []): ?>
  <div class="pq-vacio-panel"><p><strong>Todavía no tienes equipo.</strong><br>Agrega a tu gente en <a href="<?= e(base_url('/panel/empleados')) ?>">Equipo</a> y ponles su porcentaje.</p></div>
<?php else: ?>
  <ul class="pq-admin-tarjeta pq-admin-filas pq-comision-lista">
    <?php foreach ($liquidacion as $fila): ?>
      <li class="pq-admin-fila">
        <span class="pq-admin-fila-texto">
          <strong><?= e($fila['nombre']) ?></strong>
          <span class="pq-ayuda"><?= (int) $fila['citas'] ?> cita<?= $fila['citas'] === 1 ? '' : 's' ?> · vendió <span class="pq-mono"><?= pesos($fila['vendido']) ?></span><?= $fila['pct'] !== null ? ' · ' . $fila['pct'] . '%' : ' · sin comisión' ?></span>
        </span>
        <?php if ($fila['comision'] !== null): ?>
          <a class="pq-comision-monto" href="<?= e($urlPeriodo($clave, $fila['id'])) ?>#detalle"><?= pesos($fila['comision']) ?></a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($conComision !== []): ?>
    <p class="pq-comision-total">Total en comisiones: <strong class="pq-mono"><?= pesos($totalComisiones) ?></strong></p>
  <?php else: ?>
    <p class="pq-ayuda">Nadie tiene porcentaje configurado. Ponlo en la ficha de cada persona en <a href="<?= e(base_url('/panel/empleados')) ?>">Equipo</a>.</p>
  <?php endif; ?>
<?php endif; ?>

<?php if ($empleado !== null): ?>
  <?php
  $pct = $empleado['comision_pct'] !== null ? (int) $empleado['comision_pct'] : null;
  $totalDetalle = array_sum(array_map(fn ($c) => (int) $c['valor'], $detalle));
  ?>
  <?php // La liquidación como un tiquete imprimible, para entregarla con la plata. ?>
  <section class="pq-comanda pq-comanda-imprimible pq-comision-tiquete" id="detalle" aria-labelledby="pq-detalle-titulo">
    <div class="pq-comanda-hoja">
      <p class="pq-comanda-cabeza"><span id="pq-detalle-titulo">Liquidación · <?= e($empleado['nombre']) ?></span><span><?= e($periodo['etiqueta']) ?></span></p>
      <?php if ($detalle === []): ?>
        <p class="pq-ayuda">Sin citas atendidas en este período.</p>
      <?php endif; ?>
      <?php foreach ($detalle as $c): ?>
        <div class="pq-comanda-fila">
          <span class="pq-comanda-nombre"><?= e(fecha_corta((string) $c['fecha_hora'])) ?> · <?= e($c['nombre_servicio']) ?> · <?= e($c['cliente_nombre']) ?></span>
          <span class="pq-plato-guia" aria-hidden="true"></span>
          <span class="pq-comanda-subtotal"><?= pesos((int) $c['valor']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="pq-comanda-ajuste"><span><strong>Vendido</strong></span><span class="pq-plato-guia" aria-hidden="true"></span><span><strong><?= pesos($totalDetalle) ?></strong></span></div>
      <?php if ($pct !== null): ?>
        <div class="pq-comanda-ajuste"><span><strong>Comisión <?= $pct ?>%</strong></span><span class="pq-plato-guia" aria-hidden="true"></span><span><strong><?= pesos((int) round($totalDetalle * $pct / 100)) ?></strong></span></div>
      <?php endif; ?>
    </div>
  </section>
  <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-imprimir>Imprimir liquidación</button>
<?php endif; ?>
