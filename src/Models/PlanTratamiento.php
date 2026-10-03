<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use App\Services\HorarioCobro;

/**
 * Plan de tratamiento (salud, ver database/migrations/2026-10-03_18_salud.sql).
 *
 * Un presupuesto por fases (valoración, brackets, 12 controles…) que el
 * paciente aprueba desde su enlace y paga con abonos, como se hace en los
 * consultorios colombianos. Las citas se vinculan a su fase: así se ve el
 * avance ("3 de 12 controles") y valen $0 por sí solas, porque la plata
 * entra por los abonos (si no, cada sesión se cobraría dos veces).
 *
 * No es una historia clínica: ni diagnósticos ni evoluciones; solo lo
 * comercial y de agenda del tratamiento.
 */
class PlanTratamiento
{
    public const MAX_FASES = 8;
    public const VALIDEZ = [15, 30, 60, 90];
    public const ETIQUETAS = [
        'propuesto' => 'Por aprobar', 'aprobado' => 'En tratamiento', 'rechazado' => 'No aprobado',
        'terminado' => 'Terminado', 'cancelado' => 'Cancelado',
    ];
    public const METODOS_ABONO = ['efectivo' => 'Efectivo', 'nequi' => 'Nequi', 'breb' => 'Bre-B', 'tarjeta' => 'Tarjeta'];

    public static function esSalud(array $sede): bool
    {
        return ($sede['tipo_negocio'] ?? '') === 'reservas' && ($sede['rubro'] ?? 'general') === 'salud';
    }

    // ---------- Crear y leer ----------

    /**
     * Las fases válidas de un formulario (filas vacías se ignoran) y el total.
     *
     * @return array{fases: array<int, array{nombre: string, sesiones: int, valor: int}>, total: int}
     */
    public static function fasesDesdeFormulario(array $filas): array
    {
        $fases = [];
        foreach (array_slice($filas, 0, self::MAX_FASES) as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            $valor = dinero_desde_texto((string) ($fila['valor'] ?? ''));
            if ($nombre === '' || $valor <= 0) {
                continue;
            }
            $fases[] = ['nombre' => mb_substr($nombre, 0, 120), 'sesiones' => max(1, min(99, (int) ($fila['sesiones'] ?? 1))), 'valor' => $valor];
        }

        return ['fases' => $fases, 'total' => array_sum(array_column($fases, 'valor'))];
    }

