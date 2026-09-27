<div class="pq-content" style="display: flex; flex-direction: column; min-height: 100vh; justify-content: center; align-items: center; gap: 22px; text-align: center">

  <div style="width: 108px; height: 108px; border-radius: 50%; border: 3px solid var(--caja); display: flex; align-items: center; justify-content: center; transform: rotate(-6deg)">
    <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#16A36A" stroke-width="2.4"><path d="M5 13l5 5L20 7"/></svg>
  </div>

  <div>
    <h1 class="pq-h1" style="font-size: 30px">Tu tienda ya existe</h1>
    <p class="pq-lead" style="max-width: 280px; margin-left: auto; margin-right: auto">
      Compártela en tu estado de WhatsApp o en Instagram y empieza a recibir pedidos.
    </p>
  </div>

  <div class="pq-card" style="width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px">
    <span class="pq-mono" style="font-size: 13px; word-break: break-all"><?= e(url_publica('/t/' . $negocio['slug'])) ?></span>
  </div>

  <div class="pq-stack" style="gap: 10px; width: 100%">
    <a href="<?= e(base_url('/t/' . $negocio['slug'])) ?>" class="pq-btn pq-btn-aji">Ver cómo la ve tu cliente →</a>
    <a href="<?= e(base_url('/panel')) ?>" class="pq-btn pq-btn-ghost">Ir a mi panel</a>
  </div>

</div>
