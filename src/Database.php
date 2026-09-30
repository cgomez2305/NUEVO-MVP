<?php

declare(strict_types=1);

namespace App;

/**
 * Una sola conexión PDO para toda la petición.
 */
class Database
{
    private static ?\PDO $instancia = null;

    public static function conexion(): \PDO
    {
        if (self::$instancia === null) {
            $config = config('db');

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['name'],
                $config['charset']
            );

            self::$instancia = new \PDO($dsn, $config['user'], $config['pass'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                // El servidor MySQL puede estar en UTC (o en cualquier zona del
                // sistema donde corra el hosting) mientras PHP está fijo en
                // America/Bogota (ver bootstrap.php). Sin esto, NOW()/CURRENT_
                // TIMESTAMP de MySQL y el time()/strtotime() de PHP no cuadran,
                // y cualquier cálculo de "cuánto lleva esperando" sale mal.
                // Bogotá no tiene horario de verano, así que el offset fijo
                // -05:00 es correcto siempre.
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'",
            ]);
        }

        return self::$instancia;
    }
}
