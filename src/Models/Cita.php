<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Cita
{
    public const ESTADOS = ['pendiente', 'confirmada', 'completada', 'cancelada'];

    public static function crear(
        int $sedeId,
        int $clienteId,
        ?int $servicioId,
        string $nombreServicio,
        int $precio,
        string $fechaHora,
        int $duracionMin,
        ?string $notas = null,
        ?int $empleadoId = null,
        int $anticipoMonto = 0
    ): int {
        $pdo = Database::conexion();
        $token = bin2hex(random_bytes(16));
        $anticipoEstado = $anticipoMonto > 0 ? 'pendiente' : 'no_requerido';

        $stmt = $pdo->prepare(
            'INSERT INTO citas (sede_id, cliente_id, servicio_id, empleado_id, nombre_servicio, precio, fecha_hora, duracion_min, estado, notas, token_gestion, anticipo_monto, anticipo_estado)
             VALUES (:sede_id, :cliente_id, :servicio_id, :empleado_id, :nombre_servicio, :precio, :fecha_hora, :duracion_min, :pendiente, :notas, :token, :anticipo_monto, :anticipo_estado)'
        );
        $stmt->execute([
            'sede_id'      => $sedeId,
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
            'anticipo_monto'  => $anticipoMonto,
            'anticipo_estado' => $anticipoEstado,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Para la vista pública del cliente (sin sesión), identificada por el token que se le entrega al reservar. */
    public static function buscarPorToken(string $token): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono,
                    s.slug AS sede_slug, s.nombre AS sede_nombre, n.nombre AS negocio_nombre
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             JOIN sedes s ON s.id = c.sede_id
             JOIN negocios n ON n.id = s.negocio_id
             WHERE c.token_gestion = :token'
        );
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    public static function reprogramar(int $id, int $sedeId, string $fechaHora): void
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE citas SET fecha_hora = :fecha_hora, estado = 'pendiente' WHERE id = :id AND sede_id = :sede_id"
        );
        $stmt->execute(['fecha_hora' => $fechaHora, 'id' => $id, 'sede_id' => $sedeId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId, int $limite = 50): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono, e.nombre AS empleado_nombre
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN empleados e ON e.id = c.empleado_id
             WHERE c.sede_id = :sede_id
             ORDER BY c.fecha_hora DESC
             LIMIT :limite'
        );
        $stmt->bindValue('sede_id', $sedeId, \PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Próximas citas (hoy en adelante), para la agenda del panel. */
    public static function listarProximas(int $sedeId, int $limite = 100): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono, e.nombre AS empleado_nombre
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN empleados e ON e.id = c.empleado_id
             WHERE c.sede_id = :sede_id
               AND c.fecha_hora >= DATE(NOW())
               AND c.estado != "cancelada"
             ORDER BY c.fecha_hora ASC
             LIMIT :limite'
        );
        $stmt->bindValue('sede_id', $sedeId, \PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.id = :id AND c.sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Citas que caen dentro de las próximas 24-30 horas y todavía no tienen
     * recordatorio enviado. Ventana de 6 horas (no un corte exacto a las 24h)
     * para que un cron que corre cada tanto no se salte ninguna.
     */
    public static function pendientesDeRecordatorio(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.sede_id = :sede_id
               AND c.estado != 'cancelada'
               AND c.recordatorio_enviado = 0
               AND c.fecha_hora BETWEEN DATE_ADD(NOW(), INTERVAL 24 HOUR) AND DATE_ADD(NOW(), INTERVAL 30 HOUR)
             ORDER BY c.fecha_hora ASC"
        );
        $stmt->execute(['sede_id' => $sedeId]);
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
             ORDER BY c.sede_id ASC, c.fecha_hora ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function marcarRecordatorioEnviado(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE citas SET recordatorio_enviado = 1 WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    /**
     * El dueño confirma a mano que recibió el anticipo (p. ej. vio el
     * comprobante por WhatsApp). El webhook de Bre-B hace lo mismo
     * automáticamente cuando hay un pago real conectado.
     */
    public static function marcarAnticipoPagado(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE citas SET anticipo_estado = 'pagado' WHERE id = :id AND sede_id = :sede_id AND anticipo_estado = 'pendiente'"
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    public static function actualizarEstado(int $id, int $sedeId, string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return;
        }
        $stmt = Database::conexion()->prepare(
            'UPDATE citas SET estado = :estado WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['estado' => $estado, 'id' => $id, 'sede_id' => $sedeId]);
    }

    /**
     * A qué estado pasa una cita al pulsar el botón de acción principal, y
     * cómo se llama ese botón. Null cuando ya está en un estado final
     * (completada/cancelada), donde solo queda el cambio manual.
     *
     * @return array{estado:string, texto:string}|null
     */
    public static function siguientePaso(array $cita): ?array
    {
        return match ($cita['estado']) {
            'pendiente' => ['estado' => 'confirmada', 'texto' => 'Confirmar cita'],
            'confirmada' => ['estado' => 'completada', 'texto' => 'Marcar completada'],
            default => null,
        };
    }

    /** Citas creadas después de cierto ID, para el polling de notificaciones del panel. */
    public static function nuevasDesde(int $sedeId, int $desdeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.id, c.fecha_hora, c.nombre_servicio, c.creado_en, cl.nombre AS cliente_nombre
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.sede_id = :sede_id AND c.id > :desde_id
             ORDER BY c.id ASC
             LIMIT 20'
        );
        $stmt->execute(['sede_id' => $sedeId, 'desde_id' => $desdeId]);
        return $stmt->fetchAll();
    }

    /**
     * Citas creadas este mes calendario, sumadas entre todas las sedes del
     * negocio (el límite del plan es por negocio, no por sede — ver
     * planes.limite_pedidos_mes). Cuenta todos los estados, canceladas
     * incluidas: son citas que de todas formas consumieron el flujo.
     */
    public static function contarEsteMesPorNegocio(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM citas c
             JOIN sedes s ON s.id = c.sede_id
             WHERE s.negocio_id = :negocio_id AND c.creado_en >= :desde'
        );
        $stmt->execute([
            'negocio_id' => $negocioId,
            'desde'      => (new \DateTimeImmutable('first day of this month midnight'))->format('Y-m-d H:i:s'),
        ]);
        return (int) $stmt->fetch()['total'];
    }

    public static function contarHoy(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM citas
             WHERE sede_id = :sede_id AND DATE(fecha_hora) = CURDATE() AND estado != "cancelada"'
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return (int) $stmt->fetch()['total'];
    }

    /** Suma de las citas de hoy, sin contar las canceladas (no son venta real). */
    public static function ventasHoy(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COALESCE(SUM(precio), 0) AS total FROM citas
             WHERE sede_id = :sede_id AND DATE(fecha_hora) = CURDATE() AND estado != "cancelada"'
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Resumen de los últimos 7 días para el dashboard: citas, lo vendido
     * (sin canceladas) y cuántos de esos clientes ya habían reservado antes.
     *
     * @return array{pedidos: int, ventas: int, recurrentes: int}
     */
    public static function resumenSemana(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT
                COUNT(*) AS citas,
                COALESCE(SUM(c.precio), 0) AS ventas,
                COUNT(DISTINCT CASE WHEN historico.total_citas >= 2 THEN c.cliente_id END) AS recurrentes
             FROM citas c
             JOIN (SELECT cliente_id, COUNT(*) AS total_citas FROM citas WHERE sede_id = :sede_id_h GROUP BY cliente_id) historico
               ON historico.cliente_id = c.cliente_id
             WHERE c.sede_id = :sede_id AND c.creado_en >= :desde AND c.estado != "cancelada"'
        );
        $stmt->execute([
            'sede_id_h' => $sedeId,
            'sede_id'   => $sedeId,
            'desde'     => (new \DateTimeImmutable('-6 days midnight'))->format('Y-m-d H:i:s'),
        ]);
        $fila = $stmt->fetch();

        return [
            'pedidos'     => (int) $fila['citas'],
            'ventas'      => (int) $fila['ventas'],
            'recurrentes' => (int) $fila['recurrentes'],
        ];
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
    public static function ocupadosEnFecha(int $sedeId, string $fecha, ?int $excluirCitaId = null, ?int $empleadoId = null): array
    {
        $sql = 'SELECT fecha_hora, duracion_min FROM citas
                WHERE sede_id = :sede_id AND DATE(fecha_hora) = :fecha AND estado != "cancelada"';
        $params = ['sede_id' => $sedeId, 'fecha' => $fecha];

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
