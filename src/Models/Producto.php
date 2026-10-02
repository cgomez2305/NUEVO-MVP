<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Producto
{
    private const PALETA = ['#E8452C', '#F2B632', '#5B7F3A', '#1B1A17', '#3B4CCA'];

    public static function crear(
        int $sedeId,
        string $nombre,
        int $precio,
        string $categoria = 'General',
        ?string $descripcion = null
    ): int {
        $pdo = Database::conexion();
        $orden = self::contarPorSede($sedeId);
        $color = self::PALETA[$orden % count(self::PALETA)];

        $stmt = $pdo->prepare(
            'INSERT INTO productos (sede_id, nombre, precio, categoria, descripcion, color, orden)
             VALUES (:sede_id, :nombre, :precio, :categoria, :descripcion, :color, :orden)'
        );
        $stmt->execute([
            'sede_id'     => $sedeId,
            'nombre'      => $nombre,
            'precio'      => $precio,
            'categoria'   => $categoria,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'color'       => $color,
            'orden'       => $orden,
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

    public static function actualizar(
        int $id,
        int $sedeId,
        string $nombre,
        int $precio,
        string $categoria,
        ?string $descripcion = null
    ): void {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET nombre = :nombre, precio = :precio, categoria = :categoria, descripcion = :descripcion
             WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute([
            'nombre'      => $nombre,
            'precio'      => $precio,
            'categoria'   => $categoria,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'id'          => $id,
            'sede_id'     => $sedeId,
        ]);
    }

    public static function actualizarImagen(int $id, int $sedeId, string $rutaImagen): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET imagen = :imagen WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['imagen' => $rutaImagen, 'id' => $id, 'sede_id' => $sedeId]);
    }

    /** Categorías ya usadas por esta sede, para sugerir con datalist y evitar duplicados como "Bebida" y "Bebidas". */
    public static function categoriasPorSede(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT DISTINCT categoria FROM productos WHERE sede_id = :sede_id ORDER BY categoria ASC'
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return array_column($stmt->fetchAll(), 'categoria');
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

    /**
     * Distinto de "agotado": esto lo saca por completo de la tienda pública
     * (TiendaController ya filtra por activo=1), para cuando el dueño
     * quiere preparar o pausar un producto sin que el cliente lo vea
     * todavía. La columna ya existía en el esquema desde el principio —
     * solo le faltaba un botón en el panel para usarla.
     */
    public static function alternarActivo(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET activo = NOT activo WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    /** A diferencia de alternar*, estos fijan el valor exacto — para cuando vienen como checkbox de un formulario, no de un botón de "cambiar". */
    public static function establecerAgotado(int $id, int $sedeId, bool $agotado): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET agotado = :agotado WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['agotado' => $agotado ? 1 : 0, 'id' => $id, 'sede_id' => $sedeId]);
    }

    public static function establecerActivo(int $id, int $sedeId, bool $activo): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET activo = :activo WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['activo' => $activo ? 1 : 0, 'id' => $id, 'sede_id' => $sedeId]);
    }

    public static function eliminarImagen(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET imagen = NULL WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    /**
     * Catálogo del panel: búsqueda + filtro de categoría/disponibilidad +
     * orden. A diferencia del historial de pedidos, un catálogo de
     * productos rara vez pasa de unos pocos cientos de filas, así que no
     * hace falta paginar — pero sí conviene que el filtrado sea en SQL,
     * no trayendo todo y recortando en PHP.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buscarPorSede(
        int $sedeId,
        string $busqueda = '',
        string $categoria = '',
        string $disponibilidad = '',
        string $orden = 'nombre'
    ): array {
        $condiciones = ['sede_id = :sede_id'];
        $params = ['sede_id' => $sedeId];

        if ($busqueda !== '') {
            $condiciones[] = 'nombre LIKE :busqueda';
            $params['busqueda'] = '%' . $busqueda . '%';
        }
        if ($categoria !== '') {
            $condiciones[] = 'categoria = :categoria';
            $params['categoria'] = $categoria;
        }
        if ($disponibilidad === 'disponibles') {
            $condiciones[] = 'agotado = 0';
        } elseif ($disponibilidad === 'agotados') {
            $condiciones[] = 'agotado = 1';
        }

        $ordenSql = match ($orden) {
            'precio' => 'precio ASC',
            'recientes' => 'creado_en DESC',
            default => 'nombre ASC',
        };

        $stmt = Database::conexion()->prepare(
            'SELECT * FROM productos WHERE ' . implode(' AND ', $condiciones) . ' ORDER BY ' . $ordenSql
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Cuántos productos de la sede no tienen precio todavía: la lectura con IA
     * los deja en 0 cuando el precio no se veía en la foto, y así no se
     * puede abrir (se venderían gratis).
     */
    public static function contarSinPrecio(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM productos WHERE sede_id = :sede_id AND precio = 0'
        );
        $stmt->execute(['sede_id' => $sedeId]);

        return (int) $stmt->fetch()['total'];
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
