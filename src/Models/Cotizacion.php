<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Cotización por ítems de una visita (ver migrations/2026-10-03_17_cotizaciones.sql).
 *
 * El técnico la arma en la visita (mano de obra, materiales, y si quiere
 * descuenta lo que ya cobró por ir) y se la manda por WhatsApp; el cliente
 * la aprueba o no desde su enlace. Aprobada, su total queda como el valor
 * de la cita (igual que un ajuste aprobado) y "Terminar" lo sugiere.
 *
 * Se cotiza mientras la visita sigue abierta: si el trabajo queda para otro
 * día, la cita se reprograma en vez de terminarla.
 */
class Cotizacion
{
    public const TIPOS = ['mano_obra' => 'Mano de obra', 'material' => 'Material', 'otro' => 'Otro', 'descuento' => 'Descuento'];
    public const GARANTIAS = [0, 30, 60, 90, 180, 365];
    public const VALIDEZ = [3, 8, 15, 30];
    public const MAX_ITEMS = 12;

    /** Solo mientras la visita está abierta (ver la nota de la clase). */
    public static function sePuedeCotizar(array $cita): bool
    {
        return in_array($cita['estado'], ['pendiente', 'confirmada', 'en_curso'], true);
    }

    /**
     * Los ítems válidos de un formulario (filas vacías se ignoran) y su total.
     *
     * @param array<int, mixed> $filas
     * @return array{items: array<int, array{tipo: string, descripcion: string, cantidad: int, valor_unitario: int}>, total: int}
     */
    public static function itemsDesdeFormulario(array $filas): array
    {
        $items = [];
        $total = 0;
        foreach (array_slice($filas, 0, self::MAX_ITEMS) as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $descripcion = trim((string) ($fila['descripcion'] ?? ''));
            $valor = dinero_desde_texto((string) ($fila['valor'] ?? ''));
            if ($descripcion === '' || $valor <= 0) {
                continue;
            }
            $tipo = isset(self::TIPOS[$fila['tipo'] ?? '']) ? (string) $fila['tipo'] : 'otro';
            $cantidad = max(1, min(999, (int) ($fila['cantidad'] ?? 1)));
            $items[] = ['tipo' => $tipo, 'descripcion' => mb_substr($descripcion, 0, 160), 'cantidad' => $cantidad, 'valor_unitario' => $valor];
            $total += ($tipo === 'descuento' ? -1 : 1) * $cantidad * $valor;
        }

        // Un tope realista (mil millones): más que eso es un error de tecleo
        // y además no cabe en la columna.
        return ['items' => $items, 'total' => $total > 1000000000 ? 0 : max(0, $total)];
    }

