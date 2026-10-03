<?php
$esDueno = $negocio['rol'] === 'dueno';
$form = $formAnterior ?? [];
?>
<div class="pq-pagina-cabeza">
  <div>
    <span class="pq-eyebrow">Operación · <?= e($negocio['negocio_nombre'] ?? $negocio['nombre']) ?></span>
    <h1 class="pq-h1">Fiado</h1>
  </div>
</div>
<p class="pq-lead pq-pagina-bajada-panel">La cuenta de cada cliente: lo que se le fió y lo que ha abonado. Es la misma en todas tus sedes.</p>

<?php if (!empty($error)): ?>
  <div class="pq-alerta" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php // Lo que está en la calle: una sola cifra grande, como el total del cuaderno. ?>
<section class="pq-fiado-resumen" aria-label="Total por cobrar">
  <span class="pq-fiado-resumen-etiqueta">Por cobrar</span>
  <strong class="pq-fiado-resumen-cifra"><?= pesos($total) ?></strong>
  <span class="pq-ayuda"><?= count($clientes) === 0 ? 'Nadie te debe' : (count($clientes) === 1 ? '1 cliente te debe' : count($clientes) . ' clientes te deben') ?></span>
</section>

<?php if ($clientes === []): ?>
  <div class="pq-vacio-panel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h11a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6V3Z"/><path d="M10 8h5M10 12h5"/></svg>
    <p><strong>El cuaderno está al día.</strong> Cuando fíes desde el mostrador, o pases aquí lo que te deben, cada cuenta aparece en esta lista.</p>
  </div>
<?php else: ?>
  <section class="pq-admin-seccion" aria-labelledby="pq-titulo-deben">
    <h2 class="pq-seccion-titulo" id="pq-titulo-deben">Te deben <span class="pq-seccion-cuenta"><?= count($clientes) ?></span></h2>
    <p class="pq-ayuda">Primero los que deben desde hace más tiempo.</p>
    <ul class="pq-admin-tarjeta pq-admin-filas">
      <?php foreach ($clientes as $cliente): ?>
        <?php
        $saldo = (int) $cliente['saldo'];
        $limite = $cliente['fiado_limite'] !== null ? (int) $cliente['fiado_limite'] : null;
        $dias = $cliente['debe_desde'] !== null ? dias_desde((string) $cliente['debe_desde']) : 0;
        ?>
        <li class="pq-admin-fila">
          <a class="pq-fiado-fila" href="<?= e(base_url('/panel/fiado/' . (int) $cliente['id'])) ?>">
            <span class="pq-fiado-inicial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $cliente['nombre'], 0, 1))) ?></span>
            <span class="pq-admin-fila-texto">
              <strong class="pq-fiado-nombre"><?= e($cliente['nombre']) ?></strong>
              <span class="pq-ayuda<?= $dias >= 30 ? ' pq-fiado-viejo' : '' ?>">Debe desde <?= e(hace_dias($dias)) ?><?= $limite !== null ? ' · límite ' . pesos($limite) : '' ?></span>
            </span>
            <span class="pq-fiado-saldo pq-mono"><?= pesos($saldo) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($todos !== []): ?>
  <?php // Para abrir la cuenta de alguien que hoy no debe (abonar, cargar lo del cuaderno, ponerle límite). ?>
  <form method="get" action="<?= e(base_url('/panel/fiado')) ?>" class="pq-fiado-buscar">
    <label class="pq-label" for="fiado-cliente">Abrir la cuenta de un cliente</label>
    <div class="pq-fiado-buscar-fila">
      <select class="pq-select" id="fiado-cliente" name="cliente" required>
        <option value="">Elige…</option>
        <?php foreach ($todos as $c): ?>
          <option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="pq-btn pq-btn-ghost pq-btn-chico">Abrir</button>
    </div>
  </form>
<?php endif; ?>

<details class="pq-agregar-panel" id="nuevo-cliente"<?= $form !== [] || ($clientes === [] && $todos === []) ? ' open' : '' ?>>
  <summary class="pq-btn pq-btn-ghost">+ Cliente nuevo</summary>
  <form method="post" action="<?= e(base_url('/panel/fiado/clientes')) ?>" class="pq-agregar-panel-form">
    <?= csrf_campo() ?>
    <div class="pq-campo">
      <label class="pq-label" for="fiado-nombre">Nombre</label>
      <input class="pq-input" id="fiado-nombre" type="text" name="nombre" maxlength="120" required value="<?= e((string) ($form['nombre'] ?? '')) ?>">
    </div>
    <div class="pq-campo">
      <label class="pq-label" for="fiado-telefono">WhatsApp</label>
      <input class="pq-input pq-mono" id="fiado-telefono" type="tel" inputmode="numeric" name="telefono" maxlength="20" required placeholder="300 123 4567" value="<?= e((string) ($form['telefono'] ?? '')) ?>">
      <span class="pq-ayuda">Si ya es tu cliente (compró por la tienda), se usa su cuenta: no se duplica.</span>
    </div>
    <div class="pq-campo">
      <label class="pq-label" for="fiado-inicial">¿Ya te debía algo? <span class="pq-ayuda">(opcional, lo del cuaderno de papel)</span></label>
      <input class="pq-input pq-mono" id="fiado-inicial" type="text" inputmode="numeric" name="saldo_inicial" maxlength="12" data-precio placeholder="0" value="<?= e((string) ($form['saldo_inicial'] ?? '')) ?>">
    </div>
    <?php if ($esDueno): ?>
      <div class="pq-campo">
        <label class="pq-label" for="fiado-limite">Límite de fiado <span class="pq-ayuda">(opcional)</span></label>
        <input class="pq-input pq-mono" id="fiado-limite" type="text" inputmode="numeric" name="limite" maxlength="12" data-precio placeholder="Sin límite">
        <span class="pq-ayuda">El mostrador no deja fiarle por encima de esto.</span>
      </div>
    <?php endif; ?>
    <label class="pq-consentimiento pq-consentimiento-requerido">
      <input type="checkbox" name="autorizo" value="1" required>
      <span>
        <span class="pq-consentimiento-titulo">El cliente autorizó guardar su nombre y número</span>
        <span class="pq-ayuda">Para llevar su cuenta de fiado y recordarle lo que debe (Ley 1581 de 2012). Pregúntaselo antes de marcar.</span>
      </span>
    </label>
    <button type="submit" class="pq-btn pq-btn-sello">Crear cuenta</button>
  </form>
</details>

<?php if (!empty($ok)): ?>
  <div class="pq-toast" role="status">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
    <?= e($ok) ?>
  </div>
<?php endif; ?>
