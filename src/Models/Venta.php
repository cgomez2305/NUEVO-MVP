<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Venta de mostrador (ver database/migrations/2026-10-03_21_ventas_mostrador.sql):
 * lo que se cobra en el local, con lector, cámara o buscando por nombre.
 * Descuenta el mismo inventario que los pedidos de la tienda en línea, con
 * el mismo cuidado (FOR UPDATE: si no alcanza, no se vende nada).
 *
 * "Cantidad de venta": unidades, o kilos si el producto va por peso.
 */
class Venta
{
    public const METODOS = ['efectivo' => 'Efectivo', 'nequi' => 'Nequi', 'breb' => 'Bre-B', 'fiado' => 'Fiado'];

    /** Topes de una línea: nadie vende 1.000 gaseosas o 50 kilos en el mostrador por error. */
    public const MAX_UNIDADES = 999;
    public const MAX_GRAMOS = 50000;

    /**
     * "¿Con cuánto paga?" no puede pasar del total por más de esto: un código
     * de barras escaneado en ese campo por error (7.702.010.000.010) no se
     * cobra como si el cliente hubiera dado esa plata.
     */
    public const MAX_VUELTAS = 200000;

    /**
     * Arma las líneas del tiquete a partir de producto_id => cantidad de
     * venta, con nombre, precio y costo leídos de la base (nunca del
     * formulario). Ignora productos de otra sede y cantidades en 0 o
     * negativas; recorta lo absurdo a los topes.
     *
     * @param array<int|string, mixed> $cantidades
     * @return array<int, array<string, mixed>>
     */
    public static function armarLineas(int $sedeId, array $cantidades): array
    {
        $limpias = self::cantidadesValidas($cantidades);
        if ($limpias === []) {
            return [];
        }
        $productos = [];
        foreach (Producto::listarPorSede($sedeId) as $p) {
            $productos[(int) $p['id']] = $p;
        }
        $lineas = [];
        foreach ($limpias as $id => $cantidad) {
            $p = $productos[$id] ?? null;
            if ($p === null) {
                continue;
            }
            $porPeso = $p['vende_por'] === 'peso';
            if ($porPeso) {
                $gramos = min(self::MAX_GRAMOS, max(1, (int) round($cantidad * Producto::GRAMOS_POR_KILO)));
                $cantidadVenta = $gramos / Producto::GRAMOS_POR_KILO;
                $subtotal = Producto::precioPorGramos((int) $p['precio'], $gramos);
            } else {
                $gramos = null;
                $cantidadVenta = (float) min(self::MAX_UNIDADES, max(1, (int) round($cantidad)));
                $subtotal = (int) $p['precio'] * (int) $cantidadVenta;
            }
            $lineas[] = [
                'producto_id'     => $id,
                'nombre'          => (string) $p['nombre'],
                'por_peso'        => $porPeso,
                'cantidad'        => $cantidadVenta,
                'gramos'          => $gramos,
                'precio_unitario' => (int) $p['precio'],
                'costo_unitario'  => $p['costo'] !== null ? (int) $p['costo'] : null,
                'subtotal'        => $subtotal,
                'stock'           => $p['stock'] !== null ? (int) $p['stock'] : null,
                'combo'           => Producto::textoCombo($p),
            ];
        }

        return $lineas;
    }

    /**
     * producto_id => cantidad (float), sin basura: ids enteros positivos y
     * cantidades numéricas mayores que 0 ("0,25" con coma también sirve).
     *
     * @param array<int|string, mixed> $cantidades
     * @return array<int, float>
     */
    public static function cantidadesValidas(array $cantidades): array
    {
        $limpias = [];
        foreach ($cantidades as $id => $cantidad) {
            $texto = str_replace(',', '.', trim((string) (is_scalar($cantidad) ? $cantidad : '')));
            if (!ctype_digit((string) $id) || (int) $id <= 0 || !is_numeric($texto) || (float) $texto <= 0) {
                continue;
            }
            $limpias[(int) $id] = (float) $texto;
            if (count($limpias) >= 200) {
                break;
            }
        }

        return $limpias;
    }