    /**
     * Crea la cotización; la anterior que seguía esperando respuesta queda
     * "reemplazada" (su enlace ya no se puede aprobar). Devuelve el token.
     *
     * @param array<int, array{tipo: string, descripcion: string, cantidad: int, valor_unitario: int}> $items
     */
    public static function crear(array $cita, array $items, int $total, int $anticipo, int $garantiaDias, int $validezDias, string $nota): string
    {
        $pdo = Database::conexion();
        $token = bin2hex(random_bytes(16));
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE cotizaciones SET estado = 'reemplazada' WHERE cita_id = :c AND estado = 'enviada'")
                ->execute(['c' => (int) $cita['id']]);
            $pdo->prepare(
                'INSERT INTO cotizaciones (sede_id, cita_id, token, total, anticipo, garantia_dias, validez_dias, nota)
                 VALUES (:s, :c, :t, :total, :a, :g, :v, :n)'
            )->execute([
                's' => (int) $cita['sede_id'], 'c' => (int) $cita['id'], 't' => $token, 'total' => $total,
                'a' => max(0, min($anticipo, $total)),
                'g' => in_array($garantiaDias, self::GARANTIAS, true) ? $garantiaDias : 0,
                'v' => in_array($validezDias, self::VALIDEZ, true) ? $validezDias : 8,
                'n' => $nota !== '' ? mb_substr($nota, 0, 500) : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $insertar = $pdo->prepare(
                'INSERT INTO cotizacion_items (cotizacion_id, tipo, descripcion, cantidad, valor_unitario) VALUES (:c, :t, :d, :q, :v)'
            );
            foreach ($items as $item) {
                $insertar->execute(['c' => $id, 't' => $item['tipo'], 'd' => $item['descripcion'], 'q' => $item['cantidad'], 'v' => $item['valor_unitario']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $token;
    }

    /** La última cotización de la cita (la vigente o la última respondida). */
    public static function deCita(int $citaId): ?array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT * FROM cotizaciones WHERE cita_id = :c AND estado <> 'reemplazada' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['c' => $citaId]);

        return $stmt->fetch() ?: null;
    }

    /** La última cotización aprobada (la que fija el valor), aunque haya una más nueva esperando respuesta. */
    public static function aprobadaDeCita(int $citaId): ?array
    {
        $stmt = Database::conexion()->prepare("SELECT * FROM cotizaciones WHERE cita_id = :c AND estado = 'aprobada' ORDER BY id DESC LIMIT 1");
        $stmt->execute(['c' => $citaId]);

        return $stmt->fetch() ?: null;
    }

    /** ¿Ya se recibió anticipo de materiales en alguna cotización aprobada de esta visita? */
    public static function anticipoYaRecibido(int $citaId): bool
    {
        $stmt = Database::conexion()->prepare("SELECT 1 FROM cotizaciones WHERE cita_id = :c AND estado = 'aprobada' AND anticipo_pagado = 1 LIMIT 1");
        $stmt->execute(['c' => $citaId]);

        return $stmt->fetchColumn() !== false;
    }

    public static function buscarPorToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = Database::conexion()->prepare('SELECT * FROM cotizaciones WHERE token = :t');
        $stmt->execute(['t' => $token]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function items(int $cotizacionId): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM cotizacion_items WHERE cotizacion_id = :c ORDER BY id');
        $stmt->execute(['c' => $cotizacionId]);

        return $stmt->fetchAll();
    }

    public static function vencida(array $cotizacion): bool
    {
        return $cotizacion['estado'] === 'enviada'
            && time() > (strtotime((string) $cotizacion['creado_en']) ?: 0) + (int) $cotizacion['validez_dias'] * 86400;
    }

    /**
     * El cliente responde. Aprobada: su total pasa a ser el valor de la cita
     * (como un ajuste aprobado). Solo si sigue "enviada", no venció y la
     * visita sigue abierta. Devuelve si se registró.
     */
    public static function responder(array $cotizacion, bool $aprobar): bool
    {
        if ($cotizacion['estado'] !== 'enviada' || self::vencida($cotizacion)) {
            return false;
        }
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE cotizaciones SET estado = :e, respondida_en = NOW() WHERE id = :id AND estado = 'enviada'"
            );
            $stmt->execute(['e' => $aprobar ? 'aprobada' : 'rechazada', 'id' => (int) $cotizacion['id']]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();

                return false;
            }
            if ($aprobar) {
                $cita = $pdo->prepare(
                    "UPDATE citas SET ajuste_precio = :p, ajuste_motivo = 'Cotización aprobada', ajuste_estado = 'aprobado',
                            precio_final = :p2, aviso_imprevisto = IF(aviso_imprevisto = 'ajuste', NULL, aviso_imprevisto)
                     WHERE id = :c AND estado IN ('pendiente', 'confirmada', 'en_curso')"
                );
                $cita->execute(['p' => (int) $cotizacion['total'], 'p2' => (int) $cotizacion['total'], 'c' => (int) $cotizacion['cita_id']]);
                if ($cita->rowCount() !== 1) {
                    $pdo->rollBack();

                    return false;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return true;
    }

    public static function marcarAnticipoPagado(int $id, int $sedeId): void
    {
        // Si ya se recibió con una cotización anterior de la misma visita, no
        // se marca otra vez: el cierre de caja lo restaría dos veces.
        $cita = Database::conexion()->prepare('SELECT cita_id FROM cotizaciones WHERE id = :id AND sede_id = :s');
        $cita->execute(['id' => $id, 's' => $sedeId]);
        $citaId = $cita->fetchColumn();
        if ($citaId === false || self::anticipoYaRecibido((int) $citaId)) {
            return;
        }
        Database::conexion()->prepare(
            "UPDATE cotizaciones SET anticipo_pagado = 1 WHERE id = :id AND sede_id = :s AND estado = 'aprobada' AND anticipo > 0"
        )->execute(['id' => $id, 's' => $sedeId]);
    }

    public static function mensaje(array $cita, array $cotizacion, array $sede): string
    {
        $nombre = explode(' ', trim((string) $cita['cliente_nombre']))[0];

        return "Hola {$nombre}, te mandamos la cotización de " . nombre_publico_sede($sede) . ' por ' . pesos((int) $cotizacion['total'])
            . ((int) $cotizacion['anticipo'] > 0 ? ' (anticipo para materiales: ' . pesos((int) $cotizacion['anticipo']) . ')' : '')
            . '. Aquí ves el detalle y la apruebas: ' . url_publica('/cotizacion/' . $cotizacion['token']);
    }
}
