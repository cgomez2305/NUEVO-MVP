<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Tarjeta de sellos del negocio (ver database/migrations/
 * 2026-10-03_02_fidelidad.sql). Los sellos no se guardan uno por uno: se
 * cuentan de los pedidos y citas no cancelados del cliente, en cualquier
 * sede, desde que el negocio activó la tarjeta y desde la compra mínima.
 * Así un pedido cancelado deja de contar solo y no hay dos verdades.
 * Cada premio entregado anota cuántos sellos gastó.
 */
class Fidelidad
{
    public const METAS = [5, 6, 8, 10, 12];

    public static function config(int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM fidelidad WHERE negocio_id = :n');
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetch() ?: null;
    }

    /** La configuración solo si la tarjeta está prendida: lo que usan la tienda y los avisos. */
    public static function activa(int $negocioId): ?array
    {
        $config = self::config($negocioId);

        return $config !== null && (int) $config['activa'] === 1 ? $config : null;
    }

    /**
     * Crea o actualiza la tarjeta. `desde` se fija la primera vez y no se
     * mueve al editar: cambiar el premio no le borra los sellos a nadie.
     */
    public static function guardar(int $negocioId, bool $activa, int $meta, string $premio, int $minimo): void
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO fidelidad (negocio_id, activa, meta, premio, minimo_compra)
             VALUES (:n, :activa, :meta, :premio, :minimo)
             ON DUPLICATE KEY UPDATE activa = VALUES(activa), meta = VALUES(meta),
                                     premio = VALUES(premio), minimo_compra = VALUES(minimo_compra)'
        );
        $stmt->execute([
            'n'      => $negocioId,
            'activa' => $activa ? 1 : 0,
            'meta'   => in_array($meta, self::METAS, true) ? $meta : 8,
            'premio' => mb_substr(trim($premio), 0, 120),
            'minimo' => max(0, $minimo),
        ]);
    }

    /**
     * Sellos disponibles por cliente (ganados − gastados en premios), solo
     * de quienes tienen al menos uno. Ordenados de más cerca al premio.
     *
     * @return array<int, array{id: int, nombre: string, telefono: string, sellos: int, premios: int}>
     */
    public static function tarjetas(int $negocioId, array $config, ?int $clienteId = null): array
    {
        $soloCliente = $clienteId !== null ? ' AND c.id = :cliente' : '';
        $stmt = Database::conexion()->prepare(
            "SELECT c.id, c.nombre, c.telefono,
                    GREATEST(0, x.ganados - COALESCE(p.usados, 0)) AS sellos,
                    COALESCE(p.premios, 0) AS premios
             FROM clientes c
             JOIN (
               SELECT t.cliente_id, COUNT(*) AS ganados FROM (
                 SELECT pe.cliente_id FROM pedidos pe JOIN sedes s ON s.id = pe.sede_id
                 WHERE s.negocio_id = :n1 AND pe.estado <> 'cancelado'
                   AND pe.total >= :min1 AND pe.creado_en >= :desde1
                 UNION ALL
                 SELECT ci.cliente_id FROM citas ci JOIN sedes s ON s.id = ci.sede_id
                 WHERE s.negocio_id = :n2 AND ci.estado <> 'cancelada'
                   AND (ci.precio - ci.descuento) >= :min2 AND ci.creado_en >= :desde2
               ) t GROUP BY t.cliente_id
             ) x ON x.cliente_id = c.id
             LEFT JOIN (
               SELECT cliente_id, SUM(sellos) AS usados, COUNT(*) AS premios
               FROM fidelidad_premios WHERE negocio_id = :n3 GROUP BY cliente_id
             ) p ON p.cliente_id = c.id
             WHERE c.negocio_id = :n4{$soloCliente}
             ORDER BY sellos DESC, c.nombre"
        );
        $parametros = [
            'n1' => $negocioId, 'n2' => $negocioId, 'n3' => $negocioId, 'n4' => $negocioId,
            'min1' => (int) $config['minimo_compra'], 'min2' => (int) $config['minimo_compra'],
            'desde1' => $config['desde'], 'desde2' => $config['desde'],
        ];
        if ($clienteId !== null) {
            $parametros['cliente'] = $clienteId;
        }
        $stmt->execute($parametros);

        return array_map(fn ($fila) => [
            'id'       => (int) $fila['id'],
            'nombre'   => (string) $fila['nombre'],
            'telefono' => (string) $fila['telefono'],
            'sellos'   => (int) $fila['sellos'],
            'premios'  => (int) $fila['premios'],
        ], $stmt->fetchAll());
    }

    /** Sellos disponibles de un cliente (0 si no tiene ninguno). */
    public static function sellosDe(int $negocioId, array $config, int $clienteId): int
    {
        return self::tarjetas($negocioId, $config, $clienteId)[0]['sellos'] ?? 0;
    }

    /** ¿Esta compra suma sello? (activa, desde el mínimo). */
    public static function cuenta(array $config, int $monto): bool
    {
        return (int) $config['activa'] === 1 && $monto >= (int) $config['minimo_compra'];
    }

    /**
     * Anota el premio entregado y gasta `meta` sellos. Revisa otra vez que
     * de verdad los tenga: dos clics seguidos no entregan dos premios.
     */
    public static function entregarPremio(int $negocioId, array $config, int $clienteId, ?int $usuarioId): bool
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            // Bloquea la fila de la tarjeta del negocio mientras se cuenta.
            $pdo->prepare('SELECT negocio_id FROM fidelidad WHERE negocio_id = :n FOR UPDATE')->execute(['n' => $negocioId]);
            if (self::sellosDe($negocioId, $config, $clienteId) < (int) $config['meta']) {
                $pdo->rollBack();
                return false;
            }
            $pdo->prepare(
                'INSERT INTO fidelidad_premios (negocio_id, cliente_id, sellos, premio, usuario_id)
                 VALUES (:n, :c, :s, :p, :u)'
            )->execute([
                'n' => $negocioId, 'c' => $clienteId, 's' => (int) $config['meta'],
                'p' => $config['premio'], 'u' => $usuarioId,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return true;
    }

    /** Premios entregados en total (para la cabecera del panel). */
    public static function premiosEntregados(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM fidelidad_premios WHERE negocio_id = :n');
        $stmt->execute(['n' => $negocioId]);

        return (int) $stmt->fetchColumn();
    }

    /** "Llevas 3 de 8 sellos" / "¡Completaste tu tarjeta!" — para el texto de WhatsApp. */
    public static function textoProgreso(array $config, int $sellos): string
    {
        $meta = (int) $config['meta'];
        if ($sellos >= $meta) {
            return "Tarjeta de sellos completa ({$meta}/{$meta}): le toca {$config['premio']}.";
        }

        return "Tarjeta de sellos: {$sellos}/{$meta}.";
    }
}
