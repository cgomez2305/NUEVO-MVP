<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Cita
{
    public const ESTADOS = ['pendiente', 'confirmada', 'completada', 'cancelada'];

    public static function crear(
        int $negocioId,
        int $clienteId,
        ?int $servicioId,
        string $nombreServicio,
        int $precio,
        string $fechaHora,
        int $duracionMin,
        ?string $notas = null
    ): int {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare(
            'INSERT INTO citas (negocio_id, cliente_id, servicio_id, nombre_servicio, precio, fecha_hora, duracion_min, estado, notas)
             VALUES (:negocio_id, :cliente_id, :servicio_id, :nombre_servicio, :precio, :fecha_hora, :duracion_min, :pendiente, :notas)'
        );
        $stmt->execute([
            'negocio_id'      => $negocioId,
            'cliente_id'      => $clienteId,
            'servicio_id'     => $servicioId,
            'nombre_servicio' => $nombreServicio,
            'precio'          => $precio,
            'fecha_hora'      => $fechaHora,
            'duracion_min'    => $duracionMin,
            'pendiente'       => 'pendiente',
            'notas'           => $notas,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId, int $limite = 50): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.negocio_id = :negocio_id
             ORDER BY c.fecha_hora DESC
             LIMIT :limite'
        );
        $stmt->bindValue('negocio_id', $negocioId, \PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Próximas citas (hoy en adelante), para la agenda del panel. */
    public static function listarProximas(int $negocioId, int $limite = 100): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.negocio_id = :negocio_id
               AND c.fecha_hora >= DATE(NOW())
               AND c.estado != "cancelada"
             ORDER BY c.fecha_hora ASC
             LIMIT :limite'
        );
        $stmt->bindValue('negocio_id', $negocioId, \PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.id = :id AND c.negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
        return $stmt->fetch() ?: null;
    }

    public static function actualizarEstado(int $id, int $negocioId, string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return;
        }
        $stmt = Database::conexion()->prepare(
            'UPDATE citas SET estado = :estado WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['estado' => $estado, 'id' => $id, 'negocio_id' => $negocioId]);
    }

    public static function contarHoy(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM citas
             WHERE negocio_id = :negocio_id AND DATE(fecha_hora) = CURDATE() AND estado != "cancelada"'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Bloques ya ocupados ese día (para no dejar reservar encima de otra cita).
     * @return array<int, array{inicio:string, duracion_min:int}>
     */
    public static function ocupadosEnFecha(int $negocioId, string $fecha): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT fecha_hora, duracion_min FROM citas
             WHERE negocio_id = :negocio_id AND DATE(fecha_hora) = :fecha AND estado != "cancelada"'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'fecha' => $fecha]);

        return array_map(
            fn ($fila) => ['inicio' => $fila['fecha_hora'], 'duracion_min' => (int) $fila['duracion_min']],
            $stmt->fetchAll()
        );
    }

    /**
     * Calcula los horarios disponibles de un día para un servicio de cierta
     * duración, cruzando el horario de atención del negocio con las citas
     * que ya existen ese día. Devuelve horas "HH:MM" que el cliente puede
     * elegir en la tienda pública.
     *
     * @param array<string, array{0:string,1:string}> $horarioAtencion día ISO (1-7) => [inicio, fin]
     * @param array<int, array{inicio:string, duracion_min:int}> $ocupados
     * @return array<int, string>
     */
    public static function calcularDisponibilidad(
        array $horarioAtencion,
        int $intervaloMin,
        string $fecha,
        int $duracionServicioMin,
        array $ocupados
    ): array {
        $diaSemana = (string) (int) date('N', strtotime($fecha));
        if (!isset($horarioAtencion[$diaSemana]) || !is_array($horarioAtencion[$diaSemana])) {
            return [];
        }

        [$horaInicio, $horaFin] = $horarioAtencion[$diaSemana];
        $inicioMin = self::horaAMinutos($horaInicio);
        $finMin = self::horaAMinutos($horaFin);
        if ($inicioMin === null || $finMin === null || $intervaloMin < 5) {
            return [];
        }

        $ocupadosMin = array_map(function ($bloque) {
            $inicio = strtotime($bloque['inicio']);
            $minutosDesdeMedianoche = (int) date('G', $inicio) * 60 + (int) date('i', $inicio);
            return ['inicio' => $minutosDesdeMedianoche, 'fin' => $minutosDesdeMedianoche + $bloque['duracion_min']];
        }, $ocupados);

        $ahora = null;
        if ($fecha === date('Y-m-d')) {
            $ahora = (int) date('G') * 60 + (int) date('i');
        }

        $slots = [];
        for ($minuto = $inicioMin; $minuto + $duracionServicioMin <= $finMin; $minuto += $intervaloMin) {
            if ($ahora !== null && $minuto <= $ahora) {
                continue;
            }

            $seSolapa = false;
            foreach ($ocupadosMin as $bloque) {
                if ($minuto < $bloque['fin'] && ($minuto + $duracionServicioMin) > $bloque['inicio']) {
                    $seSolapa = true;
                    break;
                }
            }

            if (!$seSolapa) {
                $slots[] = sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60);
            }
        }

        return $slots;
    }

    private static function horaAMinutos(string $hora): ?int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $m)) {
            return null;
        }
        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
