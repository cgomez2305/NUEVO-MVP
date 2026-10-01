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
}
