<?php
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ir a la tienda';
require __DIR__ . '/_cabecera_corta.php';
$llamado = $turno['estado'] === 'llamado';
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Tu turno</h1>
  <?php if (!empty($ok)): ?>
    <div class="pq-alerta pq-alerta-ok pq-confirmacion-aviso"><?= e($ok) ?></div>
  <?php endif; ?>

  <?php if ($vivo): ?>
    <?php // La ficha de turno como las de la panadería: el número grande, lo demás chiquito. ?>
    <section class="pq-cola-ficha<?= $llamado ? ' pq-cola-ficha-llamado' : '' ?>" aria-live="polite">
      <?php if ($llamado): ?>
        <span class="pq-cola-ficha-etiqueta">¡Ya casi!</span>
        <strong class="pq-cola-ficha-numero">Acércate</strong>
        <p>Te estamos esperando en <?= e(nombre_publico_sede($negocio)) ?>.</p>
      <?php elseif ($posicion['adelante'] === 0): ?>
        <span class="pq-cola-ficha-etiqueta">Eres el siguiente</span>
        <strong class="pq-cola-ficha-numero">1.º</strong>
        <p>Te escribimos por WhatsApp para que te acerques.</p>
      <?php else: ?>
        <span class="pq-cola-ficha-etiqueta">Antes de ti</span>
        <strong class="pq-cola-ficha-numero"><?= (int) $posicion['adelante'] ?></strong>
        <p>
          <?= $posicion['adelante'] === 1 ? 'persona' : 'personas' ?>
          <?php if ($posicion['minutos'] !== null): ?>· unos <?= (int) $posicion['minutos'] ?> min de espera<?php endif; ?>
        </p>
      <?php endif; ?>
      <span class="pq-ayuda"><?= e($turno['cliente_nombre']) ?><?= !empty($turno['servicio_nombre']) ? ' · ' . e($turno['servicio_nombre']) : '' ?><?= !empty($turno['empleado_nombre']) ? ' · con ' . e($turno['empleado_nombre']) : '' ?></span>
    </section>
    <p class="pq-ayuda pq-cola-nota">Esta página se actualiza sola cada 30 segundos. No hace falta que estés pendiente: te avisamos por WhatsApp.</p>
    <form method="post" action="<?= e(base_url('/fila/' . $turno['token'] . '/salir')) ?>" class="pq-gestion-acciones" data-confirmar="¿Salir de la fila?">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-boton-peligro">Ya no voy</button>
    </form>
  <?php else: ?>
    <p class="pq-pagina-bajada">
      <?= match ((string) $turno['estado']) {
          'atendido' => '¡Gracias por venir! Tu turno ya fue atendido.',
          'se_fue'   => 'Saliste de la fila.',
          default    => 'Este turno era de otro día.',
      } ?>
    </p>
    <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-btn pq-btn-oscuro pq-gestion-acciones">Volver a la tienda</a>
  <?php endif; ?>
</div>
