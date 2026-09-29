<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Producto
{
    private const PALETA = ['#E8452C', '#F2B632', '#5B7F3A', '#1B1A17', '#3B4CCA'];

    public static function crear(int $negocioId, string $nombre, int $precio, string $categoria = 'General'): int
    {
        $pdo = Database::conexion();
        $orden = self::contarPorNegocio($negocioId);
        $color = self::PALETA[$orden % count(self::PALETA)];

        $stmt = $pdo->prepare(
            'INSERT INTO productos (negocio_id, nombre, precio, categoria, color, orden)
             VALUES (:negocio_id, :nombre, :precio, :categoria, :color, :orden)'
        );
        $stmt->execute([
            'negocio_id' => $negocioId,
            'nombre'     => $nombre,
            'precio'     => $precio,
            'categoria'  => $categoria,
            'color'      => $color,
            'orden'      => $orden,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId, bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM productos WHERE negocio_id = :negocio_id';
        if ($soloActivos) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' ORDER BY orden ASC, id ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['negocio_id' => $negocioId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM productos WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);

        return $stmt->fetch() ?: null;
    }

    public static function actualizar(int $id, int $negocioId, string $nombre, int $precio, string $categoria): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET nombre = :nombre, precio = :precio, categoria = :categoria
             WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute([
            'nombre'     => $nombre,
            'precio'     => $precio,
            'categoria'  => $categoria,
            'id'         => $id,
            'negocio_id' => $negocioId,
        ]);
    }

    public static function eliminar(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM productos WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }

    /** Marca/desmarca un producto como agotado sin borrarlo: sigue visible en la tienda pero no se puede agregar al carrito. */
    public static function alternarAgotado(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET agotado = NOT agotado WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }

    public static function contarPorNegocio(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM productos WHERE negocio_id = :negocio_id'
        );
        $stmt->execute(['negocio_id' => $negocioId]);

        return (int) $stmt->fetch()['total'];
    }
}
