<span class="pq-eyebrow">Panel interno</span>
<h1 class="pq-h1" style="font-size: 28px">Todos los negocios</h1>
<p class="pq-lead">Busca por nombre del negocio o WhatsApp de cualquiera de sus usuarios.</p>

<form method="get" action="<?= e(base_url('/admin')) ?>" style="margin-top: 16px; display: flex; gap: 8px">
  <input class="pq-input" type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Doña María, 3001234567...">
  <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Buscar</button>
</form>

<div class="pq-stack" style="gap: 10px; margin-top: 20px">
  <?php foreach ($negocios as $negocio): ?>
    <a href="<?= e(base_url('/admin/negocios/' . $negocio['id'])) ?>" class="pq-card-borde" style="display: flex; align-items: center; justify-content: space-between; text-decoration: none; gap: 12px">
      <div class="pq-stack" style="gap: 2px">
        <span style="font-size: 14px; font-weight: 600; color: var(--carbon)">
          <?= e($negocio['nombre']) ?>
          <?php if ((int) $negocio['suspendido'] === 1): ?>
            <span class="pq-chip pq-chip-cancelado" style="font-size: 10px; padding: 2px 7px; margin-left: 4px">Suspendido</span>
          <?php endif; ?>
        </span>
        <span class="pq-ayuda">
          <?= $negocio['tipo_negocio'] === 'reservas' ? 'Servicios con cita' : 'Productos con carrito' ?>
          · <?= (int) $negocio['total_sedes'] ?> sede<?= (int) $negocio['total_sedes'] === 1 ? '' : 's' ?>
          · <?= (int) $negocio['total_usuarios'] ?> usuario<?= (int) $negocio['total_usuarios'] === 1 ? '' : 's' ?>
        </span>
      </div>
      <span class="pq-ayuda pq-mono"><?= e(date('d/m/Y', strtotime((string) $negocio['creado_en']))) ?></span>
    </a>
  <?php endforeach; ?>

  <?php if ($negocios === []): ?>
    <p class="pq-ayuda">No hay negocios que coincidan con esa búsqueda.</p>
  <?php endif; ?>
</div>
