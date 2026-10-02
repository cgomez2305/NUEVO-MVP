<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Cuántas cuentas nuevas se crearon desde cada IP, para frenar la
 * multiplicación de cupos del plan Gratis: sin esto, cualquiera puede
 * registrar negocios ilimitados (cada uno con sus 50 pedidos/3 análisis
 * de IA gratis por mes) con solo cambiar el número de WhatsApp. No cierra
 * el abuso del todo — alguien con varias IPs reales sigue pudiendo
 * registrar varias cuentas — pero sí el caso más común: un script o una
 * persona registrando muchas cuentas seguidas desde el mismo lugar.
 */
class RegistroIp
{
    /** Máximo de cuentas nuevas que se dejan crear desde la misma IP en lo que dura la ventana (ver demasiadosDesde). */
    private const MAX_REGISTROS = 3;
    private const VENTANA_HORAS = 24;

    public static function registrar(string $ip): void
    {
        $stmt = Database::conexion()->prepare('INSERT INTO registros_ip (ip) VALUES (:ip)');
        $stmt->execute(['ip' => $ip]);
    }

    /** true si esa IP ya creó MAX_REGISTROS cuentas o más en las últimas VENTANA_HORAS horas. */
    public static function demasiadosDesde(string $ip): bool
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM registros_ip WHERE ip = :ip AND creado_en >= :desde'
        );
        $stmt->execute([
            'ip'    => $ip,
            'desde' => (new \DateTimeImmutable('-' . self::VENTANA_HORAS . ' hours'))->format('Y-m-d H:i:s'),
        ]);
        return (int) $stmt->fetchColumn() >= self::MAX_REGISTROS;
    }
}
