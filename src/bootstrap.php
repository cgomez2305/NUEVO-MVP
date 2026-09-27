<?php

declare(strict_types=1);

session_start();

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
