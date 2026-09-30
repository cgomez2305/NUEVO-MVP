<a href="<?= e(base_url('/panel/sedes')) ?>" class="pq-mono" style="font-size: 12px; color: var(--gris-suave); text-decoration: none">‹ Sedes</a>

<h1 class="pq-h1" style="font-size: 26px; margin-top: 8px">Editar sede</h1>

<form method="post" action="<?= e(base_url('/panel/sedes/' . $sede['id'] . '/actualizar')) ?>" class="pq-stack" style="gap: 16px; margin-top: 20px; max-width: 420px">
  <?= csrf_campo() ?>

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="nombre">Nombre de la sede</label>
    <input class="pq-input" type="text" id="nombre" name="nombre" value="<?= e($sede['nombre']) ?>" required maxlength="120">
  </div>

  <div class="pq-campo" style="margin-bottom: 0">
    <label class="pq-label" for="whatsapp">WhatsApp para pedidos de esta sede</label>
    <input class="pq-input pq-mono" type="tel" id="whatsapp" name="whatsapp" value="<?= e($sede['whatsapp']) ?>" placeholder="+57 300 000 0000" required maxlength="20">
    <span class="pq-ayuda">Solo números, sin espacios ni signos.</span>
  </div>

  <div style="display: flex; gap: 10px; margin-top: 4px">
    <a href="<?= e(base_url('/panel/sedes')) ?>" class="pq-btn pq-btn-ghost" style="width: auto; flex-grow: 1">Cancelar</a>
    <button type="submit" class="pq-btn pq-btn-sello" style="width: auto; flex-grow: 1">Guardar cambios</button>
  </div>
</form>
