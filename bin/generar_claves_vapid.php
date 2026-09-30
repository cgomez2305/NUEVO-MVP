<?php

declare(strict_types=1);

/**
 * Genera un par de llaves VAPID nuevo para las notificaciones push. Se
 * corre UNA sola vez por instalación (no por negocio: todos los negocios
 * de esta instalación de Veci comparten el mismo par de llaves VAPID).
 * El resultado se pega tal cual en config/config.php, bajo 'push_vapid'.
 *
 * Uso:
 *   php bin/generar_claves_vapid.php
 */

require __DIR__ . '/../src/Services/WebPush.php';

use App\Services\WebPush;

$claves = WebPush::generarClavesVapid();

echo "Pega esto en config/config.php, dentro del arreglo que devuelve:\n\n";
echo "    'push_vapid' => [\n";
echo "        'public_key'  => '" . $claves['public_key'] . "',\n";
echo "        'private_key' => <<<'PEM'\n" . rtrim($claves['private_key'], "\n") . "\nPEM,\n";
echo "        'subject'     => 'mailto:soporte@tuveci.co', // cámbialo por un correo real de contacto\n";
echo "    ],\n";
