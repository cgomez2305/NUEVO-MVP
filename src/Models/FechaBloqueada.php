<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Días en que un negocio de reservas no atiende aunque su horario semanal
 * lo permita (vacaciones, festivos, cierres puntuales). La disponibilidad
 * de citas de la tienda pública descarta estos días.
 */
class FechaBloqueada
{
    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM fechas_bloqueadas WHERE sede_id = :sede_id AND fecha >= CURDATE() ORDER BY fecha ASC'
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return $stmt->fetchAll();
    }

    public static function crear(int $sedeId, string $fecha, ?string $motivo): void
    {
        $stmt = Database::conexion()->prepare(
            'INSERT IGNORE INTO fechas_bloqueadas (sede_id, fecha, motivo) VALUES (:sede_id, :fecha, :motivo)'
        );
        $stmt->execute(['sede_id' => $sedeId, 'fecha' => $fecha, 'motivo' => $motivo]);
    }

    public static function eliminar(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM fechas_bloqueadas WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    public static function estaBloqueada(int $sedeId, string $fecha): bool
    {
        $stmt = Database::conexion()->prepare(
            'SELECT id FROM fechas_bloqueadas WHERE sede_id = :sede_id AND fecha = :fecha'
        );
        $stmt->execute(['sede_id' => $sedeId, 'fecha' => $fecha]);
        return $stmt->fetch() !== false;
    }
}
