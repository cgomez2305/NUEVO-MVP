<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use DateTimeImmutable;

/**
 * El copiloto de recompra: reglas simples de frecuencia y último pedido,
 * como pide el documento de producto para el MVP (antes de modelos más
 * complejos): "Doña Marta no pide hace 18 días; mándale esto".
 */
class Copiloto
{
    /**
     * Clientes con al menos 2 pedidos cuyo silencio actual supera 1.5x
     * su frecuencia habitual (con un piso de 14 días para no molestar
     * a quien compra casi a diario). Es el segmento "inactivo" de
     * segmentar(), en el formato que ya usaba el dashboard.
     *
     * @return array<int, array{cliente: array<string, mixed>, dias_sin_pedir: int, frecuencia_prom: int, motivo: string}>
     */
    public static function clientesAReactivar(int $negocioId, string $tipoNegocio = 'pedidos'): array
    {
        $inactivos = array_filter(
            self::segmentar($negocioId, $tipoNegocio),
            fn ($fila) => in_array('inactivo', $fila['tags'], true)
        );

        return array_values(array_map(fn ($fila) => [
            'cliente'         => $fila['cliente'],
            'dias_sin_pedir'  => $fila['dias_sin_pedir'],
            'frecuencia_prom' => $fila['frecuencia_prom'],
            'motivo'          => $fila['motivo'],
        ], $inactivos));
    }

    public static function mensajeSugerido(array $cliente, string $segmento = 'inactivo'): string
    {
        $nombreParaSaludo = self::nombreParaSaludo((string) $cliente['nombre']);

        return match ($segmento) {
            'vip'   => "Hola {$nombreParaSaludo}, eres uno de nuestros mejores clientes y queremos que lo notes: "
                . 'tienes 15% en tu próximo pedido, solo por ser tú. ¿Qué te separamos?',
            'nuevo' => "Hola {$nombreParaSaludo}, gracias por tu primera compra con nosotros. "
                . 'Si quieres repetir, tienes 10% en tu segundo pedido. ¿Te ayudamos con algo?',
            default => "Hola {$nombreParaSaludo}, te extrañamos. Como cliente frecuente tienes 10% en tu próximo "
                . 'pedido. ¿Te separamos lo de siempre?',
        };
    }

    private static function nombreParaSaludo(string $nombreCompleto): string
    {
        $palabras = explode(' ', trim($nombreCompleto));
        $honorificos = ['doña', 'don', 'señora', 'señor'];

        // "Doña Marta" debe saludar como "Marta", no como "Doña".
        $primeraPalabra = mb_strtolower($palabras[0]);
        return (in_array($primeraPalabra, $honorificos, true) && isset($palabras[1]))
            ? $palabras[1]
            : $palabras[0];
    }

    /**
     * Segmenta TODOS los clientes con al menos una compra/cita en: inactivo
     * (se está enfriando, misma regla que antes), vip (gasta o compra
     * mucho más que el resto), nuevo (una sola compra reciente) y
     * recurrente (el resto: compra seguido y no necesita nada especial
     * ahora mismo). Un cliente puede tener varias etiquetas a la vez —
     * un VIP que se está enfriando es justo el caso más valioso de ver.
     *
     * @return array<int, array{
     *   cliente: array<string, mixed>, total_compras: int, gasto_total: int,
     *   dias_sin_pedir: int, frecuencia_prom: ?int, tags: array<int, string>, motivo: string
     * }>
     */
    public static function segmentar(int $negocioId, string $tipoNegocio = 'pedidos'): array
    {
        $sql = $tipoNegocio === 'reservas'
            ? 'SELECT cliente_id, fecha_hora AS fecha, precio AS monto FROM citas
               WHERE negocio_id = :negocio_id AND estado != "cancelada" ORDER BY cliente_id ASC, fecha_hora ASC'
            : 'SELECT cliente_id, creado_en AS fecha, total AS monto FROM pedidos
               WHERE negocio_id = :negocio_id ORDER BY cliente_id ASC, creado_en ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['negocio_id' => $negocioId]);

