<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Cliente
{
    /**
     * Crea el cliente o, si ya existe (mismo teléfono en el mismo negocio),
     * actualiza su nombre. $aceptaMarketing es un opt-in APARTE del
     * consentimiento de procesar el pedido: se reescribe en cada pedido, así
     * que un cliente que cambia de opinión (marca o desmarca la casilla la
     * próxima vez) queda reflejado.
     */
    public static function buscarOCrear(int $negocioId, string $nombre, string $telefono, bool $autorizoDatos, bool $aceptaMarketing = false): int
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare(
            'SELECT id FROM clientes WHERE negocio_id = :negocio_id AND telefono = :telefono'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'telefono' => $telefono]);
        $existente = $stmt->fetch();

        if ($existente !== false) {
            $actualizar = $pdo->prepare(
                'UPDATE clientes SET nombre = :nombre, acepta_marketing = :acepta_marketing WHERE id = :id'
            );
            $actualizar->execute(['nombre' => $nombre, 'acepta_marketing' => $aceptaMarketing ? 1 : 0, 'id' => $existente['id']]);
            return (int) $existente['id'];
        }

        $crear = $pdo->prepare(
            'INSERT INTO clientes (negocio_id, nombre, telefono, autorizo_datos, autorizado_en, acepta_marketing)
             VALUES (:negocio_id, :nombre, :telefono, :autorizo, :autorizado_en, :acepta_marketing)'
        );
        $crear->execute([
            'negocio_id'       => $negocioId,
            'nombre'           => $nombre,
            'telefono'         => $telefono,
            'autorizo'         => $autorizoDatos ? 1 : 0,
            'autorizado_en'    => $autorizoDatos ? date('Y-m-d H:i:s') : null,
            'acepta_marketing' => $aceptaMarketing ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Para el panel: el cliente con ese WhatsApp si ya existe (sin tocarle el
     * nombre ni sus permisos), o uno nuevo con la autorización que el
     * negocio confirmó en persona.
     */
    public static function buscarOCrearDesdePanel(int $negocioId, string $nombre, string $telefono): int
    {
        $stmt = Database::conexion()->prepare('SELECT id FROM clientes WHERE negocio_id = :n AND telefono = :t');
        $stmt->execute(['n' => $negocioId, 't' => $telefono]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        Database::conexion()->prepare(
            'INSERT INTO clientes (negocio_id, nombre, telefono, autorizo_datos, autorizado_en) VALUES (:n, :nombre, :t, 1, NOW())'
        )->execute(['n' => $negocioId, 'nombre' => mb_substr($nombre, 0, 120), 't' => $telefono]);

        return (int) Database::conexion()->lastInsertId();
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
        // Borrar al cliente (derecho de supresión, Ley 1581) también borra
        // las fotos de sus visitas del disco, no solo sus filas.
        $fotos = Database::conexion()->prepare(
            'SELECT f.archivo FROM cita_fotos f JOIN citas c ON c.id = f.cita_id JOIN clientes cl ON cl.id = c.cliente_id
             WHERE cl.id = :id AND cl.negocio_id = :n'
        );
        $fotos->execute(['id' => $id, 'n' => $negocioId]);
        foreach ($fotos->fetchAll(\PDO::FETCH_COLUMN) as $archivo) {
            $ruta = \App\Services\Subida::rutaPrivada((string) $archivo);
            if ($ruta !== null && is_file($ruta)) {
                @unlink($ruta);
            }
        }
        $stmt = Database::conexion()->prepare(
            'DELETE FROM clientes WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }
}
