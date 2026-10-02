<?php
$esDueno = $negocio['rol'] === 'dueno';
$nombreNegocio = (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']);
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Tu negocio</span>
    <h1 class="pq-h1">Sedes</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Cada sede tiene su propia tienda, catálogo, horario y agenda.</p>

<?php
// Cada sede se muestra como una fachada pequeña (toldo, insignia y nombre
// con la letra de la tienda): se reconoce como "una tienda", no como una
// fila de configuración.
?>
<div class="pq-sedes-lista">
  <?php foreach ($sedes as $sede): ?>
    <?php
    $activa = (int) $sede['id'] === (int) $negocio['id'];
    $urlSede = (int) $sede['publicada'] === 1 ? url_publica('/t/' . $sede['slug']) : null;
    $inicial = mb_strtoupper(mb_substr((string) ($sede['inicial'] ?? $sede['nombre']), 0, 1));
    ?>
    <section class="pq-escaparate pq-sede-fachada<?= $activa ? ' pq-sede-fachada-activa' : '' ?>" aria-label="<?= e($sede['nombre']) ?>">
      <div class="pq-toldo pq-toldo-corto" aria-hidden="true"></div>
      <div class="pq-escaparate-cuerpo">
        <div class="pq-escaparate-letrero">
          <span class="pq-letrero-insignia pq-letrero-insignia-chica" aria-hidden="true"><?= e($inicial) ?></span>
          <div class="pq-escaparate-texto">
            <span class="pq-escaparate-eyebrow"><?= $activa ? 'Estás trabajando en esta sede' : e($nombreNegocio) ?></span>
            <strong class="pq-escaparate-nombre"><?= e($sede['nombre']) ?></strong>
          </div>
        </div>
        <?php if ($urlSede !== null): ?>
          <a href="<?= e($urlSede) ?>" target="_blank" rel="noopener" class="pq-escaparate-url"><?= e(preg_replace('#^https?://#', '', $urlSede)) ?></a>
        <?php else: ?>
          <p class="pq-ayuda pq-escaparate-nota">Sin publicar: termina su configuración para que tenga tienda.</p>
        <?php endif; ?>
        <div class="pq-escaparate-botones">
          <?php if (!$activa): ?>
            <form method="post" action="<?= e(base_url('/panel/sede/cambiar')) ?>">
              <?= csrf_campo() ?>
              <input type="hidden" name="sede_id" value="<?= (int) $sede['id'] ?>">
              <input type="hidden" name="volver" value="<?= e(base_url('/panel/sedes')) ?>">
              <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Trabajar en esta sede</button>
            </form>
          <?php endif; ?>
          <?php if ($urlSede !== null): ?>
            <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-copiar="<?= e($urlSede) ?>">Copiar enlace</button>
          <?php endif; ?>
          <?php if ($esDueno): ?>
            <a href="<?= e(base_url('/panel/sedes/' . $sede['id'] . '/editar')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Editar datos</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<?php if ($esDueno): ?>
  <details class="pq-agregar-panel">
    <summary class="pq-btn pq-btn-ghost">+ Nueva sede</summary>
    <form method="post" action="<?= e(base_url('/panel/sedes')) ?>" class="pq-agregar-panel-form">
      <?= csrf_campo() ?>
      <div class="pq-campo">
        <label class="pq-label" for="nueva-sede-nombre">Nombre de la sede</label>
        <input class="pq-input" id="nueva-sede-nombre" type="text" name="nombre" placeholder="Ej.: Sede Norte" required maxlength="120">
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="nueva-sede-whatsapp">WhatsApp para pedidos de esta sede</label>
        <input class="pq-input pq-mono" id="nueva-sede-whatsapp" type="tel" inputmode="tel" name="whatsapp" placeholder="300 000 0000" required maxlength="20">
        <span class="pq-ayuda">Puedes escribirlo con espacios: Veci lo limpia.</span>
      </div>
      <button type="submit" class="pq-btn pq-btn-sello">Crear sede</button>
    </form>
  </details>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
