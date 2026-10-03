<?php
$volverUrl = '/t/' . $negocio['slug'];
$volverTexto = 'Ir a la tienda';
require __DIR__ . '/_cabecera_corta.php';
?>
<div class="pq-content-tienda pq-flujo pq-flujo-angosto">
  <h1 class="pq-pagina-titulo">Haz la fila sin estar en la fila</h1>
  <?php if (!$abierta): ?>
    <p class="pq-pagina-bajada">La fila está cerrada en este momento. Puedes reservar una cita con hora fija.</p>
    <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-btn pq-btn-oscuro pq-gestion-acciones">Ver horarios para reservar</a>
  <?php else: ?>
    <p class="pq-pagina-bajada">
      Anótate y espera donde quieras: te escribimos por WhatsApp cuando se acerque tu turno.
      <?php if ($enFila > 0): ?><strong>Hay <?= (int) $enFila ?> en la fila.</strong><?php else: ?><strong>No hay nadie esperando.</strong><?php endif; ?>
    </p>
    <?php if (!empty($error)): ?>
      <div class="pq-alerta" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(base_url('/t/' . $negocio['slug'] . '/fila')) ?>" class="pq-confirmar-form pq-cola-form">
      <?= csrf_campo() ?>
      <div class="pq-campo">
        <label class="pq-label" for="fila-nombre">Tu nombre</label>
        <input class="pq-input" type="text" id="fila-nombre" name="nombre" required maxlength="120" autocomplete="name">
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="fila-telefono">Tu WhatsApp</label>
        <div class="pq-input-telefono">
          <span class="pq-input-telefono-prefijo">🇨🇴 +57</span>
          <input class="pq-input" type="tel" inputmode="numeric" id="fila-telefono" name="telefono" placeholder="300 123 4567" required maxlength="20" autocomplete="tel-national">
        </div>
      </div>
      <?php if ($servicios !== []): ?>
        <div class="pq-campo">
          <label class="pq-label" for="fila-servicio">¿Qué te vas a hacer? <span class="pq-ayuda">(opcional)</span></label>
          <select class="pq-select pq-input" id="fila-servicio" name="servicio_id">
            <option value="0">Todavía no sé</option>
            <?php foreach ($servicios as $servicio): ?>
              <?php if ((int) $servicio['agotado'] === 0): ?><option value="<?= (int) $servicio['id'] ?>"><?= e($servicio['nombre']) ?> · <?= e(precio_texto($servicio)) ?></option><?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <?php if ($empleados !== []): ?>
        <div class="pq-campo">
          <label class="pq-label" for="fila-empleado">¿Con alguien en especial? <span class="pq-ayuda">(opcional)</span></label>
          <select class="pq-select pq-input" id="fila-empleado" name="empleado_id">
            <option value="0">Con quien quede libre</option>
            <?php foreach ($empleados as $persona): ?>
              <option value="<?= (int) $persona['id'] ?>"><?= e($persona['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <label class="pq-consentimiento">
        <input type="checkbox" name="autorizo_datos" value="1" required>
        <span>
          <span class="pq-consentimiento-titulo">Avísame por WhatsApp cuando se acerque mi turno</span>
          <span class="pq-ayuda">Solo para avisarte el turno, nada de promociones.</span>
        </span>
      </label>
      <button type="submit" class="pq-btn pq-btn-oscuro pq-btn-ancho">Anotarme en la fila</button>
    </form>
  <?php endif; ?>
</div>
