<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Referidos entre negocios (ver database/migrations/2026-10-03_11_referidos.sql).
 * El premio llega cuando el invitado PAGA su primer plan (no al
 * registrarse): así no se gana nada creando cuentas de mentira.
 */
class Referido
{
    public const DIAS_PREMIO = 30;

    /** El código del negocio; lo crea la primera vez ("DONAMARIA" + 3 cifras). */
    public static function codigoDe(int $negocioId, string $nombre): string
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT codigo_referido FROM negocios WHERE id = :id');
        $stmt->execute(['id' => $negocioId]);
        $codigo = $stmt->fetchColumn();
        if (is_string($codigo) && $codigo !== '') {
            return $codigo;
        }
        $base = substr(strtoupper((string) preg_replace('/[^A-Za-z]/', '', (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre))), 0, 10);
        $base = $base !== '' ? $base : 'VECI';
        $existe = $pdo->prepare('SELECT 1 FROM negocios WHERE codigo_referido = :c');
        do {
            $codigo = $base . random_int(100, 999);
            $existe->execute(['c' => $codigo]);
        } while ($existe->fetchColumn() !== false);
        $pdo->prepare('UPDATE negocios SET codigo_referido = :c WHERE id = :id AND codigo_referido IS NULL')
            ->execute(['c' => $codigo, 'id' => $negocioId]);

        return $codigo;
    }

    public static function negocioPorCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '');
        if ($codigo === '') {
            return null;
        }
        $stmt = Database::conexion()->prepare('SELECT id, nombre FROM negocios WHERE codigo_referido = :c AND suspendido = 0');
        $stmt->execute(['c' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    public static function registrar(int $referidorId, int $referidoId): void
    {
        if ($referidorId === $referidoId) {
            return;
        }
        Database::conexion()->prepare('INSERT IGNORE INTO referidos (referidor_id, referido_id, dias_premio) VALUES (:a, :b, :d)')
            ->execute(['a' => $referidorId, 'b' => $referidoId, 'd' => self::DIAS_PREMIO]);
    }

    /**
     * Si este negocio llegó invitado y es su primer plan pagado, le regala
     * los días al que lo invitó: extiende su plan pago vigente o, si está en
     * Gratis, le da Barrio esos días. Una sola vez por invitado.
     * El premio nunca vale más que lo que pagó el invitado: con un Barrio
     * mensual ($29.900) no se compran 30 días de Pro ($69.900), así abrir
     * cuentas de mentira y pagarles el plan más barato no deja ganancia.
     * Devuelve el id del negocio premiado (o null).
     */
    public static function premiarPorPago(int $referidoId, int $montoPagado = 0): ?int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM referidos WHERE referido_id = :r AND premiado_en IS NULL FOR UPDATE');
            $stmt->execute(['r' => $referidoId]);
            $referido = $stmt->fetch();
            if ($referido === false) {
                $pdo->rollBack();
                return null;
            }
            $stmt = $pdo->prepare(
                'SELECT n.plan_id, n.plan_vence_en, p.precio_mensual,
                        (SELECT precio_mensual FROM planes WHERE id = 2) AS precio_barrio
                 FROM negocios n JOIN planes p ON p.id = n.plan_id WHERE n.id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $referido['referidor_id']]);
            $referidor = $stmt->fetch();
            if ($referidor === false) {
                $pdo->rollBack();
                return null;
            }
            $dias = (int) $referido['dias_premio'];
            $hoy = date('Y-m-d');
            $extiende = (int) $referidor['plan_id'] > 1 && !empty($referidor['plan_vence_en']) && $referidor['plan_vence_en'] >= $hoy;
            // Los días que alcanza lo pagado, al precio del plan que se regala.
            $precioDia = (int) ($extiende ? $referidor['precio_mensual'] : $referidor['precio_barrio']);
            if ($montoPagado > 0 && $precioDia > 0) {
                $dias = min($dias, max(1, intdiv($montoPagado * 30, $precioDia)));
            }
            if ($extiende) {
                $pdo->prepare('UPDATE negocios SET plan_vence_en = DATE_ADD(plan_vence_en, INTERVAL :d DAY) WHERE id = :id')
                    ->execute(['d' => $dias, 'id' => $referido['referidor_id']]);
            } else {
                $pdo->prepare("UPDATE negocios SET plan_id = 2, plan_estado = 'activo', plan_ciclo = 'mensual', plan_vence_en = DATE_ADD(CURDATE(), INTERVAL :d DAY) WHERE id = :id")
                    ->execute(['d' => $dias, 'id' => $referido['referidor_id']]);
            }
            $pdo->prepare('UPDATE referidos SET premiado_en = NOW(), dias_premio = :d WHERE id = :id')->execute(['d' => $dias, 'id' => $referido['id']]);
            $pdo->commit();

            return (int) $referido['referidor_id'];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> a quién invitó este negocio y en qué va cada uno */
    public static function invitados(int $referidorId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT r.*, n.nombre, n.tipo_negocio, n.creado_en AS registrado_en
             FROM referidos r JOIN negocios n ON n.id = r.referido_id
             WHERE r.referidor_id = :id ORDER BY r.creado_en DESC'
        );
        $stmt->execute(['id' => $referidorId]);

        return $stmt->fetchAll();
    }
}
