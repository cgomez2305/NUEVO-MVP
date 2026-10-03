<?php

declare(strict_types=1);

// Solo consola: si el hosting llegara a exponer bin/ por web, nadie puede
// dispararlo desde un navegador (crear admins, mandar recordatorios...).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Cron de recordatorios de cita: revisa todas las citas de todos los
 * negocios que caen en las próximas 24-30 horas y, si hay una cuenta de
 * WhatsApp Business API conectada (config/config.php → whatsapp_api),
 * envía el recordatorio automáticamente y lo marca como enviado.
 *
 * Sin esa API conectada este script no hace nada: el recordatorio se queda
 * disponible para envío manual en /panel/recordatorios (enlace wa.me), así
 * que correr o no este cron es opcional, no un requisito del flujo.
 *
 * Uso (crontab, cada hora):
 *   0 * * * * php /ruta/al/proyecto/bin/recordatorios.php
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Models\Cita;
use App\Services\RecordatorioWhatsapp;

if (!RecordatorioWhatsapp::disponible()) {
    echo "Sin whatsapp_api configurado en config/config.php: nada que enviar automáticamente.\n";
    exit(0);
}

$citas = Cita::pendientesDeRecordatorioGlobal();
$enviados = 0;

$sedes = [];
foreach ($citas as $cita) {
    // Se "reserva" la cita antes de enviar: si dos ejecuciones del cron se
    // cruzan, solo una le escribe al cliente (nada de recordatorios dobles).
    if (!Cita::reclamarRecordatorio((int) $cita['id'])) {
        continue;
    }
    $sede = $sedes[(int) $cita['sede_id']] ??= (\App\Models\Sede::buscarPorId((int) $cita['sede_id']) ?? []);
    $mensaje = RecordatorioWhatsapp::mensajeRecordatorio($cita, $sede);
    $ok = RecordatorioWhatsapp::enviar($cita, (string) $cita['cliente_telefono'], $mensaje);

    if (!$ok) {
        Cita::liberarRecordatorio((int) $cita['id']);
    }
    if ($ok) {
        $enviados++;
    }
}

echo "Recordatorios enviados: {$enviados} de " . count($citas) . ".\n";
