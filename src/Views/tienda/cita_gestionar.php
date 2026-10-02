<?php
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ir a la tienda';
require __DIR__ . '/_cabecera_corta.php';

$sellos = [
    'pendiente'  => ['Pendiente', ''],
    'confirmada' => ['Confirmada', 'ok'],
    'completada' => ['Atendida', 'ok'],
    'cancelada'  => ['Cancelada', 'no'],
];
[$selloTexto, $selloTono] = $sellos[$cita['estado']] ?? [ucfirst((string) $cita['estado']), ''];
$activa = !in_array($cita['estado'], ['cancelada', 'completada'], true);
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Tu cita</h1>
  <p class="pq-pagina-bajada">
    <?php if ($cita['estado'] === 'cancelada'): ?>
      Esta cita fue cancelada. Si quieres, puedes reservar otra cuando gustes.
    <?php elseif ($cita['estado'] === 'completada'): ?>
      Esta cita ya fue atendida. ¡Gracias por venir!
    <?php else: ?>
      Guarda este enlace: desde aquí puedes cambiar la hora o cancelarla.
    <?php endif; ?>
  </p>

  <?php if (!empty($ok)): ?>
    <div class="pq-alerta pq-alerta-ok pq-confirmacion-aviso"><?= e($ok) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="pq-alerta pq-confirmacion-aviso"><?= e($error) ?></div>
  <?php endif; ?>

  <?php require __DIR__ . '/_tiquete_cita.php'; ?>

  <?php if ($activa): ?>
    <div class="pq-gestion-acciones">
      <a href="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/reprogramar')) ?>" class="pq-btn pq-btn-oscuro">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
        Cambiar día u hora
      </a>
      <form method="post" action="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/cancelar')) ?>" data-confirmar="¿Seguro que quieres cancelar tu cita?">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-boton-peligro">Cancelar cita</button>
      </form>
    </div>
  <?php else: ?>
    <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-btn pq-btn-oscuro pq-gestion-acciones">Reservar otra cita</a>
  <?php endif; ?>
</div>
