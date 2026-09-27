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
            ]);
        }

        return self::$instancia;
    }
}
