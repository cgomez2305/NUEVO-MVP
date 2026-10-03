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
    /** Los servicios de push de los navegadores: solo a ellos se les envía (nada de URLs arbitrarias). */
    private const HOSTS_PERMITIDOS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'];
    private const SUFIJOS_PERMITIDOS = ['.notify.windows.com', '.push.apple.com', '.push.services.mozilla.com'];
    public const MAX_POR_USUARIO = 10;

    /** ¿Es la URL de un servicio de push real (https y host conocido)? */
    public static function endpointValido(string $endpoint): bool
    {
        if (strlen($endpoint) > 600 || parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if (in_array($host, self::HOSTS_PERMITIDOS, true)) {
            return true;
        }
        foreach (self::SUFIJOS_PERMITIDOS as $sufijo) {
            if (str_ends_with($host, $sufijo)) {
                return true;
            }
        }

        return false;
    }

    public static function guardar(int $usuarioId, string $endpoint, string $p256dh, string $auth): void
    {
        if (!self::endpointValido($endpoint) || strlen($p256dh) > 200 || strlen($auth) > 100) {
            return;
        }
        // Un dueño con 10 navegadores ya es mucho: el más viejo cede su lugar.
        Database::conexion()->prepare(
            'DELETE FROM push_subscripciones WHERE usuario_id = :u AND id NOT IN (
               SELECT id FROM (SELECT id FROM push_subscripciones WHERE usuario_id = :u2 ORDER BY id DESC LIMIT ' . (self::MAX_POR_USUARIO - 1) . ') ultimas)'
        )->execute(['u' => $usuarioId, 'u2' => $usuarioId]);
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
