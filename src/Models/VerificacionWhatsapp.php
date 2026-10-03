<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use App\Services\CodigoWhatsapp;

/**
 * "Confirma que este WhatsApp es tuyo": un código de 6 dígitos por WhatsApp
 * que vence a los 10 minutos y aguanta 5 intentos. En la base solo queda su
 * HMAC. Una verificación correcta vale 30 minutos en la sesión para ese
 * propósito (p. ej. 'oferta': aplicar una oferta de plan).
 */
class VerificacionWhatsapp
{
    private const MINUTOS_CODIGO = 10;
    private const MAX_INTENTOS = 5;
    private const MINUTOS_VALIDA = 30;

    /** 'enviado', 'frenado' (muchos envíos) o 'fallo' (Meta no lo envió). */
    public static function enviar(int $usuarioId, string $telefono, string $proposito): string
    {
        // 3 códigos por hora por usuario y 6 por IP: cada envío cuesta y
        // llega al celular de alguien.
        if (LimiteTasa::excedido('otp_usuario', 'u' . $usuarioId, 3, 3600) || LimiteTasa::excedido('otp_ip', ip_cliente(), 6, 3600)) {
            return 'frenado';
        }
        LimiteTasa::registrar('otp_usuario', 'u' . $usuarioId);
        LimiteTasa::registrar('otp_ip', ip_cliente());

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo = Database::conexion();
        // Un código nuevo anula los anteriores sin usar.
        $pdo->prepare('UPDATE verificaciones_whatsapp SET intentos = :max WHERE usuario_id = :u AND proposito = :p AND verificado_en IS NULL')
            ->execute(['max' => self::MAX_INTENTOS, 'u' => $usuarioId, 'p' => $proposito]);
        $pdo->prepare('INSERT INTO verificaciones_whatsapp (usuario_id, proposito, codigo_hash, expira_en) VALUES (:u, :p, :h, :e)')
            ->execute([
                'u' => $usuarioId, 'p' => $proposito,
                'h' => hash_identidad('otp', $usuarioId . '|' . $proposito . '|' . $codigo),
                'e' => date('Y-m-d H:i:s', time() + self::MINUTOS_CODIGO * 60),
            ]);

        return CodigoWhatsapp::enviar($telefono, $codigo) ? 'enviado' : 'fallo';
    }

    /** true si el código es el último enviado, no vencido y con intentos; lo deja verificado en la sesión. */
    public static function verificar(int $usuarioId, string $proposito, string $codigo): bool
    {
        $codigo = preg_replace('/\D+/', '', $codigo) ?? '';
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT * FROM verificaciones_whatsapp WHERE usuario_id = :u AND proposito = :p AND verificado_en IS NULL
               AND intentos < :max AND expira_en > :ahora ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['u' => $usuarioId, 'p' => $proposito, 'max' => self::MAX_INTENTOS, 'ahora' => date('Y-m-d H:i:s')]);
        $fila = $stmt->fetch();
        if ($fila === false || strlen($codigo) !== 6) {
            return false;
        }
        // El intento se cuenta antes de comparar: adivinar cuesta intentos.
        $pdo->prepare('UPDATE verificaciones_whatsapp SET intentos = intentos + 1 WHERE id = :id')->execute(['id' => $fila['id']]);
        if (!hash_equals((string) $fila['codigo_hash'], hash_identidad('otp', $usuarioId . '|' . $proposito . '|' . $codigo))) {
            return false;
        }
        $pdo->prepare('UPDATE verificaciones_whatsapp SET verificado_en = NOW() WHERE id = :id AND verificado_en IS NULL')->execute(['id' => $fila['id']]);
        $_SESSION['whatsapp_verificado'][$proposito] = ['usuario' => $usuarioId, 'en' => time()];

        return true;
    }

    public static function verificadoEnSesion(int $usuarioId, string $proposito): bool
    {
        $marca = $_SESSION['whatsapp_verificado'][$proposito] ?? null;

        return is_array($marca) && (int) $marca['usuario'] === $usuarioId && time() - (int) $marca['en'] <= self::MINUTOS_VALIDA * 60;
    }

    /** ¿Hay un código enviado, vigente y sin usar? (para mostrar el campo). */
    public static function hayCodigoVigente(int $usuarioId, string $proposito): bool
    {
        $stmt = Database::conexion()->prepare(
            'SELECT 1 FROM verificaciones_whatsapp WHERE usuario_id = :u AND proposito = :p AND verificado_en IS NULL
               AND intentos < :max AND expira_en > :ahora LIMIT 1'
        );
        $stmt->execute(['u' => $usuarioId, 'p' => $proposito, 'max' => self::MAX_INTENTOS, 'ahora' => date('Y-m-d H:i:s')]);

        return $stmt->fetchColumn() !== false;
    }
}
