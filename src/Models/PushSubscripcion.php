<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Suscripciones de notificaciones push del navegador del dueño. Guardamos
 * lo que entrega PushManager.subscribe() tal cual: endpoint (la URL del
 * servicio de push real, FCM o Mozilla autopush) y las llaves p256dh/auth
 * que se usan para cifrar cada mensaje (ver Services/WebPush.php).
 */
class PushSubscripcion
{
    public static function guardar(int $negocioId, string $endpoint, string $p256dh, string $auth): void
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO push_subscripciones (negocio_id, endpoint, p256dh, auth)
             VALUES (:negocio_id, :endpoint, :p256dh, :auth)
             ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), negocio_id = VALUES(negocio_id)'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth]);
    }

    public static function eliminarPorEndpoint(string $endpoint): void
    {
        $stmt = Database::conexion()->prepare('DELETE FROM push_subscripciones WHERE endpoint = :endpoint');
        $stmt->execute(['endpoint' => $endpoint]);
    }

    public static function eliminarPorEndpointYNegocio(string $endpoint, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM push_subscripciones WHERE endpoint = :endpoint AND negocio_id = :negocio_id'
        );
        $stmt->execute(['endpoint' => $endpoint, 'negocio_id' => $negocioId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM push_subscripciones WHERE negocio_id = :negocio_id');
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetchAll();
    }

    public static function existeParaNegocio(int $negocioId): bool
    {
        $stmt = Database::conexion()->prepare('SELECT id FROM push_subscripciones WHERE negocio_id = :negocio_id LIMIT 1');
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetch() !== false;
    }
}
