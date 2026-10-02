<?php
/**
 * Los 7 días con interruptor y horas (panel/horario.php y el paso de
 * horario del onboarding). Espera $horario (Sede::horario()). Si viene
 * vacío y $sugerirSiVacio es true, propone lunes a sábado de 8 a 6: así
 * el onboarding no arranca con todo apagado (antes "Continuar" con todo
 * apagado devolvía a la misma pantalla sin explicar por qué).
 *
 * Los data-dia / data-inicio-dia / data-fin-dia los usa "Copiar horario
 * del lunes" (interacciones.js); sin JS cada día se edita a mano.
 */
$pqDias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$pqDiasCortos = [2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
$pqSugerido = ($horario === [] && !empty($sugerirSiVacio));
$pqMostrarNota = $pqSugerido && empty($error); // tras un error, la nota contradiría el mensaje
?>
<?php if ($pqMostrarNota): ?>
  <p class="pq-nota-panel pq-semana-sugerencia">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
    <span>Te dejamos <strong>lunes a sábado de 8 a. m. a 6 p. m.</strong> Cámbialo a tu gusto.</span>
  </p>
<?php endif; ?>

<fieldset class="pq-semana">
  <legend class="pq-sr-solo">Días y horas de atención</legend>
  <?php foreach ($pqDias as $num => $nombre): ?>
    <?php
    $abierto = $pqSugerido ? $num <= 6 : isset($horario[(string) $num]);
    $rango = $horario[(string) $num] ?? ['08:00', '18:00'];
    ?>
    <div class="pq-semana-dia">
      <label class="pq-interruptor">
        <input type="checkbox" name="abierto_<?= $num ?>" value="1" data-dia="<?= $num ?>" <?= $abierto ? 'checked' : '' ?>>
        <span class="pq-interruptor-pista" aria-hidden="true"></span>
        <span class="pq-semana-nombre"><?= e($nombre) ?></span>
      </label>
      <div class="pq-semana-horas">
        <input class="pq-input pq-mono" type="time" name="inicio_<?= $num ?>" data-inicio-dia="<?= $num ?>" value="<?= e($rango[0]) ?>" aria-label="<?= e($nombre) ?>: abre a las">
        <span class="pq-ayuda" aria-hidden="true">a</span>
        <input class="pq-input pq-mono" type="time" name="fin_<?= $num ?>" data-fin-dia="<?= $num ?>" value="<?= e($rango[1]) ?>" aria-label="<?= e($nombre) ?>: cierra a las">
      </div>
      <span class="pq-semana-cerrado">Cerrado</span>
    </div>
  <?php endforeach; ?>
</fieldset>

<details class="pq-copiar-lunes">
  <summary>Copiar el horario del lunes a otros días</summary>
  <div class="pq-copiar-lunes-cuerpo">
    <div class="pq-chips-check">
      <?php foreach ($pqDiasCortos as $num => $corto): ?>
        <label class="pq-chip-check">
          <input type="checkbox" data-copiar-dia="<?= $num ?>" <?= $num <= 5 ? 'checked' : '' ?>>
          <span><?= e($corto) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <button type="button" class="pq-btn pq-btn-ghost pq-btn-chico" data-aplicar-horario-semana="1">Copiar</button>
  </div>
</details>
