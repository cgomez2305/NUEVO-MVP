<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Tu equipo</span>
    <h1 class="pq-h1">Colaboradores</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Entran solo a las sedes que les marques. No ven exportar, horario, anticipos ni el copiloto.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok pq-pagina-aviso"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($colaboradores === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="9" r="3"/><path d="M2 20c0-3 2.7-5.5 6-5.5s6 2.5 6 5.5"/><circle cx="17.5" cy="8" r="2.3"/><path d="M15.8 14.7c2.4.4 4.2 2.5 4.2 5.3"/></svg>
    <p><strong>Todavía no tienes colaboradores.</strong><br>Agrega a quien te ayuda a despachar para que tenga su propio acceso.</p>
  </div>
<?php else: ?>
  <div class="pq-equipo-lista">
    <?php foreach ($colaboradores as $colaborador): ?>
      <section class="pq-colaborador" aria-label="<?= e($colaborador['nombre']) ?>">
        <div class="pq-colaborador-cabeza">
          <span class="pq-avatar pq-avatar-chico pq-cliente-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($colaborador['nombre'], 0, 1))) ?></span>
          <span class="pq-cliente-texto">
            <span class="pq-cliente-nombre"><?= e($colaborador['nombre']) ?></span>
            <span class="pq-ayuda pq-mono"><?= e($colaborador['whatsapp']) ?></span>
          </span>
          <form method="post" action="<?= e(base_url('/panel/colaboradores/' . $colaborador['id'] . '/eliminar')) ?>" data-confirmar="¿Quitar a <?= e($colaborador['nombre']) ?> del equipo? Ya no podrá entrar al panel.">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro" aria-label="Quitar a <?= e($colaborador['nombre']) ?>">Quitar</button>
          </form>
        </div>
        <form method="post" action="<?= e(base_url('/panel/colaboradores/' . $colaborador['id'] . '/sedes')) ?>" class="pq-colaborador-sedes">
          <?= csrf_campo() ?>
          <fieldset class="pq-chips-check">
            <legend class="pq-label">Puede entrar a</legend>
            <?php foreach ($sedes as $sede): ?>
              <label class="pq-chip-check">
                <input type="checkbox" name="sedes[]" value="<?= (int) $sede['id'] ?>"<?= in_array((int) $sede['id'], $colaborador['sede_ids'], true) ? ' checked' : '' ?>>
                <span><?= e($sede['nombre']) ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Guardar acceso</button>
        </form>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<details class="pq-agregar-panel"<?= $colaboradores === [] ? ' open' : '' ?>>
  <summary class="pq-btn pq-btn-ghost">+ Nuevo colaborador</summary>
  <form method="post" action="<?= e(base_url('/panel/colaboradores')) ?>" class="pq-agregar-panel-form">
    <?= csrf_campo() ?>
    <div class="pq-campo">
      <label class="pq-label" for="colab-nombre">Nombre</label>
      <input class="pq-input" id="colab-nombre" type="text" name="nombre" required maxlength="120">
    </div>
    <div class="pq-campo">
      <label class="pq-label" for="colab-whatsapp">WhatsApp <span class="pq-ayuda">(con este número entra al panel)</span></label>
      <input class="pq-input pq-mono" id="colab-whatsapp" type="tel" inputmode="tel" name="whatsapp" required maxlength="20">
    </div>
    <div class="pq-campo">
      <label class="pq-label" for="colab-password">Contraseña inicial</label>
      <input class="pq-input" id="colab-password" type="password" name="password" required minlength="8" autocomplete="new-password">
      <span class="pq-ayuda">Mínimo 8 caracteres. Compártesela en persona.</span>
    </div>
    <fieldset class="pq-chips-check">
      <legend class="pq-label">Puede entrar a</legend>
      <?php foreach ($sedes as $sede): ?>
        <label class="pq-chip-check">
          <input type="checkbox" name="sedes[]" value="<?= (int) $sede['id'] ?>">
          <span><?= e($sede['nombre']) ?></span>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <button type="submit" class="pq-btn pq-btn-sello">Agregar colaborador</button>
  </form>
</details>
