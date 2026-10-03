<?php
/**
 * Cabecera de la tienda pública en la fachada de barrio: toldo rayado con
 * el color del negocio, letrero con su nombre, estado abierto/cerrado real
 * y accesos rápidos (WhatsApp, cómo llegar, horario). Compartida por
 * mostrar.php (pedidos) y servicios.php (reservas); consultorios y
 * despachos usan _membrete.php.
 *
 * Espera: $negocio; opcionales $abiertoAhora, $proximaApertura, $horario, $resenas.
 */
$abiertoAhora = $abiertoAhora ?? null;
$proximaApertura = $proximaApertura ?? null;
$horario = $horario ?? [];
$whatsappNegocio = preg_replace('/\D+/', '', (string) ($negocio['whatsapp'] ?? '')) ?? '';
$direccion = trim((string) ($negocio['direccion'] ?? ''));
// Consultorios y despachos no llevan toldo: tienen su propio membrete.
if (fachada_tienda($negocio) !== 'barrio') {
    require __DIR__ . '/_membrete.php';
    return;
}
?>
<header class="pq-fachada">
  <div class="pq-toldo" aria-hidden="true"></div>

  <div class="pq-letrero">
    <div class="pq-letrero-insignia" aria-hidden="true">
      <?= e($negocio['inicial'] ?? mb_strtoupper(mb_substr((string) $negocio['negocio_nombre'], 0, 1))) ?>
    </div>

    <h1 class="pq-letrero-nombre"><?= e(nombre_publico_sede($negocio)) ?></h1>
    <?php if (!empty($negocio['descripcion'])): ?>
      <p class="pq-letrero-desc"><?= e($negocio['descripcion']) ?></p>
    <?php endif; ?>

    <?php require __DIR__ . '/_letrero_datos.php'; ?>
  </div>
</header>
