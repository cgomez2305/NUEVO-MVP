<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Reseñas de clientes que de verdad compraron (ver database/migrations/
 * 2026-10-03_07_resenas.sql): cada una nace de un pedido entregado o una
 * cita completada, con un enlace de un solo uso que el negocio manda por
 * WhatsApp. La tienda solo muestra el promedio cuando hay suficientes.
 */
class Resena
{
    /** Con menos de esto, un promedio no dice nada (y una sola mala hunde). */
    public const MINIMO_PARA_MOSTRAR = 3;

    /**
     * El enlace de la reseña de este pedido o cita: lo crea la primera vez
     * y lo reutiliza después (pedirla dos veces manda el mismo enlace).
     */
    public static function pedir(int $negocioId, int $sedeId, int $clienteId, ?int $pedidoId, ?int $citaId): array
    {
        $existente = self::buscarPorOrigen($pedidoId, $citaId);
        if ($existente !== null) {
            return $existente;
        }
        Database::conexion()->prepare(
            'INSERT INTO resenas (negocio_id, sede_id, cliente_id, pedido_id, cita_id, token)
             VALUES (:n, :s, :c, :p, :ci, :t)'
        )->execute([
            'n' => $negocioId, 's' => $sedeId, 'c' => $clienteId,
            'p' => $pedidoId, 'ci' => $citaId, 't' => bin2hex(random_bytes(16)),
        ]);

        return (array) self::buscarPorOrigen($pedidoId, $citaId);
    }

    public static function buscarPorOrigen(?int $pedidoId, ?int $citaId): ?array
    {
        $stmt = Database::conexion()->prepare(
            $pedidoId !== null ? 'SELECT * FROM resenas WHERE pedido_id = :id' : 'SELECT * FROM resenas WHERE cita_id = :id'
        );
        $stmt->execute(['id' => $pedidoId ?? $citaId]);

        return $stmt->fetch() ?: null;
    }

    public static function buscarPorToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = Database::conexion()->prepare(
            'SELECT r.*, cl.nombre AS cliente_nombre FROM resenas r
             JOIN clientes cl ON cl.id = r.cliente_id WHERE r.token = :t'
        );
        $stmt->execute(['t' => $token]);

        return $stmt->fetch() ?: null;
    }

    /** Se responde una sola vez: la segunda vez no cambia nada. */
    public static function responder(int $id, int $estrellas, string $comentario): bool
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE resenas SET estrellas = :e, comentario = :c, respondida_en = NOW()
             WHERE id = :id AND respondida_en IS NULL'
        );
        $stmt->execute([
            'e'  => max(1, min(5, $estrellas)),
            'c'  => ($c = mb_substr(trim($comentario), 0, 400)) !== '' ? $c : null,
            'id' => $id,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Promedio, cuántas y cuántas de cada estrella (todas las sedes del
     * negocio: la reputación es del negocio).
     *
     * @return array{promedio: float, total: int, por_estrella: array<int, int>}
     */
    public static function resumen(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT estrellas, COUNT(*) AS n FROM resenas
             WHERE negocio_id = :n AND respondida_en IS NOT NULL GROUP BY estrellas'
        );
        $stmt->execute(['n' => $negocioId]);
        $porEstrella = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $total = 0;
        $suma = 0;
        foreach ($stmt->fetchAll() as $fila) {
            $porEstrella[(int) $fila['estrellas']] = (int) $fila['n'];
            $total += (int) $fila['n'];
            $suma += (int) $fila['estrellas'] * (int) $fila['n'];
        }

        return ['promedio' => $total > 0 ? round($suma / $total, 1) : 0.0, 'total' => $total, 'por_estrella' => $porEstrella];
    }

    /** @return array<int, array<string, mixed>> */
    public static function listar(int $negocioId, int $limite = 50): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT r.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono
             FROM resenas r JOIN clientes cl ON cl.id = r.cliente_id
             WHERE r.negocio_id = :n AND r.respondida_en IS NOT NULL
             ORDER BY r.respondida_en DESC LIMIT {$limite}"
        );
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetchAll();
    }

    /** Pedidas y sin responder todavía (para "esperando respuesta"). */
    public static function pendientes(int $negocioId): int
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM resenas WHERE negocio_id = :n AND respondida_en IS NULL');
        $stmt->execute(['n' => $negocioId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Las que se ven en la tienda: con comentario visible, de las mejor
     * escritas a las más recientes (todas las estrellas: no se esconden
     * las malas, solo los comentarios que el dueño ocultó por abuso).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function paraTienda(int $negocioId, int $limite = 3): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT r.estrellas, r.comentario, r.respondida_en, cl.nombre AS cliente_nombre
             FROM resenas r JOIN clientes cl ON cl.id = r.cliente_id
             WHERE r.negocio_id = :n AND r.respondida_en IS NOT NULL
               AND r.comentario IS NOT NULL AND r.comentario_oculto = 0
             ORDER BY r.respondida_en DESC LIMIT {$limite}"
        );
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetchAll();
    }

    public static function alternarComentario(int $id, int $negocioId): void
    {
        Database::conexion()->prepare('UPDATE resenas SET comentario_oculto = 1 - comentario_oculto WHERE id = :id AND negocio_id = :n')
            ->execute(['id' => $id, 'n' => $negocioId]);
    }

    /** "Ana R." — en público, nombre y la inicial del apellido. */
    public static function nombrePublico(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre)) ?: [];
        $primero = $partes[0] ?? 'Cliente';

        return isset($partes[1]) ? $primero . ' ' . mb_strtoupper(mb_substr($partes[1], 0, 1)) . '.' : $primero;
    }
}
