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
     * $ajustes: lo que cambia el total además de los productos —
     *   descuento (pesos, por cupón y/o premio de fidelidad) y cupon_codigo.
     *   costo_domicilio y zona_domicilio (copiados de la zona elegida).
     * total = suma de los productos − descuento + domicilio: lo que paga el cliente.
     *
     * @param array<int, array{producto_id:int, nombre:string, precio:int, cantidad:int}> $items
     * @param array{descuento?:int, cupon_codigo?:?string, costo_domicilio?:int, zona_domicilio?:?string} $ajustes
     */
    public static function crear(
        int $sedeId,
        int $clienteId,
        string $metodoPago,
        array $items,
        string $tipoEntrega = 'domicilio',
        ?string $direccion = null,
        ?string $mesa = null,
        ?string $notas = null,
        array $ajustes = []
    ): int {
        $pdo = Database::conexion();
        $subtotal = array_sum(array_map(fn ($it) => $it['precio'] * $it['cantidad'], $items));
        $descuento = max(0, min($subtotal, (int) ($ajustes['descuento'] ?? 0)));
        $domicilio = $tipoEntrega === 'domicilio' ? max(0, (int) ($ajustes['costo_domicilio'] ?? 0)) : 0;
        $total = $subtotal - $descuento + $domicilio;

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pedidos (sede_id, cliente_id, total, descuento, cupon_codigo, costo_domicilio, zona_domicilio, metodo_pago, tipo_entrega, direccion, mesa, notas, estado)
                 VALUES (:sede_id, :cliente_id, :total, :descuento, :cupon_codigo, :costo_domicilio, :zona_domicilio, :metodo_pago, :tipo_entrega, :direccion, :mesa, :notas, :pendiente)'
            );
            $stmt->execute([
                'sede_id'      => $sedeId,
                'cliente_id'   => $clienteId,
                'total'        => $total,
                'descuento'    => $descuento,
                'cupon_codigo' => $ajustes['cupon_codigo'] ?? null,
                'costo_domicilio' => $domicilio,
                'zona_domicilio'  => $tipoEntrega === 'domicilio' ? ($ajustes['zona_domicilio'] ?? null) : null,
                'metodo_pago'  => $metodoPago,
                'tipo_entrega' => $tipoEntrega,
                'direccion'    => $tipoEntrega === 'domicilio' ? $direccion : null,
                'mesa'         => $tipoEntrega === 'mesa' ? $mesa : null,
                'notas'        => $notas !== '' ? $notas : null,
                'pendiente'    => 'pendiente',
            ]);
            $pedidoId = (int) $pdo->lastInsertId();

            // Inventario: se bloquea la fila de cada producto con stock y se
            // descuenta dentro de la misma transacción, así dos clientes a
            // la vez no se llevan la última unidad los dos.
            $stmtStock = $pdo->prepare('SELECT nombre, stock FROM productos WHERE id = :id AND sede_id = :sede FOR UPDATE');
            $stmtDescontar = $pdo->prepare('UPDATE productos SET stock = stock - :cantidad WHERE id = :id');
            foreach ($items as $item) {
                $stmtStock->execute(['id' => $item['producto_id'], 'sede' => $sedeId]);
                $fila = $stmtStock->fetch();
                if ($fila === false || $fila['stock'] === null) {
                    continue;
                }
                if ((int) $fila['stock'] < (int) $item['cantidad']) {
                    throw new \DomainException((int) $fila['stock'] <= 0
                        ? "Se acabó {$fila['nombre']} mientras pedías. Lo quitamos: revisa tu pedido."
                        : "Solo quedan {$fila['stock']} de {$fila['nombre']}. Ajusta la cantidad y vuelve a enviar.");
                }
                $stmtDescontar->execute(['cantidad' => $item['cantidad'], 'id' => $item['producto_id']]);
            }

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

    /**
     * Pedidos "en vuelo" (confirmado → en camino) para el tablero. A
     * diferencia del historial completo, esto nunca debería crecer sin
     * límite — un negocio real tiene un número acotado de pedidos abiertos
     * a la vez — así que no hace falta paginar.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarActivosPorSede(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT p.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono
             FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.sede_id = :sede_id
               AND p.estado IN ('pendiente','pagado','en_cocina','listo','en_camino')
             ORDER BY p.creado_en DESC"
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return $stmt->fetchAll();
    }

    /** Cuántos pedidos hay de cada estado, sobre TODO el historial (no solo lo que se está viendo). */
    public static function conteosPorEstado(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT estado, COUNT(*) AS total FROM pedidos WHERE sede_id = :sede_id GROUP BY estado'
        );
        $stmt->execute(['sede_id' => $sedeId]);
        $conteos = [];
        foreach ($stmt->fetchAll() as $fila) {
            $conteos[$fila['estado']] = (int) $fila['total'];
        }
        return $conteos;
    }

    /**
     * Historial filtrado + paginado: la base del listado de abajo del
     * tablero. $porPagina en null trae todo sin límite (lo usa el CSV,
     * que debe exportar exactamente lo que el filtro actual muestra).
     *
     * @return array{filas: array<int, array<string, mixed>>, total: int, suma: int}
     */
    public static function buscarPorSede(
        int $sedeId,
        string $estado = '',
        string $busqueda = '',
        ?string $desde = null,
        ?string $hasta = null,
        int $pagina = 1,
        ?int $porPagina = 20
    ): array {
        $condiciones = ['p.sede_id = :sede_id'];
        $params = ['sede_id' => $sedeId];

        if ($estado !== '' && in_array($estado, self::ESTADOS, true)) {
            $condiciones[] = 'p.estado = :estado';
            $params['estado'] = $estado;
        }
        if ($busqueda !== '') {
            $condiciones[] = '(c.nombre LIKE :busqueda OR p.id = :busqueda_id)';
            $params['busqueda'] = '%' . $busqueda . '%';
            $params['busqueda_id'] = ctype_digit($busqueda) ? (int) $busqueda : 0;
        }
        if ($desde !== null) {
            $condiciones[] = 'p.creado_en >= :desde';
            $params['desde'] = $desde;
        }
        if ($hasta !== null) {
            $condiciones[] = 'p.creado_en <= :hasta';
            $params['hasta'] = $hasta;
        }

        $where = implode(' AND ', $condiciones);
        $pdo = Database::conexion();

        $stmtTotales = $pdo->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(p.total), 0) AS suma
             FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
             WHERE {$where}"
        );
        $stmtTotales->execute($params);
        $totales = $stmtTotales->fetch();

        $sql = "SELECT p.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono
                FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
                WHERE {$where}
                ORDER BY p.creado_en DESC";
        if ($porPagina !== null) {
            $sql .= ' LIMIT :limite OFFSET :offset';
        }
        $stmt = $pdo->prepare($sql);
        foreach ($params as $llave => $valor) {
            $stmt->bindValue($llave, $valor);
        }
        if ($porPagina !== null) {
            $stmt->bindValue('limite', $porPagina, \PDO::PARAM_INT);
            $stmt->bindValue('offset', max(0, ($pagina - 1) * $porPagina), \PDO::PARAM_INT);
        }
        $stmt->execute();

        return [
            'filas' => $stmt->fetchAll(),
            'total' => (int) $totales['total'],
            'suma'  => (int) $totales['suma'],
        ];
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
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id = :id AND sede_id = :sede_id FOR UPDATE');
            $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
            $antes = $stmt->fetchColumn();
            if ($antes === false) {
                $pdo->rollBack();
                return;
            }
            $pdo->prepare('UPDATE pedidos SET estado = :estado WHERE id = :id AND sede_id = :sede_id')
                ->execute(['estado' => $estado, 'id' => $id, 'sede_id' => $sedeId]);
            // Cancelar devuelve las unidades al inventario (y "descancelar"
            // las vuelve a tomar): el stock siempre cuadra con lo vendido.
            $signo = match (true) {
                $antes !== 'cancelado' && $estado === 'cancelado' => '+',
                $antes === 'cancelado' && $estado !== 'cancelado' => '-',
                default => null,
            };
            if ($signo !== null) {
                $pdo->prepare(
                    "UPDATE productos p JOIN pedido_items i ON i.producto_id = p.id
                     SET p.stock = p.stock {$signo} i.cantidad
                     WHERE i.pedido_id = :id AND p.stock IS NOT NULL"
                )->execute(['id' => $id]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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

    /**
     * Pedidos creados este mes calendario, sumados entre todas las sedes
     * del negocio (el límite del plan es por negocio, no por sede — ver
     * planes.limite_pedidos_mes). Cuenta todos los estados, cancelados
     * incluidos: son pedidos que de todas formas consumieron el flujo.
     */
    public static function contarEsteMesPorNegocio(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM pedidos p
             JOIN sedes s ON s.id = p.sede_id
             WHERE s.negocio_id = :negocio_id AND p.creado_en >= :desde'
        );
        $stmt->execute([
            'negocio_id' => $negocioId,
            'desde'      => (new \DateTimeImmutable('first day of this month midnight'))->format('Y-m-d H:i:s'),
        ]);
        return (int) $stmt->fetch()['total'];
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

    /** Suma de pedidos de hoy, sin contar los cancelados (no son venta real). */
    public static function ventasHoy(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            "SELECT COALESCE(SUM(total), 0) AS total FROM pedidos
             WHERE sede_id = :sede_id AND DATE(creado_en) = CURDATE() AND estado != 'cancelado'"
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Resumen de los últimos 7 días para el dashboard: pedidos, lo vendido
     * (sin cancelados) y cuántos de esos clientes ya habían pedido antes
     * (recurrentes, no nuevos) — todo en una sola consulta con subquery en
     * vez de traer los pedidos a PHP para contarlos.
     *
     * @return array{pedidos: int, ventas: int, recurrentes: int}
     */
    public static function resumenSemana(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT
                COUNT(*) AS pedidos,
                COALESCE(SUM(p.total), 0) AS ventas,
                COUNT(DISTINCT CASE WHEN historico.total_compras >= 2 THEN p.cliente_id END) AS recurrentes
             FROM pedidos p
             JOIN (SELECT cliente_id, COUNT(*) AS total_compras FROM pedidos WHERE sede_id = :sede_id_h GROUP BY cliente_id) historico
               ON historico.cliente_id = p.cliente_id
             WHERE p.sede_id = :sede_id AND p.creado_en >= :desde AND p.estado != 'cancelado'"
        );
        $stmt->execute([
            'sede_id_h' => $sedeId,
            'sede_id'   => $sedeId,
            'desde'     => (new \DateTimeImmutable('-6 days midnight'))->format('Y-m-d H:i:s'),
        ]);
        $fila = $stmt->fetch();

        return [
            'pedidos'     => (int) $fila['pedidos'],
            'ventas'      => (int) $fila['ventas'],
            'recurrentes' => (int) $fila['recurrentes'],
        ];
    }
}
