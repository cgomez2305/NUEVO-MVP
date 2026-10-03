<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Qué WhatsApp y qué documento (cédula o NIT) ya tuvieron un negocio en
 * Veci. Solo se guarda un HMAC con la clave del servidor (ver
 * hash_identidad()): con la base sola no se puede saber de quién es, pero
 * sí reconocer el mismo número o documento cuando vuelve. Sirve para las
 * ofertas "solo negocios nuevos" (ver OfertaPlan).
 */
class Identidad
{
    public static function registrar(string $tipo, string $hash, int $negocioId): void
    {
        Database::conexion()->prepare('INSERT IGNORE INTO identidades_negocio (tipo, hash, negocio_id) VALUES (:t, :h, :n)')
            ->execute(['t' => $tipo, 'h' => $hash, 'n' => $negocioId]);
    }

    /** ¿Ese WhatsApp o documento ya estuvo en OTRO negocio (aunque se haya borrado o suspendido)? */
    public static function usadaEnOtroNegocio(string $tipo, string $hash, int $negocioId): bool
    {
        $stmt = Database::conexion()->prepare('SELECT 1 FROM identidades_negocio WHERE tipo = :t AND hash = :h AND negocio_id <> :n LIMIT 1');
        $stmt->execute(['t' => $tipo, 'h' => $hash, 'n' => $negocioId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Cédula o NIT normalizado: sin el dígito de verificación (lo que va
     * después del guion), solo números y sin ceros a la izquierda. null si
     * no parece un documento (5 a 12 dígitos).
     */
    public static function normalizarDocumento(string $documento): ?string
    {
        $documento = explode('-', $documento, 2)[0];
        $digitos = ltrim(preg_replace('/\D+/', '', $documento) ?? '', '0');

        return preg_match('/^\d{5,12}$/', $digitos) === 1 ? $digitos : null;
    }
}
