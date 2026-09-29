<?php

declare(strict_types=1);

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

foreach ($citas as $cita) {
    $mensaje = RecordatorioWhatsapp::mensajeRecordatorio($cita);
    $ok = RecordatorioWhatsapp::enviar($cita, (string) $cita['cliente_telefono'], $mensaje);

    if ($ok) {
        Cita::marcarRecordatorioEnviado((int) $cita['id'], (int) $cita['negocio_id']);
        $enviados++;
    }
}

echo "Recordatorios enviados: {$enviados} de " . count($citas) . ".\n";
