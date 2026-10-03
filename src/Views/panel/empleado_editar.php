<?php
$id = (int) $empleado['id'];
$base = '/panel/empleados/' . $id;
$primerNombre = explode(' ', trim((string) $empleado['nombre']))[0];
$restringido = $propios !== [];
$usaPropio = $empleado['horario_atencion'] !== null;
$urlPublica = url_publica('/t/' . $negocio['slug'] . '/equipo/' . $id);
?>
<div class="pq-pagina-cabeza">
  <div>
    <a class="pq-volver-panel" href="<?= e(base_url('/panel/empleados')) ?>">← Tu equipo</a>
    <h1 class="pq-h1"><?= e($empleado['nombre']) ?></h1>
  </div>
</div>
<?php if ((int) $empleado['activo'] !== 1): ?>
  <div class="pq-alerta pq-alerta-aviso pq-pagina-aviso">En pausa: no aparece para reservar. Sus citas siguen en la agenda.</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="pq-alerta pq-pagina-aviso" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="pq-ficha-panel">
  <section class="pq-ficha-bloque" aria-labelledby="pq-perfil-titulo">
    <h2 class="pq-seccion-titulo" id="pq-perfil-titulo">Lo que ven tus clientes</h2>
    <form method="post" action="<?= e(base_url($base . '/perfil')) ?>" enctype="multipart/form-data" class="pq-agregar-panel-form">
      <?= csrf_campo() ?>
      <div class="pq-ficha-foto">
        <?php if (!empty($empleado['foto'])): ?>
          <img src="<?= e(base_url($empleado['foto'])) ?>" alt="Foto de <?= e($empleado['nombre']) ?>" width="88" height="88">
        <?php else: ?>
          <span class="pq-ficha-foto-vacia" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $empleado['nombre'], 0, 1))) ?></span>
        <?php endif; ?>
        <div class="pq-campo">
          <label class="pq-label" for="emp-foto"><?= !empty($empleado['foto']) ? 'Cambiar foto' : 'Foto' ?></label>
          <input class="pq-input" id="emp-foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
          <span class="pq-ayuda">Una foto de frente, con buena luz. Pídele permiso antes de publicarla.</span>
          <?php if (!empty($empleado['foto'])): ?>
            <label class="pq-reglas-opcion"><input type="checkbox" name="quitar_foto" value="1"><span>Quitar la foto</span></label>
          <?php endif; ?>
        </div>
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="emp-nombre">Nombre</label>
        <input class="pq-input" id="emp-nombre" type="text" name="nombre" value="<?= e($empleado['nombre']) ?>" required maxlength="120">
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="emp-esp">Especialidad</label>
        <input class="pq-input" id="emp-esp" type="text" name="especialidad" value="<?= e((string) $empleado['especialidad']) ?>" maxlength="80" placeholder="Ej.: Degradados y diseño de barba">
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="emp-bio">Una frase sobre su trabajo <span class="pq-ayuda">(opcional)</span></label>
        <textarea class="pq-input" id="emp-bio" name="bio" rows="2" maxlength="240" placeholder="Ej.: 8 años cortando en el barrio. Especialista en cabello rizado."><?= e((string) $empleado['bio']) ?></textarea>
      </div>
      <div class="pq-campo">
        <label class="pq-label" for="emp-comision">Comisión <span class="pq-ayuda">(opcional, solo la ves tú)</span></label>
        <div class="pq-campo-sufijo pq-campo-unidades" data-sufijo="%">
          <input class="pq-input pq-mono" id="emp-comision" type="number" name="comision_pct" min="0" max="100" value="<?= $empleado['comision_pct'] !== null ? (int) $empleado['comision_pct'] : '' ?>" placeholder="—">
        </div>
        <span class="pq-ayuda">Si trabaja por porcentaje de lo que atiende. Vacío si tiene sueldo o alquila la silla.</span>
      </div>
      <button type="submit" class="pq-btn pq-btn-sello">Guardar perfil</button>
    </form>
    <p class="pq-ayuda pq-ficha-enlace">Su página: <a href="<?= e($urlPublica) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $urlPublica)) ?></a></p>
  </section>

  <section class="pq-ficha-bloque" id="trabajos" aria-labelledby="pq-trabajos-titulo">
    <h2 class="pq-seccion-titulo" id="pq-trabajos-titulo">Sus trabajos <span class="pq-seccion-cuenta"><?= count($fotos) ?>/<?= \App\Models\Empleado::MAX_FOTOS ?></span></h2>
    <p class="pq-ayuda">Fotos de cortes, peinados o uñas que haya hecho. Es lo que convence a un cliente nuevo.</p>
    <?php if ($fotos !== []): ?>
      <ul class="pq-ficha-trabajos">
        <?php foreach ($fotos as $foto): ?>
          <li>
            <img src="<?= e(base_url($foto['ruta'])) ?>" alt="" width="120" height="120" loading="lazy">
            <form method="post" action="<?= e(base_url($base . '/fotos/' . $foto['id'] . '/eliminar')) ?>" data-confirmar="¿Quitar esta foto?">
              <?= csrf_campo() ?>
              <button type="submit" class="pq-ficha-trabajo-quitar" aria-label="Quitar esta foto">×</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if (count($fotos) < \App\Models\Empleado::MAX_FOTOS): ?>
      <form method="post" action="<?= e(base_url($base . '/fotos')) ?>" enctype="multipart/form-data" class="pq-ficha-subir">
        <?= csrf_campo() ?>
        <input class="pq-input" type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required aria-label="Fotos de trabajos de <?= e($primerNombre) ?>">
        <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Subir fotos</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="pq-ficha-bloque" id="servicios" aria-labelledby="pq-servicios-emp-titulo">
    <h2 class="pq-seccion-titulo" id="pq-servicios-emp-titulo">Qué servicios hace</h2>
    <p class="pq-ayuda"><?= $restringido ? 'Solo aparece para los servicios marcados.' : 'Hoy hace todos los servicios al precio normal.' ?> Si cobra distinto o se demora distinto, escríbelo; vacío = lo del servicio.</p>
    <?php if ($servicios === []): ?>
      <p class="pq-ayuda">Primero crea tus servicios.</p>
    <?php else: ?>
      <form method="post" action="<?= e(base_url($base . '/servicios')) ?>" class="pq-ficha-servicios">
        <?= csrf_campo() ?>
        <?php foreach ($servicios as $servicio): ?>
          <?php
          $sid = (int) $servicio['id'];
          $propio = $propios[$sid] ?? null;
          $hace = !$restringido || $propio !== null;
          ?>
          <div class="pq-ficha-servicio">
            <label class="pq-reglas-opcion"><input type="checkbox" name="servicio[<?= $sid ?>][hace]" value="1" <?= $hace ? 'checked' : '' ?>><span><strong><?= e($servicio['nombre']) ?></strong> <span class="pq-ayuda"><?= e(precio_texto($servicio)) ?> · <?= (int) $servicio['duracion_min'] ?> min</span></span></label>
            <div class="pq-ficha-servicio-propio">
              <div class="pq-campo-dinero"><input class="pq-input pq-mono" type="text" inputmode="numeric" name="servicio[<?= $sid ?>][precio]" value="<?= ($propio['precio'] ?? null) !== null ? number_format((int) $propio['precio'], 0, ',', '.') : '' ?>" placeholder="Su precio" data-precio-cop aria-label="Precio de <?= e($primerNombre) ?> para <?= e($servicio['nombre']) ?>"></div>
              <div class="pq-campo-sufijo" data-sufijo="min"><input class="pq-input pq-mono" type="number" min="5" step="5" name="servicio[<?= $sid ?>][duracion_min]" value="<?= ($propio['duracion_min'] ?? null) !== null ? (int) $propio['duracion_min'] : '' ?>" placeholder="—" aria-label="Duración de <?= e($primerNombre) ?> para <?= e($servicio['nombre']) ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
        <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar servicios</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="pq-ficha-bloque" id="horario" aria-labelledby="pq-horario-emp-titulo">
    <h2 class="pq-seccion-titulo" id="pq-horario-emp-titulo">Horario</h2>
    <form method="post" action="<?= e(base_url($base . '/horario')) ?>" class="pq-horario-form">
      <?= csrf_campo() ?>
      <label class="pq-reglas-opcion"><input type="radio" name="usa_horario" value="negocio" <?= $usaPropio ? '' : 'checked' ?>><span>El mismo del negocio</span></label>
      <label class="pq-reglas-opcion"><input type="radio" name="usa_horario" value="propio" <?= $usaPropio ? 'checked' : '' ?>><span>Su propio horario <span class="pq-ayuda">(p. ej. entra más tarde o descansa un día)</span></span></label>
      <div data-mostrar-si="usa_horario=propio">
        <?php $sugerirSiVacio = false; require __DIR__ . '/_semana.php'; ?>
        <p class="pq-ayuda">Los días que el negocio no abre no se ofrecen aunque los marques.</p>
      </div>
      <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Guardar horario</button>
    </form>
  </section>

  <section class="pq-ficha-bloque pq-ficha-acciones">
    <form method="post" action="<?= e(base_url($base . '/alternar')) ?>">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-enlace-boton"><?= (int) $empleado['activo'] === 1 ? 'Pausar (vacaciones, incapacidad…)' : 'Volver a activar' ?></button>
    </form>
    <form method="post" action="<?= e(base_url($base . '/eliminar')) ?>" data-confirmar="¿Quitar a <?= e($empleado['nombre']) ?> del equipo? Sus citas quedan sin persona asignada y se borran sus fotos.">
      <?= csrf_campo() ?>
      <button type="submit" class="pq-enlace-boton pq-enlace-boton-peligro">Quitar del equipo</button>
    </form>
  </section>
</div>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
