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
            // Un combo descuenta también sus partes (ver demandaDeUnidades).
            $stmtStock = $pdo->prepare('SELECT nombre, stock, vende_por FROM productos WHERE id = :id AND sede_id = :sede FOR UPDATE');
            $stmtDescontar = $pdo->prepare('UPDATE productos SET stock = stock - :cantidad WHERE id = :id');
            $pedidas = [];
            foreach ($items as $item) {
                // Por peso (en línea) se descuenta lo pesado: gramos ÷ 1.000 kilos.
                $pedidas[(int) $item['producto_id']] = ($pedidas[(int) $item['producto_id']] ?? 0)
                    + (!empty($item['gramos']) ? (int) $item['gramos'] / Producto::GRAMOS_POR_KILO : (int) $item['cantidad']);
            }
            $movido = [];
            foreach (self::demandaDeUnidades($pdo, $pedidas) as $productoId => $unidades) {
                $stmtStock->execute(['id' => $productoId, 'sede' => $sedeId]);
                $fila = $stmtStock->fetch();
                if ($fila === false || $fila['stock'] === null) {
                    continue;
                }
                if ((int) $fila['stock'] < $unidades) {
                    // Por peso el stock va en gramos: se dice en kilos o gramos.
                    $quedan = $fila['vende_por'] === 'peso' ? Producto::gramosLegibles((int) $fila['stock']) : (string) $fila['stock'];
                    throw new \DomainException((int) $fila['stock'] <= 0
                        ? "Se acabó {$fila['nombre']} mientras pedías. Revisa tu pedido."
                        : "Solo quedan {$quedan} de {$fila['nombre']}. Ajusta la cantidad y vuelve a enviar.");
                }
                $stmtDescontar->execute(['cantidad' => $unidades, 'id' => $productoId]);
                $movido[$productoId] = $unidades;
            }
            // Lo descontado, tal cual: cancelar devuelve esto (ver actualizarEstado).
            $pdo->prepare('UPDATE pedidos SET inventario_movido = :m WHERE id = :id')
                ->execute(['m' => json_encode($movido, JSON_FORCE_OBJECT), 'id' => $pedidoId]);

            // por_peso se copia: cancelar devuelve en la unidad en que se pidió.
            $stmtPeso = $pdo->prepare("SELECT vende_por = 'peso' FROM productos WHERE id = :id AND sede_id = :sede");
            $stmtItem = $pdo->prepare(
                'INSERT INTO pedido_items (pedido_id, producto_id, nombre_producto, precio_unitario, cantidad, por_peso, gramos)
                 VALUES (:pedido_id, :producto_id, :nombre, :precio, :cantidad, :por_peso, :gramos)'
            );
            foreach ($items as $item) {
                $stmtPeso->execute(['id' => $item['producto_id'], 'sede' => $sedeId]);
                $stmtItem->execute([
                    'pedido_id'   => $pedidoId,
                    'producto_id' => $item['producto_id'],
                    'nombre'      => $item['nombre'],
                    'precio'      => $item['precio'],
                    'cantidad'    => $item['cantidad'],
                    'por_peso'    => (int) $stmtPeso->fetchColumn() === 1 || !empty($item['gramos']) ? 1 : 0,
                    'gramos'      => !empty($item['gramos']) ? (int) $item['gramos'] : null,
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
            // Lo "vendido" no incluye los cancelados (sí se cuentan en el total de pedidos).
            "SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN p.estado <> 'cancelado' THEN p.total ELSE 0 END), 0) AS suma
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

    /**
     * El último pedido (no cancelado) del cliente en esta sede, con sus
     * renglones, para "pedir lo mismo". null si nunca ha pedido aquí.
     *
     * @return array{pedido: array<string, mixed>, items: array<int, array<string, mixed>>}|null
     */
    public static function ultimoParaRepetir(int $clienteId, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT * FROM pedidos WHERE cliente_id = :c AND sede_id = :s AND estado <> 'cancelado'
             ORDER BY creado_en DESC, id DESC LIMIT 1"
        );
        $stmt->execute(['c' => $clienteId, 's' => $sedeId]);
        $pedido = $stmt->fetch();
        if ($pedido === false) {
            return null;
        }

        return ['pedido' => $pedido, 'items' => self::items((int) $pedido['id'])];
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
            $stmt = $pdo->prepare('SELECT estado, inventario_movido FROM pedidos WHERE id = :id AND sede_id = :sede_id FOR UPDATE');
            $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
            $pedido = $stmt->fetch();
            if ($pedido === false) {
                $pdo->rollBack();
                return;
            }
            $antes = $pedido['estado'];
            $pdo->prepare('UPDATE pedidos SET estado = :estado WHERE id = :id AND sede_id = :sede_id')
                ->execute(['estado' => $estado, 'id' => $id, 'sede_id' => $sedeId]);
            // Cancelar devuelve las unidades al inventario (y "descancelar"
            // las vuelve a tomar): el stock siempre cuadra con lo vendido.
            $signo = match (true) {
                $antes !== 'cancelado' && $estado === 'cancelado' => '+',
                $antes === 'cancelado' && $estado !== 'cancelado' => '-',
                default => null,
            };
            $movido = $pedido['inventario_movido'] !== null ? json_decode((string) $pedido['inventario_movido'], true) : null;
            if ($signo !== null && is_array($movido)) {
                // Exactamente lo que este pedido descontó al crearse.
                ksort($movido);
                $bloquear = $pdo->prepare('SELECT nombre, stock, vende_por FROM productos WHERE id = :p FOR UPDATE');
                $ajustar = $pdo->prepare("UPDATE productos SET stock = stock {$signo} :n WHERE id = :p AND stock IS NOT NULL");
                foreach ($movido as $productoId => $unidades) {
                    if ($signo === '-') {
                        // Reabrir un pedido cancelado vuelve a tomar inventario:
                        // solo si todavía alcanza (no se deja el stock en negativo).
                        $bloquear->execute(['p' => (int) $productoId]);
                        $fila = $bloquear->fetch();
                        if ($fila !== false && $fila['stock'] !== null && (int) $fila['stock'] < (int) $unidades) {
                            $quedan = $fila['vende_por'] === 'peso' ? Producto::gramosLegibles((int) $fila['stock']) : (string) $fila['stock'];
                            throw new \DomainException("No se puede reabrir: de {$fila['nombre']} quedan {$quedan} y este pedido necesita más.");
                        }
                    }
                    $ajustar->execute(['n' => (int) $unidades, 'p' => (int) $productoId]);
                }
            } elseif ($signo !== null) {
                // Pedidos de antes de guardar lo movido: se calcula como entonces.
                $stmtItems = $pdo->prepare('SELECT producto_id, cantidad, por_peso, gramos FROM pedido_items WHERE pedido_id = :id AND producto_id IS NOT NULL');
                $stmtItems->execute(['id' => $id]);
                $pedidas = [];
                $porPeso = [];
                foreach ($stmtItems->fetchAll() as $item) {
                    // Con gramos (pedido en libras) se devuelve lo pesado; sin, cantidad = kilos o unidades.
                    $pedidas[(int) $item['producto_id']] = ($pedidas[(int) $item['producto_id']] ?? 0)
                        + ($item['gramos'] !== null ? (int) $item['gramos'] / Producto::GRAMOS_POR_KILO : (int) $item['cantidad']);
                    $porPeso[(int) $item['producto_id']] = (int) $item['por_peso'] === 1;
                }
                $ajustar = $pdo->prepare("UPDATE productos SET stock = stock {$signo} :n WHERE id = :p AND stock IS NOT NULL");
                // En la unidad en que se pidió (por_peso copiado), no en la de hoy.
                foreach (Producto::demandaDeStock($pdo, $pedidas, $porPeso) as $productoId => $unidades) {
                    $ajustar->execute(['n' => $unidades, 'p' => $productoId]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Unidades que mueve un pedido por producto: lo pedido directo más, por
     * cada combo, sus partes × cantidad. Así "2 Almuerzos" descuenta 2
     * bandejas y 2 jugos aunque la bandeja también se pidiera suelta.
     * Un producto por peso se pide por kilos en la tienda (1 = 1 kg) y su
     * stock va en gramos: la conversión (y el orden fijo de bloqueo, sin
     * interbloqueos) la hace Producto::demandaDeStock, que también usa la
     * venta de mostrador.
     *
     * @param array<int, int> $pedidas producto_id => cantidad
     * @return array<int, int>
     */
    private static function demandaDeUnidades(\PDO $pdo, array $pedidas): array
    {
        return Producto::demandaDeStock($pdo, $pedidas);
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
            "SELECT COUNT(*) AS total FROM pedidos
             WHERE sede_id = :sede_id AND DATE(creado_en) = CURDATE() AND estado <> 'cancelado'"
        );
        $stmt->execute(['sede_id' => $sedeId]);
        return (int) $stmt->fetch()['total'];
    }

    /** Suma de pedidos de hoy, sin contar los cancelados (no son venta real). */
    /**
     * Lo vendido hoy: pedidos sin cancelar más las ventas de mostrador sin
     * anular (también las fiadas: se vendieron, aunque la plata llegue
     * después). El mostrador no cuenta en el límite del plan Gratis, pero sí
     * es venta del día.
     */
    public static function ventasHoy(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            "SELECT (SELECT COALESCE(SUM(total), 0) FROM pedidos
                     WHERE sede_id = :sede_id AND DATE(creado_en) = CURDATE() AND estado != 'cancelado')
                  + (SELECT COALESCE(SUM(total), 0) FROM ventas
                     WHERE sede_id = :sede_id2 AND DATE(creado_en) = CURDATE() AND anulada = 0) AS total"
        );
        $stmt->execute(['sede_id' => $sedeId, 'sede_id2' => $sedeId]);
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
