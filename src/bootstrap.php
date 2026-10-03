<?php

declare(strict_types=1);

// Nunca mostrar errores al visitante: un stack trace o un mensaje de PDO
// revela rutas del servidor, nombres de tablas y a veces credenciales. Van
// al log del servidor (error_log) y el visitante ve una página genérica.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e): void {
    error_log('[Veci] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Algo salió mal · Veci</title>'
        . '<div style="font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem;text-align:center">'
        . '<h1 style="font-size:1.4rem">Algo salió mal</h1>'
        . '<p>Tuvimos un problema procesando tu solicitud. Intenta de nuevo en un momento.</p>'
        . '<p><a href="/">Volver al inicio</a></p></div>';
});

// Cookie de sesión reforzada: HttpOnly evita que JS la lea (mitiga robo por
// XSS), SameSite=Lax evita que viaje en peticiones cross-site, y Secure se
// activa solo si la petición ya llega por HTTPS (así no rompe el desarrollo
// local por HTTP, pero exige HTTPS en producción real).
$httpsActivo = (
    (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? '') === '443'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
);
define('VECI_HTTPS', $httpsActivo);
// Estricto: PHP no acepta un id de sesión que él no creó (evita que alguien
// "siembre" un id conocido en el navegador de otro antes de que inicie sesión).
ini_set('session.use_strict_mode', '1');
header_remove('X-Powered-By');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $httpsActivo,
    'httponly' => true,
    'samesite' => 'Lax',
]);
// La API pública (/api/menu-demo) no usa sesión: sin cookie ni archivo de
// sesión por cada visita del sitio.
if (preg_match('#/api/[a-z-]+/?$#', (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)) !== 1) {
    session_start();
}

// Cabeceras de seguridad para toda respuesta de la app (no aplica a los
// activos estáticos de docs/, que sirve GitHub Pages por separado).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
// La cámara solo para este mismo sitio: el mostrador lee códigos de barras
// con ella (BarcodeDetector). Nadie embebido ni de otro origen la pide.
header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
if ($httpsActivo) {
    // Una vez el navegador vio HTTPS, no vuelve a intentar HTTP por un año
    // (evita que alguien en el wifi del barrio degrade la conexión).
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header(
    "Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; "
    . "style-src 'self' 'unsafe-inline'; "
    . "font-src 'self'; "
    . "script-src 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'"
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
