<?php

declare(strict_types=1);

// Solo consola: si el hosting llegara a exponer bin/ por web, nadie puede
// dispararlo desde un navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Pone al día la base de datos de una instalación existente: aplica, en
 * orden, los archivos de database/migrations/ que todavía no se han
 * aplicado, y anota cada uno en la tabla `migraciones`. Correrlo dos veces
 * no hace nada la segunda.
 *
 * Uso (después de cada git pull):
 *   php bin/migrar.php
 *
 * Instalaciones nuevas no lo necesitan: database/schema.sql ya trae todo y
 * marca todas las migraciones como aplicadas.
 *
 * Línea base: las migraciones anteriores a este script (2026-10-01_*) se
 * aplicaban a mano. Si la tabla `migraciones` no existe todavía, se dan por
 * aplicadas (una base creada con el schema.sql de ese momento ya las trae)
 * y solo se corren las posteriores.
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Database;

const LINEA_BASE = '2026-10-02';

$pdo = Database::conexion();
$carpeta = __DIR__ . '/../database/migrations';

$existia = (bool) $pdo->query("SHOW TABLES LIKE 'migraciones'")->fetchColumn();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migraciones (
       nombre      VARCHAR(190) PRIMARY KEY,
       aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
     ) ENGINE=InnoDB'
);

$archivos = glob($carpeta . '/*.sql') ?: [];
sort($archivos, SORT_STRING);

if (!$existia) {
    $marcar = $pdo->prepare('INSERT IGNORE INTO migraciones (nombre) VALUES (:nombre)');
    foreach ($archivos as $archivo) {
        if (strcmp(basename($archivo), LINEA_BASE) < 0) {
            $marcar->execute(['nombre' => basename($archivo)]);
        }
    }
}

$aplicadas = array_flip($pdo->query('SELECT nombre FROM migraciones')->fetchAll(PDO::FETCH_COLUMN));
$pendientes = array_values(array_filter($archivos, fn ($a) => !isset($aplicadas[basename($a)])));

if ($pendientes === []) {
    echo "La base de datos ya está al día.\n";
    exit(0);
}

foreach ($pendientes as $archivo) {
    $nombre = basename($archivo);
    echo "Aplicando {$nombre}… ";
    try {
        foreach (sentencias((string) file_get_contents($archivo)) as $sql) {
            $pdo->exec($sql);
        }
        $pdo->prepare('INSERT INTO migraciones (nombre) VALUES (:nombre)')->execute(['nombre' => $nombre]);
        echo "listo.\n";
    } catch (Throwable $e) {
        // MySQL confirma solo los ALTER TABLE: no hay vuelta atrás a medias.
        // Se para aquí para que nadie siga con una base a medio migrar.
        echo "FALLÓ.\n";
        fwrite(STDERR, "  " . $e->getMessage() . "\n");
        fwrite(STDERR, "  Corrige el problema y vuelve a correr php bin/migrar.php\n");
        exit(1);
    }
}

echo count($pendientes) . " migración(es) aplicada(s).\n";

/**
 * Parte un archivo .sql en sentencias: quita comentarios de línea (--) y
 * corta en cada ';' que cierra línea. Las migraciones de Veci no usan
 * procedimientos ni ';' dentro de textos, así que basta.
 *
 * @return array<int, string>
 */
function sentencias(string $sql): array
{
    $lineas = array_filter(
        preg_split('/\R/', $sql) ?: [],
        fn ($linea) => !str_starts_with(ltrim($linea), '--')
    );
    $partes = preg_split('/;\s*$/m', implode("\n", $lineas)) ?: [];

    return array_values(array_filter(array_map('trim', $partes), fn ($p) => $p !== ''));
}
