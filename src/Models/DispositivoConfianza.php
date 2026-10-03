<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Celulares (navegadores) donde un usuario ya entró con su contraseña.
 *
 * Sirven para que un ataque a la cuenta no deje por fuera al dueño: los
 * intentos fallidos desde un celular conocido se cuentan aparte de los que
 * llegan de cualquier otro lado, y si la cuenta recibe muchos intentos
 * desde lugares desconocidos, solo se puede entrar desde uno conocido (o
 * recuperando la contraseña). La cookie lleva un token al azar; en la base
 * solo queda su hash, así que una copia de la base no sirve para entrar.
 */
class DispositivoConfianza
{
    public const COOKIE = 'veci_dc';

    private const DIAS = 180;

    /** El registro de ESTE navegador para ese usuario, o null si no es de confianza. */
    public static function deEsteNavegador(int $usuarioId): ?array
    {
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM dispositivos_confianza WHERE token_hash = :h AND usuario_id = :u AND usado_en >= :desde'
        );
        $stmt->execute(['h' => hash('sha256', $token), 'u' => $usuarioId, 'desde' => date('Y-m-d H:i:s', time() - self::DIAS * 86400)]);

        return $stmt->fetch() ?: null;
    }

    /** Tras entrar con la contraseña (o recuperarla): este navegador queda como conocido. */
    public static function recordar(int $usuarioId): void
    {
        $pdo = Database::conexion();
        $actual = self::deEsteNavegador($usuarioId);
        if ($actual !== null) {
            $pdo->prepare('UPDATE dispositivos_confianza SET usado_en = NOW() WHERE id = :id')->execute(['id' => $actual['id']]);
            $token = (string) $_COOKIE[self::COOKIE];
        } else {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO dispositivos_confianza (usuario_id, token_hash, descripcion) VALUES (:u, :h, :d)')
                ->execute(['u' => $usuarioId, 'h' => hash('sha256', $token), 'd' => descripcion_navegador()]);
            // Los de hace más de 180 días ya no cuentan: se limpian aquí.
            $pdo->prepare('DELETE FROM dispositivos_confianza WHERE usuario_id = :u AND usado_en < :desde')
                ->execute(['u' => $usuarioId, 'desde' => date('Y-m-d H:i:s', time() - self::DIAS * 86400)]);
        }
        setcookie(self::COOKIE, $token, [
            'expires'  => time() + self::DIAS * 86400,
            'path'     => '/',
            'secure'   => defined('VECI_HTTPS') && VECI_HTTPS,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** "Cerrar sesión en todos los dispositivos" o contraseña nueva: ningún celular queda como conocido. */
    public static function olvidarTodos(int $usuarioId): void
    {
        Database::conexion()->prepare('DELETE FROM dispositivos_confianza WHERE usuario_id = :u')->execute(['u' => $usuarioId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function listar(int $usuarioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT id, descripcion, creado_en, usado_en FROM dispositivos_confianza WHERE usuario_id = :u AND usado_en >= :desde ORDER BY usado_en DESC'
        );
        $stmt->execute(['u' => $usuarioId, 'desde' => date('Y-m-d H:i:s', time() - self::DIAS * 86400)]);

        return $stmt->fetchAll();
    }
}
