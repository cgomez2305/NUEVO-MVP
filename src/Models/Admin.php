<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Quién del equipo de Veci entra al panel interno (/admin). Separado por
 * completo de `usuarios` (los dueños/colaboradores de un negocio): no hay
 * registro público, solo se crea con bin/crear_admin.php.
 */
class Admin
{
    /**
     * Mismo umbral que Usuario::MAX_INTENTOS_LOGIN/BLOQUEO_MINUTOS: 5
     * intentos fallidos seguidos bloquean la cuenta 15 minutos. Antes este
     * login no tenía ningún límite — y ahora un admin puede confirmar
     * pagos (activar Barrio/Pro gratis) y suspender cuentas, así que esa
     * sola contraseña sin fuerza-bruta-protegida era el camino más corto
     * para regalar planes pagos sin pagar un peso.
     */
    private const MAX_INTENTOS_LOGIN = 5;
    private const BLOQUEO_MINUTOS = 15;

    public static function crear(string $nombre, string $correo, string $password): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO admins (nombre, correo, password_hash) VALUES (:nombre, :correo, :password_hash)'
        );
        $stmt->execute([
            'nombre'        => $nombre,
            'correo'        => $correo,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function buscarPorCorreo(string $correo): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM admins WHERE correo = :correo');
        $stmt->execute(['correo' => $correo]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM admins WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function bloqueado(array $admin): bool
    {
        return !empty($admin['bloqueado_hasta']) && (string) $admin['bloqueado_hasta'] > date('Y-m-d H:i:s');
    }

    public static function registrarIntentoFallido(int $id): void
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare('UPDATE admins SET intentos_fallidos = intentos_fallidos + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $stmt = $pdo->prepare('SELECT intentos_fallidos FROM admins WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $intentos = (int) ($stmt->fetch()['intentos_fallidos'] ?? 0);

        if ($intentos >= self::MAX_INTENTOS_LOGIN) {
            $hasta = date('Y-m-d H:i:s', time() + self::BLOQUEO_MINUTOS * 60);
            $stmt = $pdo->prepare('UPDATE admins SET bloqueado_hasta = :hasta WHERE id = :id');
            $stmt->execute(['hasta' => $hasta, 'id' => $id]);
        }
    }

    public static function registrarLoginExitoso(int $id): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE admins SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}
