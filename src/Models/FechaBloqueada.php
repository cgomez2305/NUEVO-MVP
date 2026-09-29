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
    public static function listarPorNegocio(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM fechas_bloqueadas WHERE negocio_id = :negocio_id AND fecha >= CURDATE() ORDER BY fecha ASC'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetchAll();
    }

    public static function crear(int $negocioId, string $fecha, ?string $motivo): void
    {
        $stmt = Database::conexion()->prepare(
            'INSERT IGNORE INTO fechas_bloqueadas (negocio_id, fecha, motivo) VALUES (:negocio_id, :fecha, :motivo)'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'fecha' => $fecha, 'motivo' => $motivo]);
    }

    public static function eliminar(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM fechas_bloqueadas WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }

    public static function estaBloqueada(int $negocioId, string $fecha): bool
    {
        $stmt = Database::conexion()->prepare(
            'SELECT id FROM fechas_bloqueadas WHERE negocio_id = :negocio_id AND fecha = :fecha'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'fecha' => $fecha]);
        return $stmt->fetch() !== false;
    }
}
