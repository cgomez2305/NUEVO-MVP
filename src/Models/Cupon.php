<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Cupones de descuento del negocio (ver database/migrations/
 * 2026-10-03_01_cupones.sql). Se validan dos veces: al aplicarlos en el
 * carrito (lo general: que exista, esté activo, no haya vencido, alcance el
 * mínimo) y otra vez al crear el pedido o la cita, ya con el cliente
 * conocido (lo personal: que sea suyo y que no lo haya usado antes).
 */
class Cupon
{
    /** Días que dura un cupón personal del copiloto. */
    public const DIAS_COPILOTO = 15;

    /** @param array<string, mixed> $datos */
    public static function crear(int $negocioId, array $datos): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO cupones (negocio_id, codigo, tipo, valor, minimo_compra, vence_en, usos_maximos, una_vez_por_cliente, cliente_id, origen)
             VALUES (:negocio_id, :codigo, :tipo, :valor, :minimo, :vence, :usos, :una_vez, :cliente_id, :origen)'
        );
        $stmt->execute([
            'negocio_id' => $negocioId,
            'codigo'     => self::normalizarCodigo((string) $datos['codigo']),
            'tipo'       => $datos['tipo'] === 'monto' ? 'monto' : 'porcentaje',
            'valor'      => (int) $datos['valor'],
            'minimo'     => (int) ($datos['minimo_compra'] ?? 0),
            'vence'      => $datos['vence_en'] ?? null,
            'usos'       => $datos['usos_maximos'] ?? null,
            'una_vez'    => !empty($datos['una_vez_por_cliente']) ? 1 : 0,
            'cliente_id' => $datos['cliente_id'] ?? null,
            'origen'     => ($datos['origen'] ?? 'panel') === 'copiloto' ? 'copiloto' : 'panel',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Mayúsculas, sin espacios ni símbolos raros: "veci 10" → "VECI10". */
    public static function normalizarCodigo(string $codigo): string
    {
        return mb_substr(preg_replace('/[^A-Z0-9-]/', '', strtoupper(trim($codigo))) ?? '', 0, 20);
    }

    /** Código que no se presta a confusión al dictarlo (sin 0/O, 1/I). */
    public static function codigoAleatorio(string $prefijo = ''): string
    {
        $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';
        for ($i = 0; $i < 4; $i++) {
            $codigo .= $letras[random_int(0, strlen($letras) - 1)];
        }

        return $prefijo !== '' ? $prefijo . '-' . $codigo : $codigo;
    }

    public static function existeCodigo(int $negocioId, string $codigo): bool
    {
        $stmt = Database::conexion()->prepare('SELECT 1 FROM cupones WHERE negocio_id = :n AND codigo = :c');
        $stmt->execute(['n' => $negocioId, 'c' => self::normalizarCodigo($codigo)]);

        return $stmt->fetchColumn() !== false;
    }

    public static function buscarPorCodigo(int $negocioId, string $codigo): ?array
    {
        $codigo = self::normalizarCodigo($codigo);
        if ($codigo === '') {
            return null;
        }
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM cupon_usos u WHERE u.cupon_id = c.id) AS usos
             FROM cupones c WHERE c.negocio_id = :n AND c.codigo = :c'
        );
        $stmt->execute(['n' => $negocioId, 'c' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    public static function buscar(int $id, int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM cupon_usos u WHERE u.cupon_id = c.id) AS usos
             FROM cupones c WHERE c.id = :id AND c.negocio_id = :n'
        );
        $stmt->execute(['id' => $id, 'n' => $negocioId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Cupones del negocio con cuántas veces se usaron y cuánto descontaron.
     * Los personales del copiloto van aparte ($origen) para no llenar la
     * lista principal con un código por cliente.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarPorNegocio(int $negocioId, string $origen = 'panel'): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT c.*, cl.nombre AS cliente_nombre,
                    (SELECT COUNT(*) FROM cupon_usos u WHERE u.cupon_id = c.id) AS usos,
                    (SELECT COALESCE(SUM(u.descuento), 0) FROM cupon_usos u WHERE u.cupon_id = c.id) AS descontado
             FROM cupones c
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.negocio_id = :n AND c.origen = :origen
             ORDER BY c.activo DESC, c.creado_en DESC'
        );
        $stmt->execute(['n' => $negocioId, 'origen' => $origen]);

        return $stmt->fetchAll();
    }

    /** 'activo' | 'pausado' | 'vencido' | 'agotado' — para el chip de la lista. */
    public static function estado(array $cupon): string
    {
        if ((int) $cupon['activo'] !== 1) {
            return 'pausado';
        }
        if (!empty($cupon['vence_en']) && $cupon['vence_en'] < date('Y-m-d')) {
            return 'vencido';
        }
        if ($cupon['usos_maximos'] !== null && (int) ($cupon['usos'] ?? 0) >= (int) $cupon['usos_maximos']) {
            return 'agotado';
        }

        return 'activo';
    }

    /** "10%" o "$5.000". */
    public static function etiqueta(array $cupon): string
    {
        return $cupon['tipo'] === 'monto' ? pesos((int) $cupon['valor']) : (int) $cupon['valor'] . '%';
    }

    /** Pesos que descuenta sobre un subtotal (redondeado a $100, nunca más que el subtotal). */
    public static function calcularDescuento(array $cupon, int $subtotal): int
    {
        $descuento = $cupon['tipo'] === 'monto'
            ? (int) $cupon['valor']
            : (int) (floor($subtotal * (int) $cupon['valor'] / 100 / 100) * 100);

        return max(0, min($subtotal, $descuento));
    }

    /**
     * Revisa si el cupón sirve para esta compra. Con $clienteId (al crear el
     * pedido o la cita) revisa también lo personal. El mensaje se le muestra
     * tal cual al cliente.
     *
     * @return array{ok: bool, descuento: int, mensaje: string}
     */
    public static function evaluar(?array $cupon, int $subtotal, ?int $clienteId = null): array
    {
        $no = fn (string $m) => ['ok' => false, 'descuento' => 0, 'mensaje' => $m];

        if ($cupon === null) {
            return $no('Ese código no existe. Revisa que esté bien escrito.');
        }
        $estado = self::estado($cupon);
        if ($estado === 'pausado') {
            return $no('Ese cupón ya no está disponible.');
        }
        if ($estado === 'vencido') {
            return $no('Ese cupón venció el ' . fecha_larga((string) $cupon['vence_en']) . '.');
        }
        if ($estado === 'agotado') {
            return $no('Ese cupón ya se usó todas las veces que se podía.');
        }
        if ($subtotal < (int) $cupon['minimo_compra']) {
            return $no('Ese cupón es para compras desde ' . pesos((int) $cupon['minimo_compra']) . '. Te faltan ' . pesos((int) $cupon['minimo_compra'] - $subtotal) . '.');
        }
        if ($clienteId !== null) {
            if ($cupon['cliente_id'] !== null && (int) $cupon['cliente_id'] !== $clienteId) {
                return $no('Ese cupón es personal y está a nombre de otro número de WhatsApp.');
            }
            if ((int) $cupon['una_vez_por_cliente'] === 1 && self::usosDelCliente((int) $cupon['id'], $clienteId) > 0) {
                return $no('Ya usaste ese cupón antes: es de un solo uso por persona.');
            }
        }

        return ['ok' => true, 'descuento' => self::calcularDescuento($cupon, $subtotal), 'mensaje' => ''];
    }

    public static function usosDelCliente(int $cuponId, int $clienteId): int
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM cupon_usos WHERE cupon_id = :c AND cliente_id = :cl');
        $stmt->execute(['c' => $cuponId, 'cl' => $clienteId]);

        return (int) $stmt->fetchColumn();
    }

    public static function registrarUso(int $cuponId, int $clienteId, int $descuento, ?int $pedidoId = null, ?int $citaId = null): void
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO cupon_usos (cupon_id, cliente_id, pedido_id, cita_id, descuento)
             VALUES (:cupon, :cliente, :pedido, :cita, :descuento)'
        );
        $stmt->execute(['cupon' => $cuponId, 'cliente' => $clienteId, 'pedido' => $pedidoId, 'cita' => $citaId, 'descuento' => $descuento]);
    }

    public static function alternarActivo(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare('UPDATE cupones SET activo = 1 - activo WHERE id = :id AND negocio_id = :n');
        $stmt->execute(['id' => $id, 'n' => $negocioId]);
    }

    /** Un cupón que ya se usó no se borra (el historial de pedidos lo nombra): se pausa. */
    public static function eliminar(int $id, int $negocioId): bool
    {
        $cupon = self::buscar($id, $negocioId);
        if ($cupon === null || (int) $cupon['usos'] > 0) {
            return false;
        }
        $stmt = Database::conexion()->prepare('DELETE FROM cupones WHERE id = :id AND negocio_id = :n');
        $stmt->execute(['id' => $id, 'n' => $negocioId]);

        return true;
    }

    /**
     * El cupón personal del copiloto para este cliente y este porcentaje que
     * todavía sirve (activo, sin usar, sin vencer), si ya existe: mandarle
     * dos mensajes al mismo cliente no le crea dos cupones.
     */
    public static function personalVigente(int $negocioId, int $clienteId, int $porcentaje): ?array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.* FROM cupones c
             WHERE c.negocio_id = :n AND c.cliente_id = :cl AND c.origen = 'copiloto'
               AND c.tipo = 'porcentaje' AND c.valor = :pct AND c.activo = 1
               AND (c.vence_en IS NULL OR c.vence_en >= CURDATE())
               AND NOT EXISTS (SELECT 1 FROM cupon_usos u WHERE u.cupon_id = c.id)
             ORDER BY c.id DESC LIMIT 1"
        );
        $stmt->execute(['n' => $negocioId, 'cl' => $clienteId, 'pct' => $porcentaje]);

        return $stmt->fetch() ?: null;
    }

    /** Crea (si no existe ya) el cupón personal con el código dado, para un cliente y un porcentaje. */
    public static function asegurarPersonal(int $negocioId, int $clienteId, int $porcentaje, string $codigo): array
    {
        $vigente = self::personalVigente($negocioId, $clienteId, $porcentaje);
        if ($vigente !== null) {
            return $vigente;
        }
        $codigo = self::normalizarCodigo($codigo);
        if ($codigo === '' || self::existeCodigo($negocioId, $codigo)) {
            do {
                $codigo = self::codigoAleatorio('VUELVE');
            } while (self::existeCodigo($negocioId, $codigo));
        }
        $id = self::crear($negocioId, [
            'codigo'              => $codigo,
            'tipo'                => 'porcentaje',
            'valor'               => $porcentaje,
            'vence_en'            => date('Y-m-d', strtotime('+' . self::DIAS_COPILOTO . ' days')),
            'usos_maximos'        => 1,
            'una_vez_por_cliente' => 1,
            'cliente_id'          => $clienteId,
            'origen'              => 'copiloto',
        ]);

        return (array) self::buscar($id, $negocioId);
    }
}
