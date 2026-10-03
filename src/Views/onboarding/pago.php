<?php
$esReservas = $negocio['tipo_negocio'] === 'reservas';
$pasoActual = $esReservas ? 4 : 3;
$volverUrl = $esReservas ? '/panel/onboarding/horario' : '/panel/onboarding/productos';
require __DIR__ . '/_pasos.php';

$colorActual = color_seguro($negocio['color_marca'] ?? null);
$paleta = paleta_marca();
if (!array_key_exists(strtoupper($colorActual), $paleta)) {
    $colorActual = array_key_first($paleta);
}
$nombreNegocio = (string) ($negocio['negocio_nombre'] ?? $negocio['nombre']);
$inicial = mb_strtoupper(mb_substr((string) ($negocio['inicial'] ?? $nombreNegocio), 0, 1));
$llaveTipo = $llavePrevia['tipo'] ?? ($negocio['llave_breb_tipo'] ?? 'celular');
$llaveValor = $llavePrevia['valor'] ?? ($negocio['llave_breb_valor'] ?? $negocio['whatsapp']);
// El teclado correcto desde el principio (interacciones.js lo cambia al
// elegir otro tipo).
$llaveInput = match ($llaveTipo) {
    'correo' => ['type' => 'email', 'modo' => 'email'],
    'cedula' => ['type' => 'text', 'modo' => 'numeric'],
    default  => ['type' => 'tel', 'modo' => 'numeric'],
};
$cobroOpcional = $esReservas && !$tieneAnticipos;
?>
<main class="pq-onb-cuerpo">
  <h1 class="pq-h1">Ponle tu color y abre</h1>
  <p class="pq-lead pq-onb-bajada">Así va a verse la entrada de tu tienda. El color lo cambias cuando quieras.</p>

  <?php if (!empty($error)): ?>
    <div class="pq-alerta pq-onb-aviso" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('/panel/onboarding/publicar')) ?>" class="pq-onb-form" data-enviando="Abriendo tu tienda…">
    <?= csrf_campo() ?>

    <?php
    // La vista previa es la cabecera real de la tienda (toldo, insignia y
    // nombre). interacciones.js le cambia --marca al elegir un color; sin
    // JS muestra el color guardado y la elección se aplica al publicar.
    ?>
    <?php
    // Consultorios y despachos no llevan toldo: la vista previa muestra su membrete.
    $fachadaAlta = fachada_tienda($negocio);
    ?>
    <div class="pq-escaparate pq-onb-vista pq-estilo-<?= e($fachadaAlta) ?>" data-vista-marca style="--marca: <?= e($colorActual) ?>; --marca-sobre: <?= e(color_texto_sobre($colorActual)) ?>">
      <?php if ($fachadaAlta === 'barrio'): ?>
        <div class="pq-toldo pq-toldo-corto" aria-hidden="true"></div>
      <?php else: ?>
        <div class="pq-membrete-filete pq-membrete-filete-corto" aria-hidden="true"></div>
      <?php endif; ?>
      <div class="pq-escaparate-cuerpo">
        <div class="pq-escaparate-letrero">
          <span class="<?= $fachadaAlta === 'barrio' ? 'pq-letrero-insignia pq-letrero-insignia-chica' : 'pq-membrete-sello pq-membrete-sello-chico' ?>" aria-hidden="true"><?= e($inicial) ?></span>
          <div class="pq-escaparate-texto">
            <span class="pq-escaparate-eyebrow">Así te verán tus clientes</span>
            <strong class="pq-escaparate-nombre"><?= e($nombreNegocio) ?></strong>
          </div>
        </div>
        <span class="pq-escaparate-url" aria-hidden="true"><?= e(preg_replace('#^https?://#', '', url_publica('/t/' . $negocio['slug']))) ?></span>
      </div>
    </div>

    <fieldset class="pq-onb-colores">
      <legend class="pq-label"><?= $fachadaAlta === 'barrio' ? 'Color de tu toldo' : 'Color de tu marca' ?></legend>
      <div class="pq-onb-colores-lista">
        <?php foreach ($paleta as $hex => $nombreColor): ?>
          <label class="pq-onb-color" style="--muestra: <?= e($hex) ?>">
            <input type="radio" name="color_marca" value="<?= e($hex) ?>" data-color-marca data-sobre="<?= e(color_texto_sobre($hex)) ?>"<?= strtoupper($colorActual) === $hex ? ' checked' : '' ?>>
            <span class="pq-onb-color-muestra" aria-hidden="true"></span>
            <span class="pq-onb-color-nombre"><?= e($nombreColor) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <section class="pq-onb-seccion" aria-labelledby="pq-titulo-cobro">
      <h2 class="pq-seccion-titulo" id="pq-titulo-cobro">
        <?= $esReservas ? 'Dónde recibes los anticipos' : 'Dónde te pagan' ?>
        <?php if ($cobroOpcional): ?><span class="pq-ayuda">(opcional por ahora)</span><?php endif; ?>
      </h2>
      <p class="pq-ayuda">
        <?= $cobroOpcional
          ? 'Ninguno de tus servicios pide anticipo todavía. Dejamos tu WhatsApp como llave; lo cambias cuando quieras.'
          : 'Tu llave Bre-B: el cliente te transfiere directo, sin comisión de tarjeta.' ?>
      </p>

      <div class="pq-llave-tipo" role="radiogroup" aria-label="Tipo de llave">
        <?php foreach (['celular' => 'Celular', 'cedula' => 'Cédula', 'correo' => 'Correo'] as $valorTipo => $textoTipo): ?>
          <label class="pq-llave-tipo-opcion">
            <input type="radio" name="llave_tipo" value="<?= $valorTipo ?>" data-llave-tipo-input <?= $llaveTipo === $valorTipo ? 'checked' : '' ?>>
            <?php if ($valorTipo === 'celular'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>
            <?php elseif ($valorTipo === 'cedula'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="12" r="2"/><path d="M14 10h4M14 14h3"/></svg>
            <?php else: ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
            <?php endif; ?>
            <span><?= $textoTipo ?></span>
            <span class="pq-llave-tipo-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="pq-campo">
        <label class="pq-label" for="llave_valor">Tu llave</label>
        <input class="pq-input pq-mono<?= !empty($error) ? ' pq-input-invalido' : '' ?>" type="<?= $llaveInput['type'] ?>" inputmode="<?= $llaveInput['modo'] ?>" id="llave_valor" name="llave_valor" data-llave-valor-input value="<?= e($llaveValor) ?>" maxlength="120" autocomplete="off" required<?= !empty($error) ? ' aria-invalid="true" autofocus' : '' ?>>
      </div>

      <p class="pq-nota-panel">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2 3 7v6c0 5 4 8 9 9 5-1 9-4 9-9V7Z"/></svg>
        <span>Veci no toca tu plata: el cliente te paga directo y te manda el comprobante por WhatsApp.</span>
      </p>
    </section>

    <div class="pq-onb-pie">
      <button type="submit" class="pq-btn pq-btn-sello">Abrir mi tienda</button>
    </div>
  </form>
</main>
