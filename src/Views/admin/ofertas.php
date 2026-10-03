<?php
/**
 * Códigos de oferta de los planes de Veci (ver App\Models\OfertaPlan).
 * Arriba cada oferta con su cupo usado y su fecha de fin, editables; abajo
 * el registro de canjes con lo que importa para saber si funcionó: si el
 * negocio pagó el segundo mes.
 */
$estados = ['activa' => 'Activa', 'pausada' => 'Pausada', 'vencida' => 'Vencida', 'agotada' => 'Agotada'];
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Panel interno</span>
    <h1 class="pq-h1">Ofertas de planes</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Solo el primer mes de Barrio o Pro con pago mensual, un uso por negocio y solo negocios nuevos (WhatsApp y cédula/NIT). Lo apartado cuenta para el cupo; si la solicitud se cancela, el cupo vuelve.</p>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" role="status"><?= e($ok) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-ofertas">
  <h2 class="pq-seccion-titulo" id="pq-titulo-ofertas">Códigos</h2>
  <div class="pq-ofertas-lista">
    <?php foreach ($ofertas as $oferta): $estado = \App\Models\OfertaPlan::estado($oferta); ?>
      <form method="post" action="<?= e(base_url('/admin/ofertas/' . (int) $oferta['id'])) ?>" class="pq-card pq-oferta-admin">
        <?= csrf_campo() ?>
        <div class="pq-oferta-admin-cabeza">
          <span class="pq-oferta-cupon pq-mono"><?= e((string) $oferta['codigo']) ?></span>
          <span class="pq-chip<?= $estado === 'activa' ? ' pq-chip-caja' : ' pq-chip-cancelado' ?>"><?= e($estados[$estado]) ?></span>
          <span class="pq-ayuda pq-mono"><?= (int) $oferta['usos'] ?><?= $oferta['cupo_total'] !== null ? ' / ' . (int) $oferta['cupo_total'] : '' ?> usados · <?= (int) $oferta['pagados'] ?> pagados</span>
        </div>
        <div class="pq-oferta-admin-campos">
          <div class="pq-campo">
            <label class="pq-label" for="o-desc-<?= (int) $oferta['id'] ?>">Descripción</label>
            <input class="pq-input" id="o-desc-<?= (int) $oferta['id'] ?>" name="descripcion" maxlength="160" value="<?= e((string) $oferta['descripcion']) ?>">
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="o-pct-<?= (int) $oferta['id'] ?>">% del primer mes</label>
            <input class="pq-input pq-mono" id="o-pct-<?= (int) $oferta['id'] ?>" name="porcentaje" type="number" min="1" max="100" required value="<?= (int) $oferta['porcentaje'] ?>">
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="o-vence-<?= (int) $oferta['id'] ?>">Fecha de fin</label>
            <input class="pq-input pq-mono" id="o-vence-<?= (int) $oferta['id'] ?>" name="vence_en" type="date" value="<?= e((string) ($oferta['vence_en'] ?? '')) ?>">
          </div>
          <div class="pq-campo">
            <label class="pq-label" for="o-cupo-<?= (int) $oferta['id'] ?>">Cupo total</label>
            <input class="pq-input pq-mono" id="o-cupo-<?= (int) $oferta['id'] ?>" name="cupo_total" type="number" min="1" placeholder="Sin límite" value="<?= $oferta['cupo_total'] !== null ? (int) $oferta['cupo_total'] : '' ?>">
          </div>
        </div>
        <div class="pq-oferta-admin-pie">
          <label class="pq-interruptor pq-interruptor-con-texto">
            <input type="checkbox" name="activa" value="1"<?= (int) $oferta['activa'] === 1 ? ' checked' : '' ?>>
            <span class="pq-interruptor-pista" aria-hidden="true"></span>
            <span>Activa</span>
          </label>
          <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>

  <details class="pq-agregar-panel">
    <summary class="pq-btn pq-btn-ghost">+ Nueva oferta</summary>
    <form method="post" action="<?= e(base_url('/admin/ofertas')) ?>" class="pq-agregar-panel-form">
      <?= csrf_campo() ?>
      <div class="pq-campo">
        <label class="pq-label" for="o-codigo">Código</label>
        <input class="pq-input pq-mono" id="o-codigo" name="codigo" maxlength="20" required pattern="[A-Za-z0-9]{3,20}" autocapitalize="characters" placeholder="FERIA20">
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="o-desc">Descripción</label>
        <input class="pq-input" id="o-desc" name="descripcion" maxlength="160">
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="o-pct">% del primer mes</label>
        <input class="pq-input pq-mono" id="o-pct" name="porcentaje" type="number" min="1" max="100" required>
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="o-vence">Fecha de fin</label>
        <input class="pq-input pq-mono" id="o-vence" name="vence_en" type="date">
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="o-cupo">Cupo total</label>
        <input class="pq-input pq-mono" id="o-cupo" name="cupo_total" type="number" min="1" placeholder="Sin límite">
      </div>
      <label class="pq-interruptor pq-interruptor-con-texto">
        <input type="checkbox" name="activa" value="1" checked>
        <span class="pq-interruptor-pista" aria-hidden="true"></span>
        <span>Activa</span>
      </label>
      <button type="submit" class="pq-btn pq-btn-sello">Crear oferta</button>
    </form>
  </details>
</section>

<section class="pq-admin-seccion" aria-labelledby="pq-titulo-canjes">
  <h2 class="pq-seccion-titulo" id="pq-titulo-canjes">Canjes</h2>
  <?php if ($canjes === []): ?>
    <p class="pq-ayuda">Todavía nadie ha usado un código.</p>
  <?php else: ?>
    <div class="pq-tabla-scroll">
      <table class="pq-tabla pq-canjes">
        <thead>
          <tr><th scope="col">Fecha</th><th scope="col">Código</th><th scope="col">Negocio</th><th scope="col">Plan</th><th scope="col">Descuento</th><th scope="col">Estado</th><th scope="col">¿Pagó el 2.º mes?</th></tr>
        </thead>
        <tbody>
          <?php foreach ($canjes as $canje): ?>
            <tr>
              <td class="pq-mono"><?= e(fecha_corta((string) $canje['creado_en'], ' ')) ?></td>
              <td class="pq-mono"><?= e((string) $canje['codigo']) ?></td>
              <td><?php if ($canje['negocio_nombre'] !== null): ?><a href="<?= e(base_url('/admin/negocios/' . (int) $canje['negocio_id'])) ?>"><?= e((string) $canje['negocio_nombre']) ?></a><?php else: ?>Negocio borrado<?php endif; ?></td>
              <td><?= e(ucfirst((string) $canje['plan_nombre'])) ?></td>
              <td class="pq-mono">−<?= pesos((int) $canje['descuento']) ?></td>
              <td><?= e(['apartado' => 'Por pagar', 'confirmado' => 'Pagado', 'liberado' => 'Cancelado'][$canje['estado']] ?? $canje['estado']) ?></td>
              <td><?= $canje['estado'] !== 'confirmado' ? '—' : ((int) $canje['pago_segundo_mes'] === 1 ? 'Sí' : 'Todavía no') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
