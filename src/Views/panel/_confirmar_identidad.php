<?php
/**
 * Campo "Tu contraseña" para acciones sensibles (ver Auth::confirmarIdentidad).
 * No sale si el usuario ya la escribió hace menos de 10 minutos.
 * Variables: $motivoIdentidad (qué protege), $idCampoIdentidad (opcional), $identidadOpcional (true: solo se pide si cambia algo).
 */
if (\App\Auth::identidadReciente()) {
    return;
}
$idCampoIdentidad ??= 'confirmar-identidad';
?>
<div class="pq-campo pq-identidad">
  <label class="pq-label" for="<?= e($idCampoIdentidad) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
    Tu contraseña de Veci
  </label>
  <input class="pq-input" id="<?= e($idCampoIdentidad) ?>" type="password" name="confirmar_password" autocomplete="current-password"<?= empty($identidadOpcional) ? ' required' : '' ?>>
  <span class="pq-ayuda"><?= e($motivoIdentidad) ?> Te la pedimos para confirmar que eres tú (no más de una vez cada 10 minutos).</span>
</div>
