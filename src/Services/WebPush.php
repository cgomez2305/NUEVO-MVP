<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Web Push estándar (RFC 8291 + RFC 8292/VAPID), implementado con
 * ext-openssl nada más: sin SDK de Firebase, sin cuenta de terceros, sin
 * dependencias de Composer. El navegador se suscribe directo al servicio
 * de push real del sistema operativo (FCM en Chrome/Edge/Android, Mozilla
 * autopush en Firefox, Apple Push en Safari); esta clase le manda el
 * mensaje cifrado a esa URL usando solo la llave pública/privada VAPID
 * que este proyecto genera una vez (ver bin/generar_claves_vapid.php).
 *
 * Es lo mismo que hace cualquier librería de "web push" en cualquier
 * lenguaje; aquí está escrito a mano para no meterle un árbol de
 * dependencias a una app que hasta ahora no necesitaba Composer.
 */
class WebPush
{
    private const CURVA = 'prime256v1'; // P-256, el que exige el estándar Web Push.

    /** Genera un par de llaves VAPID nuevas. Se usa una sola vez; el resultado se pega en config.php. */
    public static function generarClavesVapid(): array
    {
        $par = self::generarParEc();
        $detalles = openssl_pkey_get_details($par);

        $pem = '';
        openssl_pkey_export($par, $pem);

        return [
            'public_key'  => self::base64url(self::puntoSinComprimir($detalles)),
            'private_key' => $pem,
        ];
    }

    /**
     * Manda una notificación push cifrada a una suscripción real del navegador.
     *
     * @param array{endpoint:string, p256dh:string, auth:string} $suscripcion
     * @return array{ok: bool, http_code: int, expirada: bool}
     */
    public static function enviar(array $suscripcion, string $payloadJson): array
    {
        $vapidPublic = (string) config('push_vapid.public_key', '');
        $vapidPrivate = (string) config('push_vapid.private_key', '');
        $vapidSubject = (string) config('push_vapid.subject', 'mailto:soporte@tuveci.co');

        if ($vapidPublic === '' || $vapidPrivate === '') {
            return ['ok' => false, 'http_code' => 0, 'expirada' => false];
        }

        $cuerpo = self::cifrarPayload(
            $payloadJson,
            self::base64urlDecode($suscripcion['p256dh']),
            self::base64urlDecode($suscripcion['auth'])
        );

        $origen = (string) parse_url($suscripcion['endpoint'], PHP_URL_SCHEME) . '://' . parse_url($suscripcion['endpoint'], PHP_URL_HOST);
        $jwt = self::firmarVapid($origen, $vapidSubject, $vapidPrivate);

        $ch = curl_init($suscripcion['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 86400',
                'Authorization: vapid t=' . $jwt . ', k=' . $vapidPublic,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'ok'       => $codigo >= 200 && $codigo < 300,
            'http_code' => $codigo,
            // 404/410: el navegador canceló la suscripción o ya no existe. Toca borrarla.
            'expirada' => in_array($codigo, [404, 410], true),
        ];
    }

    /**
     * Manda el mismo mensaje a todos los usuarios con acceso a esa sede
     * (el/los dueño(s) del negocio + los colaboradores asignados a esa
     * sede específica) y borra las suscripciones que ya no sirven.
     */
    public static function notificarSede(int $sedeId, string $titulo, string $cuerpo, string $url): void
    {
        $payload = json_encode(['titulo' => $titulo, 'cuerpo' => $cuerpo, 'url' => $url], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        $suscripciones = [];
        foreach (\App\Models\Usuario::conAccesoASede($sedeId) as $usuario) {
            foreach (\App\Models\PushSubscripcion::listarPorUsuario((int) $usuario['id']) as $suscripcion) {
                $suscripciones[] = $suscripcion;
            }
        }

        foreach ($suscripciones as $suscripcion) {
            try {
                $resultado = self::enviar($suscripcion, $payload);
                if ($resultado['expirada']) {
                    \App\Models\PushSubscripcion::eliminarPorEndpoint($suscripcion['endpoint']);
                }
            } catch (\Throwable $e) {
                // Una suscripción corrupta o un fallo de red no debe romper el flujo
                // del cliente (crear su pedido/cita) ni el resto de las suscripciones.
                continue;
            }
        }
    }

    // --- RFC 8291: cifrado del contenido -----------------------------------

    private static function cifrarPayload(string $payload, string $uaPublicRaw, string $authSecret): string
    {
        $salt = random_bytes(16);
        $asPar = self::generarParEc();
        $asDetalles = openssl_pkey_get_details($asPar);
        $asPublicRaw = self::puntoSinComprimir($asDetalles);

        $uaPublicPem = self::pemDesdePuntoSinComprimir($uaPublicRaw);
        $ecdhSecret = openssl_pkey_derive($uaPublicPem, $asPar, 32);
        if ($ecdhSecret === false) {
            throw new \RuntimeException('No se pudo derivar el secreto ECDH para Web Push.');
        }

        $infoClaves = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;
        $prkClaves = hash_hmac('sha256', $ecdhSecret, $authSecret, true);
        $ikm = self::hkdfExpand($prkClaves, $infoClaves, 32);

        $prkContenido = hash_hmac('sha256', $ikm, $salt, true);
        $cek = self::hkdfExpand($prkContenido, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::hkdfExpand($prkContenido, "Content-Encoding: nonce\x00", 12);

        // Un solo registro: el payload más el byte delimitador 0x02 (RFC 8188), sin relleno extra.
        $tag = '';
        $cifrado = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cifrado === false) {
            throw new \RuntimeException('No se pudo cifrar el payload de Web Push.');
        }

        // Cabecera aes128gcm (RFC 8188 §2.1): salt(16) + record_size(4, big endian) + idlen(1) + keyid(idlen).
        $cabecera = $salt . pack('N', 4096) . chr(strlen($asPublicRaw)) . $asPublicRaw;

        return $cabecera . $cifrado . $tag;
    }

    // --- RFC 8292: VAPID -----------------------------------------------------

    private static function firmarVapid(string $audiencia, string $sujeto, string $vapidPrivatePem): string
    {
        $cabecera = self::base64url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
        $claims = self::base64url((string) json_encode([
            'aud' => $audiencia,
            'exp' => time() + 12 * 3600,
            'sub' => $sujeto,
        ], JSON_UNESCAPED_SLASHES));

        $entrada = $cabecera . '.' . $claims;

        $llavePrivada = openssl_pkey_get_private($vapidPrivatePem);
        if ($llavePrivada === false) {
            throw new \RuntimeException('push_vapid.private_key en config.php no es una llave EC privada válida.');
        }

        $firmaDer = '';
        openssl_sign($entrada, $firmaDer, $llavePrivada, OPENSSL_ALGO_SHA256);
        $firmaRS = self::derASignatureRS($firmaDer);

        return $entrada . '.' . self::base64url($firmaRS);
    }

    // --- Utilidades EC / DER / HKDF / base64url -------------------------------

    /** @return \OpenSSLAsymmetricKey */
    private static function generarParEc()
    {
        $par = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => self::CURVA,
        ]);
        if ($par === false) {
            throw new \RuntimeException('Este servidor no puede generar llaves EC (¿falta ext-openssl con soporte EC?).');
        }
        return $par;
    }

