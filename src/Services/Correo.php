<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Envía correos por SMTP con autenticación (Gmail, SendGrid, el proveedor
 * que sea) cuando hay credenciales configuradas (config/config.php →
 * smtp.host/usuario/password). Se usa solo para el enlace de recuperación
 * de contraseña: el resto de la app es WhatsApp-first a propósito.
 *
 * Sin SMTP configurado, disponible() devuelve false y quien llame debe
 * caer al plan B: la solicitud de recuperación queda pendiente para que
 * el equipo de Veci genere el enlace a mano desde el panel interno
 * (ver AdminController::generarReset()).
 *
 * Cliente SMTP mínimo (RFC 5321) hecho a mano en vez de traer una
 * dependencia de Composer, siguiendo el resto del proyecto: se sube y
 * corre en un hosting compartido sin acceso a SSH ni Composer.
 */
class Correo
{
    public static function disponible(): bool
    {
        return is_string(config('smtp.host')) && config('smtp.host') !== ''
            && is_string(config('smtp.usuario')) && config('smtp.usuario') !== ''
            && is_string(config('smtp.password')) && config('smtp.password') !== '';
    }

    public static function enviar(string $destinatario, string $asunto, string $cuerpo): bool
    {
        if (!self::disponible()) {
            return false;
        }

        $host = (string) config('smtp.host');
        $puerto = (int) config('smtp.port', 587);
        $usuario = (string) config('smtp.usuario');
        $password = (string) config('smtp.password');
        $remitente = (string) config('smtp.remitente', $usuario);
        $remitenteNombre = (string) config('smtp.remitente_nombre', 'Veci');

        $socket = @stream_socket_client(
            "tcp://{$host}:{$puerto}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );
        if ($socket === false) {
            return false;
        }

        try {
            self::leer($socket); // saludo del servidor
            self::comando($socket, "EHLO {$host}", '250');
            self::comando($socket, 'STARTTLS', '220');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return false;
            }

            self::comando($socket, "EHLO {$host}", '250');
            self::comando($socket, 'AUTH LOGIN', '334');
            self::comando($socket, base64_encode($usuario), '334');
            self::comando($socket, base64_encode($password), '235');

            self::comando($socket, "MAIL FROM:<{$remitente}>", '250');
            self::comando($socket, "RCPT TO:<{$destinatario}>", '250');
            self::comando($socket, 'DATA', '354');

            $encabezados = implode("\r\n", [
                'From: ' . self::codificarNombre($remitenteNombre) . " <{$remitente}>",
                "To: <{$destinatario}>",
                'Subject: ' . self::codificarAsunto($asunto),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ]);

            $cuerpoEscapado = preg_replace('/^\./m', '..', $cuerpo) ?? $cuerpo;
            self::escribir($socket, $encabezados . "\r\n\r\n" . $cuerpoEscapado . "\r\n.\r\n");
            self::leer($socket, '250');

            self::comando($socket, 'QUIT', '221');

            return true;
        } catch (\RuntimeException $e) {
            return false;
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private static function comando($socket, string $linea, string $codigoEsperado): void
    {
        self::escribir($socket, $linea . "\r\n");
        self::leer($socket, $codigoEsperado);
    }

    /** @param resource $socket */
    private static function escribir($socket, string $datos): void
    {
        if (fwrite($socket, $datos) === false) {
            throw new \RuntimeException('No se pudo escribir al socket SMTP.');
        }
    }

    /** @param resource $socket */
    private static function leer($socket, ?string $codigoEsperado = null): string
    {
        $respuesta = '';
        while (($linea = fgets($socket, 1024)) !== false) {
            $respuesta .= $linea;
            // Las respuestas multilínea usan "250-" hasta la última "250 ".
            if (preg_match('/^\d{3} /', $linea) === 1) {
                break;
            }
        }

        if ($codigoEsperado !== null && !str_starts_with($respuesta, $codigoEsperado)) {
            throw new \RuntimeException("SMTP esperaba {$codigoEsperado}, recibió: {$respuesta}");
        }

        return $respuesta;
    }

    private static function codificarAsunto(string $asunto): string
    {
        return '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    }

    private static function codificarNombre(string $nombre): string
    {
        return '=?UTF-8?B?' . base64_encode($nombre) . '?=';
    }
}
