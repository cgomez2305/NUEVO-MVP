<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Pedido
{
    public const ESTADOS = ['pendiente', 'pagado', 'en_cocina', 'listo', 'en_camino', 'entregado', 'cancelado'];

    /**
     * A qué estado pasa un pedido al pulsar el botón de acción principal, y
     * cómo se llama ese botón. No es fijo: desde "listo", un domicilio pasa
     * por "en camino", pero recoger/mesa cierran directo (no tiene sentido
     * un "en camino" para algo que el cliente recoge en el local). Vuelve
     * null cuando el pedido ya está en un estado final (entregado/cancelado)
     * o en un estado que solo se cambia a mano (pendiente/pagado).
     *
     * @return array{estado:string, texto:string}|null
     */
    public static function siguientePaso(array $pedido): ?array
    {
        return match ($pedido['estado']) {
            'pendiente', 'pagado' => ['estado' => 'en_cocina', 'texto' => 'Iniciar preparación'],
            'en_cocina' => ['estado' => 'listo', 'texto' => 'Marcar listo'],
            'listo' => match ($pedido['tipo_entrega']) {
                'domicilio' => ['estado' => 'en_camino', 'texto' => 'Enviar · En camino'],
                'mesa' => ['estado' => 'entregado', 'texto' => 'Marcar servido'],
                default => ['estado' => 'entregado', 'texto' => 'Entregar'],
            },
            'en_camino' => ['estado' => 'entregado', 'texto' => 'Marcar entregado'],
            default => null,
        };
    }

    /**
     * @param array<int, array{producto_id:int, nombre:string, precio:int, cantidad:int}> $items
     */
    public static function crear(
        int $sedeId,
        int $clienteId,
        string $metodoPago,
        array $items,
        string $tipoEntrega = 'domicilio',
        ?string $direccion = null,
        ?string $mesa = null,
        ?string $notas = null
    ): int {
        $pdo = Database::conexion();
        $total = array_sum(array_map(fn ($it) => $it['precio'] * $it['cantidad'], $items));

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pedidos (sede_id, cliente_id, total, metodo_pago, tipo_entrega, direccion, mesa, notas, estado)
                 VALUES (:sede_id, :cliente_id, :total, :metodo_pago, :tipo_entrega, :direccion, :mesa, :notas, :pendiente)'
            );
            $stmt->execute([
                'sede_id'      => $sedeId,
                'cliente_id'   => $clienteId,
                'total'        => $total,
                'metodo_pago'  => $metodoPago,
                'tipo_entrega' => $tipoEntrega,
                'direccion'    => $tipoEntrega === 'domicilio' ? $direccion : null,
                'mesa'         => $tipoEntrega === 'mesa' ? $mesa : null,
                'notas'        => $notas !== '' ? $notas : null,
                'pendiente'    => 'pendiente',
            ]);
            $pedidoId = (int) $pdo->lastInsertId();

            $stmtItem = $pdo->prepare(
                'INSERT INTO pedido_items (pedido_id, producto_id, nombre_producto, precio_unitario, cantidad)
                 VALUES (:pedido_id, :producto_id, :nombre, :precio, :cantidad)'
            );
            foreach ($items as $item) {
                $stmtItem->execute([
                    'pedido_id'   => $pedidoId,
                    'producto_id' => $item['producto_id'],
                    'nombre'      => $item['nombre'],
                    'precio'      => $item['precio'],
                    'cantidad'    => $item['cantidad'],
                ]);
            }

            $pdo->commit();
            return $pedidoId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId, int $limite = 50): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT p.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono
             FROM pedidos p
             JOIN clientes c ON c.id = p.cliente_id
             WHERE p.sede_id = :sede_id
             ORDER BY p.creado_en DESC
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
            'SELECT p.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono
             FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.id = :id AND p.sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function items(int $pedidoId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM pedido_items WHERE pedido_id = :pedido_id'
        );
        $stmt->execute(['pedido_id' => $pedidoId]);
        return $stmt->fetchAll();
    }

    public static function actualizarEstado(int $id, int $sedeId, string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return;
        }
        $stmt = Database::conexion()->prepare(
            'UPDATE pedidos SET estado = :estado WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['estado' => $estado, 'id' => $id, 'sede_id' => $sedeId]);
    }

    /** Pedidos creados después de cierto ID, para el polling de notificaciones del panel. */
    public static function nuevosDesde(int $sedeId, int $desdeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT p.id, p.total, p.creado_en, c.nombre AS cliente_nombre
             FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.sede_id = :sede_id AND p.id > :desde_id
             ORDER BY p.id ASC
             LIMIT 20'
        );
        $stmt->execute(['sede_id' => $sedeId, 'desde_id' => $desdeId]);
        return $stmt->fetchAll();
    }

    public static function contarHoy(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM pedidos
             WHERE sede_id = :sede_id AND DATE(creado_en) = CURDATE()'
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return (int) $stmt->fetch()['total'];
    }
}
