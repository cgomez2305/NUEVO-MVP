<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Paquetes de sesiones y bonos vendidos (ver database/migrations/
 * 2026-10-03_08_paquetes_bonos.sql). Las sesiones usadas no se guardan
 * como número: se cuentan de bono_usos (una fila por cita), así cancelar
 * una cita devuelve la sesión con solo borrar su fila.
 */
class Bono
{
    // ---------- Paquetes (lo que el negocio ofrece) ----------

    public static function crearPaquete(int $sedeId, int $servicioId, int $sesiones, int $precio, ?int $vigenciaDias): void
    {
        Database::conexion()->prepare(
            'INSERT INTO paquetes (sede_id, servicio_id, sesiones, precio, vigencia_dias) VALUES (:s, :sv, :n, :p, :v)'
        )->execute(['s' => $sedeId, 'sv' => $servicioId, 'n' => $sesiones, 'p' => $precio, 'v' => $vigenciaDias]);
    }

    /**
     * Con el servicio, su precio suelto y cuánto se ahorra.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function paquetes(int $sedeId, bool $soloActivos = false): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT p.*, s.nombre AS servicio_nombre, s.precio AS servicio_precio,
                    (s.precio * p.sesiones) AS precio_suelto,
                    (SELECT COUNT(*) FROM bonos b WHERE b.paquete_id = p.id) AS vendidos
             FROM paquetes p JOIN servicios s ON s.id = p.servicio_id
             WHERE p.sede_id = :s' . ($soloActivos ? ' AND p.activo = 1 AND s.activo = 1' : '') . '
             ORDER BY s.nombre, p.sesiones'
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    public static function paquete(int $id, int $sedeId): ?array
    {
        foreach (self::paquetes($sedeId) as $paquete) {
            if ((int) $paquete['id'] === $id) {
                return $paquete;
            }
        }

        return null;
    }

    public static function alternarPaquete(int $id, int $sedeId): void
    {
        Database::conexion()->prepare('UPDATE paquetes SET activo = 1 - activo WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $id, 's' => $sedeId]);
    }

    /** Los bonos ya vendidos conservan su copia: borrar el paquete no les quita nada. */
    public static function eliminarPaquete(int $id, int $sedeId): void
    {
        Database::conexion()->prepare('DELETE FROM paquetes WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $id, 's' => $sedeId]);
    }

    // ---------- Bonos (lo que un cliente compró) ----------

    public static function vender(array $paquete, int $clienteId, ?int $usuarioId): array
    {
        $token = bin2hex(random_bytes(16));
        Database::conexion()->prepare(
            'INSERT INTO bonos (sede_id, paquete_id, cliente_id, servicio_id, nombre_servicio, sesiones_total, precio_pagado, vence_en, token, usuario_id)
             VALUES (:s, :p, :c, :sv, :nombre, :n, :precio, :vence, :t, :u)'
        )->execute([
            's' => (int) $paquete['sede_id'], 'p' => (int) $paquete['id'], 'c' => $clienteId,
            'sv' => (int) $paquete['servicio_id'], 'nombre' => $paquete['servicio_nombre'],
            'n' => (int) $paquete['sesiones'], 'precio' => (int) $paquete['precio'],
            'vence' => $paquete['vigencia_dias'] !== null ? date('Y-m-d', strtotime('+' . (int) $paquete['vigencia_dias'] . ' days')) : null,
            't' => $token, 'u' => $usuarioId,
        ]);

        return (array) self::buscarPorToken($token);
    }

    private const SELECT = 'SELECT b.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono,
            (SELECT COUNT(*) FROM bono_usos u WHERE u.bono_id = b.id) AS usadas
        FROM bonos b JOIN clientes cl ON cl.id = b.cliente_id';

    public static function buscarPorToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = Database::conexion()->prepare(self::SELECT . ' WHERE b.token = :t');
        $stmt->execute(['t' => $token]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> los de la sede, con sesiones por usar primero */
    public static function listar(int $sedeId, int $limite = 60): array
    {
        $stmt = Database::conexion()->prepare(
            self::SELECT . " WHERE b.sede_id = :s ORDER BY (b.sesiones_total - (SELECT COUNT(*) FROM bono_usos u WHERE u.bono_id = b.id)) > 0 DESC, b.creado_en DESC LIMIT {$limite}"
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    /** 'activo' | 'agotado' | 'vencido' */
    public static function estado(array $bono): string
    {
        if ((int) $bono['usadas'] >= (int) $bono['sesiones_total']) {
            return 'agotado';
        }
        if (!empty($bono['vence_en']) && $bono['vence_en'] < date('Y-m-d')) {
            return 'vencido';
        }

        return 'activo';
    }

    /**
     * El bono que sirve para esta cita: del cliente, del mismo servicio,
     * con sesiones y vigente el día de la cita. Si hay varios, el que vence
     * primero (que no se le pierdan sesiones).
     */
    public static function paraCita(int $sedeId, int $clienteId, int $servicioId, string $fecha): ?array
    {
        $stmt = Database::conexion()->prepare(
            self::SELECT . ' WHERE b.sede_id = :s AND b.cliente_id = :c AND b.servicio_id = :sv
               AND (b.vence_en IS NULL OR b.vence_en >= :f)
               AND (SELECT COUNT(*) FROM bono_usos u WHERE u.bono_id = b.id) < b.sesiones_total
             ORDER BY b.vence_en IS NULL, b.vence_en, b.id LIMIT 1'
        );
        $stmt->execute(['s' => $sedeId, 'c' => $clienteId, 'sv' => $servicioId, 'f' => $fecha]);

        return $stmt->fetch() ?: null;
    }

    public static function usar(int $bonoId, int $citaId): void
    {
        Database::conexion()->prepare('INSERT IGNORE INTO bono_usos (bono_id, cita_id) VALUES (:b, :c)')
            ->execute(['b' => $bonoId, 'c' => $citaId]);
    }

    /** Cancelar la cita devuelve la sesión. */
    public static function devolverPorCita(int $citaId): void
    {
        Database::conexion()->prepare('DELETE FROM bono_usos WHERE cita_id = :c')->execute(['c' => $citaId]);
    }

    public static function usoDeCita(int $citaId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT b.token, b.sesiones_total, (SELECT COUNT(*) FROM bono_usos x WHERE x.bono_id = b.id) AS usadas
             FROM bono_usos u JOIN bonos b ON b.id = u.bono_id WHERE u.cita_id = :c'
        );
        $stmt->execute(['c' => $citaId]);

        return $stmt->fetch() ?: null;
    }
}
