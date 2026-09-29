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
    public static function crear(int $negocioId, string $nombre): int
    {
        $pdo = Database::conexion();
        $orden = self::contarPorNegocio($negocioId);

        $stmt = $pdo->prepare(
            'INSERT INTO empleados (negocio_id, nombre, orden) VALUES (:negocio_id, :nombre, :orden)'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'nombre' => $nombre, 'orden' => $orden]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId, bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM empleados WHERE negocio_id = :negocio_id';
        if ($soloActivos) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' ORDER BY orden ASC, id ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['negocio_id' => $negocioId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM empleados WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);

        return $stmt->fetch() ?: null;
    }

    public static function eliminar(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM empleados WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }

    public static function contarPorNegocio(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM empleados WHERE negocio_id = :negocio_id'
        );
        $stmt->execute(['negocio_id' => $negocioId]);

        return (int) $stmt->fetch()['total'];
    }
}
