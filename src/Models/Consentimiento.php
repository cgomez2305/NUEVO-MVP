<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Registro de consentimientos de los clientes de cada negocio (ver
 * migrations/2026-10-03_32_consentimientos.sql). La Ley 1581 pide poder
 * demostrar cuándo, cómo y bajo qué política alguien autorizó que le
 * escribieran con promociones, y que pueda retirarlo cuando quiera: esto
 * guarda cada cambio, nunca lo sobrescribe.
 */
class Consentimiento
{
    /**
     * Versión de la política de privacidad vigente (docs/privacidad.html).
     * Cambiarla cuando cambie algo importante de la política: los
     * consentimientos nuevos quedan atados a la versión que la persona vio.
     */
    public const POLITICA_VERSION = '2026-10-03';

    /** Orígenes en los que la acción la hizo el propio cliente (se guarda su IP como evidencia). */
    private const ORIGENES_DEL_CLIENTE = ['pedido', 'reserva', 'enlace'];

    public static function registrar(
        int $negocioId,
        int $clienteId,
        string $finalidad,
        bool $otorgado,
        string $origen,
        ?int $usuarioId = null
    ): void {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO consentimientos (negocio_id, cliente_id, finalidad, otorgado, origen, politica_version, usuario_id, ip)
             VALUES (:n, :c, :f, :o, :origen, :v, :u, :ip)'
        );
        $stmt->execute([
            'n'      => $negocioId,
            'c'      => $clienteId,
            'f'      => $finalidad === 'datos' ? 'datos' : 'marketing',
            'o'      => $otorgado ? 1 : 0,
            'origen' => mb_substr($origen, 0, 20),
            'v'      => self::POLITICA_VERSION,
            'u'      => $usuarioId,
            'ip'     => in_array($origen, self::ORIGENES_DEL_CLIENTE, true) && PHP_SAPI !== 'cli' ? ip_cliente() : null,
        ]);
    }

    /**
     * Cambia el permiso de promociones del cliente solo si de verdad cambia,
     * y deja la huella en el registro. Devuelve si hubo cambio.
     */
    public static function cambiarMarketing(int $negocioId, int $clienteId, bool $acepta, string $origen, ?int $usuarioId = null): bool
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE clientes SET acepta_marketing = :a, marketing_actualizado_en = NOW()
             WHERE id = :id AND negocio_id = :n AND acepta_marketing <> :a2'
        );
        $stmt->execute(['a' => $acepta ? 1 : 0, 'a2' => $acepta ? 1 : 0, 'id' => $clienteId, 'n' => $negocioId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        self::registrar($negocioId, $clienteId, 'marketing', $acepta, $origen, $usuarioId);

        return true;
    }

    /**
     * Historial de un cliente, lo más reciente primero (para el panel).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function historial(int $clienteId, int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT finalidad, otorgado, origen, politica_version, creado_en FROM consentimientos
             WHERE cliente_id = :c AND negocio_id = :n ORDER BY id DESC LIMIT 20'
        );
        $stmt->execute(['c' => $clienteId, 'n' => $negocioId]);

        return $stmt->fetchAll();
    }

    /** Texto corto de dónde salió un cambio, para mostrarlo al dueño. */
    public static function origenTexto(string $origen): string
    {
        return match ($origen) {
            'pedido'   => 'al hacer un pedido',
            'reserva'  => 'al reservar',
            'enlace'   => 'desde su enlace de preferencias',
            'panel'    => 'lo anotó el negocio',
            'anterior' => 'antes de este registro',
            default    => $origen,
        };
    }
}
