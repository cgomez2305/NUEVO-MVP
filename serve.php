<?php

/**
 * Solo para desarrollo local con el servidor embebido de PHP:
 *   php -S localhost:8000 serve.php
 *
 * En producción no se usa: ahí manda public/.htaccess.
 */

$ruta = urldecode((string) (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/'));
$archivo = __DIR__ . '/public' . $ruta;

if ($ruta !== '/' && is_file($archivo)) {
    // Servido a mano (y no con "return false") para que esto funcione sin
    // importar si arrancaste el servidor con -t public o desde la raíz.
    $tipos = [
        'css' => 'text/css', 'js' => 'application/javascript',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
    ];
    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($tipos[$extension] ?? 'application/octet-stream'));
    readfile($archivo);
    return true;
}

require __DIR__ . '/public/index.php';
