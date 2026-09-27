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
     * a quien compra casi a diario).
     *
     * @return array<int, array{cliente: array<string, mixed>, dias_sin_pedir: int, frecuencia_prom: int, motivo: string}>
     */
    public static function clientesAReactivar(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT cliente_id, creado_en FROM pedidos
             WHERE negocio_id = :negocio_id
             ORDER BY cliente_id ASC, creado_en ASC'
        );
        $stmt->execute(['negocio_id' => $negocioId]);

        $porCliente = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porCliente[(int) $fila['cliente_id']][] = new DateTimeImmutable((string) $fila['creado_en']);
        }

        $hoy = new DateTimeImmutable('today');
        $resultado = [];

        foreach ($porCliente as $clienteId => $fechas) {
            if (count($fechas) < 2) {
                continue; // no hay suficiente historia todavía
            }

            $brechas = [];
            for ($i = 1; $i < count($fechas); $i++) {
                $brechas[] = (int) $fechas[$i - 1]->diff($fechas[$i])->days;
            }
            $frecuenciaProm = (int) round(array_sum($brechas) / count($brechas));

            $ultimoPedido = end($fechas);
            $diasSinPedir = (int) $ultimoPedido->diff($hoy)->days;
            $umbral = max(14, (int) round($frecuenciaProm * 1.5));

            if ($diasSinPedir > $umbral) {
                $cliente = Cliente::buscar($clienteId, $negocioId);
                if ($cliente === null) {
                    continue;
                }
                $resultado[] = [
                    'cliente'         => $cliente,
                    'dias_sin_pedir'  => $diasSinPedir,
                    'frecuencia_prom' => $frecuenciaProm,
                    'motivo'          => "No pide hace {$diasSinPedir} días · antes pedía cada {$frecuenciaProm} días",
                ];
            }
        }

        usort($resultado, fn ($a, $b) => $b['dias_sin_pedir'] <=> $a['dias_sin_pedir']);

        return $resultado;
    }

    public static function mensajeSugerido(array $cliente): string
    {
        $palabras = explode(' ', trim((string) $cliente['nombre']));
        $honorificos = ['doña', 'don', 'señora', 'señor'];

        // "Doña Marta" debe saludar como "Marta", no como "Doña".
        $primeraPalabra = mb_strtolower($palabras[0]);
        $nombreParaSaludo = (in_array($primeraPalabra, $honorificos, true) && isset($palabras[1]))
            ? $palabras[1]
            : $palabras[0];

        return "Hola {$nombreParaSaludo}, te extrañamos. Como cliente frecuente tienes 10% en tu próximo "
            . 'pedido. ¿Te separamos lo de siempre?';
    }

    /** % de clientes que pidieron más de una vez en los últimos 30 días. */
    public static function recompraMensualPct(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT cliente_id, COUNT(*) AS total FROM pedidos
             WHERE negocio_id = :negocio_id AND creado_en >= NOW() - INTERVAL 30 DAY
             GROUP BY cliente_id'
        );
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
