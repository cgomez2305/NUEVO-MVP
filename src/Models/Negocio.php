<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Un negocio es la marca: agrupa usuarios (quién entra al panel) y sedes
 * (dónde vende). Lo que antes vivía aquí uno por uno (tienda pública,
 * horario, catálogo, login, Bre-B) ahora vive en Usuario y Sede.
 */
class Negocio
{
    public static function crear(string $nombre, string $tipoNegocio = 'pedidos'): int
    {
        if (!in_array($tipoNegocio, ['pedidos', 'reservas'], true)) {
            $tipoNegocio = 'pedidos';
        }

        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO negocios (nombre, tipo_negocio) VALUES (:nombre, :tipo_negocio)'
        );
        $stmt->execute(['nombre' => $nombre, 'tipo_negocio' => $tipoNegocio]);

        return (int) $pdo->lastInsertId();
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM negocios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
