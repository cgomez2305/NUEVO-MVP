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
    public static function guardar(int $usuarioId, string $endpoint, string $p256dh, string $auth): void
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO push_subscripciones (usuario_id, endpoint, p256dh, auth)
             VALUES (:usuario_id, :endpoint, :p256dh, :auth)
             ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), usuario_id = VALUES(usuario_id)'
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth]);
    }

    public static function eliminarPorEndpoint(string $endpoint): void
    {
        $stmt = Database::conexion()->prepare('DELETE FROM push_subscripciones WHERE endpoint = :endpoint');
        $stmt->execute(['endpoint' => $endpoint]);
    }

    public static function eliminarPorEndpointYUsuario(string $endpoint, int $usuarioId): void
    {
        $stmt = Database::conexion()->prepare(
            'DELETE FROM push_subscripciones WHERE endpoint = :endpoint AND usuario_id = :usuario_id'
        );
        $stmt->execute(['endpoint' => $endpoint, 'usuario_id' => $usuarioId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorUsuario(int $usuarioId): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM push_subscripciones WHERE usuario_id = :usuario_id');
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    public static function existeParaUsuario(int $usuarioId): bool
    {
        $stmt = Database::conexion()->prepare('SELECT id FROM push_subscripciones WHERE usuario_id = :usuario_id LIMIT 1');
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetch() !== false;
    }
}
