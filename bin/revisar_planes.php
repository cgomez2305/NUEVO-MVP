<?php

declare(strict_types=1);

// Solo consola: si el hosting llegara a exponer bin/ por web, nadie puede
// dispararlo desde un navegador (crear admins, mandar recordatorios...).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Cron de vencimiento de planes: baja a Gratis todo negocio cuyo plan
 * pago (Barrio/Pro) venció sin que se confirmara un pago nuevo a tiempo
 * (ver src/Models/PagoPlan.php y /panel/plan). Nunca bloquea la tienda —
 * esa es la política de "sin penalidad" que promete la web — solo vuelve
 * a aplicar los límites del plan Gratis desde ese momento.
 *
 * Uso (crontab, una vez al día es suficiente):
 *   0 3 * * * php /ruta/al/proyecto/bin/revisar_planes.php
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Models\Negocio;

$degradados = Negocio::degradarVencidos();

echo "Negocios degradados a Gratis por vencimiento: {$degradados}.\n";
