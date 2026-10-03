<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Catálogo compartido de códigos de barras entre las tiendas Veci (ver
 * database/migrations/2026-10-03_24_codigos_barras.sql). Solo guarda cómo se
 * llama el producto: nunca precios, costos ni de qué tienda vino.
 */
class CodigoBarras
{
    /**
     * Solo se comparten códigos de producto reales: GTIN de 8, 12, 13 o 14
     * dígitos con el dígito de control correcto. Los de "circulación
     * restringida" son de uso interno (los de la balanza, que traen el peso
     * o el precio adentro, y los que se inventa cada tienda): en otra tienda
     * significan otra cosa, así que no se comparten.
     */
    public static function esCompartible(string $codigo): bool
    {
        if (preg_match('/^(\d{8}|\d{12,14})$/', $codigo) !== 1) {
            return false;
        }
        $interno = match (strlen($codigo)) {
            8       => in_array($codigo[0], ['0', '2'], true),      // RCN-8
            12      => in_array($codigo[0], ['2', '4'], true),      // UPC-A: peso variable y uso en tienda
            13      => $codigo[0] === '2',                          // 20–29: uso interno
            default => $codigo[1] === '2',                          // GTIN-14 que envuelve uno interno
        };

        return !$interno && self::digitoControlValido($codigo);
    }

    /** Dígito de control GS1: pesos 3 y 1 alternados desde la derecha. */
    public static function digitoControlValido(string $codigo): bool
    {
        $digitos = array_map('intval', str_split($codigo));
        $control = array_pop($digitos);
        $suma = 0;
        foreach (array_reverse($digitos) as $i => $d) {
            $suma += $d * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - $suma % 10) % 10 === $control;
    }

    /**
     * Una tienda le puso este nombre a este código: se anota (o se
     * actualiza con el nombre más reciente) y se cuenta una vez más.
     */
    public static function registrar(string $codigo, string $nombre): void
    {
        $nombre = trim($nombre);
        if ($nombre === '' || !self::esCompartible($codigo)) {
            return;
        }
        Database::conexion()->prepare(
            'INSERT INTO codigos_barras (codigo, nombre, veces_usado, actualizado_en) VALUES (:c, :n, 1, NOW())
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), veces_usado = veces_usado + 1, actualizado_en = NOW()'
        )->execute(['c' => $codigo, 'n' => mb_substr($nombre, 0, 120)]);
    }

    /** Cómo lo llaman otras tiendas, o null si nadie lo ha usado. */
    public static function sugerencia(string $codigo): ?string
    {
        if (!self::esCompartible($codigo)) {
            return null;
        }
        $stmt = Database::conexion()->prepare('SELECT nombre FROM codigos_barras WHERE codigo = :c');
        $stmt->execute(['c' => $codigo]);
        $nombre = $stmt->fetchColumn();

        return $nombre !== false ? (string) $nombre : null;
    }
}
