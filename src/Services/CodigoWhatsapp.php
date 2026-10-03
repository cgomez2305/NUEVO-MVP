<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Envía un código de un solo uso por WhatsApp (OTP) con la API de WhatsApp
 * Cloud de Meta. Un código no se puede mandar como texto libre a quien no
 * le ha escrito al negocio en 24 horas: Meta exige una plantilla de
 * categoría "autenticación" aprobada (con el botón "Copiar código"). Su
 * nombre va en config/config.php → whatsapp_api.plantilla_codigo.
 *
 * Sin token, número o plantilla, disponible() es false y quien lo use debe
 * seguir sin OTP (no se puede verificar lo que no se puede enviar).
 *
 * Solo para pruebas locales (tests/ofertas_otp.php): con la variable de
 * entorno VECI_OTP_ARCHIVO y el servidor de desarrollo de PHP, en vez de
 * llamar a Meta escribe "telefono codigo" en ese archivo.
 */
class CodigoWhatsapp
{
    public static function disponible(): bool
    {
        if (self::archivoDePrueba() !== null) {
            return true;
        }
        $plantilla = config('whatsapp_api.plantilla_codigo');

        return RecordatorioWhatsapp::disponible() && is_string($plantilla) && $plantilla !== '';
    }

    public static function enviar(string $telefono, string $codigo): bool
    {
        $telefono = preg_replace('/\D+/', '', $telefono) ?? '';
        $archivo = self::archivoDePrueba();
        if ($archivo !== null) {
            return file_put_contents($archivo, "{$telefono} {$codigo}\n", FILE_APPEND | LOCK_EX) !== false;
        }
        if (!self::disponible() || !function_exists('curl_init')) {
            return false;
        }

        $cuerpo = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => '57' . $telefono,
            'type'              => 'template',
            'template'          => [
                'name'       => (string) config('whatsapp_api.plantilla_codigo'),
                'language'   => ['code' => (string) config('whatsapp_api.plantilla_idioma', 'es')],
                // Las plantillas de autenticación llevan el código en el
                // cuerpo y en el botón de copiar.
                'components' => [
                    ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $codigo]]],
                    ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $codigo]]],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        if ($cuerpo === false) {
            return false;
        }

        $ch = curl_init('https://graph.facebook.com/v20.0/' . config('whatsapp_api.phone_number_id') . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['content-type: application/json', 'Authorization: Bearer ' . config('whatsapp_api.token')],
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $respuesta = curl_exec($ch);
        $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $huboError = curl_errno($ch) !== 0;
        curl_close($ch);
        if ($huboError || $codigoHttp < 200 || $codigoHttp >= 300) {
            error_log('CodigoWhatsapp: Meta respondió ' . $codigoHttp . ' ' . (is_string($respuesta) ? mb_substr($respuesta, 0, 200) : ''));
            return false;
        }

        return true;
    }

    private static function archivoDePrueba(): ?string
    {
        $archivo = getenv('VECI_OTP_ARCHIVO');

        return is_string($archivo) && $archivo !== '' && PHP_SAPI === 'cli-server' ? $archivo : null;
    }
}
