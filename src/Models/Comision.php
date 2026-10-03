<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Liquidación de comisiones: muchos barberos y estilistas trabajan por
 * porcentaje de lo que atienden (empleados.comision_pct). Se calcula sobre
 * lo cobrado de verdad (Cita::sqlValor: precio final menos descuentos) de
 * las citas COMPLETADAS del período; las canceladas y "no vino" no cuentan.
 * Es un cálculo, no un pago: Veci no mueve la plata.
 */
class Comision
{
    /** @return array<string, array{desde: string, hasta: string, etiqueta: string}> */
    public static function periodos(): array
    {
        $lunes = new \DateTimeImmutable('monday this week');
        $hoy = new \DateTimeImmutable('today');

        return [
            'semana'   => ['desde' => $lunes->format('Y-m-d'), 'hasta' => $hoy->format('Y-m-d'), 'etiqueta' => 'Esta semana'],
            'anterior' => ['desde' => $lunes->modify('-7 days')->format('Y-m-d'), 'hasta' => $lunes->modify('-1 day')->format('Y-m-d'), 'etiqueta' => 'Semana pasada'],
            'mes'      => ['desde' => $hoy->format('Y-m-01'), 'hasta' => $hoy->format('Y-m-d'), 'etiqueta' => 'Este mes'],
            'mes_ant'  => ['desde' => $hoy->modify('first day of last month')->format('Y-m-d'), 'hasta' => $hoy->modify('last day of last month')->format('Y-m-d'), 'etiqueta' => 'Mes pasado'],
        ];
    }

    /**
     * Por profesional (de la sede): citas atendidas, lo vendido y la
     * comisión. Incluye a quien no va por comisión (comision_pct NULL) para
     * que el dueño vea lo que vendió cada uno, sin calcularle nada.
     *
     * @return array<int, array{id: int, nombre: string, pct: ?int, citas: int, vendido: int, comision: ?int}>
     */
    /**
     * Base de la comisión de una cita: lo que se cobró (Cita::sqlValor). Si
     * la sesión iba por un bono, el descuento no es plata perdida: el bono se
     * pagó antes. Ahí la base es el valor del servicio, como en un salón de
     * verdad, donde el profesional cobra su parte de cada sesión del paquete.
     */
    public static function sqlBase(string $alias): string
    {
        return "IF(EXISTS (SELECT 1 FROM bono_usos bu WHERE bu.cita_id = {$alias}.id), "
            . "CAST(COALESCE({$alias}.precio_final, {$alias}.precio) AS SIGNED), " . Cita::sqlValor($alias) . ')';
    }

    public static function liquidacion(int $sedeId, string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT e.id, e.nombre, e.comision_pct, COUNT(c.id) AS citas, COALESCE(SUM(" . self::sqlBase('c') . "), 0) AS vendido
             FROM empleados e
             LEFT JOIN citas c ON c.empleado_id = e.id AND c.estado = 'completada'
                  AND c.fecha_hora >= :desde AND c.fecha_hora < DATE_ADD(:hasta, INTERVAL 1 DAY)
             WHERE e.sede_id = :s
             GROUP BY e.id, e.nombre, e.comision_pct
             ORDER BY vendido DESC, e.nombre"
        );
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta, 's' => $sedeId]);
        $filas = [];
        foreach ($stmt->fetchAll() as $fila) {
            $pct = $fila['comision_pct'] !== null ? (int) $fila['comision_pct'] : null;
            $vendido = (int) $fila['vendido'];
            $filas[] = [
                'id'       => (int) $fila['id'],
                'nombre'   => (string) $fila['nombre'],
                'pct'      => $pct,
                'citas'    => (int) $fila['citas'],
                'vendido'  => $vendido,
                'comision' => $pct !== null ? (int) round($vendido * $pct / 100) : null,
            ];
        }

        return $filas;
    }

    /** @return array<int, array<string, mixed>> el detalle de un profesional en el período (para su tiquete) */
    public static function detalle(int $sedeId, int $empleadoId, string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.id, c.fecha_hora, c.nombre_servicio, cl.nombre AS cliente_nombre, " . self::sqlBase('c') . " AS valor
             FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.sede_id = :s AND c.empleado_id = :e AND c.estado = 'completada'
               AND c.fecha_hora >= :desde AND c.fecha_hora < DATE_ADD(:hasta, INTERVAL 1 DAY)
             ORDER BY c.fecha_hora"
        );
        $stmt->execute(['s' => $sedeId, 'e' => $empleadoId, 'desde' => $desde, 'hasta' => $hasta]);

        return $stmt->fetchAll();
    }
}
