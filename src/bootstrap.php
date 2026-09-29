<?php

declare(strict_types=1);

// Cookie de sesión reforzada: HttpOnly evita que JS la lea (mitiga robo por
// XSS), SameSite=Lax evita que viaje en peticiones cross-site, y Secure se
// activa solo si la petición ya llega por HTTPS (así no rompe el desarrollo
// local por HTTP, pero exige HTTPS en producción real).
$httpsActivo = (
    (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? '') === '443'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $httpsActivo,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Cabeceras de seguridad para toda respuesta de la app (no aplica a los
// activos estáticos de docs/, que sirve GitHub Pages por separado).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header(
    "Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "script-src 'self'; frame-ancestors 'none'"
);

date_default_timezone_set('America/Bogota');

spl_autoload_register(function (string $clase): void {
    $prefijo = 'App\\';
    if (strncmp($prefijo, $clase, strlen($prefijo)) !== 0) {
        return;
    }
    $ruta = __DIR__ . '/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});

require_once __DIR__ . '/helpers.php';