    /** @param array<int, array{nombre: string, sesiones: int, valor: int}> $fases */
    public static function crear(int $sedeId, int $clienteId, string $titulo, array $fases, int $validez, string $nota): int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO planes_tratamiento (sede_id, cliente_id, token, titulo, total, validez_dias, nota)
                 VALUES (:s, :c, :t, :ti, :total, :v, :n)'
            )->execute([
                's' => $sedeId, 'c' => $clienteId, 't' => bin2hex(random_bytes(16)), 'ti' => mb_substr($titulo, 0, 120),
                'total' => array_sum(array_column($fases, 'valor')),
                'v' => in_array($validez, self::VALIDEZ, true) ? $validez : 30,
                'n' => $nota !== '' ? mb_substr($nota, 0, 500) : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $insertar = $pdo->prepare('INSERT INTO plan_fases (plan_id, orden, nombre, sesiones, valor) VALUES (:p, :o, :n, :s, :v)');
            foreach (array_values($fases) as $i => $fase) {
                $insertar->execute(['p' => $id, 'o' => $i + 1, 'n' => $fase['nombre'], 's' => $fase['sesiones'], 'v' => $fase['valor']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $id;
    }

    private const SELECT = "SELECT p.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono,
            (SELECT COALESCE(SUM(a.monto), 0) FROM plan_abonos a WHERE a.plan_id = p.id AND a.anulado = 0) AS pagado
        FROM planes_tratamiento p JOIN clientes cl ON cl.id = p.cliente_id";

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(self::SELECT . ' WHERE p.id = :id AND p.sede_id = :s');
        $stmt->execute(['id' => $id, 's' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    public static function buscarPorToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = Database::conexion()->prepare(self::SELECT . ' WHERE p.token = :t');
        $stmt->execute(['t' => $token]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function listar(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            self::SELECT . " WHERE p.sede_id = :s
             ORDER BY FIELD(p.estado, 'aprobado', 'propuesto', 'terminado', 'rechazado', 'cancelado'), p.creado_en DESC LIMIT 200"
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> los planes vivos (por aprobar o en tratamiento) de un paciente */
    public static function vivosDeCliente(int $sedeId, int $clienteId): array
    {
        $stmt = Database::conexion()->prepare(
            self::SELECT . " WHERE p.sede_id = :s AND p.cliente_id = :c AND p.estado IN ('propuesto', 'aprobado') ORDER BY p.id DESC"
        );
        $stmt->execute(['s' => $sedeId, 'c' => $clienteId]);

        return $stmt->fetchAll();
    }

    /**
     * Fases con su avance: sesiones atendidas y agendadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fases(int $planId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM citas c WHERE c.plan_fase_id = f.id AND c.estado = 'completada') AS hechas,
                    (SELECT COUNT(*) FROM citas c WHERE c.plan_fase_id = f.id AND c.estado IN ('pendiente', 'confirmada', 'en_curso')) AS agendadas
             FROM plan_fases f WHERE f.plan_id = :p ORDER BY f.orden"
        );
        $stmt->execute(['p' => $planId]);

        return $stmt->fetchAll();
    }

    public static function saldo(array $plan): int
    {
        return max(0, (int) $plan['total'] - (int) $plan['pagado']);
    }

    public static function vencido(array $plan): bool
    {
        return $plan['estado'] === 'propuesto'
            && time() > (strtotime((string) $plan['creado_en']) ?: 0) + (int) $plan['validez_dias'] * 86400;
    }

    /** El paciente responde desde su enlace (solo si sigue por aprobar y no venció). */
    public static function responder(array $plan, bool $aprobar): bool
    {
        if ($plan['estado'] !== 'propuesto' || self::vencido($plan)) {
            return false;
        }
        $stmt = Database::conexion()->prepare(
            "UPDATE planes_tratamiento SET estado = :e, respondido_en = NOW() WHERE id = :id AND estado = 'propuesto'"
        );
        $stmt->execute(['e' => $aprobar ? 'aprobado' : 'rechazado', 'id' => (int) $plan['id']]);

        return $stmt->rowCount() === 1;
    }

    /** Terminar o cancelar (decide el dueño). Un plan terminado o cancelado ya no recibe citas ni abonos. */
    public static function cerrar(int $id, int $sedeId, string $estado): bool
    {
        if (!in_array($estado, ['terminado', 'cancelado'], true)) {
            return false;
        }
        $stmt = Database::conexion()->prepare(
            "UPDATE planes_tratamiento SET estado = :e WHERE id = :id AND sede_id = :s AND estado IN ('propuesto', 'aprobado')"
        );
        $stmt->execute(['e' => $estado, 'id' => $id, 's' => $sedeId]);

        return $stmt->rowCount() === 1;
    }

    // ---------- Abonos ----------

    /** @return array<int, array<string, mixed>> */
    public static function abonos(int $planId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT a.*, u.nombre AS usuario_nombre FROM plan_abonos a LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.plan_id = :p ORDER BY a.creado_en DESC, a.id DESC'
        );
        $stmt->execute(['p' => $planId]);

        return $stmt->fetchAll();
    }

    /**
     * Registra un abono. Devuelve null si quedó, o el porqué si no: solo en
     * planes aprobados y sin pasarse del saldo (con el plan bloqueado, para
     * que dos abonos al tiempo no sumen más que el total).
     */
    public static function abonar(int $planId, int $sedeId, int $monto, string $metodo, string $nota, ?int $usuarioId): ?string
    {
        if ($monto <= 0) {
            return 'Escribe el valor del abono.';
        }
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT estado, total FROM planes_tratamiento WHERE id = :id AND sede_id = :s FOR UPDATE');
            $stmt->execute(['id' => $planId, 's' => $sedeId]);
            $plan = $stmt->fetch();
            if ($plan === false || $plan['estado'] !== 'aprobado') {
                $pdo->rollBack();

                return 'Solo se abona a un plan aprobado y en curso.';
            }
            $pagado = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM plan_abonos WHERE plan_id = :p AND anulado = 0');
            $pagado->execute(['p' => $planId]);
            $saldo = (int) $plan['total'] - (int) $pagado->fetchColumn();
            if ($monto > $saldo) {
                $pdo->rollBack();

                return 'Ese abono es mayor que lo que falta (' . pesos(max(0, $saldo)) . ').';
            }
            $pdo->prepare(
                'INSERT INTO plan_abonos (plan_id, sede_id, monto, metodo, nota, usuario_id) VALUES (:p, :s, :m, :me, :n, :u)'
            )->execute([
                'p' => $planId, 's' => $sedeId, 'm' => $monto,
                'me' => isset(self::METODOS_ABONO[$metodo]) ? $metodo : 'efectivo',
                'n' => $nota !== '' ? mb_substr($nota, 0, 160) : null, 'u' => $usuarioId,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return null;
    }

    /** Anular un abono mal anotado: solo el mismo día, para que el cierre de caja de ayer no cambie. */
    public static function anularAbono(int $abonoId, int $planId, int $sedeId): bool
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE plan_abonos SET anulado = 1
             WHERE id = :a AND plan_id = :p AND sede_id = :s AND anulado = 0 AND DATE(creado_en) = CURDATE()'
        );
        $stmt->execute(['a' => $abonoId, 'p' => $planId, 's' => $sedeId]);

        return $stmt->rowCount() === 1;
    }

    /** Abonos de planes de un rango, por método (para el cierre de caja). */
    public static function abonosDelRango(int $sedeId, string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT metodo, COUNT(*) AS abonos, COALESCE(SUM(monto), 0) AS total FROM plan_abonos
             WHERE sede_id = :s AND anulado = 0 AND creado_en >= :desde AND creado_en < :hasta GROUP BY metodo'
        );
        $stmt->execute(['s' => $sedeId, 'desde' => $desde, 'hasta' => $hasta]);
        $porMetodo = array_fill_keys(array_keys(self::METODOS_ABONO), ['abonos' => 0, 'total' => 0]);
        foreach ($stmt->fetchAll() as $fila) {
            $porMetodo[(string) $fila['metodo']] = ['abonos' => (int) $fila['abonos'], 'total' => (int) $fila['total']];
        }

        return $porMetodo;
    }

    // ---------- Citas del plan ----------

    /**
     * Citas del paciente que se pueden vincular: sin plan, vivas o recién
     * atendidas, sin cobro ya registrado, sin cupón ni bono (esas ya tienen
     * su propia forma de pago).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function citasVinculables(array $plan): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.id, c.fecha_hora, c.nombre_servicio, c.estado FROM citas c
             WHERE c.sede_id = :s AND c.cliente_id = :cl AND c.plan_id IS NULL
               AND c.estado IN ('pendiente', 'confirmada', 'en_curso', 'completada')
               AND c.precio_final IS NULL AND c.descuento = 0
               AND c.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND NOT EXISTS (SELECT 1 FROM bono_usos b WHERE b.cita_id = c.id)
             ORDER BY c.fecha_hora"
        );
        $stmt->execute(['s' => (int) $plan['sede_id'], 'cl' => (int) $plan['cliente_id']]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function citas(int $planId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.id, c.fecha_hora, c.nombre_servicio, c.estado, c.plan_fase_id, e.nombre AS empleado_nombre
             FROM citas c LEFT JOIN empleados e ON e.id = c.empleado_id WHERE c.plan_id = :p ORDER BY c.fecha_hora'
        );
        $stmt->execute(['p' => $planId]);

        return $stmt->fetchAll();
    }

    /** La cita va por cuenta del plan: descuento = su precio (vale $0; la plata entra por los abonos). */
    public static function vincularCita(array $plan, int $faseId, int $citaId): bool
    {
        if ($plan['estado'] !== 'aprobado') {
            return false;
        }
        $fase = Database::conexion()->prepare('SELECT 1 FROM plan_fases WHERE id = :f AND plan_id = :p');
        $fase->execute(['f' => $faseId, 'p' => (int) $plan['id']]);
        if ($fase->fetchColumn() === false || !in_array($citaId, array_map('intval', array_column(self::citasVinculables($plan), 'id')), true)) {
            return false;
        }
        $stmt = Database::conexion()->prepare(
            'UPDATE citas SET plan_id = :p, plan_fase_id = :f, descuento = precio
             WHERE id = :c AND sede_id = :s AND plan_id IS NULL AND precio_final IS NULL AND descuento = 0'
        );
        $stmt->execute(['p' => (int) $plan['id'], 'f' => $faseId, 'c' => $citaId, 's' => (int) $plan['sede_id']]);

        return $stmt->rowCount() === 1;
    }

    /** Sacarla del plan: vuelve a valer su precio (si todavía no se cobró nada aparte). */
    public static function desvincularCita(array $plan, int $citaId): bool
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE citas SET plan_id = NULL, plan_fase_id = NULL, descuento = 0
             WHERE id = :c AND plan_id = :p AND sede_id = :s AND precio_final IS NULL'
        );
        $stmt->execute(['c' => $citaId, 'p' => (int) $plan['id'], 's' => (int) $plan['sede_id']]);

        return $stmt->rowCount() === 1;
    }

    // ---------- Motivo de consulta (dato sensible) ----------

    /**
     * Guarda el motivo solo con la autorización expresa para datos de salud
     * (Ley 1581, art. 6): sin ella el motivo no se guarda. Ni el motivo ni
     * nada de salud viaja en mensajes de WhatsApp.
     */
    public static function guardarMotivo(int $citaId, string $motivo): void
    {
        Database::conexion()->prepare('UPDATE citas SET motivo_consulta = :m, autorizo_sensibles_en = NOW() WHERE id = :id')
            ->execute(['m' => mb_substr($motivo, 0, 500), 'id' => $citaId]);
    }

    // ---------- Mensajes ----------

    public static function mensajePlan(array $plan, array $sede): string
    {
        $nombre = explode(' ', trim((string) $plan['cliente_nombre']))[0];

        return "Hola {$nombre}, te compartimos tu plan de tratamiento de " . nombre_publico_sede($sede) . " ({$plan['titulo']}) por "
            . pesos((int) $plan['total']) . '. Aquí ves las fases y lo apruebas: ' . url_publica('/plan/' . $plan['token']);
    }

    /**
     * ¿Se le puede recordar el saldo ahora? Mismas reglas que el fiado
     * (Ley 2300 de 2023): en horario permitido y máximo uno por semana.
     *
     * @return array{permitido: bool, razon: ?string}
     */
    public static function puedeRecordarSaldo(array $plan): array
    {
        if ($plan['estado'] !== 'aprobado' || self::saldo($plan) <= 0) {
            return ['permitido' => false, 'razon' => 'Este plan no tiene saldo pendiente.'];
        }
        if (!empty($plan['saldo_recordado_en']) && strtotime((string) $plan['saldo_recordado_en']) > time() - 7 * 86400) {
            return ['permitido' => false, 'razon' => 'Ya le recordaste esta semana: la ley permite un recordatorio de cobro por semana.'];
        }
        $horario = HorarioCobro::evaluar();

        return ['permitido' => $horario['permitido'], 'razon' => $horario['razon']];
    }

    public static function marcarSaldoRecordado(int $id, int $sedeId): void
    {
        Database::conexion()->prepare('UPDATE planes_tratamiento SET saldo_recordado_en = NOW() WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $id, 's' => $sedeId]);
    }

    public static function mensajeSaldo(array $plan, array $sede): string
    {
        $nombre = explode(' ', trim((string) $plan['cliente_nombre']))[0];

        return "Hola {$nombre}, te escribimos de " . nombre_publico_sede($sede) . ". De tu plan ({$plan['titulo']}) llevas abonado "
            . pesos((int) $plan['pagado']) . ' y quedan ' . pesos(self::saldo($plan)) . '. '
            . 'Aquí ves el detalle: ' . url_publica('/plan/' . $plan['token']);
    }
}
