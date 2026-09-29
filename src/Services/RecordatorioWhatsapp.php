<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Envía el recordatorio de cita 24h antes por WhatsApp Business API (Meta
 * Cloud API) cuando hay credenciales configuradas (config/config.php →
 * whatsapp_api.token y whatsapp_api.phone_number_id).
 *
 * Sin esas credenciales, enviar() devuelve false y no hace nada: la cita
 * se queda en la cola de envío manual que ve el dueño en el panel
 * (/panel/recordatorios), con el mismo enlace wa.me que ya usa el copiloto.
 * Así el recordatorio funciona de punta a punta desde el día uno, y se
 * vuelve automático el día que el negocio conecte una cuenta real de
 * WhatsApp Business.
 */
class RecordatorioWhatsapp
{
    public static function disponible(): bool
    {
        return is_string(config('whatsapp_api.token'))
            && config('whatsapp_api.token') !== ''
            && is_string(config('whatsapp_api.phone_number_id'))
            && config('whatsapp_api.phone_number_id') !== '';
    }

    /** @param array<string, mixed> $cita */
    public static function enviar(array $cita, string $telefonoCliente, string $mensaje): bool
    {
        if (!self::disponible() || !function_exists('curl_init')) {
            return false;
        }

        $token = (string) config('whatsapp_api.token');
        $phoneNumberId = (string) config('whatsapp_api.phone_number_id');
        $telefono = preg_replace('/\D+/', '', $telefonoCliente) ?? '';

        $cuerpo = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => '57' . $telefono,
            'type'              => 'text',
            'text'              => ['body' => $mensaje],
        ], JSON_UNESCAPED_UNICODE);

        if ($cuerpo === false) {
            return false;
        }

        $ch = curl_init("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_TIMEOUT    => 15,
        ]);
        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $huboError = curl_errno($ch) !== 0;
        curl_close($ch);

        return !$huboError && $respuesta !== false && $codigo >= 200 && $codigo < 300;
    }

    public static function mensajeRecordatorio(array $cita): string
    {
        $fecha = date('d/m/Y', strtotime((string) $cita['fecha_hora']));
        $hora = date('g:i a', strtotime((string) $cita['fecha_hora']));

        return "Hola {$cita['cliente_nombre']}, te recordamos tu cita de {$cita['nombre_servicio']} "
            . "mañana {$fecha} a las {$hora}. Si necesitas cambiarla, escríbenos por aquí.";
    }
}
