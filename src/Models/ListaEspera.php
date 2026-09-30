<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Cuando un día de reservas ya no tiene horarios libres, el cliente se
 * anota aquí en vez de irse sin más. El dueño ve quién quiere ese día y le
 * escribe a mano si se libera un cupo. No reserva nada por sí sola: es una
 * lista de interesados, no una cola automática.
 */
class ListaEspera
{
    public static function crear(
        int $sedeId,
        int $clienteId,
        ?int $servicioId,
        string $nombreServicio,
        string $fecha
    ): int {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO lista_espera (sede_id, cliente_id, servicio_id, nombre_servicio, fecha)
             VALUES (:sede_id, :cliente_id, :servicio_id, :nombre_servicio, :fecha)'
        );
        $stmt->execute([
            'sede_id'         => $sedeId,
            'cliente_id'      => $clienteId,
            'servicio_id'     => $servicioId,
            'nombre_servicio' => $nombreServicio,
            'fecha'           => $fecha,
        ]);
        return (int) Database::conexion()->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId, int $limite = 50): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT le.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono
             FROM lista_espera le
             JOIN clientes c ON c.id = le.cliente_id
             WHERE le.sede_id = :sede_id AND le.estado = "pendiente" AND le.fecha >= CURDATE()
             ORDER BY le.fecha ASC, le.creado_en ASC
             LIMIT :limite'
        );
        $stmt->bindValue('sede_id', $sedeId, \PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function contarPendientesPorSede(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM lista_espera
             WHERE sede_id = :sede_id AND estado = "pendiente" AND fecha >= CURDATE()'
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return (int) $stmt->fetch()['total'];
    }

    public static function marcarContactado(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE lista_espera SET estado = "contactado" WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }
}
