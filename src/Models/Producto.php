<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Producto
{
    private const PALETA = ['#E8452C', '#F2B632', '#5B7F3A', '#1B1A17', '#3B4CCA'];

    public static function crear(int $sedeId, string $nombre, int $precio, string $categoria = 'General'): int
    {
        $pdo = Database::conexion();
        $orden = self::contarPorSede($sedeId);
        $color = self::PALETA[$orden % count(self::PALETA)];

        $stmt = $pdo->prepare(
            'INSERT INTO productos (sede_id, nombre, precio, categoria, color, orden)
             VALUES (:sede_id, :nombre, :precio, :categoria, :color, :orden)'
        );
        $stmt->execute([
            'sede_id' => $sedeId,
            'nombre'     => $nombre,
            'precio'     => $precio,
            'categoria'  => $categoria,
            'color'      => $color,
            'orden'      => $orden,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId, bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM productos WHERE sede_id = :sede_id';
        if ($soloActivos) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' ORDER BY orden ASC, id ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['sede_id' => $sedeId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM productos WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    public static function actualizar(int $id, int $sedeId, string $nombre, int $precio, string $categoria): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET nombre = :nombre, precio = :precio, categoria = :categoria
             WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute([
            'nombre'     => $nombre,
            'precio'     => $precio,
            'categoria'  => $categoria,
            'id'         => $id,
            'sede_id' => $sedeId,
        ]);
    }

    public static function eliminar(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM productos WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    /** Marca/desmarca un producto como agotado sin borrarlo: sigue visible en la tienda pero no se puede agregar al carrito. */
    public static function alternarAgotado(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET agotado = NOT agotado WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    public static function contarPorSede(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM productos WHERE sede_id = :sede_id'
        );
        $stmt->execute(['sede_id' => $sedeId]);

        return (int) $stmt->fetch()['total'];
    }
}
