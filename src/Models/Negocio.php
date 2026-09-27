<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Negocio
{
    public static function crear(string $nombre, string $whatsapp, string $password, string $tipoNegocio = 'pedidos'): int
    {
        if (!in_array($tipoNegocio, ['pedidos', 'reservas'], true)) {
            $tipoNegocio = 'pedidos';
        }

        $pdo = Database::conexion();

        $stmt = $pdo->prepare(
            'INSERT INTO negocios (slug, nombre, whatsapp, password_hash, inicial, tipo_negocio)
             VALUES (:slug, :nombre, :whatsapp, :password_hash, :inicial, :tipo_negocio)'
        );
        $stmt->execute([
            'slug'          => self::slugUnico($nombre),
            'nombre'        => $nombre,
            'whatsapp'      => $whatsapp,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'inicial'       => mb_strtoupper(mb_substr($nombre, 0, 1)),
            'tipo_negocio'  => $tipoNegocio,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function buscarPorWhatsapp(string $whatsapp): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM negocios WHERE whatsapp = :whatsapp');
        $stmt->execute(['whatsapp' => $whatsapp]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM negocios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorSlugPublicada(string $slug): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM negocios WHERE slug = :slug AND publicada = 1'
        );
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    }

    public static function guardarFotoMenu(int $id, string $rutaRelativa): void
    {
        $stmt = Database::conexion()->prepare('UPDATE negocios SET menu_foto = :ruta WHERE id = :id');
        $stmt->execute(['ruta' => $rutaRelativa, 'id' => $id]);
    }

    public static function guardarLlaveBreB(int $id, string $tipo, string $valor): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE negocios SET llave_breb_tipo = :tipo, llave_breb_valor = :valor WHERE id = :id'
        );
        $stmt->execute(['tipo' => $tipo, 'valor' => $valor, 'id' => $id]);
    }

    public static function publicar(int $id): void
    {
        $stmt = Database::conexion()->prepare('UPDATE negocios SET publicada = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, array{0:string,1:string}> $horario día ISO (1-7) => [inicio, fin] */
    public static function guardarHorario(int $id, array $horario, int $intervaloMin): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE negocios SET horario_atencion = :horario, intervalo_citas_min = :intervalo WHERE id = :id'
        );
        $stmt->execute([
            'horario'   => json_encode($horario, JSON_UNESCAPED_UNICODE),
            'intervalo' => $intervaloMin,
            'id'        => $id,
        ]);
    }

    /** @return array<string, array{0:string,1:string}> */
    public static function horario(array $negocio): array
    {
        if (empty($negocio['horario_atencion'])) {
            return [];
        }
        $decodificado = json_decode((string) $negocio['horario_atencion'], true);
        return is_array($decodificado) ? $decodificado : [];
    }

    /**
     * Arma el JSON de horario a partir de un formulario con campos
     * abierto_1..abierto_7, inicio_1..inicio_7, fin_1..fin_7 (1=lunes..7=domingo).
     *
     * @param array<string, mixed> $post
     * @return array<string, array{0:string,1:string}>
     */
    public static function horarioDesdePost(array $post): array
    {
        $horario = [];
        for ($dia = 1; $dia <= 7; $dia++) {
            if (empty($post["abierto_{$dia}"])) {
                continue;
            }
            $inicio = (string) ($post["inicio_{$dia}"] ?? '');
            $fin = (string) ($post["fin_{$dia}"] ?? '');
            if (preg_match('/^\d{1,2}:\d{2}$/', $inicio) && preg_match('/^\d{1,2}:\d{2}$/', $fin) && $inicio < $fin) {
                $horario[(string) $dia] = [$inicio, $fin];
            }
        }
        return $horario;
    }

    /** @param array<string, mixed> $post */
    public static function intervaloDesdePost(array $post): int
    {
        $intervalo = (int) ($post['intervalo'] ?? 30);
        return in_array($intervalo, [15, 20, 30, 45, 60], true) ? $intervalo : 30;
    }

    private static function existeSlug(string $slug): bool
    {
        $stmt = Database::conexion()->prepare('SELECT id FROM negocios WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() !== false;
    }

    private static function slugUnico(string $nombre): string
    {
        $base = self::normalizarSlug($nombre);
        $slug = $base;
        $sufijo = 1;

        while (self::existeSlug($slug)) {
            $sufijo++;
            $slug = $base . $sufijo;
        }

        return $slug;
    }

    private static function normalizarSlug(string $texto): string
    {
        $mapa = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'ñ' => 'n', 'Ñ' => 'n', 'ü' => 'u', 'Ü' => 'u',
        ];

        $slug = strtr($texto, $mapa);
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '', $slug) ?? '';
        $slug = mb_substr($slug, 0, 40);

        return $slug === '' ? 'negocio' : $slug;
    }
}
