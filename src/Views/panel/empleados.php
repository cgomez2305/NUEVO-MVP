<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Equipo</span>
    <h1 class="pq-h1">Empleados</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Con al menos uno, tus clientes eligen con quién reservar y cada persona tiene su propia agenda.</p>

<?php if ($empleados === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="18" cy="8.5" r="2.6"/><path d="M16.5 14.3c2.3.5 4 2.5 4.5 5.7"/></svg>
    <p><strong>Sin empleados registrados.</strong><br>Las citas se agendan con el negocio, sin elegir persona.</p>
  </div>
<?php else: ?>
  <ul class="pq-equipo-lista">
    <?php foreach ($empleados as $empleado): ?>
      <li class="pq-equipo-fila">
        <span class="pq-avatar pq-avatar-chico pq-equipo-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($empleado['nombre'], 0, 1))) ?></span>
        <span class="pq-equipo-nombre"><?= e($empleado['nombre']) ?></span>
        <form method="post" action="<?= e(base_url('/panel/empleados/' . $empleado['id'] . '/eliminar')) ?>" data-confirmar="¿Quitar a <?= e($empleado['nombre']) ?>? Sus citas futuras quedan sin persona asignada.">
          <?= csrf_campo() ?>
          <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro" aria-label="Quitar a <?= e($empleado['nombre']) ?>">Quitar</button>
        </form>
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
