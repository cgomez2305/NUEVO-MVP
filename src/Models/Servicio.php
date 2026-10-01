<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Servicio
{
    private const PALETA = ['#5B7F3A', '#3B4CCA', '#E8452C', '#F2B632', '#1B1A17'];

    public static function crear(int $sedeId, string $nombre, int $precio, int $duracionMin = 30): int
    {
        $pdo = Database::conexion();
        $orden = self::contarPorSede($sedeId);
        $color = self::PALETA[$orden % count(self::PALETA)];

        $stmt = $pdo->prepare(
            'INSERT INTO servicios (sede_id, nombre, precio, duracion_min, color, orden)
             VALUES (:sede_id, :nombre, :precio, :duracion_min, :color, :orden)'
        );
        $stmt->execute([
            'sede_id'   => $sedeId,
            'nombre'       => $nombre,
            'precio'       => $precio,
            'duracion_min' => $duracionMin,
            'color'        => $color,
            'orden'        => $orden,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorSede(int $sedeId, bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM servicios WHERE sede_id = :sede_id';
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
            'SELECT * FROM servicios WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);

        return $stmt->fetch() ?: null;
    }

    public static function actualizar(int $id, int $sedeId, string $nombre, int $precio, int $duracionMin): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE servicios SET nombre = :nombre, precio = :precio, duracion_min = :duracion_min
             WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute([
            'nombre'       => $nombre,
            'precio'       => $precio,
            'duracion_min' => $duracionMin,
            'id'           => $id,
            'sede_id'   => $sedeId,
        ]);
    }

    public static function eliminar(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM servicios WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    /** Marca/desmarca un servicio como agotado sin borrarlo: sigue visible en la tienda pero no se puede reservar. */
    public static function alternarAgotado(int $id, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE servicios SET agotado = NOT agotado WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['id' => $id, 'sede_id' => $sedeId]);
    }

    public static function actualizarDeposito(int $id, int $sedeId, string $tipo, int $valor): void
    {
        if (!in_array($tipo, ['ninguno', 'porcentaje', 'monto_fijo'], true)) {
            $tipo = 'ninguno';
        }
        if ($tipo === 'ninguno') {
            $valor = 0;
        } elseif ($tipo === 'porcentaje') {
            $valor = max(1, min(100, $valor));
        } else {
            $valor = max(1, $valor);
        }

        $stmt = Database::conexion()->prepare(
            'UPDATE servicios SET deposito_tipo = :tipo, deposito_valor = :valor WHERE id = :id AND sede_id = :sede_id'
        );
        $stmt->execute(['tipo' => $tipo, 'valor' => $valor, 'id' => $id, 'sede_id' => $sedeId]);
    }

    /** Cuánto anticipo hay que pagar en pesos para un servicio, dado su precio actual. 0 si no pide anticipo. */
    public static function calcularAnticipo(array $servicio): int
    {
        return match ($servicio['deposito_tipo']) {
            'porcentaje'  => (int) round((int) $servicio['precio'] * ((int) $servicio['deposito_valor'] / 100)),
            'monto_fijo'  => min((int) $servicio['deposito_valor'], (int) $servicio['precio']),
            default       => 0,
        };
    }

    public static function contarPorSede(int $sedeId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM servicios WHERE sede_id = :sede_id'
        );
        $stmt->execute(['sede_id' => $sedeId]);

        return (int) $stmt->fetch()['total'];
    }

    /** true si al menos un servicio de la sede pide anticipo. Usado en el onboarding para saber si el paso de cobros es urgente o puede configurarse después. */
    public static function tieneAnticipoActivo(int $sedeId): bool
    {
        $stmt = Database::conexion()->prepare(
            "SELECT COUNT(*) AS total FROM servicios WHERE sede_id = :sede_id AND deposito_tipo != 'ninguno'"
        );
        $stmt->execute(['sede_id' => $sedeId]);

        return (int) $stmt->fetch()['total'] > 0;
    }
}
