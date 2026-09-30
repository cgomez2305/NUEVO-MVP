<div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 12px">
  <div>
    <span class="pq-eyebrow">Agenda</span>
    <h1 class="pq-h1" style="font-size: 28px">Tus próximas citas</h1>
  </div>
  <a href="<?= e(base_url('/panel/citas/exportar.csv')) ?>" class="pq-btn pq-btn-ghost pq-btn-chico">Exportar CSV</a>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-alerta pq-alerta-ok" style="margin-top: 16px"><?= e($ok) ?></div>
<?php endif; ?>

<?php if ($listaEspera !== []): ?>
  <div style="margin-top: 20px">
    <span class="pq-eyebrow">Lista de espera · <?= count($listaEspera) ?></span>
    <div class="pq-stack" style="gap: 8px; margin-top: 8px">
      <?php foreach ($listaEspera as $fila): ?>
        <?php
        $mensajeWa = "Hola {$fila['cliente_nombre']}, se liberó un cupo para {$fila['nombre_servicio']} el "
            . date('d/m', strtotime((string) $fila['fecha'])) . '. ¿Te sirve que te lo reserve?';
        $telefonoWa = preg_replace('/\D+/', '', (string) $fila['cliente_telefono']);
        $enlaceWa = 'https://wa.me/57' . $telefonoWa . '?text=' . rawurlencode($mensajeWa);
        ?>
        <div class="pq-card-borde" style="display: flex; align-items: center; gap: 12px">
          <div class="pq-avatar pq-avatar-chico" style="background: var(--mostaza)"><?= e(mb_strtoupper(mb_substr($fila['cliente_nombre'], 0, 1))) ?></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600"><?= e($fila['cliente_nombre']) ?></span>
            <span class="pq-ayuda"><?= e($fila['nombre_servicio']) ?> · quiere el <?= e(date('d M', strtotime((string) $fila['fecha']))) ?></span>
          </div>
          <a href="<?= e($enlaceWa) ?>" target="_blank" rel="noopener" class="pq-btn pq-btn-ghost pq-btn-chico">Avisar</a>
          <form method="post" action="<?= e(base_url('/panel/lista-espera/' . $fila['id'] . '/contactado')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="pq-btn-icono" title="Ya la contacté" aria-label="Marcar como contactada">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($citas === []): ?>
  <p class="pq-lead" style="margin-top: 16px">Todavía no tienes citas reservadas.</p>
<?php else: ?>
  <div class="pq-stack" style="gap: 10px; margin-top: 20px">
    <?php foreach ($citas as $cita): ?>
      <?php
      $citaSinConfirmar = $cita['estado'] === 'pendiente';
      $minutosEspera = minutos_desde((string) $cita['creado_en']);
      $nivel = nivel_espera($minutosEspera, 15);
      $demorada = $citaSinConfirmar && $nivel === 'prioridad';
      ?>
      <div class="pq-card-borde<?= $demorada ? ' pq-card-demorado' : '' ?>">
        <div style="display: flex; align-items: center; gap: 12px">
          <div class="pq-avatar pq-avatar-chico" style="background: var(--sello)"><?= e(mb_strtoupper(mb_substr($cita['cliente_nombre'], 0, 1))) ?></div>
          <div class="pq-stack" style="flex-grow: 1">
            <span style="font-size: 14px; font-weight: 600">
              <?= e(date('d M, g:i a', strtotime((string) $cita['fecha_hora']))) ?> · <?= e($cita['cliente_nombre']) ?>
            </span>
            <span class="pq-ayuda">
              <?= e($cita['nombre_servicio']) ?> · <?= (int) $cita['duracion_min'] ?> min
              <?php if (!empty($cita['empleado_nombre'])): ?> · <?= e($cita['empleado_nombre']) ?><?php endif; ?>
            </span>
          </div>
          <div class="pq-stack" style="align-items: flex-end; gap: 3px">
            <span class="pq-mono pq-precio-suave" style="font-size: 14px"><?= pesos((int) $cita['precio']) ?></span>
            <?php if ($citaSinConfirmar): ?>
              <span class="pq-tiempo-espera pq-tiempo-<?= e($nivel) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                <?= e(texto_espera($minutosEspera)) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ((int) $cita['anticipo_monto'] > 0): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px">
            <span class="pq-ayuda">Anticipo: <?= pesos((int) $cita['anticipo_monto']) ?></span>
            <?php if ($cita['anticipo_estado'] === 'pagado'): ?>
              <span class="pq-chip pq-chip-caja">Anticipo pagado</span>
            <?php else: ?>
              <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/anticipo')) ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="pq-chip pq-chip-pendiente" style="border: none; cursor: pointer">Marcar anticipo pagado</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php $siguientePasoCita = \App\Models\Cita::siguientePaso($cita); ?>
        <div style="display: flex; align-items: center; gap: 10px; margin-top: 12px; flex-wrap: wrap">
          <?php if ($siguientePasoCita !== null): ?>
            <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/estado')) ?>">
              <?= csrf_campo() ?>
              <input type="hidden" name="estado" value="<?= e($siguientePasoCita['estado']) ?>">
              <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico"><?= e($siguientePasoCita['texto']) ?> →</button>
            </form>
          <?php endif; ?>
          <details>
            <summary class="pq-mono" style="font-size: 11px; color: var(--gris-suave); cursor: pointer">cambiar estado manualmente</summary>
            <form method="post" action="<?= e(base_url('/panel/citas/' . $cita['id'] . '/estado')) ?>" style="display: flex; gap: 8px; margin-top: 8px">
              <?= csrf_campo() ?>
              <select class="pq-select" name="estado" style="flex-grow: 1">
                <?php foreach (['pendiente', 'confirmada', 'completada', 'cancelada'] as $estado): ?>
                  <option value="<?= e($estado) ?>" <?= $cita['estado'] === $estado ? 'selected' : '' ?>><?= e(ucfirst($estado)) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Actualizar</button>
            </form>
          </details>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
