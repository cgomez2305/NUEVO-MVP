<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Cobro manual verificado de un plan pago (ver database/migrations/…
 * _planes_suscripciones.sql): el dueño pide el cambio desde /panel/plan
 * (queda una fila sin confirmar, con lo que se espera que transfiera), y
 * un admin la confirma desde /admin/negocios/{id} al ver el comprobante,
 * lo que activa o extiende el plan del negocio.
 */
class PagoPlan
{
    public static function crearPendiente(
        int $negocioId,
        int $planId,
        int $monto,
        string $ciclo,
        string $periodoInicio,
        string $periodoFin
    ): int {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO pagos_plan (negocio_id, plan_id, monto, metodo_pago, ciclo, periodo_inicio, periodo_fin)
             VALUES (:negocio_id, :plan_id, :monto, :metodo_pago, :ciclo, :periodo_inicio, :periodo_fin)'
        );
        $stmt->execute([
            'negocio_id'     => $negocioId,
            'plan_id'        => $planId,
            'monto'          => $monto,
            'metodo_pago'    => 'breb_manual',
            'ciclo'          => $ciclo,
            'periodo_inicio' => $periodoInicio,
            'periodo_fin'    => $periodoFin,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** La solicitud de cambio de plan más reciente de un negocio que todavía nadie confirmó, si hay una. */
    public static function pendientePorNegocio(int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT pp.*, p.nombre AS plan_nombre
             FROM pagos_plan pp JOIN planes p ON p.id = pp.plan_id
             WHERE pp.negocio_id = :negocio_id AND pp.confirmado_en IS NULL
             ORDER BY pp.creado_en DESC LIMIT 1'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPendientes(): array
    {
        $stmt = Database::conexion()->query(
            'SELECT pp.*, p.nombre AS plan_nombre, n.nombre AS negocio_nombre
             FROM pagos_plan pp
             JOIN planes p ON p.id = pp.plan_id
             JOIN negocios n ON n.id = pp.negocio_id
             WHERE pp.confirmado_en IS NULL
             ORDER BY pp.creado_en ASC'
        );
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT pp.*, p.nombre AS plan_nombre
             FROM pagos_plan pp JOIN planes p ON p.id = pp.plan_id
             WHERE pp.negocio_id = :negocio_id
             ORDER BY pp.creado_en DESC'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM pagos_plan WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Confirma un pago pendiente: lo marca confirmado por ese admin y
     * activa/extiende el plan del negocio hasta el fin del período pagado.
     * No valida de nuevo que esté pendiente — eso lo hace el controller
     * antes de llamar, para poder avisar un mensaje claro si ya no lo está.
     */
    public static function confirmar(int $id, int $adminId): void
    {
        $pago = self::buscarPorId($id);
        if ($pago === null) {
            return;
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE pagos_plan SET confirmado_por = :admin_id, confirmado_en = NOW() WHERE id = :id'
            );
            $stmt->execute(['admin_id' => $adminId, 'id' => $id]);

            $stmtNegocio = $pdo->prepare(
                "UPDATE negocios SET plan_id = :plan_id, plan_estado = 'activo', plan_vence_en = :vence_en, plan_ciclo = :ciclo WHERE id = :negocio_id"
            );
            $stmtNegocio->execute([
                'plan_id'    => $pago['plan_id'],
                'vence_en'   => $pago['periodo_fin'],
                'ciclo'      => $pago['ciclo'],
                'negocio_id' => $pago['negocio_id'],
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