        $porCliente = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porCliente[(int) $fila['cliente_id']]['fechas'][] = new DateTimeImmutable((string) $fila['fecha']);
            $porCliente[(int) $fila['cliente_id']]['montos'][] = (int) $fila['monto'];
        }

        $hoy = new DateTimeImmutable('today');
        $resultado = [];

        foreach ($porCliente as $clienteId => $datos) {
            $fechas = $datos['fechas'];
            $totalCompras = count($fechas);
            $gastoTotal = array_sum($datos['montos']);

            $frecuenciaProm = null;
            if ($totalCompras >= 2) {
                $brechas = [];
                for ($i = 1; $i < $totalCompras; $i++) {
                    $brechas[] = (int) $fechas[$i - 1]->diff($fechas[$i])->days;
                }
                $frecuenciaProm = (int) round(array_sum($brechas) / count($brechas));
            }

            $ultimaCompra = end($fechas);
            $primeraCompra = $fechas[0];
            $diasSinPedir = (int) $ultimaCompra->diff($hoy)->days;
            $diasDesdeAlta = (int) $primeraCompra->diff($hoy)->days;

            $cliente = Cliente::buscar($clienteId, $negocioId);
            if ($cliente === null) {
                continue;
            }

            $resultado[] = [
                'cliente'         => $cliente,
                'total_compras'   => $totalCompras,
                'gasto_total'     => $gastoTotal,
                'dias_sin_pedir'  => $diasSinPedir,
                'dias_desde_alta' => $diasDesdeAlta,
                'frecuencia_prom' => $frecuenciaProm,
            ];
        }

        // VIP: top 20% por gasto total, con al menos 3 compras (para no
        // etiquetar como VIP a alguien con una sola compra grande).
        $gastosElegiblesVip = array_values(array_filter(
            array_map(fn ($r) => $r['total_compras'] >= 3 ? $r['gasto_total'] : null, $resultado),
            fn ($g) => $g !== null
        ));
        rsort($gastosElegiblesVip);
        $umbralVip = $gastosElegiblesVip === []
            ? null
            : $gastosElegiblesVip[max(0, (int) ceil(count($gastosElegiblesVip) * 0.2) - 1)];

        foreach ($resultado as &$fila) {
            $tags = [];

            $umbralInactivo = $fila['frecuencia_prom'] !== null ? max(14, (int) round($fila['frecuencia_prom'] * 1.5)) : null;
            $esInactivo = $fila['total_compras'] >= 2 && $umbralInactivo !== null && $fila['dias_sin_pedir'] > $umbralInactivo;
            $esVip = $fila['total_compras'] >= 3 && $umbralVip !== null && $fila['gasto_total'] >= $umbralVip;
            $esNuevo = $fila['total_compras'] === 1 && $fila['dias_sin_pedir'] <= 30;

            if ($esInactivo) {
                $tags[] = 'inactivo';
            }
            if ($esVip) {
                $tags[] = 'vip';
            }
            if ($esNuevo) {
                $tags[] = 'nuevo';
            }
            if ($tags === []) {
                $tags[] = 'recurrente';
            }

            $fila['tags'] = $tags;
            $fila['motivo'] = match (true) {
                $esInactivo => "No pide hace {$fila['dias_sin_pedir']} días · antes pedía cada {$fila['frecuencia_prom']} días",
                $esNuevo    => "Su primera compra fue hace {$fila['dias_sin_pedir']} días",
                $esVip      => "{$fila['total_compras']} compras · " . number_format($fila['gasto_total'], 0, ',', '.') . ' en total',
                default     => "{$fila['total_compras']} compras · última hace {$fila['dias_sin_pedir']} días",
            };
        }
        unset($fila);

        usort($resultado, fn ($a, $b) => $b['dias_sin_pedir'] <=> $a['dias_sin_pedir']);

        return $resultado;
    }

    /** % de clientes que pidieron/reservaron más de una vez en los últimos 30 días. */
    public static function recompraMensualPct(int $negocioId, string $tipoNegocio = 'pedidos'): int
    {
        $sql = $tipoNegocio === 'reservas'
            ? 'SELECT cliente_id, COUNT(*) AS total FROM citas
               WHERE negocio_id = :negocio_id AND fecha_hora >= NOW() - INTERVAL 30 DAY AND estado != "cancelada"
               GROUP BY cliente_id'
            : 'SELECT cliente_id, COUNT(*) AS total FROM pedidos
               WHERE negocio_id = :negocio_id AND creado_en >= NOW() - INTERVAL 30 DAY
               GROUP BY cliente_id';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['negocio_id' => $negocioId]);
        $filas = $stmt->fetchAll();

        if ($filas === []) {
            return 0;
        }

        $repiten = count(array_filter($filas, fn ($f) => (int) $f['total'] >= 2));

        return (int) round($repiten / count($filas) * 100);
    }

    public static function registrarEnvio(int $negocioId, int $clienteId, string $mensaje): void
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO mensajes_copiloto (negocio_id, cliente_id, mensaje)
             VALUES (:negocio_id, :cliente_id, :mensaje)'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'cliente_id' => $clienteId, 'mensaje' => $mensaje]);
    }
}
