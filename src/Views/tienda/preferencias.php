<?php
/**
 * Preferencias de mensajes del cliente (sin login, por enlace). Un solo
 * interruptor honesto: qué recibe hoy y un botón para cambiarlo. Los avisos
 * de sus propios pedidos y citas no dependen de esto (son parte del servicio).
 */
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ir a la tienda';
require __DIR__ . '/_cabecera_corta.php';

$marca = (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']);
$recibe = (int) $cliente['acepta_marketing'] === 1;
$primerNombre = explode(' ', trim((string) $cliente['nombre']))[0];
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <?php if (!empty($ok)): ?>
    <div class="pq-alerta pq-alerta-ok pq-confirmacion-aviso" role="status"><?= e($ok) ?></div>
  <?php endif; ?>

  <h1 class="pq-pagina-titulo">Tus mensajes de <?= e($marca) ?></h1>
  <p class="pq-pagina-bajada">Hola, <?= e($primerNombre) ?>. Aquí decides si <?= e($marca) ?> te puede escribir por WhatsApp con promociones y novedades.</p>

  <section class="pq-card pq-preferencias" aria-labelledby="pq-pref-estado">
    <p class="pq-preferencias-estado" id="pq-pref-estado">
      <span class="pq-preferencias-punto<?= $recibe ? ' pq-preferencias-punto-si' : '' ?>" aria-hidden="true"></span>
      <?= $recibe ? 'Recibes promociones y novedades' : 'No recibes promociones' ?>
    </p>
    <?php if (!empty($cliente['marketing_actualizado_en'])): ?>
      <p class="pq-ayuda">Desde el <?= e(fecha_larga(substr((string) $cliente['marketing_actualizado_en'], 0, 10))) ?>.</p>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('/preferencias/' . $token)) ?>">
      <?= csrf_campo() ?>
      <?php if ($recibe): ?>
        <input type="hidden" name="promociones" value="no">
        <button type="submit" class="pq-btn pq-btn-ghost-oscuro pq-btn-ancho">No quiero recibir más promociones</button>
      <?php else: ?>
        <input type="hidden" name="promociones" value="si">
        <button type="submit" class="pq-btn pq-btn-oscuro pq-btn-ancho">Sí, quiero recibir promociones</button>
      <?php endif; ?>
    </form>
  </section>

  <p class="pq-ayuda pq-preferencias-nota">
    Los mensajes sobre tus propios pedidos y citas (confirmaciones, cambios, recordatorios) te siguen llegando: son parte del servicio.
    Para borrar todos tus datos, escríbele a <?= e($marca) ?> por WhatsApp. Más en nuestra
    <a href="https://tuveci.co/privacidad.html" target="_blank" rel="noopener" class="pq-enlace-suave">política de privacidad</a>.
  </p>
</div>