    /** Punto sin comprimir 0x04||X||Y (65 bytes) a partir de los detalles de una llave EC de openssl_pkey_get_details(). */
    private static function puntoSinComprimir(array $detalles): string
    {
        $x = str_pad($detalles['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad($detalles['ec']['y'], 32, "\0", STR_PAD_LEFT);
        return "\x04" . $x . $y;
    }

    /**
     * Convierte un punto EC sin comprimir (65 bytes) en un PEM de llave
     * pública que openssl_pkey_derive() pueda usar. openssl no tiene una
     * función para cargar un punto crudo directo, así que se envuelve en
     * el DER estándar de SubjectPublicKeyInfo para prime256v1 (RFC 5480).
     */
    private static function pemDesdePuntoSinComprimir(string $puntoSinComprimir): string
    {
        if (strlen($puntoSinComprimir) !== 65 || $puntoSinComprimir[0] !== "\x04") {
            throw new \RuntimeException('Llave pública EC inválida (se esperaba un punto sin comprimir de 65 bytes).');
        }

        // SEQUENCE { SEQUENCE { OID ecPublicKey, OID prime256v1 }, BIT STRING <punto> }
        $prefijo = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
        $der = $prefijo . $puntoSinComprimir;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** HKDF-Expand (RFC 5869) para longitudes de salida de hasta 32 bytes (un solo bloque HMAC-SHA256). */
    private static function hkdfExpand(string $prk, string $info, int $longitud): string
    {
        if ($longitud > 32) {
            throw new \RuntimeException('hkdfExpand aquí solo soporta hasta 32 bytes de salida.');
        }
        $t = hash_hmac('sha256', $info . "\x01", $prk, true);
        return substr($t, 0, $longitud);
    }

    private static function derASignatureRS(string $der): string
    {
        // DER: SEQUENCE { INTEGER r, INTEGER s }. Cada INTEGER puede traer un
        // byte 0x00 de relleno si el bit alto está en 1; hay que quitarlo y
        // dejar r y s en exactamente 32 bytes cada uno (con padding a la izquierda).
        $offset = 2; // saltar 0x30 <len>
        [$r, $offset] = self::leerEnteroDer($der, $offset);
        [$s, $offset] = self::leerEnteroDer($der, $offset);

        return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);
    }

    /** @return array{0: string, 1: int} */
    private static function leerEnteroDer(string $der, int $offset): array
    {
        // $der[$offset] debe ser 0x02 (INTEGER).
        $longitud = ord($der[$offset + 1]);
        $valor = substr($der, $offset + 2, $longitud);
        $valor = ltrim($valor, "\x00");
        return [$valor, $offset + 2 + $longitud];
    }

    private static function base64url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $datos): string
    {
        $datos = strtr($datos, '-_', '+/');
        $resto = strlen($datos) % 4;
        if ($resto !== 0) {
            $datos .= str_repeat('=', 4 - $resto);
        }
        return (string) base64_decode($datos);
    }
}
