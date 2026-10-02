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
        string $periodoFin,
        string $concepto = 'plan',
        int $sedesExtra = 0
    ): int {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO pagos_plan (negocio_id, plan_id, concepto, sedes_extra, monto, metodo_pago, ciclo, periodo_inicio, periodo_fin)
             VALUES (:negocio_id, :plan_id, :concepto, :sedes_extra, :monto, :metodo_pago, :ciclo, :periodo_inicio, :periodo_fin)'
        );
        $stmt->execute([
            'negocio_id'     => $negocioId,
            'plan_id'        => $planId,
            'concepto'       => $concepto === 'sede_extra' ? 'sede_extra' : 'plan',
            'sedes_extra'    => max(0, $sedesExtra),
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
     * Confirma un pago pendiente y activa/extiende el plan del negocio.
     * Devuelve false si el pago ya no estaba pendiente (otro admin lo
     * confirmó, o un doble clic): la fila se bloquea con FOR UPDATE y el
     * UPDATE exige confirmado_en IS NULL, así un mismo pago nunca puede
     * extender el plan dos veces.
     *
     * El período se calcula aquí, al confirmar, y no al pedir: si el
     * negocio está renovando el MISMO plan y todavía le quedan días, el
     * período nuevo arranca donde termina el actual (no pierde lo pagado);
     * en cualquier otro caso arranca hoy.
     *
     * Una sede extra (concepto 'sede_extra') no mueve el período: suma sus
     * sedes al cupo hasta que vence el plan vigente. Un pago de plan deja
     * el cupo extra en lo que ese pago cubrió.
     */
    public static function confirmar(int $id, int $adminId): bool
    {
        return self::aplicar($id, $adminId, null);
    }

    /**
     * Lo mismo que confirmar(), pero por la pasarela (Wompi): sin admin, con
     * la transacción que pagó (única: la misma transacción no confirma dos
     * pagos) y método de pago 'wompi'.
     */
    public static function confirmarPorPasarela(int $id, string $transaccionId): bool
    {
        return self::aplicar($id, null, $transaccionId !== '' ? $transaccionId : null);
    }

    private static function aplicar(int $id, ?int $adminId, ?string $transaccionId): bool
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM pagos_plan WHERE id = :id AND confirmado_en IS NULL FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $pago = $stmt->fetch();
            if ($pago === false) {
                $pdo->rollBack();
                return false;
            }

            if ($pago['concepto'] === 'sede_extra') {
                $pdo->prepare(
                    'UPDATE pagos_plan
                        SET confirmado_por = :admin_id, confirmado_en = NOW(), periodo_inicio = CURDATE(),
                            transaccion_pasarela = :transaccion,
                            metodo_pago = IF(:es_pasarela = 1, \'wompi\', metodo_pago)
                      WHERE id = :id AND confirmado_en IS NULL'
                )->execute([
                    'admin_id'    => $adminId,
                    'transaccion' => $transaccionId,
                    'es_pasarela' => $transaccionId !== null ? 1 : 0,
                    'id'          => $id,
                ]);
                $pdo->prepare('UPDATE negocios SET sedes_extra = LEAST(255, sedes_extra + :n) WHERE id = :negocio_id')
                    ->execute(['n' => (int) $pago['sedes_extra'], 'negocio_id' => $pago['negocio_id']]);
                $pdo->commit();

                return true;
            }

            $stmtNegocio = $pdo->prepare('SELECT plan_id, plan_vence_en FROM negocios WHERE id = :id FOR UPDATE');
            $stmtNegocio->execute(['id' => $pago['negocio_id']]);
            $negocio = $stmtNegocio->fetch();

            $hoy = new \DateTimeImmutable('today');
            $inicio = $hoy;
            if (
                $negocio !== false
                && (int) $negocio['plan_id'] === (int) $pago['plan_id']
                && !empty($negocio['plan_vence_en'])
                && $negocio['plan_vence_en'] >= $hoy->format('Y-m-d')
            ) {
                $inicio = new \DateTimeImmutable((string) $negocio['plan_vence_en']);
            }
            $fin = $inicio->modify($pago['ciclo'] === 'anual' ? '+1 year' : '+30 days');

            $pdo->prepare(
                'UPDATE pagos_plan
                    SET confirmado_por = :admin_id, confirmado_en = NOW(),
                        periodo_inicio = :inicio, periodo_fin = :fin,
                        transaccion_pasarela = :transaccion,
                        metodo_pago = IF(:es_pasarela = 1, \'wompi\', metodo_pago)
                  WHERE id = :id AND confirmado_en IS NULL'
            )->execute([
                'admin_id' => $adminId,
                'transaccion' => $transaccionId,
                'es_pasarela' => $transaccionId !== null ? 1 : 0,
                'inicio'   => $inicio->format('Y-m-d'),
                'fin'      => $fin->format('Y-m-d'),
                'id'       => $id,
            ]);

            $pdo->prepare(
                "UPDATE negocios SET plan_id = :plan_id, plan_estado = 'activo', plan_vence_en = :vence_en, plan_ciclo = :ciclo, sedes_extra = :sedes_extra WHERE id = :negocio_id"
            )->execute([
                'plan_id'    => $pago['plan_id'],
                'sedes_extra' => (int) $pago['sedes_extra'],
                'vence_en'   => $fin->format('Y-m-d'),
                'ciclo'      => $pago['ciclo'],
                'negocio_id' => $pago['negocio_id'],
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // Si este negocio llegó invitado, quien lo invitó gana sus días
        // ahora que pagó (solo la primera vez; ver Referido).
        Referido::premiarPorPago((int) $pago['negocio_id']);

        return true;
    }

    /** El dueño retira su propia solicitud pendiente (acotado a SU negocio: nunca toca la de otro). */
    public static function cancelarPendienteDeNegocio(int $negocioId): bool
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM pagos_plan WHERE negocio_id = :negocio_id AND confirmado_en IS NULL'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->rowCount() > 0;
    }

    /** Descarta una solicitud pendiente que nunca se pagó (o se pagó mal), para que el dueño pueda pedir de nuevo. */
    public static function rechazar(int $id): bool
    {
        $stmt = Database::conexion()->prepare('DELETE FROM pagos_plan WHERE id = :id AND confirmado_en IS NULL');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }
}
