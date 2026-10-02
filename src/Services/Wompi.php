<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PagoPlan;

/**
 * Pago de planes con Wompi (Web Checkout): tarjeta, PSE, Nequi y Bancolombia
 * sin que el dinero pase por Veci a mano. Es la fase 2 del cobro híbrido:
 * sin llaves configuradas (config/config.php → wompi) todo sigue como antes,
 * con la transferencia Bre-B que un admin confirma.
 *
 * Seguridad:
 * - El monto y la referencia van firmados (firma de integridad) para que
 *   nadie cambie el precio en el navegador.
 * - Nunca se confía en el regreso del navegador: un plan se activa solo con
 *   un evento firmado (webhook, checksum con el secreto de eventos) o
 *   consultando la transacción directamente a la API de Wompi.
 * - Se exige APPROVED, la referencia de un pago pendiente y el monto exacto.
 */
class Wompi
{
    public static function disponible(): bool
    {
        return self::cadena('wompi.llave_publica') !== '' && self::cadena('wompi.secreto_integridad') !== '';
    }

    public static function llavePublica(): string
    {
        return self::cadena('wompi.llave_publica');
    }

    public static function urlCheckout(): string
    {
        return 'https://checkout.wompi.co/p/';
    }

    /** Sandbox si la llave es de pruebas (pub_test_...). */
    public static function urlApi(): string
    {
        return str_starts_with(self::llavePublica(), 'pub_test_') ? 'https://sandbox.wompi.co/v1' : 'https://production.wompi.co/v1';
    }

    /** Una referencia nueva por intento (Wompi no deja repetirlas): VECIPLAN-{pago}-{azar}. */
    public static function referencia(int $pagoId): string
    {
        return 'VECIPLAN-' . $pagoId . '-' . bin2hex(random_bytes(4));
    }

    public static function pagoDeReferencia(string $referencia): ?int
    {
        return preg_match('/^VECIPLAN-(\d+)-[a-f0-9]{8}$/', $referencia, $m) === 1 ? (int) $m[1] : null;
    }

    /** Firma de integridad del Web Checkout: SHA-256(referencia + centavos + moneda + secreto). */
    public static function firmaIntegridad(string $referencia, int $centavos, string $moneda = 'COP'): string
    {
        return hash('sha256', $referencia . $centavos . $moneda . self::cadena('wompi.secreto_integridad'));
    }

    /**
     * Valida el checksum de un evento: concatena los valores de
     * signature.properties (rutas dentro de data), el timestamp y el
     * secreto de eventos, y compara su SHA-256 con signature.checksum.
     */
    public static function eventoValido(array $evento): bool
    {
        $secreto = self::cadena('wompi.secreto_eventos');
        $propiedades = $evento['signature']['properties'] ?? null;
        $checksum = $evento['signature']['checksum'] ?? null;
        if ($secreto === '' || !is_array($propiedades) || !is_string($checksum) || !isset($evento['timestamp'])) {
            return false;
        }
        $cadena = '';
        foreach ($propiedades as $ruta) {
            $valor = $evento['data'] ?? null;
            foreach (explode('.', (string) $ruta) as $parte) {
                $valor = is_array($valor) ? ($valor[$parte] ?? null) : null;
            }
            if ($valor === null || is_array($valor)) {
                return false;
            }
            $cadena .= is_bool($valor) ? ($valor ? 'true' : 'false') : (string) $valor;
        }
        $cadena .= (string) $evento['timestamp'] . $secreto;

        return hash_equals(strtolower(hash('sha256', $cadena)), strtolower($checksum));
    }

    /**
     * Consulta la transacción a la API de Wompi (fuente de verdad, no el
     * navegador). null si no se pudo consultar.
     */
    public static function consultarTransaccion(string $id): ?array
    {
        if (!preg_match('/^[A-Za-z0-9-]{6,64}$/', $id) || !function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init(self::urlApi() . '/transactions/' . rawurlencode($id));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12]);
        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($respuesta) || $codigo !== 200) {
            return null;
        }
        $datos = json_decode($respuesta, true);

        return is_array($datos['data'] ?? null) ? $datos['data'] : null;
    }

    /**
     * Aplica una transacción de Wompi a su pago de plan. Devuelve
     * 'confirmado' | 'ya_confirmado' | 'pendiente' (aún no aprobada) |
     * 'rechazado' | 'invalido' (referencia o monto que no cuadran).
     */
    public static function procesarTransaccion(array $transaccion): string
    {
        $pagoId = self::pagoDeReferencia((string) ($transaccion['reference'] ?? ''));
        $pago = $pagoId !== null ? PagoPlan::buscarPorId($pagoId) : null;
        if ($pago === null) {
            return 'invalido';
        }
        if ($pago['confirmado_en'] !== null) {
            return 'ya_confirmado';
        }
        $estado = (string) ($transaccion['status'] ?? '');
        if ($estado === 'PENDING') {
            return 'pendiente';
        }
        if ($estado !== 'APPROVED') {
            return 'rechazado';
        }
        if ((int) ($transaccion['amount_in_cents'] ?? 0) !== (int) $pago['monto'] * 100 || ($transaccion['currency'] ?? 'COP') !== 'COP') {
            return 'invalido';
        }

        return PagoPlan::confirmarPorPasarela((int) $pago['id'], (string) ($transaccion['id'] ?? '')) ? 'confirmado' : 'ya_confirmado';
    }

    private static function cadena(string $clave): string
    {
        $valor = config($clave);

        return is_string($valor) ? trim($valor) : '';
    }
}
