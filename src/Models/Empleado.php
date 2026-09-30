<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Empleados/recursos de un negocio de reservas. Si un negocio no tiene
 * ninguno registrado, las citas se agendan contra el negocio completo
 * (comportamiento de siempre, un solo recurso implícito). En cuanto tiene
 * al menos uno activo, el cliente elige con quién agenda y la
 * disponibilidad se calcula por empleado, no por negocio.
 */
class Empleado
{
    public static function crear(int $sedeId, string $nombre): int
    {
        $pdo = Database::conexion();
        $orden = self::contarPorSede($sedeId);

        $stmt = $pdo->prepare(
            'INSERT INTO empleados (sede_id, nombre, orden) VALUES (:sede_id, :nombre, :orden)'
        );
        $stmt->execute(['sede_id' => $sedeId, 'nombre' => $nombre, 'orden' => $orden]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId, bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM empleados WHERE sede_id = :sede_id';
        if ($soloActivos) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' ORDER BY orden ASC, id ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['sede_id' => $sedeId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM empleados WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    public static function eliminar(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM empleados WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    public static function contarPorSede(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM empleados WHERE sede_id = :sede_id'
        );
        $stmt->execute(['sede_id' => $sedeId]);

        return (int) $stmt->fetch()['total'];
    }
}
