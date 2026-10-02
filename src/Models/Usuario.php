<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Quién entra al panel. 'dueno' administra todo su negocio (todas sus
 * sedes, colaboradores, ajustes). 'colaborador' solo entra a las sedes
 * que se le asignen (usuario_sedes) y el panel le esconde los ajustes
 * de negocio (ver App\Auth::exigirDueno()).
 */
class Usuario
{
    private const MAX_INTENTOS_LOGIN = 5;
    private const BLOQUEO_MINUTOS = 15;

    public static function crear(int $negocioId, string $nombre, string $whatsapp, string $password, string $rol = 'dueno'): int
    {
        if (!in_array($rol, ['dueno', 'colaborador'], true)) {
            $rol = 'colaborador';
        }

        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (negocio_id, nombre, whatsapp, password_hash, rol)
             VALUES (:negocio_id, :nombre, :whatsapp, :password_hash, :rol)'
        );
        $stmt->execute([
            'negocio_id'    => $negocioId,
            'nombre'        => $nombre,
            'whatsapp'      => $whatsapp,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'rol'           => $rol,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Incluye negocios.suspendido, para que Auth pueda bloquear el login de una cuenta suspendida. */
    public static function buscarPorWhatsapp(string $whatsapp): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT u.*, n.suspendido AS negocio_suspendido FROM usuarios u
             JOIN negocios n ON n.id = u.negocio_id
             WHERE u.whatsapp = :whatsapp'
        );
        $stmt->execute(['whatsapp' => $whatsapp]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorCorreo(string $correo): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM usuarios WHERE correo = :correo');
        $stmt->execute(['correo' => $correo]);
        return $stmt->fetch() ?: null;
    }

    public static function guardarCorreo(int $id, ?string $correo): bool
    {
        try {
            $stmt = Database::conexion()->prepare('UPDATE usuarios SET correo = :correo WHERE id = :id');
            $stmt->execute(['correo' => $correo === '' ? null : $correo, 'id' => $id]);
            return true;
        } catch (\PDOException $e) {
            return false; // correo duplicado (uniq_usuarios_correo)
        }
    }

    public static function cambiarPassword(int $id, string $password): void
    {
        $stmt = Database::conexion()->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    /**
     * Genera un token de recuperación válido por 1 hora y lo devuelve en
     * claro (va en el enlace). En la base solo queda su SHA-256: si algún
     * día se filtra un backup, los tokens vigentes no sirven para tomar
     * cuentas — igual que una contraseña, el token real nunca se guarda.
     */
    public static function generarTokenReset(int $id): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = Database::conexion()->prepare(
            'UPDATE usuarios SET reset_token = :token, reset_token_expira = :expira WHERE id = :id'
        );
        $stmt->execute([
            'token'  => hash('sha256', $token),
            'expira' => date('Y-m-d H:i:s', time() + 3600),
            'id'     => $id,
        ]);
        return $token;
    }

    /**
     * Compara contra date('Y-m-d H:i:s') en PHP, no NOW() de SQL: el server
     * de la app corre en America/Bogota (ver src/bootstrap.php) pero MySQL
     * suele correr en UTC, así que NOW() desfasaría la expiración 5 horas.
     * Mismo patrón que bloqueado() más abajo.
     */
    public static function buscarPorTokenReset(string $token): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM usuarios WHERE reset_token = :token AND reset_token_expira > :ahora'
        );
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $stmt->execute(['token' => hash('sha256', $token), 'ahora' => date('Y-m-d H:i:s')]);
        return $stmt->fetch() ?: null;
    }

    public static function restablecerPassword(int $id, string $password): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE usuarios SET password_hash = :hash, reset_token = NULL, reset_token_expira = NULL,
             intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id'
        );
        $stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorIdYNegocio(int $id, int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM usuarios WHERE id = :id AND negocio_id = :negocio_id');
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
        return $stmt->fetch() ?: null;
    }

    /** Colaboradores del negocio (no incluye al/los dueño(s)). */
    public static function listarColaboradores(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT * FROM usuarios WHERE negocio_id = :negocio_id AND rol = 'colaborador' ORDER BY nombre ASC"
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetchAll();
    }

    public static function eliminar(int $id, int $negocioId): void
    {
        $stmt = Database::conexion()->prepare(
            "DELETE FROM usuarios WHERE id = :id AND negocio_id = :negocio_id AND rol = 'colaborador'"
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }

    /** @return array<int, int> IDs de sede a las que puede entrar el colaborador. */
    public static function sedeIdsAsignadas(int $usuarioId): array
    {
        $stmt = Database::conexion()->prepare('SELECT sede_id FROM usuario_sedes WHERE usuario_id = :usuario_id');
        $stmt->execute(['usuario_id' => $usuarioId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'sede_id'));
    }

    /** Reemplaza por completo las sedes asignadas a un colaborador. */
    public static function asignarSedes(int $usuarioId, array $sedeIds): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM usuario_sedes WHERE usuario_id = :usuario_id')->execute(['usuario_id' => $usuarioId]);
            $stmt = $pdo->prepare('INSERT INTO usuario_sedes (usuario_id, sede_id) VALUES (:usuario_id, :sede_id)');
            foreach (array_unique(array_map('intval', $sedeIds)) as $sedeId) {
                $stmt->execute(['usuario_id' => $usuarioId, 'sede_id' => $sedeId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function tieneAccesoASede(array $usuario, int $sedeId): bool
    {
        if ($usuario['rol'] === 'dueno') {
            $stmt = Database::conexion()->prepare('SELECT id FROM sedes WHERE id = :id AND negocio_id = :negocio_id');
            $stmt->execute(['id' => $sedeId, 'negocio_id' => $usuario['negocio_id']]);
            return $stmt->fetch() !== false;
        }

        $stmt = Database::conexion()->prepare(
            'SELECT 1 FROM usuario_sedes WHERE usuario_id = :usuario_id AND sede_id = :sede_id'
        );
        $stmt->execute(['usuario_id' => $usuario['id'], 'sede_id' => $sedeId]);
        return $stmt->fetch() !== false;
    }

    /** Todos los usuarios que deberían enterarse de algo que pasó en esa sede: el/los dueño(s) + colaboradores asignados. */
    public static function conAccesoASede(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT u.* FROM usuarios u
             JOIN sedes s ON s.negocio_id = u.negocio_id
             WHERE s.id = :sede_id AND u.rol = 'dueno'
             UNION
             SELECT u.* FROM usuarios u
             JOIN usuario_sedes us ON us.usuario_id = u.id
             WHERE us.sede_id = :sede_id2"
        );
        $stmt->execute(['sede_id' => $sedeId, 'sede_id2' => $sedeId]);
        return $stmt->fetchAll();
    }

    public static function bloqueado(array $usuario): bool
    {
        return !empty($usuario['bloqueado_hasta']) && (string) $usuario['bloqueado_hasta'] > date('Y-m-d H:i:s');
    }

    public static function registrarIntentoFallido(int $id): void
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $stmt = $pdo->prepare('SELECT intentos_fallidos FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $intentos = (int) ($stmt->fetch()['intentos_fallidos'] ?? 0);

        if ($intentos >= self::MAX_INTENTOS_LOGIN) {
            $hasta = date('Y-m-d H:i:s', time() + self::BLOQUEO_MINUTOS * 60);
            $stmt = $pdo->prepare('UPDATE usuarios SET bloqueado_hasta = :hasta WHERE id = :id');
            $stmt->execute(['hasta' => $hasta, 'id' => $id]);
        }
    }

    public static function registrarLoginExitoso(int $id): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}
