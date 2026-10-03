<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Fila virtual para clientes sin cita (ver migrations/..._14_profesionales.sql):
 * el cliente se anota desde la tienda (solo con la fila abierta), ve cuántos
 * tiene adelante desde su enlace /fila/{token} y el negocio lo llama por
 * WhatsApp cuando se acerca su turno. Al atenderlo se crea una cita
 * completada: así cuenta en la caja, en las comisiones y en el copiloto
 * como cualquier otra.
 *
 * Una fila es de un solo día: lo que quedó de ayer no cuenta.
 */
class TurnoFila
{
    private const SELECT = 'SELECT t.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono,
            sv.nombre AS servicio_nombre, sv.duracion_min AS servicio_duracion, e.nombre AS empleado_nombre
        FROM turnos_fila t
        JOIN clientes cl ON cl.id = t.cliente_id
        LEFT JOIN servicios sv ON sv.id = t.servicio_id
        LEFT JOIN empleados e ON e.id = t.empleado_id';

    public static function abrirOCerrar(int $sedeId, bool $abrir): void
    {
        Database::conexion()->prepare('UPDATE sedes SET fila_abierta = :a WHERE id = :id')
            ->execute(['a' => $abrir ? 1 : 0, 'id' => $sedeId]);
    }

    /**
     * Anota al cliente. Si ya tiene un turno vivo hoy en esta sede, no crea
     * otro y devuelve ese (nuevo = false): el controlador decide si puede
     * mostrarlo, porque cualquiera puede escribir un WhatsApp ajeno.
     *
     * @return array{token: string, nuevo: bool}
     */
    public static function anotar(int $sedeId, int $clienteId, ?int $servicioId, ?int $empleadoId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT token FROM turnos_fila WHERE sede_id = :s AND cliente_id = :c AND estado IN ('esperando', 'llamado') AND DATE(creado_en) = CURDATE()"
        );
        $stmt->execute(['s' => $sedeId, 'c' => $clienteId]);
        $existente = $stmt->fetchColumn();
        if (is_string($existente)) {
            return ['token' => $existente, 'nuevo' => false];
        }
        $token = bin2hex(random_bytes(16));
        Database::conexion()->prepare(
            'INSERT INTO turnos_fila (sede_id, cliente_id, servicio_id, empleado_id, token) VALUES (:s, :c, :sv, :e, :t)'
        )->execute(['s' => $sedeId, 'c' => $clienteId, 'sv' => $servicioId, 'e' => $empleadoId, 't' => $token]);

