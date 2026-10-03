<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Cuentas de la demo pública "Sube tu foto sin cuenta" (/api/menu-demo):
 * cada lectura queda en demo_ia_usos con los tokens que gastó, para cortar
 * el día al llegar a un número de lecturas o de tokens. No se guarda la
 * foto ni la IP: solo su huella (HMAC).
 */
class DemoIA
{
    /** Lo que se cuenta por una lectura que todavía no termina (su gasto aún no se sabe). */
    private const TOKENS_EN_CURSO = 6000;

    public static function lecturasAlDia(): int
    {
        return max(0, (int) config('demo_ia.lecturas_al_dia', 150));
    }

    public static function topeTokensAlDia(): int
    {
        return max(0, (int) config('demo_ia.tope_tokens_al_dia', 1_500_000));
    }

    /**
     * Aparta una lectura si al día le queda cupo; devuelve su id o null.
     * Un candado de la base hace que dos visitas a la vez no pasen las dos
     * por el último cupo.
     */
    public static function reservar(string $ipHash): ?int
    {
        $pdo = Database::conexion();
        $candado = $pdo->query("SELECT GET_LOCK('veci_demo_ia', 10)")->fetchColumn();
        if ((int) $candado !== 1) {
            return null;
        }
        try {
            $hoy = self::delDia();
            if ($hoy['lecturas'] >= self::lecturasAlDia() || $hoy['tokens'] >= self::topeTokensAlDia()) {
                return null;
            }
            $pdo->prepare("INSERT INTO demo_ia_usos (ip_hash, estado) VALUES (:ip, 'en_curso')")->execute(['ip' => $ipHash]);

            return (int) $pdo->lastInsertId();
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('veci_demo_ia')");
        }
    }

    public static function cerrar(int $id, string $estado, int $entrada, int $salida): void
    {
        Database::conexion()->prepare(
            'UPDATE demo_ia_usos SET estado = :e, tokens_entrada = :en, tokens_salida = :sa WHERE id = :id'
        )->execute(['e' => mb_substr($estado, 0, 12), 'en' => max(0, $entrada), 'sa' => max(0, $salida), 'id' => $id]);
    }

    /** @return array{lecturas:int, leidas:int, tokens:int} lo usado hoy (las en curso cuentan con su máximo estimado). */
    public static function delDia(): array
    {
        $fila = Database::conexion()->query(
            "SELECT COUNT(*) AS lecturas, COALESCE(SUM(estado = 'ok'), 0) AS leidas,
                    COALESCE(SUM(CASE WHEN estado = 'en_curso' THEN " . self::TOKENS_EN_CURSO . " ELSE tokens_entrada + tokens_salida END), 0) AS tokens
               FROM demo_ia_usos WHERE creado_en >= CURDATE()"
        )->fetch();

        return ['lecturas' => (int) $fila['lecturas'], 'leidas' => (int) $fila['leidas'], 'tokens' => (int) $fila['tokens']];
    }

    /** @return array{lecturas:int, leidas:int, tokens:int} para el panel interno (null = desde siempre). */
    public static function resumen(?int $dias): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT COUNT(*) AS lecturas, COALESCE(SUM(estado = 'ok'), 0) AS leidas,
                    COALESCE(SUM(tokens_entrada + tokens_salida), 0) AS tokens
               FROM demo_ia_usos WHERE creado_en >= :desde"
        );
        $stmt->execute(['desde' => $dias !== null ? date('Y-m-d H:i:s', strtotime('-' . $dias . ' days')) : '1970-01-01 00:00:00']);
        $fila = $stmt->fetch();

        return ['lecturas' => (int) $fila['lecturas'], 'leidas' => (int) $fila['leidas'], 'tokens' => (int) $fila['tokens']];
    }
}
