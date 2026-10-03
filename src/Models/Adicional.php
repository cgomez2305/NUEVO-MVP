<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Adicionales que el cliente suma al reservar (barba, cejas, mascarilla):
 * precio y minutos extra (ver migrations/2026-10-03_14_profesionales.sql).
 * La cita guarda una copia en cita_adicionales y ya trae el precio y la
 * duración totales, así las sumas de siempre no cambian.
 */
class Adicional
{
    public static function crear(int $sedeId, string $nombre, int $precio, int $duracionMin, ?int $servicioId): void
    {
        if ($servicioId !== null && Servicio::buscar($servicioId, $sedeId) === null) {
            $servicioId = null;
        }
        Database::conexion()->prepare(
            'INSERT INTO adicionales (sede_id, servicio_id, nombre, precio, duracion_min) VALUES (:s, :sv, :n, :p, :d)'
        )->execute([
            's' => $sedeId, 'sv' => $servicioId, 'n' => mb_substr($nombre, 0, 80),
            'p' => max(0, $precio), 'd' => max(0, min(240, $duracionMin)),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function listar(int $sedeId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT a.*, s.nombre AS servicio_nombre FROM adicionales a LEFT JOIN servicios s ON s.id = a.servicio_id
             WHERE a.sede_id = :s ORDER BY a.activo DESC, a.nombre'
        );
        $stmt->execute(['s' => $sedeId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> los activos que se ofrecen con este servicio */
    public static function paraServicio(int $sedeId, int $servicioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM adicionales WHERE sede_id = :s AND activo = 1 AND (servicio_id IS NULL OR servicio_id = :sv) ORDER BY precio, nombre'
        );
        $stmt->execute(['s' => $sedeId, 'sv' => $servicioId]);

        return $stmt->fetchAll();
    }

    /**
     * Los adicionales elegidos que de verdad aplican a este servicio (ids
     * que llegan por URL o formulario: se valida cada uno).
     *
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public static function elegidos(int $sedeId, int $servicioId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        return array_values(array_filter(self::paraServicio($sedeId, $servicioId), fn ($a) => in_array((int) $a['id'], $ids, true)));
    }

    /** "1,3,7" (de la URL) → [1, 3, 7] */
    public static function idsDesdeTexto(string $texto): array
    {
        return array_slice(array_values(array_filter(array_map('intval', explode(',', $texto)), fn ($id) => $id > 0)), 0, 10);
    }

    public static function alternar(int $id, int $sedeId): void
    {
        Database::conexion()->prepare('UPDATE adicionales SET activo = NOT activo WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $id, 's' => $sedeId]);
    }

    public static function eliminar(int $id, int $sedeId): void
    {
        Database::conexion()->prepare('DELETE FROM adicionales WHERE id = :id AND sede_id = :s')
            ->execute(['id' => $id, 's' => $sedeId]);
    }

    /** @param array<int, array<string, mixed>> $adicionales */
    public static function guardarEnCita(int $citaId, array $adicionales): void
    {
        $insertar = Database::conexion()->prepare(
            'INSERT INTO cita_adicionales (cita_id, adicional_id, nombre, precio, duracion_min) VALUES (:c, :a, :n, :p, :d)'
        );
        foreach ($adicionales as $a) {
            $insertar->execute(['c' => $citaId, 'a' => (int) $a['id'], 'n' => $a['nombre'], 'p' => (int) $a['precio'], 'd' => (int) $a['duracion_min']]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function deCita(int $citaId): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM cita_adicionales WHERE cita_id = :c ORDER BY id');
        $stmt->execute(['c' => $citaId]);

        return $stmt->fetchAll();
    }
}
