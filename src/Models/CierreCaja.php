<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Cierre de caja diario de una sede (ver database/migrations/
 * 2026-10-03_06_cierres_caja.sql). El resumen del día se calcula de los
 * pedidos y citas; al cerrar se guarda una foto de ese resumen (si mañana
 * cancelan un pedido de hoy, el cierre de hoy no cambia: fue lo que se contó).
 */
class CierreCaja
{
    public const METODOS = ['efectivo' => 'Efectivo', 'breb' => 'Bre-B', 'nequi' => 'Nequi'];

    /**
     * Lo vendido el día en la sede: pedidos no cancelados por forma de
     * pago, descuentos, domicilios, pendientes por entregar y citas.
     *
     * @return array<string, mixed>
     */
    public static function resumenDelDia(int $sedeId, string $fecha): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "SELECT metodo_pago, COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS total,
                    COALESCE(SUM(descuento), 0) AS descuentos, COALESCE(SUM(costo_domicilio), 0) AS domicilios,
                    SUM(estado NOT IN ('entregado', 'cancelado')) AS sin_entregar
             FROM pedidos
             WHERE sede_id = :s AND creado_en >= :desde AND creado_en < :hasta AND estado <> 'cancelado'
             GROUP BY metodo_pago"
        );
        $rango = ['s' => $sedeId, 'desde' => $fecha . ' 00:00:00', 'hasta' => date('Y-m-d', strtotime($fecha . ' +1 day')) . ' 00:00:00'];
        $stmt->execute($rango);
        $porMetodo = array_fill_keys(array_keys(self::METODOS), ['pedidos' => 0, 'total' => 0]);
        $pedidos = 0;
        $totalPedidos = 0;
        $descuentos = 0;
        $domicilios = 0;
        $sinEntregar = 0;
        foreach ($stmt->fetchAll() as $fila) {
            $metodo = (string) $fila['metodo_pago'];
            $porMetodo[$metodo] = ['pedidos' => (int) $fila['pedidos'], 'total' => (int) $fila['total']];
            $pedidos += (int) $fila['pedidos'];
            $totalPedidos += (int) $fila['total'];
            $descuentos += (int) $fila['descuentos'];
            $domicilios += (int) $fila['domicilios'];
            $sinEntregar += (int) $fila['sin_entregar'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE sede_id = :s AND creado_en >= :desde AND creado_en < :hasta AND estado = 'cancelado'");
        $stmt->execute($rango);
        $cancelados = (int) $stmt->fetchColumn();

        // Citas del día (las de la agenda de ese día, no las reservadas ese día).
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS citas, COALESCE(SUM(' . Cita::sqlValor() . "), 0) AS total,
                    COALESCE(SUM(CASE WHEN anticipo_estado = 'pagado' THEN anticipo_monto ELSE 0 END), 0) AS anticipos
             FROM citas
             WHERE sede_id = :s AND fecha_hora >= :desde AND fecha_hora < :hasta AND " . Cita::sqlCuenta()
        );
        $stmt->execute($rango);
        $citas = $stmt->fetch() ?: ['citas' => 0, 'total' => 0, 'anticipos' => 0];
        // El anticipo que se quedó el negocio porque el cliente no llegó
        // (regla "se pierde": sin cupón de abono) es plata que sí entró.
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(anticipo_monto), 0) FROM citas
             WHERE sede_id = :s AND fecha_hora >= :desde AND fecha_hora < :hasta
               AND estado = 'no_asistio' AND anticipo_estado = 'pagado' AND cupon_abono_id IS NULL"
        );
        $stmt->execute($rango);
        // Va a "total" y también a "anticipos": entró por transferencia, no es efectivo del cajón.
        $retenido = (int) $stmt->fetchColumn();
        $citas['total'] = (int) $citas['total'] + $retenido;
        $citas['anticipos'] = (int) $citas['anticipos'] + $retenido;

        // Tiendas (fase 4): ventas de mostrador (no anuladas) y abonos de
        // fiado. Lo fiado no es plata que entró: no suma a "Vendido" ni al
        // efectivo; va aparte como "Fiado hoy". Un abono en efectivo sí entró.
        $mostrador = Venta::resumenDelRango($sedeId, $rango['desde'], $rango['hasta']);
        $abonosFiado = Fiado::abonosDelRango($sedeId, $rango['desde'], $rango['hasta']);
        $mostradorCobrado = array_sum(array_column($mostrador['por_metodo'], 'total')) - $mostrador['por_metodo']['fiado']['total'];

        return [
            'mostrador'     => $mostrador,
            'abonos_fiado'  => $abonosFiado,
            'fiado_hoy'     => $mostrador['por_metodo']['fiado']['total'],
            'fecha'         => $fecha,
            'por_metodo'    => $porMetodo,
            'pedidos'       => $pedidos,
            'total_pedidos' => $totalPedidos,
            'descuentos'    => $descuentos,
            'domicilios'    => $domicilios,
            'sin_entregar'  => $sinEntregar,
            'cancelados'    => $cancelados,
            'citas'         => (int) $citas['citas'],
            'total_citas'   => (int) $citas['total'],
            'anticipos'     => (int) $citas['anticipos'],
            'ventas_total'  => $totalPedidos + (int) $citas['total'] + $mostradorCobrado,
        ];
    }

    /**
     * Efectivo que entró por el mostrador y por abonos de fiado. Los
     * cierres guardados antes de la fase 4 no traen estas claves: cuentan 0.
     */
    public static function efectivoDeTienda(array $resumen): int
    {
        return (int) ($resumen['mostrador']['por_metodo']['efectivo']['total'] ?? 0)
            + (int) ($resumen['abonos_fiado']['efectivo']['total'] ?? 0);
    }

    public static function buscar(int $sedeId, string $fecha): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, u.nombre AS usuario_nombre FROM cierres_caja c
             LEFT JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.sede_id = :s AND c.fecha = :f'
        );
        $stmt->execute(['s' => $sedeId, 'f' => $fecha]);
        $cierre = $stmt->fetch();
        if (!$cierre) {
            return null;
        }
        $cierre['resumen'] = json_decode((string) $cierre['resumen'], true) ?: [];

        return $cierre;
    }

    /**
     * Cierra (o vuelve a cerrar) el día. Lo esperado en efectivo es la base
     * más lo vendido en efectivo; la diferencia es contado − esperado.
     */
    public static function cerrar(int $sedeId, string $fecha, int $base, int $contado, string $notas, ?int $usuarioId, int $efectivoServicios = 0): array
    {
        $resumen = self::resumenDelDia($sedeId, $fecha);
        // Las citas no guardan forma de pago: quien cierra dice cuánto de
        // los servicios le pagaron en efectivo (nunca más de lo vendido).
        $resumen['efectivo_servicios'] = max(0, min($efectivoServicios, (int) $resumen['total_citas']));
        $esperado = $base + (int) $resumen['por_metodo']['efectivo']['total'] + $resumen['efectivo_servicios'] + self::efectivoDeTienda($resumen);
        Database::conexion()->prepare(
            'INSERT INTO cierres_caja (sede_id, fecha, ventas_total, base, efectivo_esperado, efectivo_contado, diferencia, resumen, notas, usuario_id)
             VALUES (:s, :f, :ventas, :base, :esperado, :contado, :dif, :resumen, :notas, :u)
             ON DUPLICATE KEY UPDATE ventas_total = VALUES(ventas_total), base = VALUES(base),
               efectivo_esperado = VALUES(efectivo_esperado), efectivo_contado = VALUES(efectivo_contado),
               diferencia = VALUES(diferencia), resumen = VALUES(resumen), notas = VALUES(notas), usuario_id = VALUES(usuario_id)'
        )->execute([
            's' => $sedeId, 'f' => $fecha, 'ventas' => $resumen['ventas_total'], 'base' => $base,
            'esperado' => $esperado, 'contado' => $contado, 'dif' => $contado - $esperado,
            'resumen' => json_encode($resumen), 'notas' => $notas !== '' ? mb_substr($notas, 0, 255) : null, 'u' => $usuarioId,
        ]);

        return (array) self::buscar($sedeId, $fecha);
    }

    /** @return array<int, array<string, mixed>> */
    public static function historial(int $sedeId, int $limite = 14): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT fecha, ventas_total, diferencia FROM cierres_caja WHERE sede_id = :s ORDER BY fecha DESC LIMIT {$limite}"
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    /** La base del último cierre: casi siempre se abre con la misma. */
    public static function ultimaBase(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare('SELECT base FROM cierres_caja WHERE sede_id = :s ORDER BY fecha DESC LIMIT 1');
        $stmt->execute(['s' => $sedeId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
