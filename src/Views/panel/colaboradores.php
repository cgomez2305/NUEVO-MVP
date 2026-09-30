<span class="pq-eyebrow">Tu equipo</span>
<h1 class="pq-h1" style="font-size: 28px">Colaboradores</h1>
<p class="pq-lead">Un colaborador entra solo a las sedes que le asignes: no ve exportar, horario, depósitos ni el copiloto.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta" style="margin-top: 16px"><?= e($error) ?></div>
<?php endif; ?>

<div class="pq-stack" style="gap: 10px; margin-top: 20px">
  <?php foreach ($colaboradores as $colaborador): ?>
    <div class="pq-card-borde" style="display: flex; flex-direction: column; gap: 8px">
      <div style="display: flex; align-items: center; justify-content: space-between">
        <span style="font-size: 14px; font-weight: 600"><?= e($colaborador['nombre']) ?></span>
        <span class="pq-ayuda"><?= e($colaborador['whatsapp']) ?></span>
      </div>

      <form method="post" action="<?= e(base_url('/panel/colaboradores/' . $colaborador['id'] . '/sedes')) ?>" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center">
        <?= csrf_campo() ?>
        <?php foreach ($sedes as $sede): ?>
          <label style="display: flex; align-items: center; gap: 6px; font-size: 12px">
            <input type="checkbox" name="sedes[]" value="<?= (int) $sede['id'] ?>"
              <?= in_array((int) $sede['id'], $colaborador['sede_ids'], true) ? 'checked' : '' ?>>
            <?= e($sede['nombre']) ?>
          </label>
        <?php endforeach; ?>
        <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Guardar sedes</button>
      </form>

      <form method="post" action="<?= e(base_url('/panel/colaboradores/' . $colaborador['id'] . '/eliminar')) ?>" style="align-self: flex-end" onsubmit="return confirm('¿Quitar a este colaborador?')">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">eliminar</button>
      </form>
    </div>
  <?php endforeach; ?>

  <?php if ($colaboradores === []): ?>
    <p class="pq-ayuda">Todavía no tienes colaboradores.</p>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(base_url('/panel/colaboradores')) ?>" class="pq-card" style="display: flex; flex-direction: column; gap: 8px; margin-top: 20px">
  <?= csrf_campo() ?>
  <span style="font-size: 13px; font-weight: 600">Nuevo colaborador</span>
  <input class="pq-input" type="text" name="nombre" placeholder="Nombre" required maxlength="120">
  <input class="pq-input pq-mono" type="tel" name="whatsapp" placeholder="WhatsApp" required maxlength="20">
  <input class="pq-input" type="password" name="password" placeholder="Contraseña (mínimo 6 caracteres)" required minlength="6">
  <div style="display: flex; flex-wrap: wrap; gap: 10px">
    <?php foreach ($sedes as $sede): ?>
      <label style="display: flex; align-items: center; gap: 6px; font-size: 12px">
        <input type="checkbox" name="sedes[]" value="<?= (int) $sede['id'] ?>">
        <?= e($sede['nombre']) ?>
      </label>
    <?php endforeach; ?>
  </div>
  <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">+ Agregar colaborador</button>
</form>
