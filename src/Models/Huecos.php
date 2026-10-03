<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * "Llenar huecos": cruza los espacios libres del próximo día de atención
 * (mañana, o el siguiente día que abre) con los clientes a
 * los que ya les toca volver. Regla explicable, sin adivinar:
 *  - el cliente aceptó promociones, tiene al menos 2 visitas y ya está a
 *    3 días (o menos) de su ritmo habitual, o pasado;
 *  - no tiene otra cita agendada;
 *  - su servicio de la última vez (con la duración que de verdad tomó y su
 *    misma persona, si sigue en el equipo) cabe mañana en la agenda.
 * Se le propone el primer espacio en que cabe.
 */
class Huecos
{
    /** Cuántos días antes de cumplir su ritmo ya vale la pena ofrecerle el espacio. */
    private const DIAS_ANTES = 3;

    private const MAXIMO = 12;

    /**
     * @param array<string, mixed> $sede contexto de Auth::exigirSesion()
     * @return array<int, array{cliente: array<string, mixed>, servicio: string, empleado: ?string, empleado_id: ?int, hora: string, frecuencia: int, dias: int}>
     */
    public static function paraManana(array $sede): array
    {
        $fecha = self::proximoDiaDeAtencion($sede);
        $sedeId = (int) $sede['id'];
        // Visitas a domicilio van por franjas y rutas: no aplica.
        if ($fecha === null || Visita::esDomicilio($sede)) {
            return [];
        }

        $conCitaFutura = self::clientesConCitaFutura($sedeId);
        $disponibilidad = [];
        $resultado = [];

        foreach (Copiloto::segmentar((int) $sede['negocio_id'], 'reservas') as $fila) {
            $frecuencia = $fila['frecuencia_prom'];
            if (!$fila['contactable'] || $frecuencia === null || $fila['total_compras'] < 2
                || $fila['dias_sin_pedir'] < max(1, $frecuencia - self::DIAS_ANTES)
                || isset($conCitaFutura[(int) $fila['cliente']['id']])) {
                continue;
            }
            $ultima = self::ultimaCita((int) $fila['cliente']['id'], $sedeId);
            if ($ultima === null) {
                continue;
            }
            $empleado = $ultima['empleado_id'] !== null ? Empleado::buscar((int) $ultima['empleado_id'], $sedeId) : null;
            if ($empleado !== null && (int) $empleado['activo'] !== 1) {
                $empleado = null;
            }
            $duracion = max(15, (int) $ultima['duracion_min']);
            $clave = ($empleado['id'] ?? 0) . '|' . $duracion;
            if (!isset($disponibilidad[$clave])) {
                $disponibilidad[$clave] = Cita::calcularDisponibilidad(
                    Empleado::horario($empleado, $sede),
                    (int) $sede['intervalo_citas_min'],
                    $fecha,
                    $duracion,
                    Cita::ocupadosEnFecha($sedeId, $fecha, null, $empleado !== null ? (int) $empleado['id'] : null),
                    (int) $sede['colchon_min']
                );
            }
            $slots = $disponibilidad[$clave];
            if ($slots === []) {
                continue;
            }
            $resultado[] = [
                'fecha'       => $fecha,
                'cliente'     => $fila['cliente'],
                'servicio'    => (string) $ultima['nombre_servicio'],
                'empleado'    => $empleado !== null ? (string) $empleado['nombre'] : null,
                'empleado_id' => $empleado !== null ? (int) $empleado['id'] : null,
                'hora'        => (string) $slots[0],
                'frecuencia'  => (int) $frecuencia,
                'dias'        => (int) $fila['dias_sin_pedir'],
            ];
        }

        // Los más pasados de su ritmo, primero.
        usort($resultado, fn ($a, $b) => ($b['dias'] - $b['frecuencia']) <=> ($a['dias'] - $a['frecuencia']));

        return array_slice($resultado, 0, self::MAXIMO);
    }

    /**
     * El próximo día (desde mañana, hasta una semana) en que la sede atiende
     * y no está bloqueado: si mañana es domingo y no abre, es el lunes.
     */
    public static function proximoDiaDeAtencion(array $sede): ?string
    {
        $horario = Sede::horario($sede);
        for ($i = 1; $i <= 7; $i++) {
            $fecha = date('Y-m-d', strtotime("+{$i} day"));
            if (isset($horario[(string) (int) date('N', strtotime($fecha))]) && !FechaBloqueada::estaBloqueada((int) $sede['id'], $fecha)) {
                return $fecha;
            }
        }

        return null;
    }

    /** "mañana lunes" o "el martes" (si no es mañana). */
    public static function cuando(string $fecha): string
    {
        $dia = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'][(int) date('w', strtotime($fecha))];

        return $fecha === date('Y-m-d', strtotime('+1 day')) ? 'mañana ' . $dia : 'el ' . $dia;
    }

    /** El mensaje para ofrecerle el espacio (con su enlace para dejar de recibir promociones). */
    public static function mensaje(array $hueco, string $marca, string $enlacePreferencias): string
    {
        $nombre = explode(' ', trim((string) $hueco['cliente']['nombre']))[0];

        return "Hola {$nombre}, te escribimos de {$marca}. " . ucfirst(self::cuando($hueco['fecha'])) . ' tenemos un espacio a las ' . hora_completa($hueco['hora'])
            . ' para tu ' . mb_strtolower($hueco['servicio']) . ($hueco['empleado'] !== null ? ' con ' . $hueco['empleado'] : '')
            . '. ¿Te lo separo?' . "\n\nSi prefieres no recibir más promociones: " . $enlacePreferencias;
    }

    /** @return array<int, true> */
    private static function clientesConCitaFutura(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT DISTINCT cliente_id FROM citas WHERE sede_id = :s AND fecha_hora >= NOW() AND estado IN ('pendiente', 'confirmada')"
        );
        $stmt->execute(['s' => $sedeId]);

        return array_fill_keys(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)), true);
    }

    private static function ultimaCita(int $clienteId, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT nombre_servicio, empleado_id, duracion_min FROM citas
             WHERE cliente_id = :c AND sede_id = :s AND ' . Cita::sqlCuenta() . ' AND fecha_hora < NOW()
             ORDER BY fecha_hora DESC LIMIT 1'
        );
        $stmt->execute(['c' => $clienteId, 's' => $sedeId]);

        return $stmt->fetch() ?: null;
    }
}
