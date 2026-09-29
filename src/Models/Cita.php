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
        ?string $notas = null,
        ?int $empleadoId = null
    ): int {
        $pdo = Database::conexion();
        $token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare(
            'INSERT INTO citas (negocio_id, cliente_id, servicio_id, empleado_id, nombre_servicio, precio, fecha_hora, duracion_min, estado, notas, token_gestion)
             VALUES (:negocio_id, :cliente_id, :servicio_id, :empleado_id, :nombre_servicio, :precio, :fecha_hora, :duracion_min, :pendiente, :notas, :token)'
        );
        $stmt->execute([
            'negocio_id'      => $negocioId,
            'cliente_id'      => $clienteId,
            'servicio_id'     => $servicioId,
            'empleado_id'     => $empleadoId,
            'nombre_servicio' => $nombreServicio,
            'precio'          => $precio,
            'fecha_hora'      => $fechaHora,
            'duracion_min'    => $duracionMin,
            'pendiente'       => 'pendiente',
            'notas'           => $notas,
            'token'           => $token,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Para la vista pública del cliente (sin sesión), identificada por el token que se le entrega al reservar. */
    public static function buscarPorToken(string $token): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono, n.slug AS negocio_slug, n.nombre AS negocio_nombre
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             JOIN negocios n ON n.id = c.negocio_id
             WHERE c.token_gestion = :token'
        );
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    public static function reprogramar(int $id, int $negocioId, string $fechaHora): void
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE citas SET fecha_hora = :fecha_hora, estado = 'pendiente' WHERE id = :id AND negocio_id = :negocio_id"
        );
        $stmt->execute(['fecha_hora' => $fechaHora, 'id' => $id, 'negocio_id' => $negocioId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId, int $limite = 50): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono, e.nombre AS empleado_nombre
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN empleados e ON e.id = c.empleado_id
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
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono, e.nombre AS empleado_nombre
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN empleados e ON e.id = c.empleado_id
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

    /**
     * Citas que caen dentro de las próximas 24-30 horas y todavía no tienen
     * recordatorio enviado. Ventana de 6 horas (no un corte exacto a las 24h)
     * para que un cron que corre cada tanto no se salte ninguna.
     */
    public static function pendientesDeRecordatorio(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.negocio_id = :negocio_id
               AND c.estado != 'cancelada'
               AND c.recordatorio_enviado = 0
               AND c.fecha_hora BETWEEN DATE_ADD(NOW(), INTERVAL 24 HOUR) AND DATE_ADD(NOW(), INTERVAL 30 HOUR)
             ORDER BY c.fecha_hora ASC"
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetchAll();
    }

    /** Igual que pendientesDeRecordatorio() pero para todos los negocios a la vez (usado por el cron). */
    public static function pendientesDeRecordatorioGlobal(): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.estado != 'cancelada'
               AND c.recordatorio_enviado = 0
               AND c.fecha_hora BETWEEN DATE_ADD(NOW(), INTERVAL 24 HOUR) AND DATE_ADD(NOW(), INTERVAL 30 HOUR)
             ORDER BY c.negocio_id ASC, c.fecha_hora ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function marcarRecordatorioEnviado(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE citas SET recordatorio_enviado = 1 WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
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

    /** Citas creadas después de cierto ID, para el polling de notificaciones del panel. */
    public static function nuevasDesde(int $negocioId, int $desdeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.id, c.fecha_hora, c.nombre_servicio, c.creado_en, cl.nombre AS cliente_nombre
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.negocio_id = :negocio_id AND c.id > :desde_id
             ORDER BY c.id ASC
             LIMIT 20'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'desde_id' => $desdeId]);
        return $stmt->fetchAll();
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
    /**
     * $empleadoId, cuando se pasa, limita la ocupación a las citas de ese
     * empleado (así dos empleados del mismo negocio pueden tener citas en
     * el mismo horario sin chocar). Sin empleado, se mira el negocio entero,
     * como antes de que existieran los empleados.
     */
    public static function ocupadosEnFecha(int $negocioId, string $fecha, ?int $excluirCitaId = null, ?int $empleadoId = null): array
    {
        $sql = 'SELECT fecha_hora, duracion_min FROM citas
                WHERE negocio_id = :negocio_id AND DATE(fecha_hora) = :fecha AND estado != "cancelada"';
        $params = ['negocio_id' => $negocioId, 'fecha' => $fecha];

        if ($excluirCitaId !== null) {
            $sql .= ' AND id != :excluir_id';
            $params['excluir_id'] = $excluirCitaId;
        }

        if ($empleadoId !== null) {
            $sql .= ' AND empleado_id = :empleado_id';
            $params['empleado_id'] = $empleadoId;
        }

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($params);

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
