<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Equipo</span>
    <h1 class="pq-h1">Tu equipo</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Con al menos una persona, tus clientes eligen con quién reservar y cada una tiene su propia agenda. Ponles foto y especialidad: así los reconocen.</p>

<?php if ($empleados === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="18" cy="8.5" r="2.6"/><path d="M16.5 14.3c2.3.5 4 2.5 4.5 5.7"/></svg>
    <p><strong>Sin equipo registrado.</strong><br>Las citas se agendan con el negocio, sin elegir persona.</p>
  </div>
<?php else: ?>
  <ul class="pq-equipo-lista">
    <?php foreach ($empleados as $empleado): ?>
      <?php
      $pendientes = [];
      if (empty($empleado['foto'])) { $pendientes[] = 'sin foto'; }
      if (empty($empleado['especialidad'])) { $pendientes[] = 'sin especialidad'; }
      ?>
      <li>
        <a class="pq-equipo-fila pq-equipo-fila-enlace<?= (int) $empleado['activo'] === 1 ? '' : ' pq-equipo-fila-pausa' ?>" href="<?= e(base_url('/panel/empleados/' . $empleado['id'])) ?>">
          <?php if (!empty($empleado['foto'])): ?>
            <img class="pq-equipo-foto" src="<?= e(base_url($empleado['foto'])) ?>" alt="" width="40" height="40">
          <?php else: ?>
            <span class="pq-avatar pq-avatar-chico pq-equipo-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($empleado['nombre'], 0, 1))) ?></span>
          <?php endif; ?>
          <span class="pq-equipo-texto">
            <span class="pq-equipo-nombre"><?= e($empleado['nombre']) ?><?= (int) $empleado['activo'] === 1 ? '' : ' · en pausa' ?></span>
            <span class="pq-ayuda">
              <?= !empty($empleado['especialidad']) ? e($empleado['especialidad']) : '' ?>
              <?= (int) $empleado['fotos'] > 0 ? ' · ' . (int) $empleado['fotos'] . ' trabajo' . ((int) $empleado['fotos'] === 1 ? '' : 's') : '' ?>
              <?= $empleado['comision_pct'] !== null ? ' · ' . (int) $empleado['comision_pct'] . '% de comisión' : '' ?>
              <?php if ($pendientes !== []): ?><span class="pq-equipo-falta"><?= e(ucfirst(implode(', ', $pendientes))) ?></span><?php endif; ?>
            </span>
          </span>
          <span class="pq-servicio-panel-editar">Editar</span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/panel/empleados')) ?>" class="pq-card pq-fechas-form">
  <?= csrf_campo() ?>
  <div class="pq-campo">
    <label class="pq-label" for="pq-empleado-nombre">Agregar a alguien del equipo</label>
    <input class="pq-input" id="pq-empleado-nombre" type="text" name="nombre" placeholder="Nombre, como lo verán tus clientes" required maxlength="120">
  </div>
  <button type="submit" class="pq-btn pq-btn-sello">Agregar</button>
</form>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
