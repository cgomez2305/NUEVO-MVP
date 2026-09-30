<span class="pq-eyebrow">Equipo</span>
<h1 class="pq-h1" style="font-size: 28px">Empleados</h1>
<p class="pq-lead">Si registras al menos uno, tus clientes eligen con quién agendar y cada empleado tiene su propia disponibilidad.</p>

<div class="pq-stack" style="gap: 8px; margin-top: 20px">
  <?php foreach ($empleados as $empleado): ?>
    <div class="pq-card-borde" style="display: flex; align-items: center; justify-content: space-between; gap: 10px">
      <span style="font-size: 14px; font-weight: 600"><?= e($empleado['nombre']) ?></span>
      <form method="post" action="<?= e(base_url('/panel/empleados/' . $empleado['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar a <?= e($empleado['nombre']) ?>? Sus citas futuras quedarán sin empleado asignado.">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-mono" style="background: none; border: none; color: var(--gris-suave); font-size: 11px; cursor: pointer; padding: 0">eliminar</button>
      </form>
    </div>
  <?php endforeach; ?>

  <?php if ($empleados === []): ?>
    <p class="pq-ayuda">Sin empleados registrados: tus citas se agendan contra el negocio, como hasta ahora.</p>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(base_url('/panel/empleados')) ?>" class="pq-card" style="display: flex; gap: 8px; margin-top: 16px">
  <?= csrf_campo() ?>
  <input class="pq-input" style="flex-grow: 1" type="text" name="nombre" placeholder="Nombre del empleado" required maxlength="120">
  <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">+ Agregar</button>
</form>
