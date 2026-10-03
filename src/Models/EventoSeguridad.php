<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Bitácora de seguridad de cada negocio: quién entró y desde dónde, y los
 * cambios que más le importan a un atacante (contraseña, colaboradores,
 * llave de cobro, exportar la lista de clientes). El dueño la ve en Mi
 * cuenta para darse cuenta a tiempo si algo no lo hizo él. También quedan
 * las acciones del equipo de Veci sobre su cuenta (transparencia).
 *
 * No es un log de tráfico: solo eventos de cuenta, y se borran a los 180 días.
 */
class EventoSeguridad
{
    private const RETENCION_DIAS = 180;

    /** Texto para el dueño de cada tipo de evento. */
    public const TIPOS = [
        'login'                => 'Inicio de sesión',
        'login_nuevo'          => 'Inicio de sesión desde un celular nuevo',
        'cuenta_frenada'       => 'Muchos intentos fallidos: se frenó el acceso un rato',
        'password_cambiada'    => 'Contraseña cambiada',
        'password_restablecida' => 'Contraseña restablecida con enlace de recuperación',
        'reset_generado'       => 'El equipo de Veci generó un enlace de recuperación',
        'reset_solicitado'     => 'Se pidió un enlace de recuperación por correo',
        'sesiones_cerradas'    => 'Se cerró la sesión en todos los dispositivos',
        'colaborador_creado'   => 'Colaborador agregado',
        'colaborador_eliminado' => 'Colaborador eliminado',
        'colaborador_sedes'    => 'Sedes de un colaborador cambiadas',
        'cobro_cambiado'       => 'Llave Bre-B cambiada',
        'sede_whatsapp'        => 'WhatsApp de una sede cambiado',
        'correo_cambiado'      => 'Correo de recuperación cambiado',
        'exportacion'          => 'Datos exportados a CSV',
        'negocio_suspendido'   => 'El equipo de Veci suspendió la cuenta',
        'negocio_reactivado'   => 'El equipo de Veci reactivó la cuenta',
        'pago_confirmado'      => 'El equipo de Veci confirmó un pago del plan',
        'pago_rechazado'       => 'El equipo de Veci rechazó un pago del plan',
        'admin_login'          => 'Ingreso al panel interno',
        'admin_2fa'            => 'Segundo factor del panel interno activado',
    ];

    public static function registrar(string $tipo, ?int $negocioId, ?int $usuarioId, string $detalle = '', ?int $adminId = null): void
    {
        $pdo = Database::conexion();
        $pdo->prepare(
            'INSERT INTO eventos_seguridad (negocio_id, usuario_id, admin_id, tipo, detalle, ip, descripcion)
             VALUES (:n, :u, :a, :t, :d, :ip, :desc)'
        )->execute([
            'n'    => $negocioId,
            'u'    => $usuarioId,
            'a'    => $adminId,
            't'    => $tipo,
            'd'    => mb_substr($detalle, 0, 255),
            'ip'   => PHP_SAPI === 'cli' ? '' : ip_recortada(),
            'desc' => PHP_SAPI === 'cli' ? 'Consola' : descripcion_navegador(),
        ]);
        if (random_int(1, 100) === 1) {
            $pdo->prepare('DELETE FROM eventos_seguridad WHERE creado_en < :limite')
                ->execute(['limite' => date('Y-m-d H:i:s', time() - self::RETENCION_DIAS * 86400)]);
        }
    }

    /** @return array<int, array<string, mixed>> lo más reciente primero, con el nombre de quién lo hizo */
    public static function recientesDelNegocio(int $negocioId, int $limite = 30): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT e.*, u.nombre AS usuario_nombre FROM eventos_seguridad e
             LEFT JOIN usuarios u ON u.id = e.usuario_id AND u.negocio_id = e.negocio_id
             WHERE e.negocio_id = :n ORDER BY e.creado_en DESC, e.id DESC LIMIT ' . max(1, min(100, $limite))
        );
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public static function recientesDeAdmins(int $limite = 30): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT e.*, a.nombre AS admin_nombre, n.nombre AS negocio_nombre FROM eventos_seguridad e
             JOIN admins a ON a.id = e.admin_id
             LEFT JOIN negocios n ON n.id = e.negocio_id
             ORDER BY e.creado_en DESC, e.id DESC LIMIT ' . max(1, min(100, $limite))
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
