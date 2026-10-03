<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Lo que el sitio web manda a /registro: utm_*, plan y ciclo de interés,
 * modo (pedidos/reservas) y código de oferta. Se recuerda en la sesión
 * desde que llega (así sobrevive si el formulario vuelve con un error) y se
 * guarda con el negocio al crearlo.
 *
 * Seguridad: plan, ciclo y modo solo de una lista blanca; la oferta solo
 * con letras y números; los utm_* como texto plano de hasta 80 caracteres.
 * Cualquier otro valor se ignora en silencio.
 */
class OrigenRegistro
{
    public const UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    public const PLANES = ['gratis', 'barrio', 'pro'];
    public const CICLOS = ['mensual', 'anual'];
    public const MODOS = ['pedidos', 'reservas'];

    /** Lee los parámetros de la URL y los suma a los que ya traía la sesión. */
    public static function captar(array $get): void
    {
        $origen = (array) ($_SESSION['registro_origen'] ?? []);
        foreach (self::UTM as $clave) {
            $valor = isset($get[$clave]) && is_string($get[$clave]) ? self::textoPlano($get[$clave]) : '';
            if ($valor !== '') {
                $origen[$clave] = $valor;
            }
        }
        foreach (['plan' => self::PLANES, 'ciclo' => self::CICLOS, 'modo' => self::MODOS] as $clave => $permitidos) {
            $valor = isset($get[$clave]) && is_string($get[$clave]) ? strtolower(trim($get[$clave])) : '';
            if (in_array($valor, $permitidos, true)) {
                $origen[$clave] = $valor;
            }
        }
        $oferta = isset($get['oferta']) && is_string($get['oferta']) ? OfertaPlan::normalizarCodigo($get['oferta']) : '';
        if ($oferta !== '') {
            $origen['oferta'] = $oferta;
        }
        $_SESSION['registro_origen'] = $origen;
    }

    /** @return array<string, string> lo captado hasta ahora (vacío si nada) */
    public static function actual(): array
    {
        return (array) ($_SESSION['registro_origen'] ?? []);
    }

    /** Guarda el origen con el negocio recién creado y lo saca de la sesión. */
    public static function guardar(int $negocioId): void
    {
        $origen = self::actual();
        unset($_SESSION['registro_origen']);
        if ($origen === []) {
            return;
        }
        Database::conexion()->prepare(
            'INSERT INTO negocio_origen (negocio_id, utm_source, utm_medium, utm_campaign, utm_term, utm_content,
                                         plan_interes, ciclo_interes, modo_interes, oferta_codigo)
             VALUES (:n, :us, :um, :uc, :ut, :uco, :p, :c, :m, :o)'
        )->execute([
            'n'   => $negocioId,
            'us'  => $origen['utm_source'] ?? null,
            'um'  => $origen['utm_medium'] ?? null,
            'uc'  => $origen['utm_campaign'] ?? null,
            'ut'  => $origen['utm_term'] ?? null,
            'uco' => $origen['utm_content'] ?? null,
            'p'   => $origen['plan'] ?? null,
            'c'   => $origen['ciclo'] ?? null,
            'm'   => $origen['modo'] ?? null,
            'o'   => $origen['oferta'] ?? null,
        ]);
    }

    public static function deNegocio(int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM negocio_origen WHERE negocio_id = :n');
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetch() ?: null;
    }

    /** Sin etiquetas, sin caracteres de control, sin espacios de sobra, máximo 80. */
    /**
     * Reporte de campañas para el panel interno: negocios registrados en los
     * últimos $dias días (null = desde siempre) agrupados por fuente, medio y
     * campaña, con cuántos publicaron su tienda, cuántos pagaron un plan y
     * cuántos canjearon una oferta. Los que llegaron sin utm salen juntos
     * como directos (fuente null). Cuenta negocios, no visitas: lo que pasó
     * antes del registro lo mide el sitio, no la app.
     *
     * @return list<array<string, mixed>>
     */
    public static function reporte(?int $dias): array
    {
        $desde = $dias !== null ? date('Y-m-d H:i:s', strtotime('-' . $dias . ' days')) : '1970-01-01 00:00:00';
        $stmt = Database::conexion()->prepare(
            "SELECT o.utm_source AS fuente, o.utm_medium AS medio, o.utm_campaign AS campana,
                    COUNT(*) AS registros,
                    SUM(EXISTS(SELECT 1 FROM sedes s WHERE s.negocio_id = n.id AND s.publicada = 1)) AS publicaron,
                    SUM(EXISTS(SELECT 1 FROM pagos_plan p WHERE p.negocio_id = n.id AND p.concepto = 'plan'
                                  AND p.confirmado_en IS NOT NULL AND p.cancelado_en IS NULL)) AS pagaron,
                    SUM(EXISTS(SELECT 1 FROM ofertas_canjes c WHERE c.negocio_id = n.id AND c.estado = 'confirmado')) AS con_oferta,
                    SUM(o.oferta_codigo IS NOT NULL) AS llegaron_con_codigo,
                    SUM(o.plan_interes = 'barrio') AS interes_barrio,
                    SUM(o.plan_interes = 'pro') AS interes_pro,
                    MAX(n.creado_en) AS ultimo
               FROM negocios n
               LEFT JOIN negocio_origen o ON o.negocio_id = n.id
              WHERE n.creado_en >= :desde
              GROUP BY o.utm_source, o.utm_medium, o.utm_campaign
              ORDER BY registros DESC, ultimo DESC
              LIMIT 200"
        );
        $stmt->execute(['desde' => $desde]);

        return array_map(static function (array $fila): array {
            foreach (['registros', 'publicaron', 'pagaron', 'con_oferta', 'llegaron_con_codigo', 'interes_barrio', 'interes_pro'] as $campo) {
                $fila[$campo] = (int) $fila[$campo];
            }

            return $fila;
        }, $stmt->fetchAll());
    }

    private static function textoPlano(string $valor): string
    {
        $valor = strip_tags($valor);
        $valor = preg_replace('/[\x00-\x1F\x7F]+/u', '', $valor) ?? '';
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

        return mb_substr($valor, 0, 80);
    }
}
