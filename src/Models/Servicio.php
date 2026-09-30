<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Servicio
{
    private const PALETA = ['#5B7F3A', '#3B4CCA', '#E8452C', '#F2B632', '#1B1A17'];

    public static function crear(int $negocioId, string $nombre, int $precio, int $duracionMin = 30): int
    {
        $pdo = Database::conexion();
        $orden = self::contarPorNegocio($negocioId);
        $color = self::PALETA[$orden % count(self::PALETA)];

        $stmt = $pdo->prepare(
            'INSERT INTO servicios (negocio_id, nombre, precio, duracion_min, color, orden)
             VALUES (:negocio_id, :nombre, :precio, :duracion_min, :color, :orden)'
        );
        $stmt->execute([
            'negocio_id'   => $negocioId,
            'nombre'       => $nombre,
            'precio'       => $precio,
            'duracion_min' => $duracionMin,
            'color'        => $color,
            'orden'        => $orden,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId, bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM servicios WHERE negocio_id = :negocio_id';
        if ($soloActivos) {
            $sql .= ' AND activo = 1';
        }
        $sql .= ' ORDER BY orden ASC, id ASC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute(['negocio_id' => $negocioId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM servicios WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);

        return $stmt->fetch() ?: null;
    }

    public static function actualizar(int $id, int $negocioId, string $nombre, int $precio, int $duracionMin): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE servicios SET nombre = :nombre, precio = :precio, duracion_min = :duracion_min
             WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute([
            'nombre'       => $nombre,
            'precio'       => $precio,
            'duracion_min' => $duracionMin,
            'id'           => $id,
            'negocio_id'   => $negocioId,
        ]);
    }

    public static function eliminar(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM servicios WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }

    /** Marca/desmarca un servicio como agotado sin borrarlo: sigue visible en la tienda pero no se puede reservar. */
    public static function alternarAgotado(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE servicios SET agotado = NOT agotado WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }

    public static function actualizarDeposito(int $id, int $negocioId, string $tipo, int $valor): void
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
            'UPDATE servicios SET deposito_tipo = :tipo, deposito_valor = :valor WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['tipo' => $tipo, 'valor' => $valor, 'id' => $id, 'negocio_id' => $negocioId]);
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

    public static function contarPorNegocio(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM servicios WHERE negocio_id = :negocio_id'
        );
        $stmt->execute(['negocio_id' => $negocioId]);

        return (int) $stmt->fetch()['total'];
    }
}
