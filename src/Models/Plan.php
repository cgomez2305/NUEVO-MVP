<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Los 3 planes de Veci (gratis/barrio/pro). Son filas fijas de catálogo,
 * no algo que un negocio cree — este modelo solo lee.
 */
class Plan
{
    /** @return array<int, array<string, mixed>> */
    public static function listarTodos(): array
    {
        $stmt = Database::conexion()->query('SELECT * FROM planes ORDER BY precio_mensual ASC');
        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM planes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorNombre(string $nombre): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM planes WHERE nombre = :nombre');
        $stmt->execute(['nombre' => $nombre]);
        return $stmt->fetch() ?: null;
    }

    /** El id del plan Gratis: a este se degrada un negocio cuyo plan pago vence (ver bin/revisar_planes.php). */
    public static function idGratis(): int
    {
        $plan = self::buscarPorNombre('gratis');
        return $plan !== null ? (int) $plan['id'] : 1;
    }

    /**
     * Cuántas sedes por encima del cupo del plan tendría que pagar un
     * negocio con $sedes sedes. Solo los planes con precio_sede_extra (Pro)
     * venden sedes extra; en los demás, 0.
     */
    public static function sedesExtraNecesarias(array $plan, int $sedes): int
    {
        if (empty($plan['precio_sede_extra'])) {
            return 0;
        }

        return max(0, $sedes - (int) ($plan['sedes_incluidas'] ?? 1));
    }

    /** Lo que cuesta el plan en ese ciclo con esas sedes extra (anual = 2 meses gratis, también en la sede extra: se paga 10 veces). */
    public static function precio(array $plan, string $ciclo, int $sedesExtra = 0): int
    {
        $anual = $ciclo === 'anual';
        $base = $anual ? (int) $plan['precio_anual'] : (int) $plan['precio_mensual'];

        return $base + $sedesExtra * (int) ($plan['precio_sede_extra'] ?? 0) * ($anual ? 10 : 1);
    }

    /**
     * Una sede extra pedida a mitad de período se cobra solo por los días
     * que le quedan al plan (precio mensual × días / 30, redondeado a la
     * centena hacia arriba): así todas las sedes vencen juntas y la
     * renovación siguiente las cobra completas. Sin fecha de vencimiento
     * (plan sin ciclo), un mes completo. En un plan anual se prorratea el
     * precio anual de la sede (10 meses: 2 gratis, como el plan) entre 365
     * días, así nunca cuesta más que pagarla en la renovación.
     *
     * @return array{dias: int, monto: int}
     */
    public static function prorrateoSedeExtra(int $precioMensual, ?string $venceEn, bool $anual = false): array
    {
        $dias = 30;
        if ($venceEn !== null && $venceEn !== '') {
            $dias = max(1, (int) (new \DateTimeImmutable('today'))->diff(new \DateTimeImmutable($venceEn))->format('%r%a'));
        }
        $monto = $anual
            ? min($precioMensual * 10, $precioMensual * 10 * $dias / 365)
            : $precioMensual * $dias / 30;

        return ['dias' => $dias, 'monto' => (int) (ceil($monto / 100) * 100)];
    }
}
