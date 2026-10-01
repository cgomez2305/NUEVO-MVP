<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Cuenta los usos reales de la API de Claude para "la IA arma tu catálogo"
 * (ver src/Services/ExtractorMenu.php), para hacer cumplir
 * planes.limite_ia_mes del plan Gratis. Solo se registra una fila cuando
 * sí se intentó la llamada real — no cuando ya se usó el catálogo de
 * ejemplo (sea por falta de llave configurada o por límite alcanzado).
 */
class UsoIA
{
    public static function registrar(int $negocioId, int $sedeId): void
    {
        $stmt = Database::conexion()->prepare(
            'INSERT INTO usos_ia (negocio_id, sede_id) VALUES (:negocio_id, :sede_id)'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'sede_id' => $sedeId]);
    }

    /** Usos del mes calendario en curso, sumados entre todas las sedes del negocio. */
    public static function contarEsteMesPorNegocio(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) AS total FROM usos_ia WHERE negocio_id = :negocio_id AND creado_en >= :desde'
        );
        $stmt->execute([
            'negocio_id' => $negocioId,
            'desde'      => (new \DateTimeImmutable('first day of this month midnight'))->format('Y-m-d H:i:s'),
        ]);
        return (int) $stmt->fetch()['total'];
    }
}
