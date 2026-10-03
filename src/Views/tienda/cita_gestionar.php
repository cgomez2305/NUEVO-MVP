<?php
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ir a la tienda';
require __DIR__ . '/_cabecera_corta.php';

$sellos = [
    'pendiente'  => ['Pendiente', ''],
    'confirmada' => ['Confirmada', 'ok'],
    'en_curso'   => ['En curso', 'ok'],
    'completada' => ['Atendida', 'ok'],
    'no_asistio' => ['No asistió', 'no'],
    'cancelada'  => ['Cancelada', 'no'],
];
[$selloTexto, $selloTono] = $sellos[$cita['estado']] ?? [ucfirst((string) $cita['estado']), ''];
$activa = in_array($cita['estado'], ['pendiente', 'confirmada'], true);
$tsCita = strtotime((string) $cita['fecha_hora']) ?: 0;
$esHoy = date('Y-m-d', $tsCita) === date('Y-m-d');
$motivosDia = \App\Models\Imprevisto::MOTIVOS_DIA;
$ajustePendiente = $cita['ajuste_estado'] === 'pendiente' && in_array($cita['estado'], ['pendiente', 'confirmada', 'en_curso'], true);
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Tu cita</h1>
  <p class="pq-pagina-bajada">
    <?php if ($cita['estado'] === 'cancelada'): ?>
      Esta cita fue cancelada. Si quieres, puedes reservar otra cuando gustes.
    <?php elseif ($cita['estado'] === 'completada'): ?>
      Esta cita ya fue atendida. ¡Gracias por venir!
    <?php elseif ($cita['estado'] === 'no_asistio'): ?>
      El negocio marcó que no pudiste llegar a esta cita. Si fue un error, escríbele por WhatsApp.
    <?php elseif ($cita['estado'] === 'en_curso'): ?>
      Tu cita está en curso.
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

  <?php // Lo que cambió va antes del tiquete: es lo que el cliente vino a ver. ?>
  <?php if ($ajustePendiente): ?>
    <section class="pq-imprevisto-aviso pq-imprevisto-ajuste" aria-labelledby="pq-ajuste-titulo">
      <h2 id="pq-ajuste-titulo">El valor de tu <?= e(mb_strtolower((string) $cita['nombre_servicio'])) ?> cambió</h2>
      <p><?= e($cita['ajuste_motivo']) ?></p>
      <p class="pq-imprevisto-precios"><s><?= e(precio_texto($cita)) ?></s> <strong><?= pesos((int) $cita['ajuste_precio']) ?></strong></p>
      <div class="pq-imprevisto-botones">
        <form method="post" action="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/ajuste')) ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="respuesta" value="aprobar">
          <button type="submit" class="pq-btn pq-btn-oscuro">Apruebo el nuevo valor</button>
        </form>
        <form method="post" action="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/ajuste')) ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="respuesta" value="rechazar">
          <button type="submit" class="pq-btn pq-btn-ghost-oscuro">No lo apruebo</button>
        </form>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($activa && !empty($cita['imprevisto_motivo'])): ?>
    <section class="pq-imprevisto-aviso" aria-labelledby="pq-imprevisto-titulo">
      <h2 id="pq-imprevisto-titulo">Tenemos que mover tu cita</h2>
      <p>El negocio tuvo <?= e($motivosDia[(string) $cita['imprevisto_motivo']] ?? 'un imprevisto') ?> y no va a poder atenderte a esa hora. Elige otra hora, sin costo<?= $cita['anticipo_estado'] === 'pagado' ? ': tu anticipo sigue valiendo' : '' ?>.</p>
      <a href="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/reprogramar')) ?>" class="pq-btn pq-btn-oscuro">Elegir otra hora</a>
    </section>
  <?php elseif ($activa && $esHoy && (int) $cita['retraso_negocio_min'] > 0): ?>
    <section class="pq-imprevisto-aviso" aria-labelledby="pq-retraso-titulo">
      <h2 id="pq-retraso-titulo">Vamos con unos <?= (int) $cita['retraso_negocio_min'] ?> minutos de retraso</h2>
      <p>Tu turno empezaría hacia las <strong><?= e(hora_legible(date('H:i', $tsCita + (int) $cita['retraso_negocio_min'] * 60))) ?></strong>. Perdón por la espera.</p>
      <?php if ((int) $cita['cliente_espera'] === 1): ?>
        <p class="pq-imprevisto-hecho">Nos dijiste que esperas. ¡Gracias!</p>
      <?php else: ?>
        <div class="pq-imprevisto-botones">
          <form method="post" action="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/espero')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-btn pq-btn-oscuro">Espero, no hay problema</button>
          </form>
          <a href="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/reprogramar')) ?>" class="pq-btn pq-btn-ghost-oscuro">Mejor otra hora</a>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php require __DIR__ . '/_tiquete_cita.php'; ?>

  <?php if ($activa && $esHoy && empty($cita['imprevisto_motivo'])): ?>
    <?php // "Llego tarde": la cortesía de avisar, con la tolerancia del negocio a la vista. ?>
    <details class="pq-llego-tarde"<?= (int) $cita['retraso_cliente_min'] > 0 ? ' open' : '' ?>>
      <summary>¿Se te hizo tarde?</summary>
      <?php if ((int) $cita['retraso_cliente_min'] > 0): ?>
        <p class="pq-imprevisto-hecho">Le avisaste al negocio que llegas unos <?= (int) $cita['retraso_cliente_min'] ?> minutos tarde.</p>
      <?php endif; ?>
      <p class="pq-ayuda">Te esperan hasta <?= (int) $negocio['tolerancia_min'] ?> minutos. Si vas a llegar más tarde, mejor cambia la hora.</p>
      <form method="post" action="<?= e(base_url('/cita/' . $cita['token_gestion'] . '/tarde')) ?>" class="pq-llego-tarde-opciones">
        <?= csrf_campo() ?>
        <?php foreach ([5, 10, 15, 20, 30] as $minutos): ?>
          <button type="submit" name="minutos" value="<?= $minutos ?>" class="pq-chip-opcion<?= $minutos > (int) $negocio['tolerancia_min'] ? ' pq-chip-opcion-tarde' : '' ?>"><?= $minutos ?> min</button>
        <?php endforeach; ?>
      </form>
    </details>
  <?php endif; ?>

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
