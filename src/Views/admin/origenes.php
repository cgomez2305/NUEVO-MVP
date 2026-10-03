<?php
/**
 * Campañas: de dónde llegan los negocios (utm_* que el sitio pasa al
 * registro, ver App\Models\OrigenRegistro) y cuántos avanzan hasta pagar.
 * Arriba el embudo del período; abajo una fila por fuente/medio/campaña.
 */
$periodos = ['7' => '7 días', '30' => '30 días', '90' => '90 días', 'todo' => 'Desde siempre'];
$total = ['registros' => 0, 'publicaron' => 0, 'pagaron' => 0, 'con_oferta' => 0];
foreach ($filas as $fila) {
    foreach ($total as $campo => $_) {
        $total[$campo] += $fila[$campo];
    }
}
$porcentaje = static fn (int $parte, int $todo): string => $todo > 0 ? (string) round($parte * 100 / $todo) . '%' : '—';
// Ancho de la barra de cada paso, relativo a los registrados (0 a 100).
$parte = static fn (int $n): int => $total['registros'] > 0 ? (int) round($n * 100 / $total['registros']) : 0;
$texto = static fn (?string $valor): string => $valor !== null && $valor !== '' ? $valor : '—';
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Panel interno</span>
    <h1 class="pq-h1">Campañas</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Negocios registrados según la campaña (utm) con que llegaron del sitio, y cuántos publicaron su tienda y pagaron un plan. Cuenta negocios, no visitas.</p>

<nav class="pq-segmentos" aria-label="Período">
  <?php foreach ($periodos as $clave => $etiqueta): ?>
    <a href="<?= e(base_url('/admin/origenes') . '?periodo=' . $clave) ?>" class="pq-segmento<?= $periodo === (string) $clave ? ' pq-segmento-activo' : '' ?>"<?= $periodo === (string) $clave ? ' aria-current="page"' : '' ?>><?= e($etiqueta) ?></a>
  <?php endforeach; ?>
</nav>

<?php // El embudo del período: cada paso dice cuántos y qué parte de los registrados. ?>
<ol class="pq-embudo" aria-label="Embudo del período">
  <li class="pq-embudo-paso">
    <span class="pq-embudo-barra" style="--parte: <?= $parte($total['registros']) ?>%" aria-hidden="true"></span>
    <span class="pq-embudo-cifra pq-mono"><?= (int) $total['registros'] ?></span>
    <span class="pq-embudo-nombre">Se registraron</span>
  </li>
  <li class="pq-embudo-paso">
    <span class="pq-embudo-barra" style="--parte: <?= $parte($total['publicaron']) ?>%" aria-hidden="true"></span>
    <span class="pq-embudo-cifra pq-mono"><?= (int) $total['publicaron'] ?> <span class="pq-embudo-tasa"><?= e($porcentaje($total['publicaron'], $total['registros'])) ?></span></span>
    <span class="pq-embudo-nombre">Publicaron su tienda</span>
  </li>
  <li class="pq-embudo-paso">
    <span class="pq-embudo-barra" style="--parte: <?= $parte($total['pagaron']) ?>%" aria-hidden="true"></span>
    <span class="pq-embudo-cifra pq-mono"><?= (int) $total['pagaron'] ?> <span class="pq-embudo-tasa"><?= e($porcentaje($total['pagaron'], $total['registros'])) ?></span></span>
    <span class="pq-embudo-nombre">Pagaron un plan</span>
  </li>
  <li class="pq-embudo-paso">
    <span class="pq-embudo-barra" style="--parte: <?= $parte($total['con_oferta']) ?>%" aria-hidden="true"></span>
    <span class="pq-embudo-cifra pq-mono"><?= (int) $total['con_oferta'] ?></span>
    <span class="pq-embudo-nombre">Con código de oferta</span>
  </li>
</ol>

<?php // Lo que gastó la demo pública del sitio (/api/menu-demo) en el mismo período. ?>
<p class="pq-ayuda pq-campanas-demo">Demo «Sube tu foto sin cuenta»: <strong><?= (int) $demo['lecturas'] ?></strong> <?= $demo['lecturas'] === 1 ? 'lectura' : 'lecturas' ?> (<?= (int) $demo['leidas'] ?> con ítems) · <span class="pq-mono"><?= number_format((int) $demo['tokens'], 0, ',', '.') ?></span> tokens.</p>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-campanas">
  <h2 class="pq-seccion-titulo" id="pq-titulo-campanas">Por campaña</h2>
  <?php if ($filas === []): ?>
    <p class="pq-ayuda">Nadie se registró en este período.</p>
  <?php else: ?>
    <div class="pq-tabla-scroll">
      <table class="pq-tabla pq-campanas">
        <thead>
          <tr>
            <th scope="col">Fuente</th><th scope="col">Medio</th><th scope="col">Campaña</th>
            <th scope="col" class="pq-num">Registros</th><th scope="col" class="pq-num">Publicaron</th>
            <th scope="col" class="pq-num">Pagaron</th><th scope="col" class="pq-num">Con oferta</th>
            <th scope="col">Plan que miraban</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $fila): ?>
            <tr>
              <?php if ($fila['fuente'] === null && $fila['medio'] === null && $fila['campana'] === null): ?>
                <th scope="row" colspan="3" class="pq-campana-directa">Sin campaña <span class="pq-ayuda">(directo o sin utm)</span></th>
              <?php else: ?>
                <th scope="row" class="pq-mono"><?= e($texto($fila['fuente'])) ?></th>
                <td class="pq-mono"><?= e($texto($fila['medio'])) ?></td>
                <td class="pq-mono pq-campana-nombre" title="<?= e($texto($fila['campana'])) ?>"><?= e($texto($fila['campana'])) ?></td>
              <?php endif; ?>
              <td class="pq-num pq-mono"><?= (int) $fila['registros'] ?></td>
              <td class="pq-num pq-mono"><?= (int) $fila['publicaron'] ?> <span class="pq-campana-tasa"><?= e($porcentaje($fila['publicaron'], $fila['registros'])) ?></span></td>
              <td class="pq-num pq-mono"><?= (int) $fila['pagaron'] ?> <span class="pq-campana-tasa"><?= e($porcentaje($fila['pagaron'], $fila['registros'])) ?></span></td>
              <td class="pq-num pq-mono"><?= (int) $fila['con_oferta'] ?></td>
              <td><?php
                $interes = array_filter(['Barrio' => $fila['interes_barrio'], 'Pro' => $fila['interes_pro']]);
                echo $interes === [] ? '—' : e(implode(' · ', array_map(fn ($plan, $n) => "{$plan} {$n}", array_keys($interes), $interes)));
              ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
