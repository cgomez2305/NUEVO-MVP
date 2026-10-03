<?php

declare(strict_types=1);

namespace App;

/**
 * Códigos de un solo uso por tiempo (TOTP, RFC 6238), los de Google
 * Authenticator, Microsoft Authenticator, 1Password, etc.: 6 dígitos que
 * cambian cada 30 segundos. Segundo factor del panel interno: con solo la
 * contraseña de un admin no se entra.
 */
class Totp
{
    private const PERIODO = 30;
    private const DIGITOS = 6;
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Secreto nuevo de 160 bits en base32 (lo que se escribe o escanea en la app). */
    public static function nuevoSecreto(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $secreto = '';
        foreach (str_split($bits, 5) as $grupo) {
            $secreto .= self::BASE32[bindec(str_pad($grupo, 5, '0'))];
        }

        return $secreto;
    }

    public static function pasoActual(): int
    {
        return intdiv(time(), self::PERIODO);
    }

    public static function codigo(string $secreto, int $paso): string
    {
        $hmac = hash_hmac('sha1', pack('J', $paso), self::decodificar($secreto), true);
        $desfase = ord($hmac[19]) & 0x0F;
        $numero = ((ord($hmac[$desfase]) & 0x7F) << 24) | (ord($hmac[$desfase + 1]) << 16) | (ord($hmac[$desfase + 2]) << 8) | ord($hmac[$desfase + 3]);

        return str_pad((string) ($numero % (10 ** self::DIGITOS)), self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * El paso de tiempo en que el código es válido (acepta un paso antes y
     * uno después por relojes desfasados), o null. Un paso igual o anterior
     * a $ultimoPaso ya se usó: no se acepta dos veces el mismo código.
     */
    public static function verificar(string $secreto, string $codigo, ?int $ultimoPaso): ?int
    {
        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return null;
        }
        $ahora = self::pasoActual();
        for ($paso = $ahora - 1; $paso <= $ahora + 1; $paso++) {
            if (($ultimoPaso === null || $paso > $ultimoPaso) && hash_equals(self::codigo($secreto, $paso), $codigo)) {
                return $paso;
            }
        }

        return null;
    }

    /** Enlace otpauth:// para apps que lo aceptan (o para generar un QR). */
    public static function uri(string $secreto, string $cuenta): string
    {
        return 'otpauth://totp/' . rawurlencode('Veci:' . $cuenta) . '?secret=' . $secreto . '&issuer=Veci&period=' . self::PERIODO . '&digits=' . self::DIGITOS;
    }

    private static function decodificar(string $secreto): string
    {
        $secreto = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secreto) ?? '');
        $bits = '';
        foreach (str_split($secreto) as $letra) {
            $bits .= str_pad(decbin((int) strpos(self::BASE32, $letra)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $octeto) {
            if (strlen($octeto) === 8) {
                $bytes .= chr(bindec($octeto));
            }
        }

        return $bytes;
    }
}
