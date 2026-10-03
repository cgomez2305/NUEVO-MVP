<?php use App\Models\PlanTratamiento; ?>
<div class="pq-pagina-cabeza">
  <div>
    <a class="pq-volver-panel" href="<?= e(base_url('/panel/planes')) ?>">← Planes</a>
    <h1 class="pq-h1"><?= !empty($desde) ? 'Rehacer plan de tratamiento' : 'Nuevo plan de tratamiento' ?></h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">Por fases, como se lo explicas al paciente. Cada fase con cuántas sesiones lleva y su valor; el total es la suma.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('/panel/planes')) ?>" class="pq-ficha-panel pq-plan-form">
  <?= csrf_campo() ?>
  <section class="pq-ficha-bloque">
    <h2 class="pq-seccion-titulo">Paciente</h2>
    <?php if (!empty($desde)): ?>
      <input type="hidden" name="desde_id" value="<?= (int) $desde['id'] ?>">
      <p class="pq-plan-paciente"><strong><?= e($desde['cliente_nombre']) ?></strong> <span class="pq-ayuda">· a partir del plan #<?= (int) $desde['id'] ?> (<?= e(PlanTratamiento::vencido($desde) ? 'venció sin aprobar' : mb_strtolower(PlanTratamiento::ETIQUETAS[$desde['estado']] ?? $desde['estado'])) ?>). Ajusta lo que cambió; el anterior queda en el historial.</span></p>
    <?php elseif ($cita !== null): ?>
      <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
      <p class="pq-plan-paciente"><strong><?= e($cita['cliente_nombre']) ?></strong> <span class="pq-ayuda">· desde su cita de <?= e($cita['nombre_servicio']) ?> del <?= e(fecha_corta((string) $cita['fecha_hora'])) ?></span></p>
    <?php else: ?>
      <div class="pq-servicio-panel-par">
        <div class="pq-campo">
          <label class="pq-label" for="plan-nombre">Nombre</label>
          <input class="pq-input" id="plan-nombre" type="text" name="nombre" maxlength="120" required>
        </div>
        <div class="pq-campo">
          <label class="pq-label" for="plan-telefono">WhatsApp</label>
          <input class="pq-input pq-mono" id="plan-telefono" type="tel" inputmode="numeric" name="telefono" maxlength="20" placeholder="300 123 4567" required>
        </div>
      </div>
      <label class="pq-reglas-opcion"><input type="checkbox" name="autorizo" value="1" required><span>El paciente autorizó el uso de su nombre y WhatsApp para este plan <span class="pq-ayuda">(si ya es paciente, se usa su ficha como está)</span></span></label>
    <?php endif; ?>
  </section>

  <section class="pq-ficha-bloque">
    <h2 class="pq-seccion-titulo">El plan</h2>
    <div class="pq-campo">
      <label class="pq-label" for="plan-titulo">Nombre del tratamiento</label>
      <input class="pq-input" id="plan-titulo" type="text" name="titulo" maxlength="120" placeholder="Ej.: Ortodoncia con brackets metálicos" required value="<?= e((string) ($desde['titulo'] ?? '')) ?>">
    </div>
    <?php // Renglones fijos (sin JS); los vacíos no se guardan. Una fase sin valor queda en $0 (controles incluidos). ?>
    <div class="pq-plan-fases-form">
      <?php for ($i = 0; $i < PlanTratamiento::MAX_FASES; $i++): ?>
        <?php $previa = $fasesDesde[$i] ?? null; ?>
        <div class="pq-plan-fase-form">
          <span class="pq-plan-fase-numero" aria-hidden="true"><?= $i + 1 ?></span>
          <input class="pq-input" type="text" name="fases[<?= $i ?>][nombre]" value="<?= e((string) ($previa['nombre'] ?? '')) ?>" maxlength="120" placeholder="<?= ['Valoración y radiografías', 'Instalación de brackets', 'Controles mensuales', 'Retiro y retenedores'][$i] ?? 'Otra fase' ?>" aria-label="Fase <?= $i + 1 ?>">
          <div class="pq-campo-sufijo" data-sufijo="ses."><input class="pq-input pq-mono" type="number" name="fases[<?= $i ?>][sesiones]" min="1" max="99" value="<?= (int) ($previa['sesiones'] ?? 1) ?>" aria-label="Sesiones de la fase <?= $i + 1 ?>"></div>
          <div class="pq-campo-dinero"><input class="pq-input pq-mono" type="text" inputmode="numeric" name="fases[<?= $i ?>][valor]" value="<?= $previa !== null && (int) $previa['valor'] > 0 ? number_format((int) $previa['valor'], 0, ',', '.') : '' ?>" placeholder="Valor" data-precio-cop aria-label="Valor de la fase <?= $i + 1 ?>"></div>
        </div>
      <?php endfor; ?>
    </div>
    <div class="pq-servicio-panel-par">
      <div class="pq-campo">
        <label class="pq-label" for="plan-validez">El paciente lo puede aprobar durante</label>
        <select class="pq-select" id="plan-validez" name="validez_dias">
          <?php foreach (PlanTratamiento::VALIDEZ as $dias): ?>
            <option value="<?= $dias ?>" <?= $dias === 30 ? 'selected' : '' ?>><?= $dias ?> días</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="pq-campo">
      <label class="pq-label" for="plan-nota">Nota para el paciente <span class="pq-ayuda">(opcional, nada clínico)</span></label>
      <textarea class="pq-input" id="plan-nota" name="nota" rows="2" maxlength="500" placeholder="Ej.: puedes abonar mes a mes; el plan incluye los retenedores."><?= e((string) ($desde['nota'] ?? '')) ?></textarea>
    </div>
  </section>
  <button type="submit" class="pq-btn pq-btn-sello">Guardar plan</button>
</form>
