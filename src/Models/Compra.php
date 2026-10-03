<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Compra a proveedor (ver database/migrations/2026-10-03_23_compras.sql):
 * la llegada del pedido del distribuidor. Suma inventario y deja como costo
 * del producto el último que se pagó.
 *
 * Cantidad: unidades, o kilos si el producto va por peso (el costo es por
 * kilo y el stock sube en gramos).
 */
class Compra
{
    public const MAX_UNIDADES = 99999;
    public const MAX_KILOS = 9999;

    /**
     * Arma las líneas desde lo que llegó del formulario (producto, cantidad,
     * costo por unidad o kilo), solo con productos de la sede. Si un producto
     * viene dos veces se juntan (con el último costo escrito).
     *
     * @param array<int, array{producto_id:mixed, cantidad:mixed, costo:mixed}> $filas
     * @return array<int, array<string, mixed>>
     */
    public static function armarLineas(int $sedeId, array $filas): array
    {
        $productos = [];
        foreach (Producto::listarPorSede($sedeId) as $p) {
            $productos[(int) $p['id']] = $p;
        }
        $lineas = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['producto_id'] ?? 0);
            $p = $productos[$id] ?? null;
            if ($p === null || $p['combo'] !== []) {
                continue; // un combo no se compra: se compran sus partes
            }
            $porPeso = $p['vende_por'] === 'peso';
            $cantidad = self::cantidadDesdeTexto((string) ($fila['cantidad'] ?? ''), $porPeso);
            $costo = dinero_desde_texto((string) ($fila['costo'] ?? ''));
            if (isset($lineas[$id])) {
                $cantidad += $lineas[$id]['cantidad'];
            }
            $lineas[$id] = [
                'producto_id'    => $id,
                'nombre'         => (string) $p['nombre'],
                'por_peso'       => $porPeso,
                'cantidad'       => $cantidad,
                'costo_unitario' => $costo,
                'subtotal'       => (int) round($costo * $cantidad),
                'stock'          => $p['stock'] !== null ? (int) $p['stock'] : null,
                'precio'         => (int) $p['precio'],
                'codigo'         => $p['codigo_barras'],
            ];
        }

        return array_values($lineas);
    }

    /**
     * Lo escrito en "Unidades" o "Kilos". Por unidad, los puntos son de miles
     * ("1.000" = mil); por peso, la coma o el punto son decimales ("2,5" kg).
     */
    public static function cantidadDesdeTexto(string $texto, bool $porPeso): float
    {
        if ($porPeso) {
            $kilos = (float) str_replace(',', '.', (string) preg_replace('/[^\d,.]/', '', $texto));

            return round(min(self::MAX_KILOS, max(0, $kilos)), 3);
        }

        return (float) min(self::MAX_UNIDADES, (int) preg_replace('/\D+/', '', $texto));
    }

    /**
     * Guarda la compra en una transacción: por cada producto (bloqueado, en
     * orden fijo) suma lo que llegó y actualiza el costo. Si el producto no
     * llevaba inventario (stock NULL), empieza a llevarlo con lo que llegó.
     *
     * @param array<int, array<string, mixed>> $lineas salidas de armarLineas()
     */
    public static function crear(int $sedeId, string $proveedor, array $lineas, ?int $usuarioId, string $token): int
    {
        $proveedor = mb_substr(trim($proveedor), 0, 120);
        $lineas = array_values(array_filter($lineas, fn ($l) => $l['cantidad'] > 0));
        if ($proveedor === '') {
            throw new \DomainException('Escribe de quién es la compra (el proveedor o distribuidor).');
        }
        if ($lineas === []) {
            throw new \DomainException('La compra está vacía: agrega al menos un producto con su cantidad.');
        }
        foreach ($lineas as $linea) {
            if ($linea['costo_unitario'] <= 0) {
                throw new \DomainException("Escribe cuánto te costó {$linea['nombre']}" . ($linea['por_peso'] ? ' (el kilo).' : ' (cada uno).'));
            }
        }
        usort($lineas, fn ($a, $b) => $a['producto_id'] <=> $b['producto_id']);
        $total = array_sum(array_column($lineas, 'subtotal'));

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            try {
                $pdo->prepare('INSERT INTO compras (sede_id, proveedor, total, usuario_id, token) VALUES (:s, :p, :t, :u, :token)')
                    ->execute(['s' => $sedeId, 'p' => $proveedor, 't' => $total, 'u' => $usuarioId, 'token' => $token]);
            } catch (\PDOException $e) {
                if ((string) $e->getCode() === '23000') {
                    throw new \DomainException('Esa compra ya se había guardado (el formulario llegó dos veces).');
                }
                throw $e;
            }
            $compraId = (int) $pdo->lastInsertId();

            $bloquear = $pdo->prepare('SELECT stock, vende_por FROM productos WHERE id = :id AND sede_id = :s FOR UPDATE');
            $actualizar = $pdo->prepare('UPDATE productos SET stock = :stock, costo = :costo WHERE id = :id AND sede_id = :s');
            $item = $pdo->prepare(
                'INSERT INTO compra_items (compra_id, producto_id, nombre, por_peso, cantidad, costo_unitario, subtotal, stock_antes)
                 VALUES (:c, :p, :n, :peso, :cant, :costo, :sub, :antes)'
            );
            foreach ($lineas as $linea) {
                $bloquear->execute(['id' => $linea['producto_id'], 's' => $sedeId]);
                $fila = $bloquear->fetch();
                if ($fila === false) {
                    throw new \DomainException("{$linea['nombre']} ya no existe en esta sede.");
                }
                $porPeso = $fila['vende_por'] === 'peso';
                $entra = $porPeso ? (int) round($linea['cantidad'] * Producto::GRAMOS_POR_KILO) : (int) $linea['cantidad'];
                $antes = $fila['stock'] !== null ? (int) $fila['stock'] : null;
                $actualizar->execute(['stock' => max(0, $antes ?? 0) + $entra, 'costo' => $linea['costo_unitario'], 'id' => $linea['producto_id'], 's' => $sedeId]);
                $item->execute([
                    'c' => $compraId, 'p' => $linea['producto_id'], 'n' => $linea['nombre'], 'peso' => $porPeso ? 1 : 0,
                    'cant' => number_format((float) $linea['cantidad'], 3, '.', ''), 'costo' => $linea['costo_unitario'],
                    'sub' => $linea['subtotal'], 'antes' => $antes,
                ]);
            }
            $pdo->commit();

            return $compraId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function historial(int $sedeId, int $limite = 30): array
    {
        $limite = max(1, min(200, $limite));
        $stmt = Database::conexion()->prepare(
            "SELECT c.*, u.nombre AS usuario_nombre, (SELECT COUNT(*) FROM compra_items i WHERE i.compra_id = c.id) AS lineas
             FROM compras c LEFT JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.sede_id = :s ORDER BY c.id DESC LIMIT {$limite}"
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, u.nombre AS usuario_nombre FROM compras c LEFT JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = :id AND c.sede_id = :s'
        );
        $stmt->execute(['id' => $id, 's' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function items(int $compraId): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM compra_items WHERE compra_id = :c ORDER BY id');
        $stmt->execute(['c' => $compraId]);

        return $stmt->fetchAll();
    }
}
