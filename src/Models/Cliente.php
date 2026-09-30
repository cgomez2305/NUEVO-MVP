<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Cliente
{
    /** Crea el cliente o, si ya existe (mismo teléfono en el mismo negocio), actualiza su nombre. */
    public static function buscarOCrear(int $negocioId, string $nombre, string $telefono, bool $autorizoDatos): int
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare(
            'SELECT id FROM clientes WHERE negocio_id = :negocio_id AND telefono = :telefono'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'telefono' => $telefono]);
        $existente = $stmt->fetch();

        if ($existente !== false) {
            $actualizar = $pdo->prepare('UPDATE clientes SET nombre = :nombre WHERE id = :id');
            $actualizar->execute(['nombre' => $nombre, 'id' => $existente['id']]);
            return (int) $existente['id'];
        }

        $crear = $pdo->prepare(
            'INSERT INTO clientes (negocio_id, nombre, telefono, autorizo_datos, autorizado_en)
             VALUES (:negocio_id, :nombre, :telefono, :autorizo, :autorizado_en)'
        );
        $crear->execute([
            'negocio_id'     => $negocioId,
            'nombre'         => $nombre,
            'telefono'       => $telefono,
            'autorizo'       => $autorizoDatos ? 1 : 0,
            'autorizado_en'  => $autorizoDatos ? date('Y-m-d H:i:s') : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function buscar(int $id, int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM clientes WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM clientes WHERE negocio_id = :negocio_id ORDER BY nombre ASC'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetchAll();
    }

    /**
     * Derecho de suprimir datos (Ley 1581 de 2012): borra al cliente y, por
     * el ON DELETE CASCADE del esquema, todo su historial (pedidos, citas,
     * mensajes del copiloto). Es definitivo.
     */
    public static function eliminar(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM clientes WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }
}
