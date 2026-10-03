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
    private static function textoPlano(string $valor): string
    {
        $valor = strip_tags($valor);
        $valor = preg_replace('/[\x00-\x1F\x7F]+/u', '', $valor) ?? '';
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

        return mb_substr($valor, 0, 80);
    }
}
