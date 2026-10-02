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

    /**
     * Color del toldo de la tienda (y de la insignia en el panel). Solo
     * acepta colores de paleta_marca(); cualquier otro valor se ignora.
     */
    public static function actualizarColor(int $id, string $color): bool
    {
        $color = strtoupper(trim($color));
        if (!array_key_exists($color, paleta_marca())) {
            return false;
        }
        $stmt = Database::conexion()->prepare('UPDATE negocios SET color_marca = :color WHERE id = :id');
        $stmt->execute(['color' => $color, 'id' => $id]);
        return true;
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM negocios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Filtros de la lista del panel interno (clave => etiqueta). */
    public const FILTROS_ADMIN = [
        'todos'        => 'Todos',
        'por_cobrar'   => 'Pago por confirmar',
        'pagan'        => 'Pagan plan',
        'sin_publicar' => 'Sin abrir',
        'suspendidos'  => 'Suspendidos',
    ];

    /**
     * Negocios para el panel interno, con lo que el equipo necesita ver de
     * un vistazo: plan, cuántas sedes tienen tienda abierta y si hay un pago
     * esperando confirmación. Busca por nombre o por el WhatsApp de
     * cualquiera de sus usuarios.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarTodos(string $busqueda = '', string $filtro = 'todos'): array
    {
        $sql = "SELECT n.*, p.nombre AS plan_nombre,
                  (SELECT COUNT(*) FROM sedes s WHERE s.negocio_id = n.id) AS total_sedes,
                  (SELECT COUNT(*) FROM sedes s WHERE s.negocio_id = n.id AND s.publicada = 1) AS sedes_publicadas,
                  (SELECT COUNT(*) FROM usuarios u WHERE u.negocio_id = n.id) AS total_usuarios,
                  (SELECT COUNT(*) FROM pagos_plan pp WHERE pp.negocio_id = n.id AND pp.confirmado_en IS NULL) AS pagos_pendientes
                FROM negocios n
                JOIN planes p ON p.id = n.plan_id";
        $condiciones = [];
        $parametros = [];

        if ($busqueda !== '') {
            $condiciones[] = '(n.nombre LIKE :busqueda
                OR EXISTS (SELECT 1 FROM usuarios u WHERE u.negocio_id = n.id AND u.whatsapp LIKE :busqueda2))';
            $parametros['busqueda'] = '%' . $busqueda . '%';
            $parametros['busqueda2'] = '%' . $busqueda . '%';
        }
        $condiciones[] = match ($filtro) {
            'por_cobrar'   => 'EXISTS (SELECT 1 FROM pagos_plan pp WHERE pp.negocio_id = n.id AND pp.confirmado_en IS NULL)',
            'pagan'        => "n.plan_id > 1 AND n.plan_estado = 'activo'",
            'sin_publicar' => 'NOT EXISTS (SELECT 1 FROM sedes s WHERE s.negocio_id = n.id AND s.publicada = 1)',
            'suspendidos'  => 'n.suspendido = 1',
            default        => '1 = 1',
        };

        $sql .= ' WHERE ' . implode(' AND ', $condiciones) . ' ORDER BY n.creado_en DESC';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    /**
     * Cifras de la cabecera del panel interno y conteo de cada filtro.
     *
     * @return array{total:int, abiertos:int, pagan:int, nuevos_semana:int, conteos:array<string,int>}
     */
    public static function resumenAdmin(): array
    {
        $fila = Database::conexion()->query(
            "SELECT
               COUNT(*) AS total,
               SUM(EXISTS (SELECT 1 FROM sedes s WHERE s.negocio_id = n.id AND s.publicada = 1)) AS abiertos,
               SUM(n.plan_id > 1 AND n.plan_estado = 'activo') AS pagan,
               SUM(n.creado_en >= NOW() - INTERVAL 7 DAY) AS nuevos_semana,
               SUM(n.suspendido = 1) AS suspendidos,
               SUM(EXISTS (SELECT 1 FROM pagos_plan pp WHERE pp.negocio_id = n.id AND pp.confirmado_en IS NULL)) AS por_cobrar
             FROM negocios n"
        )->fetch();

        $total = (int) ($fila['total'] ?? 0);
        $abiertos = (int) ($fila['abiertos'] ?? 0);

        return [
            'total'         => $total,
            'abiertos'      => $abiertos,
            'pagan'         => (int) ($fila['pagan'] ?? 0),
            'nuevos_semana' => (int) ($fila['nuevos_semana'] ?? 0),
            'conteos'       => [
                'todos'        => $total,
                'por_cobrar'   => (int) ($fila['por_cobrar'] ?? 0),
                'pagan'        => (int) ($fila['pagan'] ?? 0),
                'sin_publicar' => $total - $abiertos,
                'suspendidos'  => (int) ($fila['suspendidos'] ?? 0),
            ],
        ];
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

    /** Bajar a Gratis es instantáneo y gratis (no hay nada que cobrar ni confirmar) — lo usa /panel/plan cuando ya se está en un plan pago. */
    public static function cambiarAGratis(int $id): void
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE negocios SET plan_id = (SELECT id FROM planes WHERE nombre = 'gratis'),
                                  plan_estado = 'activo', plan_vence_en = NULL
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Baja a Gratis todo negocio cuyo plan pago venció sin que se confirmara
     * un pago nuevo a tiempo (ver bin/revisar_planes.php). Nunca bloquea la
     * tienda: solo vuelve a los límites del plan Gratis.
     *
     * @return int cuántos negocios se degradaron
     */
    public static function degradarVencidos(): int
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE negocios SET plan_id = (SELECT id FROM planes WHERE nombre = 'gratis'),
                                  plan_estado = 'degradado_a_gratis', plan_vence_en = NULL
             WHERE plan_estado = 'activo'
               AND plan_vence_en IS NOT NULL
               AND plan_vence_en < CURDATE()
               AND plan_id != (SELECT id FROM planes WHERE nombre = 'gratis')"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
