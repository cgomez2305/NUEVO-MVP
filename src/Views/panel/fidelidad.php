<?php
$activa = $config !== null && (int) $config['activa'] === 1;
$meta = (int) ($config['meta'] ?? 8);
$premio = (string) ($config['premio'] ?? '');
$nombreNegocio = (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']);
$urlTienda = (int) ($negocio['publicada'] ?? 0) === 1 ? url_publica('/t/' . $negocio['slug']) : null;
$whatsappDe = static fn (string $telefono): string => 'https://wa.me/57' . preg_replace('/\D+/', '', $telefono);
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Crecimiento</span>
    <h1 class="pq-h1">Tarjeta de sellos</h1>
  </div>
  <?php if ($config !== null): ?>
    <span class="pq-chip <?= $activa ? 'pq-chip-caja' : 'pq-chip-pendiente' ?>"><?= $activa ? 'Activa' : 'En pausa' ?></span>
  <?php endif; ?>
</div>
<p class="pq-lead pq-pagina-bajada-panel">La tarjeta de cartón de toda la vida, pero sin cartón: cada compra con su WhatsApp suma un sello y Veci lleva la cuenta.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($config !== null): ?>
  <div class="pq-caja-dia pq-caja-dia-3" role="group" aria-label="Resumen de la tarjeta">
    <div class="pq-caja-casilla">
      <span class="pq-caja-valor"><?= (int) $conSellos ?></span>
      <span class="pq-caja-etiqueta">Clientes con sellos</span>
    </div>
    <div class="pq-caja-casilla<?= $listos !== [] ? ' pq-caja-casilla-alerta' : '' ?>">
      <span class="pq-caja-valor"><?= count($listos) ?></span>
      <span class="pq-caja-etiqueta">Listos para premio</span>
    </div>
    <div class="pq-caja-casilla">
      <span class="pq-caja-valor"><?= (int) $premios ?></span>
      <span class="pq-caja-etiqueta">Premios entregados</span>
    </div>
  </div>
<?php endif; ?>

<div class="pq-fidelidad">
  <form method="post" action="<?= e(base_url('/panel/fidelidad')) ?>" class="pq-fidelidad-form pq-admin-tarjeta">
    <?= csrf_campo() ?>
    <label class="pq-interruptor pq-interruptor-con-texto">
      <input type="checkbox" name="activa" value="1" <?= $config === null || $activa ? 'checked' : '' ?>>
      <span class="pq-interruptor-pista" aria-hidden="true"></span>
      <span><strong>Tarjeta activa</strong><span class="pq-ayuda">En pausa no se suman sellos nuevos; los que llevan se guardan.</span></span>
    </label>

    <fieldset class="pq-campo pq-fidelidad-meta">
      <legend class="pq-label">Sellos para el premio</legend>
      <div class="pq-cupon-tipo">
        <?php foreach (\App\Models\Fidelidad::METAS as $opcion): ?>
          <label><input type="radio" name="meta" value="<?= $opcion ?>" <?= $opcion === $meta ? 'checked' : '' ?>><span><?= $opcion ?></span></label>
        <?php endforeach; ?>
      </div>
      <span class="pq-ayuda">Si tus clientes vienen cada semana, 8 es un buen número: premio cada dos meses.</span>
    </fieldset>

    <div class="pq-campo">
      <label class="pq-label" for="fidelidad-premio">Premio</label>
      <input class="pq-input" id="fidelidad-premio" type="text" name="premio" value="<?= e($premio) ?>" maxlength="120" placeholder="<?= ($negocio['tipo_negocio'] ?? '') === 'reservas' ? 'Ej.: un corte gratis' : 'Ej.: un almuerzo gratis' ?>">
    </div>

    <div class="pq-campo">
      <label class="pq-label" for="fidelidad-minimo">Compra mínima para sumar sello</label>
      <input class="pq-input" id="fidelidad-minimo" type="text" inputmode="numeric" name="minimo_compra" value="<?= (int) ($config['minimo_compra'] ?? 0) > 0 ? number_format((int) $config['minimo_compra'], 0, ',', '.') : '' ?>" placeholder="Cualquier compra" data-precio>
      <span class="pq-ayuda">Evita que alguien junte sellos comprando un tinto a la vez.</span>
    </div>

    <button type="submit" class="pq-btn pq-btn-sello"><?= $config === null ? 'Activar tarjeta' : 'Guardar cambios' ?></button>
  </form>

  <div class="pq-fidelidad-vista">
    <p class="pq-ayuda pq-fidelidad-vista-nota">Así la ve tu cliente después de su tercera compra:</p>
    <div class="pq-fidelidad-papel">
      <?php $pqSellos = 3; $pqMeta = $meta; $pqPremio = $premio !== '' ? $premio : 'tu premio'; $pqNuevo = false; $pqTitulo = 'Tarjeta de ' . $nombreNegocio; require __DIR__ . '/../tienda/_tarjeta_sellos.php'; ?>
    </div>
  </div>
</div>

<?php if ($config !== null): ?>
  <section class="pq-admin-seccion" aria-labelledby="pq-titulo-listos">
    <h2 class="pq-seccion-titulo" id="pq-titulo-listos">Listos para premio</h2>
    <?php if ($listos === []): ?>
      <p class="pq-ayuda">Nadie ha completado la tarjeta todavía. Cuando pase, también lo verás en el pedido y en el mensaje de WhatsApp.</p>
    <?php else: ?>
      <ul class="pq-admin-tarjeta pq-admin-filas">
        <?php foreach ($listos as $tarjeta): ?>
          <li class="pq-admin-fila">
            <span class="pq-admin-fila-texto">
              <strong><?= e($tarjeta['nombre']) ?></strong>
              <span class="pq-ayuda pq-mono"><?= e($tarjeta['telefono']) ?> · <?= $tarjeta['sellos'] ?>/<?= $meta ?> sellos<?= $tarjeta['premios'] > 0 ? ' · ' . $tarjeta['premios'] . ' premio' . ($tarjeta['premios'] === 1 ? '' : 's') . ' antes' : '' ?></span>
            </span>
            <form method="post" action="<?= e(base_url('/panel/fidelidad/' . $tarjeta['id'] . '/premio')) ?>" data-confirmar="¿Le entregaste <?= e($premio) ?> a <?= e($tarjeta['nombre']) ?>? Su tarjeta vuelve a empezar.">
              <?= csrf_campo() ?>
              <input type="hidden" name="volver" value="/panel/fidelidad">
              <button type="submit" class="pq-btn pq-btn-sello pq-btn-chico">Entregar premio</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php if ($cerca !== []): ?>
    <section class="pq-admin-seccion" aria-labelledby="pq-titulo-cerca">
      <h2 class="pq-seccion-titulo" id="pq-titulo-cerca">A punto de completarla</h2>
      <p class="pq-ayuda">Un recordatorio a tiempo trae la compra que les falta.</p>
      <ul class="pq-admin-tarjeta pq-admin-filas">
        <?php foreach ($cerca as $tarjeta): ?>
          <?php
          $faltan = $meta - $tarjeta['sellos'];
          $aviso = 'Hola ' . explode(' ', trim($tarjeta['nombre']))[0] . ', te escribimos de ' . $nombreNegocio . '. ¡Ya llevas ' . $tarjeta['sellos'] . ' de ' . $meta . ' sellos! '
              . ($faltan === 1 ? 'Con tu próxima compra' : 'Con ' . $faltan . ' compras más') . ' te llevas ' . $premio . '.'
              . ($urlTienda !== null ? ' ' . $urlTienda : '');
          ?>
          <li class="pq-admin-fila">
            <span class="pq-admin-fila-texto">
              <strong><?= e($tarjeta['nombre']) ?></strong>
              <span class="pq-ayuda"><?= $tarjeta['sellos'] ?>/<?= $meta ?> · <?= $faltan === 1 ? 'le falta 1' : 'le faltan ' . $faltan ?></span>
            </span>
            <a class="pq-btn pq-btn-ghost pq-btn-chico" href="<?= e($whatsappDe($tarjeta['telefono'])) ?>?text=<?= rawurlencode($aviso) ?>" target="_blank" rel="noopener">Avisarle</a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
