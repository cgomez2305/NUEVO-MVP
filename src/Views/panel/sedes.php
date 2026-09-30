<span class="pq-eyebrow">Tu negocio</span>
<h1 class="pq-h1" style="font-size: 28px">Sedes</h1>
<p class="pq-lead">Cada sede tiene su propia tienda pública, catálogo, horario y agenda. Cambia de sede desde el selector de arriba.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>

<div class="pq-stack" style="gap: 8px; margin-top: 20px">
  <?php foreach ($sedes as $sede): ?>
    <?php $activa = (int) $sede['id'] === (int) $negocio['id']; ?>
    <div class="pq-card-borde" style="display: flex; align-items: center; justify-content: space-between; gap: 10px<?= $activa ? '; border-color: var(--sello)' : '' ?>">
      <div class="pq-stack">
        <span style="font-size: 14px; font-weight: 600"><?= e($sede['nombre']) ?> <?= $activa ? '· activa' : '' ?></span>
        <span class="pq-ayuda">
          <?= (int) $sede['publicada'] === 1 ? 'Publicada' : 'Sin publicar' ?> ·
          <?php if ((int) $sede['publicada'] === 1): ?>
            <a href="<?= e(url_publica('/t/' . $sede['slug'])) ?>" target="_blank" rel="noopener"><?= e(url_publica('/t/' . $sede['slug'])) ?></a>
          <?php else: ?>
            termina su configuración para publicarla
          <?php endif; ?>
        </span>
      </div>
      <?php if (!$activa): ?>
        <form method="post" action="<?= e(base_url('/panel/sede/cambiar')) ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="sede_id" value="<?= (int) $sede['id'] ?>">
          <input type="hidden" name="volver" value="<?= e(base_url('/panel/sedes')) ?>">
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Entrar</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($negocio['rol'] === 'dueno'): ?>
  <form method="post" action="<?= e(base_url('/panel/sedes')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 20px">
    <?= csrf_campo() ?>
    <span style="font-size: 13px; font-weight: 600">Nueva sede</span>
    <input class="pq-input" type="text" name="nombre" placeholder="Nombre de la sede (ej. Sede Norte)" required maxlength="120">
    <input class="pq-input pq-mono" type="tel" name="whatsapp" placeholder="WhatsApp para pedidos de esta sede" required maxlength="20">
    <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">+ Crear sede</button>
  </form>
<?php endif; ?>