    /** Token de un solo uso para el formulario de cobro (evita la venta doble). */
    public static function tokenNuevo(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Registra la venta: valida y descuenta inventario con FOR UPDATE, y si
     * es fiado, revisa el límite del cliente y le carga la cuenta. Todo o
     * nada: si algo no alcanza, lanza \DomainException y no se vende nada.
     * Si el token ya se usó (doble envío), lanza VentaDuplicada con la venta
     * que sí quedó (buscada después del rollback).
     *
     * $clienteNuevo (fiado a alguien que no estaba elegido): nombre,
     * telefono, autorizo, confirmado (id). Se resuelve DENTRO de la
     * transacción: si la venta falla, el cliente tampoco queda creado.
     *
     * @param array<int, array<string, mixed>> $lineas salidas de armarLineas()
     * @param array{nombre:string, telefono:string, autorizo:bool, confirmado:?int}|null $clienteNuevo
     */
    public static function crear(
        int $sedeId,
        int $negocioId,
        array $lineas,
        string $metodo,
        ?int $recibido,
        ?int $clienteId,
        ?int $usuarioId,
        string $token,
        ?array $clienteNuevo = null
    ): int {
        if ($lineas === []) {
            throw new \DomainException('La venta está vacía: agrega al menos un producto.');
        }
        if (!isset(self::METODOS[$metodo])) {
            throw new \DomainException('Elige cómo te pagan.');
        }
        foreach ($lineas as $linea) {
            if ((int) $linea['precio_unitario'] <= 0) {
                throw new \DomainException("«{$linea['nombre']}» no tiene precio: pónselo en Productos antes de venderlo.");
            }
        }
        $total = array_sum(array_column($lineas, 'subtotal'));
        $cambio = null;
        if ($metodo === 'efectivo') {
            $recibido ??= $total;
            if ($recibido < $total) {
                throw new \DomainException('Con ' . pesos($recibido) . ' no alcanza: faltan ' . pesos($total - $recibido) . '.');
            }
            if ($recibido > $total + self::MAX_VUELTAS) {
                throw new \DomainException('"¿Con cuánto paga?" dice ' . pesos($recibido) . ': parece un código escaneado en ese campo. Bórralo y escribe con cuánto paga.');
            }
            $cambio = $recibido - $total;
        } else {
            $recibido = null;
        }
        if ($metodo === 'fiado' && $clienteId === null && $clienteNuevo === null) {
            throw new \DomainException('Para fiar, elige a quién (o crea el cliente).');
        }
        if ($metodo !== 'fiado') {
            $clienteId = null;
            $clienteNuevo = null;
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            if ($clienteNuevo !== null) {
                $clienteId = Fiado::resolverCliente(
                    $pdo, $negocioId, (string) $clienteNuevo['nombre'], (string) $clienteNuevo['telefono'],
                    (bool) $clienteNuevo['autorizo'], $clienteNuevo['confirmado'] ?? null
                )['id'];
            }
            if ($metodo === 'fiado') {
                // El cliente bloqueado: dos ventas fiadas a la vez no pasan
                // juntas por encima del límite.
                $stmt = $pdo->prepare('SELECT id, nombre, fiado_limite FROM clientes WHERE id = :id AND negocio_id = :n FOR UPDATE');
                $stmt->execute(['id' => $clienteId, 'n' => $negocioId]);
                $cliente = $stmt->fetch();
                if ($cliente === false) {
                    throw new \DomainException('Ese cliente no existe en tu negocio.');
                }
                if ($cliente['fiado_limite'] !== null) {
                    $saldo = Fiado::saldo($negocioId, (int) $clienteId);
                    if ($saldo + $total > (int) $cliente['fiado_limite']) {
                        $primerNombre = explode(' ', trim((string) $cliente['nombre']))[0];
                        throw new \DomainException(
                            "No se puede fiar: {$primerNombre} ya debe " . pesos(max(0, $saldo)) . ' y su límite es de ' . pesos((int) $cliente['fiado_limite'])
                            . '; con esta venta quedaría en ' . pesos($saldo + $total) . '. Que abone primero, o cobra esta venta en efectivo, Nequi o Bre-B.'
                        );
                    }
                }
            }

            $cantidades = [];
            foreach ($lineas as $linea) {
                $cantidades[(int) $linea['producto_id']] = ($cantidades[(int) $linea['producto_id']] ?? 0) + (float) $linea['cantidad'];
            }
            $stmtStock = $pdo->prepare('SELECT nombre, stock, vende_por FROM productos WHERE id = :id AND sede_id = :sede FOR UPDATE');
            $stmtDescontar = $pdo->prepare('UPDATE productos SET stock = stock - :cantidad WHERE id = :id AND sede_id = :sede');
            foreach (Producto::demandaDeStock($pdo, $cantidades) as $productoId => $necesario) {
                $stmtStock->execute(['id' => $productoId, 'sede' => $sedeId]);
                $fila = $stmtStock->fetch();
                if ($fila === false || $fila['stock'] === null) {
                    continue;
                }
                if ((int) $fila['stock'] < $necesario) {
                    $porPeso = $fila['vende_por'] === 'peso';
                    $quedan = $porPeso ? Producto::gramosLegibles((int) $fila['stock']) : (string) $fila['stock'];
                    throw new \DomainException((int) $fila['stock'] <= 0
                        ? "No hay {$fila['nombre']} en el inventario. No se vendió nada: quítalo o corrige las unidades en Productos."
                        : "Solo quedan {$quedan} de {$fila['nombre']}. No se vendió nada: ajusta la cantidad y vuelve a cobrar.");
                }
                $stmtDescontar->execute(['cantidad' => $necesario, 'id' => $productoId, 'sede' => $sedeId]);
            }

            try {
                $pdo->prepare(
                    'INSERT INTO ventas (sede_id, total, metodo, recibido, cambio, cliente_id, usuario_id, token)
                     VALUES (:sede, :total, :metodo, :recibido, :cambio, :cliente, :usuario, :token)'
                )->execute([
                    'sede' => $sedeId, 'total' => $total, 'metodo' => $metodo, 'recibido' => $recibido,
                    'cambio' => $cambio, 'cliente' => $clienteId, 'usuario' => $usuarioId, 'token' => $token,
                ]);
            } catch (\PDOException $e) {
                if ((string) $e->getCode() === '23000' && str_contains($e->getMessage(), 'uniq_ventas_token')) {
                    // Se busca después del rollback (abajo): dentro de esta
                    // transacción la lectura no ve la venta que otra petición
                    // confirmó mientras tanto (REPEATABLE READ).
                    throw new VentaDuplicada(null);
                }
                throw $e;
            }
            $ventaId = (int) $pdo->lastInsertId();

            $stmtItem = $pdo->prepare(
                'INSERT INTO venta_items (venta_id, producto_id, nombre, por_peso, cantidad, precio_unitario, costo_unitario, subtotal)
                 VALUES (:venta, :producto, :nombre, :peso, :cantidad, :precio, :costo, :subtotal)'
            );
            foreach ($lineas as $linea) {
                $stmtItem->execute([
                    'venta' => $ventaId, 'producto' => $linea['producto_id'], 'nombre' => $linea['nombre'],
                    'peso' => $linea['por_peso'] ? 1 : 0, 'cantidad' => number_format((float) $linea['cantidad'], 3, '.', ''),
                    'precio' => $linea['precio_unitario'], 'costo' => $linea['costo_unitario'], 'subtotal' => $linea['subtotal'],
                ]);
            }

            if ($metodo === 'fiado') {
                $pdo->prepare(
                    "INSERT INTO fiado_movimientos (negocio_id, sede_id, cliente_id, tipo, monto, venta_id, usuario_id)
                     VALUES (:n, :s, :c, 'cargo', :monto, :v, :u)"
                )->execute(['n' => $negocioId, 's' => $sedeId, 'c' => $clienteId, 'monto' => $total, 'v' => $ventaId, 'u' => $usuarioId]);
            }

            $pdo->commit();

            return $ventaId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($e instanceof VentaDuplicada) {
                throw new VentaDuplicada(self::idPorToken($token, $sedeId));
            }
            throw $e;
        }
    }

    /**
     * ¿Es la misma venta? Mismos productos y mismas cantidades (unidades o
     * kilos, a 3 decimales). Así un doble envío se reconoce y una venta
     * distinta con el mismo total no se pierde.
     *
     * @param array<int|string, mixed> $cantidades producto_id => cantidad enviada
     */
    public static function mismasLineas(int $ventaId, array $cantidades): bool
    {
        $guardadas = [];
        foreach (self::items($ventaId) as $item) {
            $id = (int) ($item['producto_id'] ?? 0);
            $guardadas[$id] = round(($guardadas[$id] ?? 0) + (float) $item['cantidad'], 3);
        }
        $enviadas = [];
        foreach (self::cantidadesValidas($cantidades) as $id => $cantidad) {
            $enviadas[$id] = round($cantidad, 3);
        }
        ksort($guardadas);
        ksort($enviadas);

        return $guardadas === $enviadas;
    }

    private static function idPorToken(string $token, int $sedeId): ?int
    {
        $stmt = Database::conexion()->prepare('SELECT id FROM ventas WHERE token = :t AND sede_id = :s');
        $stmt->execute(['t' => $token, 's' => $sedeId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** La venta que ya se registró con ese token (para el doble envío). */
    public static function buscarPorToken(string $token, int $sedeId): ?array
    {
        $id = preg_match('/^[0-9a-f]{32}$/', $token) === 1 ? self::idPorToken($token, $sedeId) : null;

        return $id !== null ? self::buscar($id, $sedeId) : null;
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT v.*, u.nombre AS usuario_nombre, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono, a.nombre AS anulo_nombre
             FROM ventas v
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN clientes c ON c.id = v.cliente_id
             LEFT JOIN usuarios a ON a.id = v.anulada_por
             WHERE v.id = :id AND v.sede_id = :s'
        );
        $stmt->execute(['id' => $id, 's' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function items(int $ventaId): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM venta_items WHERE venta_id = :v ORDER BY id');
        $stmt->execute(['v' => $ventaId]);

        return $stmt->fetchAll();
    }

    /**
     * Ventas de un día en la sede, la más reciente primero.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function delDia(int $sedeId, string $fecha, int $limite = 60): array
    {
        $limite = max(1, min(500, $limite));
        $stmt = Database::conexion()->prepare(
            "SELECT v.*, c.nombre AS cliente_nombre, u.nombre AS usuario_nombre,
                    (SELECT COUNT(*) FROM venta_items i WHERE i.venta_id = v.id) AS lineas
             FROM ventas v
             LEFT JOIN clientes c ON c.id = v.cliente_id
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.sede_id = :s AND v.creado_en >= :desde AND v.creado_en < :hasta
             ORDER BY v.id DESC LIMIT {$limite}"
        );
        $stmt->execute(['s' => $sedeId, 'desde' => $fecha . ' 00:00:00', 'hasta' => date('Y-m-d', strtotime($fecha . ' +1 day')) . ' 00:00:00']);

        return $stmt->fetchAll();
    }

    /** Solo el día de la venta: anular la de ayer descuadraría un cierre que ya se contó. */
    public static function anulableHoy(array $venta): bool
    {
        return (int) $venta['anulada'] === 0 && substr((string) $venta['creado_en'], 0, 10) === date('Y-m-d');
    }

    /**
     * Anula la venta: devuelve el inventario (solo a los productos que lo
     * llevan) y, si fue fiado, anula el cargo. Una sola vez (FOR UPDATE:
     * dos toques no devuelven dos veces).
     */
    public static function anular(int $id, int $sedeId, ?int $usuarioId): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM ventas WHERE id = :id AND sede_id = :s FOR UPDATE');
            $stmt->execute(['id' => $id, 's' => $sedeId]);
            $venta = $stmt->fetch();
            if ($venta === false) {
                throw new \DomainException('Esa venta no existe en esta sede.');
            }
            if ((int) $venta['anulada'] === 1) {
                throw new \DomainException('Esa venta ya estaba anulada.');
            }
            if (!self::anulableHoy($venta)) {
                throw new \DomainException('Solo se puede anular una venta el mismo día en que se hizo.');
            }

            $cantidades = [];
            $porPeso = [];
            foreach (self::items($id) as $item) {
                if ($item['producto_id'] !== null) {
                    $cantidades[(int) $item['producto_id']] = ($cantidades[(int) $item['producto_id']] ?? 0) + (float) $item['cantidad'];
                    $porPeso[(int) $item['producto_id']] = (int) $item['por_peso'] === 1;
                }
            }
            $devolver = $pdo->prepare('UPDATE productos SET stock = stock + :n WHERE id = :p AND sede_id = :s AND stock IS NOT NULL');
            // En la unidad en que se vendió (por_peso copiado), no en la de hoy.
            foreach (Producto::demandaDeStock($pdo, $cantidades, $porPeso) as $productoId => $cantidad) {
                $devolver->execute(['n' => $cantidad, 'p' => $productoId, 's' => $sedeId]);
            }

            if ($venta['metodo'] === 'fiado') {
                $pdo->prepare("UPDATE fiado_movimientos SET anulado = 1 WHERE venta_id = :v AND tipo = 'cargo'")->execute(['v' => $id]);
            }
            $pdo->prepare('UPDATE ventas SET anulada = 1, anulada_en = NOW(), anulada_por = :u WHERE id = :id')
                ->execute(['u' => $usuarioId, 'id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Para el cierre de caja: ventas no anuladas del rango por método, y
     * cuántas se anularon.
     *
     * @return array{por_metodo: array<string, array{ventas:int, total:int}>, ventas:int, anuladas:int}
     */
    public static function resumenDelRango(int $sedeId, string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT metodo, anulada, COUNT(*) AS ventas, COALESCE(SUM(total), 0) AS total
             FROM ventas WHERE sede_id = :s AND creado_en >= :desde AND creado_en < :hasta
             GROUP BY metodo, anulada'
        );
        $stmt->execute(['s' => $sedeId, 'desde' => $desde, 'hasta' => $hasta]);
        $porMetodo = array_fill_keys(array_keys(self::METODOS), ['ventas' => 0, 'total' => 0]);
        $ventas = 0;
        $anuladas = 0;
        foreach ($stmt->fetchAll() as $fila) {
            if ((int) $fila['anulada'] === 1) {
                $anuladas += (int) $fila['ventas'];
                continue;
            }
            $porMetodo[(string) $fila['metodo']] = ['ventas' => (int) $fila['ventas'], 'total' => (int) $fila['total']];
            $ventas += (int) $fila['ventas'];
        }

        return ['por_metodo' => $porMetodo, 'ventas' => $ventas, 'anuladas' => $anuladas];
    }

    /**
     * "Lo que más te deja": ganancia real de los últimos días por producto,
     * con las ventas de mostrador (costo copiado al vender) y los pedidos
     * entregados (con el costo que tiene hoy el producto: los pedidos no lo
     * guardan). Solo cuenta lo vendido con costo conocido. Vacío si no hay
     * datos suficientes: nada inventado.
     *
     * @return array{filas: array<int, array{producto_id:int, nombre:string, ganancia:int, vendido:int}>, lineas:int}
     */
    public static function loQueMasDeja(int $sedeId, int $dias = 30, int $limite = 5): array
    {
        $desde = date('Y-m-d H:i:s', strtotime("-{$dias} days"));
        $pdo = Database::conexion();
        $porProducto = [];
        $lineas = 0;
        $sumar = static function (array $fila) use (&$porProducto, &$lineas): void {
            $id = (int) $fila['producto_id'];
            $porProducto[$id] ??= ['producto_id' => $id, 'nombre' => (string) $fila['nombre'], 'ganancia' => 0, 'vendido' => 0];
            $porProducto[$id]['ganancia'] += (int) $fila['ganancia'];
            $porProducto[$id]['vendido'] += (int) $fila['vendido'];
            $lineas += (int) $fila['lineas'];
        };

        $stmt = $pdo->prepare(
            'SELECT i.producto_id, p.nombre, COUNT(*) AS lineas, SUM(i.subtotal) AS vendido,
                    ROUND(SUM(CAST(i.subtotal AS SIGNED) - CAST(i.costo_unitario AS SIGNED) * i.cantidad)) AS ganancia
             FROM venta_items i JOIN ventas v ON v.id = i.venta_id JOIN productos p ON p.id = i.producto_id
             WHERE v.sede_id = :s AND v.anulada = 0 AND v.creado_en >= :desde AND i.costo_unitario IS NOT NULL
             GROUP BY i.producto_id, p.nombre'
        );
        $stmt->execute(['s' => $sedeId, 'desde' => $desde]);
        array_map($sumar, $stmt->fetchAll());

        $stmt = $pdo->prepare(
            "SELECT i.producto_id, p.nombre, COUNT(*) AS lineas, SUM(i.precio_unitario * i.cantidad) AS vendido,
                    SUM((CAST(i.precio_unitario AS SIGNED) - CAST(p.costo AS SIGNED)) * i.cantidad) AS ganancia
             FROM pedido_items i JOIN pedidos pe ON pe.id = i.pedido_id JOIN productos p ON p.id = i.producto_id
             WHERE pe.sede_id = :s AND pe.estado = 'entregado' AND pe.creado_en >= :desde AND p.costo IS NOT NULL
             GROUP BY i.producto_id, p.nombre"
        );
        $stmt->execute(['s' => $sedeId, 'desde' => $desde]);
        array_map($sumar, $stmt->fetchAll());

        // Suficiente = al menos 3 productos con costo y 10 renglones vendidos.
        if (count($porProducto) < 3 || $lineas < 10) {
            return ['filas' => [], 'lineas' => $lineas];
        }
        // Solo lo que de verdad deja plata: lo vendido a pérdida no es "lo que más te deja".
        $conGanancia = array_values(array_filter($porProducto, fn ($p) => $p['ganancia'] > 0));
        usort($conGanancia, fn ($a, $b) => $b['ganancia'] <=> $a['ganancia']);

        return ['filas' => array_slice($conGanancia, 0, $limite), 'lineas' => $lineas];
    }
}
