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

    /**
     * Para el panel interno: todos los negocios con su cantidad de sedes y
     * usuarios, más recientes primero. $busqueda filtra por nombre del
     * negocio o WhatsApp de cualquiera de sus usuarios.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarTodos(string $busqueda = ''): array
    {
        $sql = "SELECT n.*,
                  (SELECT COUNT(*) FROM sedes s WHERE s.negocio_id = n.id) AS total_sedes,
                  (SELECT COUNT(*) FROM usuarios u WHERE u.negocio_id = n.id) AS total_usuarios
                FROM negocios n";
        $parametros = [];

        if ($busqueda !== '') {
            $sql .= ' WHERE n.nombre LIKE :busqueda
                       OR EXISTS (SELECT 1 FROM usuarios u WHERE u.negocio_id = n.id AND u.whatsapp LIKE :busqueda2)';
            $parametros['busqueda'] = '%' . $busqueda . '%';
            $parametros['busqueda2'] = '%' . $busqueda . '%';
        }

        $sql .= ' ORDER BY n.creado_en DESC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public static function suspender(int $id): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE negocios SET suspendido = 1, suspendido_en = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function reactivar(int $id): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE negocios SET suspendido = 0, suspendido_en = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}
