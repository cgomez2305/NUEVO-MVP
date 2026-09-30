<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap">
  <div>
    <span class="pq-eyebrow">Tu negocio</span>
    <h1 class="pq-h1" style="font-size: 28px">Sedes</h1>
    <p class="pq-lead">Administra las ubicaciones de tu negocio. Cada una tiene su propia tienda pública, catálogo, horario y agenda.</p>
  </div>
  <?php if ($negocio['rol'] === 'dueno'): ?>
    <details class="pq-nueva-sede">
      <summary class="pq-btn pq-btn-sello pq-btn-chico">+ Nueva sede</summary>
      <form method="post" action="<?= e(base_url('/panel/sedes')) ?>" class="pq-nueva-sede-panel">
        <?= csrf_campo() ?>
        <div class="pq-campo" style="margin-bottom: 0">
          <label class="pq-label" for="nueva-sede-nombre">Nombre de la sede</label>
          <input class="pq-input" id="nueva-sede-nombre" type="text" name="nombre" placeholder="Ej. Sede Norte" required maxlength="120">
        </div>
        <div class="pq-campo" style="margin-bottom: 0">
          <label class="pq-label" for="nueva-sede-whatsapp">WhatsApp para pedidos de esta sede</label>
          <input class="pq-input pq-mono" id="nueva-sede-whatsapp" type="tel" name="whatsapp" placeholder="+57 300 000 0000" required maxlength="20">
          <span class="pq-ayuda">Solo números, sin espacios ni signos (Veci los limpia igual si los escribes).</span>
        </div>
        <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Crear sede</button>
      </form>
    </details>
  <?php endif; ?>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-toast">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>

<p class="pq-ayuda" style="margin-top: 20px">Sede activa: <strong><?= e($negocio['nombre']) ?></strong>. Cambia de sede desde el selector de arriba.</p>

<div class="pq-stack" style="gap: 10px; margin-top: 12px">
  <?php foreach ($sedes as $sede): ?>
    <?php $activa = (int) $sede['id'] === (int) $negocio['id']; $urlSede = (int) $sede['publicada'] === 1 ? url_publica('/t/' . $sede['slug']) : null; ?>
    <div class="pq-card-borde pq-sede-card">
      <div class="pq-sede-card-info">
        <span style="font-size: 14.5px; font-weight: 700; display: flex; align-items: center; gap: 8px">
          <?= e($sede['nombre']) ?>
          <?php if ($activa): ?><span class="pq-chip pq-chip-caja" style="font-size: 10.5px; padding: 2px 8px">Activa</span><?php endif; ?>
        </span>
        <span class="pq-ayuda">
          <?php if ($urlSede !== null): ?>
            <a href="<?= e($urlSede) ?>" target="_blank" rel="noopener"><?= e($urlSede) ?></a>
          <?php else: ?>
            Sin publicar · termina su configuración para publicarla
          <?php endif; ?>
        </span>
      </div>
      <div class="pq-sede-card-acciones">
        <?php if ($urlSede !== null): ?>
          <a href="<?= e($urlSede) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-ghost pq-btn-chico" style="width: auto">Ver tienda</a>
          <button type="button" class="pq-btn-icono" data-copiar="<?= e($urlSede) ?>" title="Copiar enlace" aria-label="Copiar enlace">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="4" width="10" height="14" rx="2"/><path d="M8 8H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-1"/></svg>
          </button>
        <?php endif; ?>
        <?php if ($activa): ?>
          <a href="<?= e(base_url('/panel/horario')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico" style="width: auto">Horario</a>
        <?php else: ?>
          <form method="post" action="<?= e(base_url('/panel/sede/cambiar')) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="sede_id" value="<?= (int) $sede['id'] ?>">
            <input type="hidden" name="volver" value="<?= e(base_url('/panel/sedes')) ?>">
            <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico" style="width: auto">Cambiar a esta sede</button>
          </form>
        <?php endif; ?>
        <?php if ($negocio['rol'] === 'dueno'): ?>
          <details class="pq-menu-kebab">
            <summary aria-label="Más acciones">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </summary>
            <div class="pq-menu-kebab-panel">
              <a href="<?= e(base_url('/panel/sedes/' . $sede['id'] . '/editar')) ?>">Editar nombre y WhatsApp</a>
            </div>
          </details>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
