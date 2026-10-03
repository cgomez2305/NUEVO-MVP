<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Limitador de tasa genérico: cuántas veces se hizo una acción con una
 * misma clave (normalmente la IP, a veces IP+sede) dentro de una ventana
 * de tiempo. Cubre los abusos que no tienen que ver con adivinar una
 * contraseña de una cuenta concreta (eso ya lo hace el bloqueo por cuenta
 * de Usuario/Admin) sino con repetir una acción pública muchas veces:
 *
 *  - registro:   multicuenta para multiplicar cupos de Gratis.
 *  - pedido/cita/lista_espera: inundar de pedidos falsos la tienda de un
 *                negocio — en Gratis eso le agotaría el cupo del mes y le
 *                bloquearía la tienda a sus clientes reales.
 *  - login:      probar contraseñas contra muchas cuentas distintas desde
 *                la misma IP (el bloqueo por cuenta no lo ve).
 *  - reset:      bombardear de correos de recuperación a alguien.
 *
 * Las filas viejas se borran solas (ver registrar): la tabla nunca crece
 * más allá de la ventana más larga.
 */
class LimiteTasa
{
    /** Ventana más larga usada por cualquier acción; lo que sea más viejo ya no cuenta para nada. */
    private const RETENCION_HORAS = 24;

    /** true si la clave ya hizo la acción $maximo veces o más en los últimos $ventanaSegundos. */
    public static function excedido(string $accion, string $clave, int $maximo, int $ventanaSegundos): bool
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM limites_tasa WHERE accion = :accion AND clave = :clave AND creado_en >= :desde'
        );
        $stmt->execute([
            'accion' => $accion,
            'clave'  => $clave,
            'desde'  => date('Y-m-d H:i:s', time() - $ventanaSegundos),
        ]);
        return (int) $stmt->fetchColumn() >= $maximo;
    }

    /** Borra el conteo (tras un login correcto, los fallos anteriores ya no cuentan). */
    public static function limpiar(string $accion, string $clave): void
    {
        Database::conexion()->prepare('DELETE FROM limites_tasa WHERE accion = :accion AND clave = :clave')
            ->execute(['accion' => $accion, 'clave' => $clave]);
    }

    public static function registrar(string $accion, string $clave): void
    {
        $pdo = Database::conexion();
        $pdo->prepare('INSERT INTO limites_tasa (accion, clave) VALUES (:accion, :clave)')
            ->execute(['accion' => $accion, 'clave' => $clave]);

        // Limpieza perezosa: ~1 de cada 50 inserciones borra lo vencido, sin
        // necesitar un cron más.
        if (random_int(1, 50) === 1) {
            $pdo->prepare('DELETE FROM limites_tasa WHERE creado_en < :limite')
                ->execute(['limite' => date('Y-m-d H:i:s', time() - self::RETENCION_HORAS * 3600)]);
        }
    }
}
