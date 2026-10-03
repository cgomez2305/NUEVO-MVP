<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Clientes</span>
    <h1 class="pq-h1">Toca repetir</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Clientes a los que ya les toca (o les toca en los próximos 15 días) un servicio que se repite, como el mantenimiento del aire o el retoque del tinte. Solo aparecen quienes pidieron que se lo recordaras.</p>

<?php if ($filas === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.6L20 8.7"/><path d="M20 4v4.7h-4.7"/><path d="M20 12a8 8 0 0 1-13.7 5.6L4 15.3"/><path d="M4 20v-4.7h4.7"/></svg>
    <p><strong>Nadie por recordar.</strong><br>En <a href="<?= e(base_url('/panel/servicios')) ?>">Servicios</a> marca cada cuánto se repite uno: al reservarlo, el cliente puede pedir que se lo recuerdes.</p>
  </div>
<?php else: ?>
  <ul class="pq-admin-tarjeta pq-admin-filas pq-repetir-lista">
    <?php foreach ($filas as $fila): ?>
      <?php $yaToca = (string) $fila['toca_el'] <= date('Y-m-d'); ?>
      <li class="pq-admin-fila">
        <span class="pq-admin-fila-texto">
          <strong><?= e($fila['cliente_nombre']) ?></strong>
          <span class="pq-ayuda">
            <?= e($fila['servicio_nombre']) ?> · el último fue el <?= e(fecha_larga(date('Y-m-d', strtotime((string) $fila['fecha_hora'])))) ?>
            · <span class="<?= $yaToca ? 'pq-repetir-ya' : '' ?>"><?= $yaToca ? 'ya le toca' : 'le toca el ' . e(fecha_larga((string) $fila['toca_el'])) ?></span>
          </span>
        </span>
        <form method="post" action="<?= e(base_url('/panel/repetir/' . (int) $fila['id'])) ?>" target="_blank">
          <?= csrf_campo() ?>
          <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Recordarle</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="pq-ayuda">Se abre WhatsApp con el mensaje y el enlace para agendar. Cada cliente sale de la lista cuando se lo recuerdas o cuando vuelve a reservar.</p>
<?php endif; ?>
