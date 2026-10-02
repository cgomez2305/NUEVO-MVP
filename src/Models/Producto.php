<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Producto
{
    private const PALETA = ['#E8452C', '#F2B632', '#5B7F3A', '#1B1A17', '#3B4CCA'];

    /**
     * `agotado` que lee todo el resto del código es el EFECTIVO: marcado a
     * mano, agotado solo por hoy (agotado_hasta, se quita solo mañana) o
     * sin unidades (stock en 0). El valor guardado queda en agotado_fijo y
     * el porqué en motivo_agotado ('fijo' | 'hoy' | 'stock' | null).
     */
    private const COLUMNAS = "p.*, p.agotado AS agotado_fijo,
        CASE WHEN p.agotado = 1 THEN 'fijo'
             WHEN p.agotado_hasta IS NOT NULL AND p.agotado_hasta >= CURDATE() THEN 'hoy'
             WHEN p.stock IS NOT NULL AND p.stock <= 0 THEN 'stock'
             ELSE NULL END AS motivo_agotado,
        (p.agotado = 1 OR (p.agotado_hasta IS NOT NULL AND p.agotado_hasta >= CURDATE())
                       OR (p.stock IS NOT NULL AND p.stock <= 0)) AS agotado";

    /** La misma regla, para filtrar en un WHERE. */
    private const AGOTADO_SQL = "(p.agotado = 1 OR (p.agotado_hasta IS NOT NULL AND p.agotado_hasta >= CURDATE()) OR (p.stock IS NOT NULL AND p.stock <= 0))";

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
        $sql = 'SELECT ' . self::COLUMNAS . ' FROM productos p WHERE sede_id = :sede_id';
        if ($soloActivos) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' ORDER BY orden ASC, id ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['sede_id' => $sedeId]);

        return self::conCombos($stmt->fetchAll(), $sedeId);
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM productos p WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
        $producto = $stmt->fetch();

        return $producto ? self::conCombos([$producto], $sedeId)[0] : null;
    }

    /**
     * Partes de cada combo de la sede: [combo_id => [[id, nombre, cantidad,
     * precio, agotado, stock], ...]].
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function componentesPorCombo(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT ci.combo_id, ci.cantidad, ' . self::COLUMNAS . '
             FROM combo_items ci JOIN productos p ON p.id = ci.producto_id
             WHERE p.sede_id = :sede_id ORDER BY ci.combo_id, p.nombre'
        );
        $stmt->execute(['sede_id' => $sedeId]);
        $porCombo = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porCombo[(int) $fila['combo_id']][] = $fila;
        }

        return $porCombo;
    }

    /**
     * Le pone a cada combo sus partes (`combo`), lo que costarían por
     * separado (`precio_separado`) y la disponibilidad real: agotado si
     * falta cualquier parte; si alguna parte lleva inventario, `stock` del
     * combo son los combos que alcanzan a armarse con lo que hay.
     *
     * @param array<int, array<string, mixed>> $productos
     * @return array<int, array<string, mixed>>
     */
    private static function conCombos(array $productos, int $sedeId): array
    {
        $porCombo = self::componentesPorCombo($sedeId);
        foreach ($productos as &$producto) {
            $partes = $porCombo[(int) $producto['id']] ?? [];
            $producto['combo'] = $partes;
            $producto['precio_separado'] = 0;
            if ($partes === []) {
                continue;
            }
            $alcanzan = null;
            foreach ($partes as $parte) {
                $producto['precio_separado'] += (int) $parte['precio'] * (int) $parte['cantidad'];
                if ($parte['stock'] !== null) {
                    $alcanzan = min($alcanzan ?? PHP_INT_MAX, intdiv(max(0, (int) $parte['stock']), max(1, (int) $parte['cantidad'])));
                }
                if ((int) $parte['agotado'] === 1 && (int) $producto['agotado'] === 0) {
                    $producto['agotado'] = 1;
                    $producto['motivo_agotado'] = $parte['motivo_agotado'] === 'hoy' ? 'hoy' : 'combo';
                }
            }
            if ($alcanzan !== null) {
                $producto['stock'] = $producto['stock'] === null ? $alcanzan : min((int) $producto['stock'], $alcanzan);
                if ($producto['stock'] <= 0 && (int) $producto['agotado'] === 0) {
                    $producto['agotado'] = 1;
                    $producto['motivo_agotado'] = 'combo';
                }
            }
        }
        unset($producto);

        return $productos;
    }

    /**
     * Arma (o desarma, con todo en 0) el combo. Solo con productos de la
     * misma sede que no sean combos ni el combo mismo.
     *
     * @param array<int|string, mixed> $cantidades producto_id => unidades
     */
    public static function guardarComponentes(int $comboId, int $sedeId, array $cantidades): void
    {
        $pdo = Database::conexion();
        $validos = [];
        $porCombo = self::componentesPorCombo($sedeId);
        foreach (self::listarPorSede($sedeId) as $producto) {
            $id = (int) $producto['id'];
            if ($id !== $comboId && !isset($porCombo[$id])) {
                $validos[$id] = true;
            }
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM combo_items WHERE combo_id = :c')->execute(['c' => $comboId]);
            $insertar = $pdo->prepare('INSERT INTO combo_items (combo_id, producto_id, cantidad) VALUES (:c, :p, :n)');
            foreach ($cantidades as $productoId => $cantidad) {
                $cantidad = min(20, max(0, (int) $cantidad));
                if ($cantidad > 0 && isset($validos[(int) $productoId])) {
                    $insertar->execute(['c' => $comboId, 'p' => (int) $productoId, 'n' => $cantidad]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** "Bandeja paisa + 2 Jugo natural" — lo que trae el combo, para la tienda y el WhatsApp. */
    public static function textoCombo(array $producto): string
    {
        return implode(' + ', array_map(
            fn ($parte) => ((int) $parte['cantidad'] > 1 ? $parte['cantidad'] . ' ' : '') . $parte['nombre'],
            $producto['combo'] ?? []
        ));
    }

    /**
     * Lo más pedido de verdad: productos en más pedidos (no cancelados) de
     * los últimos 30 días, con un mínimo para no destacar algo pedido dos
     * veces. Sin datos suficientes, no hay sección (nada inventado).
     *
     * @return array<int, int> producto_id => número de pedidos
     */
    public static function masPedidos(int $sedeId, int $limite = 3, int $minimoPedidos = 3, int $dias = 30): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT i.producto_id, COUNT(DISTINCT i.pedido_id) AS veces
             FROM pedido_items i JOIN pedidos pe ON pe.id = i.pedido_id
             WHERE pe.sede_id = :sede_id AND pe.estado <> 'cancelado'
               AND pe.creado_en >= NOW() - INTERVAL {$dias} DAY AND i.producto_id IS NOT NULL
             GROUP BY i.producto_id HAVING veces >= :minimo
             ORDER BY veces DESC LIMIT {$limite}"
        );
        $stmt->execute(['sede_id' => $sedeId, 'minimo' => $minimoPedidos]);
        $resultado = [];
        foreach ($stmt->fetchAll() as $fila) {
            $resultado[(int) $fila['producto_id']] = (int) $fila['veces'];
        }

        return $resultado;
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

    /**
     * Marca/desmarca un producto como agotado sin borrarlo: sigue visible en
     * la tienda pero no se puede agregar al carrito. "Marcar disponible"
     * quita también el agotado de hoy (el inventario en 0 se corrige
     * editando las unidades, no aquí).
     */
    public static function alternarAgotado(int $id, int $sedeId): void
    {
        $producto = self::buscar($id, $sedeId);
        if ($producto === null) {
            return;
        }
        $agotar = (int) $producto['agotado'] === 0;
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET agotado = :agotado, agotado_hasta = NULL WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['agotado' => $agotar ? 1 : 0, 'id' => $id, 'sede_id' => $sedeId]);
    }

    /** "Se acabó por hoy": mañana vuelve a estar disponible sin que nadie se acuerde de desmarcarlo. */
    public static function agotarPorHoy(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET agotado = 0, agotado_hasta = CURDATE() WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    /** Unidades disponibles; null = no llevar inventario de este producto. */
    public static function establecerStock(int $id, int $sedeId, ?int $stock): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE productos SET stock = :stock WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['stock' => $stock === null ? null : max(0, $stock), 'id' => $id, 'sede_id' => $sedeId]);
    }

    /** Con pocas unidades, la tienda lo dice ("Quedan 3"): es verdad y ayuda a decidir. */
    public const POCAS_UNIDADES = 5;

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
            $condiciones[] = 'NOT ' . self::AGOTADO_SQL;
        } elseif ($disponibilidad === 'agotados') {
            $condiciones[] = self::AGOTADO_SQL;
        }

        $ordenSql = match ($orden) {
            'precio' => 'precio ASC',
            'recientes' => 'creado_en DESC',
            default => 'nombre ASC',
        };

        $stmt = Database::conexion()->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM productos p WHERE ' . implode(' AND ', $condiciones) . ' ORDER BY ' . $ordenSql
        );
        $stmt->execute($params);
        return self::conCombos($stmt->fetchAll(), $sedeId);
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
