<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Zonas a las que una sede lleva domicilios, con su costo y un pedido
 * mínimo opcional (ver database/migrations/2026-10-03_03_zonas_domicilio.sql).
 * Una sede sin zonas sigue como antes: el costo del domicilio se acuerda
 * por WhatsApp y no se suma en la tienda.
 */
class ZonaDomicilio
{
    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId, bool $soloActivas = false): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM zonas_domicilio WHERE sede_id = :s' . ($soloActivas ? ' AND activa = 1' : '') . ' ORDER BY costo, nombre'
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $sedeId): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM zonas_domicilio WHERE id = :id AND sede_id = :s');
        $stmt->execute(['id' => $id, 's' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    public static function guardar(int $sedeId, ?int $id, string $nombre, int $costo, int $minimo): void
    {
        $datos = ['s' => $sedeId, 'nombre' => mb_substr(trim($nombre), 0, 80), 'costo' => max(0, $costo), 'minimo' => max(0, $minimo)];
        if ($id === null) {
            $stmt = Database::conexion()->prepare(
                'INSERT INTO zonas_domicilio (sede_id, nombre, costo, minimo_pedido) VALUES (:s, :nombre, :costo, :minimo)'
            );
        } else {
            $stmt = Database::conexion()->prepare(
                'UPDATE zonas_domicilio SET nombre = :nombre, costo = :costo, minimo_pedido = :minimo WHERE id = :id AND sede_id = :s'
            );
            $datos['id'] = $id;
        }
        $stmt->execute($datos);
    }

    public static function alternarActiva(int $id, int $sedeId): void
    {
        Database::conexion()->prepare('UPDATE zonas_domicilio SET activa = 1 - activa WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $id, 's' => $sedeId]);
    }

    /** Los pedidos guardan su copia (nombre y costo): borrar la zona no les cambia nada. */
    public static function eliminar(int $id, int $sedeId): void
    {
        Database::conexion()->prepare('DELETE FROM zonas_domicilio WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $id, 's' => $sedeId]);
    }

    public static function guardarPedidoMinimo(int $sedeId, int $minimo): void
    {
        Database::conexion()->prepare('UPDATE sedes SET pedido_minimo = :m WHERE id = :s')
            ->execute(['m' => max(0, $minimo), 's' => $sedeId]);
    }

    /** "Gratis" o "$4.000". */
    public static function etiquetaCosto(int $costo): string
    {
        return $costo === 0 ? 'Gratis' : pesos($costo);
    }
}