        return ['token' => $token, 'nuevo' => true];
    }

    public static function buscarPorToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = Database::conexion()->prepare(self::SELECT . ' WHERE t.token = :t');
        $stmt->execute(['t' => $token]);

        return $stmt->fetch() ?: null;
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(self::SELECT . ' WHERE t.id = :id AND t.sede_id = :s');
        $stmt->execute(['id' => $id, 's' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> la fila de hoy: llamados primero, luego en orden de llegada */
    public static function deHoy(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            self::SELECT . " WHERE t.sede_id = :s AND t.estado IN ('esperando', 'llamado') AND DATE(t.creado_en) = CURDATE()
             ORDER BY t.estado = 'llamado' DESC, t.id ASC"
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    /**
     * Cuántos tiene adelante (esperando o ya llamados, de hoy) y, si TODOS
     * los de adelante dijeron qué servicio quieren, cuánto podría esperar:
     * la suma de sus duraciones repartida entre los profesionales activos.
     * Si alguno no dijo, no se inventa un tiempo.
     *
     * @return array{adelante: int, minutos: ?int}
     */
    public static function posicion(array $turno): array
    {
        $stmt = Database::conexion()->prepare(
            // Con persona elegida cuenta su propia duración (ver Empleado::condiciones).
            "SELECT t.servicio_id, COALESCE(es.duracion_min, sv.duracion_min) AS duracion_min
             FROM turnos_fila t
             LEFT JOIN servicios sv ON sv.id = t.servicio_id
             LEFT JOIN empleado_servicios es ON es.empleado_id = t.empleado_id AND es.servicio_id = t.servicio_id
             WHERE t.sede_id = :s AND t.id < :id AND t.estado IN ('esperando', 'llamado') AND DATE(t.creado_en) = DATE(:f)"
        );
        $stmt->execute(['s' => (int) $turno['sede_id'], 'id' => (int) $turno['id'], 'f' => $turno['creado_en']]);
        $adelante = $stmt->fetchAll();
        $minutos = null;
        if ($adelante !== [] && !in_array(null, array_column($adelante, 'duracion_min'), true)) {
            $profesionales = max(1, count(Empleado::listarPorSede((int) $turno['sede_id'], true)));
            $minutos = (int) ceil(array_sum(array_map('intval', array_column($adelante, 'duracion_min'))) / $profesionales);
        }

        return ['adelante' => count($adelante), 'minutos' => $adelante === [] ? 0 : $minutos];
    }

    public static function llamar(int $id, int $sedeId): bool
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE turnos_fila SET estado = 'llamado', llamado_en = NOW() WHERE id = :id AND sede_id = :s AND estado = 'esperando'"
        );
        $stmt->execute(['id' => $id, 's' => $sedeId]);

        return $stmt->rowCount() === 1;
    }

    /** El cliente se va (él mismo desde su enlace, o el negocio lo marca). */
    public static function seFue(int $id, int $sedeId): void
    {
        Database::conexion()->prepare(
            "UPDATE turnos_fila SET estado = 'se_fue', cerrado_en = NOW() WHERE id = :id AND sede_id = :s AND estado IN ('esperando', 'llamado')"
        )->execute(['id' => $id, 's' => $sedeId]);
    }

    /**
     * Lo atendieron: se crea una cita completada (ahora, con el servicio y
     * el profesional que lo atendió y lo cobrado) y el turno queda cerrado.
     * Devuelve el id de la cita o null si el turno ya no estaba vivo.
     */
    public static function atender(array $turno, array $servicio, ?array $empleado, int $cobrado): ?int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            // Bloquea el turno: un doble toque en "Atendido" no crea dos citas.
            $stmt = $pdo->prepare("SELECT estado FROM turnos_fila WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => (int) $turno['id']]);
            if (!in_array($stmt->fetchColumn(), ['esperando', 'llamado'], true)) {
                $pdo->rollBack();
                return null;
            }
            $citaId = self::crearCitaAtendida($turno, $servicio, $empleado, $cobrado);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $citaId;
    }

    private static function crearCitaAtendida(array $turno, array $servicio, ?array $empleado, int $cobrado): int
    {
        $condiciones = Empleado::condiciones($empleado, $servicio);
        $citaId = Cita::crear(
            (int) $turno['sede_id'],
            (int) $turno['cliente_id'],
            (int) $servicio['id'],
            (string) $servicio['nombre'],
            $condiciones['precio'],
            date('Y-m-d H:i:s'),
            $condiciones['duracion_min'],
            'Atendido desde la fila',
            $empleado !== null ? (int) $empleado['id'] : null
        );
        $sedeId = (int) $turno['sede_id'];
        Cita::actualizarEstado($citaId, $sedeId, 'completada');
        Database::conexion()->prepare('UPDATE citas SET precio_final = :p WHERE id = :id AND sede_id = :s')
            ->execute(['p' => max(0, $cobrado), 'id' => $citaId, 's' => $sedeId]);
        Database::conexion()->prepare(
            "UPDATE turnos_fila SET estado = 'atendido', cerrado_en = NOW(), cita_id = :c, servicio_id = :sv, empleado_id = :e WHERE id = :id"
        )->execute(['c' => $citaId, 'sv' => (int) $servicio['id'], 'e' => $empleado !== null ? (int) $empleado['id'] : null, 'id' => (int) $turno['id']]);

        return $citaId;
    }
}
