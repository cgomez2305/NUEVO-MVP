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

    /** Activa (o cambia) el segundo factor. null lo desactiva. */
    public static function guardarTotp(int $id, ?string $secreto): void
    {
        Database::conexion()->prepare('UPDATE admins SET totp_secreto = :s, totp_ultimo_paso = NULL WHERE id = :id')
            ->execute(['s' => $secreto, 'id' => $id]);
    }

    /**
     * Marca el código como usado solo si nadie usó ese paso o uno posterior
     * (dos peticiones con el mismo código a la vez: solo una pasa).
     */
    public static function consumirPasoTotp(int $id, int $paso): bool
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE admins SET totp_ultimo_paso = :p WHERE id = :id AND (totp_ultimo_paso IS NULL OR totp_ultimo_paso < :p2)'
        );
        $stmt->execute(['p' => $paso, 'p2' => $paso, 'id' => $id]);

        return $stmt->rowCount() === 1;
    }
}
