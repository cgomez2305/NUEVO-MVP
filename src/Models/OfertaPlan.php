<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Códigos de oferta para los planes de Veci (no los cupones de cada negocio
 * para sus clientes: esos son App\Models\Cupon). Ejemplo: VECICHAT30, 30%
 * del primer mes de Barrio o Pro con pago mensual, solo negocios nuevos.
 *
 * Reglas (se revisan en el servidor al pedir el primer plan, no al
 * registrarse):
 *  - la oferta está activa, no venció y le queda cupo (los canjes
 *    apartados y confirmados cuentan; los liberados no);
 *  - el negocio nunca ha pagado un plan y no ha usado otra oferta (no son
 *    acumulables);
 *  - solo Barrio o Pro, pago mensual, y solo el primer mes;
 *  - el WhatsApp del dueño y el documento (cédula o NIT) no tuvieron otro
 *    negocio en Veci ni usaron ya una oferta. Se comparan como HMAC
 *    (ver hash_identidad()), nunca en claro.
 * El cupo se cuenta con la oferta bloqueada (FOR UPDATE): 300 personas a la
 * vez no pasan de 300 canjes.
 */
class OfertaPlan
{
    public const PLANES_VALIDOS = ['barrio', 'pro'];

    /** Solo letras y números, en mayúsculas, de 3 a 20; '' si no sirve. */
    public static function normalizarCodigo(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));

        return preg_match('/^[A-Z0-9]{3,20}$/', $codigo) === 1 ? $codigo : '';
    }

    public static function buscarPorCodigo(string $codigo, bool $bloquear = false): ?array
    {
        $codigo = self::normalizarCodigo($codigo);
        if ($codigo === '') {
            return null;
        }
        $stmt = Database::conexion()->prepare('SELECT * FROM ofertas_plan WHERE codigo = :c' . ($bloquear ? ' FOR UPDATE' : ''));
        $stmt->execute(['c' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    public static function buscar(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM ofertas_plan WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Canjes que ocupan cupo (apartados o confirmados). */
    public static function usos(int $ofertaId): int
    {
        $stmt = Database::conexion()->prepare("SELECT COUNT(*) FROM ofertas_canjes WHERE oferta_id = :o AND estado <> 'liberado'");
        $stmt->execute(['o' => $ofertaId]);

        return (int) $stmt->fetchColumn();
    }

    /** 'activa' | 'pausada' | 'vencida' | 'agotada' */
    public static function estado(array $oferta): string
    {
        if ((int) $oferta['activa'] !== 1) {
            return 'pausada';
        }
        if (!empty($oferta['vence_en']) && (string) $oferta['vence_en'] < date('Y-m-d')) {
            return 'vencida';
        }
        if ($oferta['cupo_total'] !== null && self::usos((int) $oferta['id']) >= (int) $oferta['cupo_total']) {
            return 'agotada';
        }

        return 'activa';
    }

    /** Lo que se descuenta del precio de lista del primer mes. */
    public static function descuento(array $oferta, int $precioMes): int
    {
        return (int) round($precioMes * min(100, (int) $oferta['porcentaje']) / 100);
    }

    /**
     * Revisa si el negocio puede usar el código (sin apartar nada). Es lo
     * que se le muestra al dueño al escribirlo; apartar() vuelve a revisar
     * todo con la oferta bloqueada.
     *
     * @param array<string, mixed> $contexto Auth::exigirSesion() del dueño
     * @return array{ok: bool, mensaje: string, oferta: ?array, documento_hash: string, whatsapp_hash: string}
     */
    public static function evaluar(array $contexto, string $codigo, string $documento, bool $bloquear = false, bool $documentoEsHash = false): array
    {
        $no = fn (string $m, ?array $o = null) => ['ok' => false, 'mensaje' => $m, 'oferta' => $o, 'documento_hash' => '', 'whatsapp_hash' => ''];
        $negocioId = (int) $contexto['negocio_id'];

        $oferta = self::buscarPorCodigo($codigo, $bloquear);
        if ($oferta === null) {
            return $no('Ese código no existe. Revisa que esté bien escrito.');
        }
        $estado = self::estado($oferta);
        if ($estado !== 'activa') {
            return $no(match ($estado) {
                'vencida' => 'Ese código ya venció.',
                'agotada' => 'Ese código ya se agotó: se usaron todos los cupos.',
                default   => 'Ese código ya no está disponible.',
            }, $oferta);
        }
        if (self::negocioYaPagoPlan($negocioId)) {
            return $no('Este código es para el primer mes de un negocio nuevo, y tu negocio ya pagó un plan.', $oferta);
        }
        if (self::canjeActivoDeNegocio($negocioId) !== null) {
            return $no('Tu negocio ya usó un código de oferta: no son acumulables.', $oferta);
        }
        // El documento llega escrito (al aplicar el código) o ya como hash
        // (al pedir el plan: la sesión nunca guarda la cédula en claro).
        $doc = $documentoEsHash ? (preg_match('/^[a-f0-9]{64}$/', $documento) === 1 ? $documento : null) : Identidad::normalizarDocumento($documento);
        if ($doc === null) {
            return $no('Escribe la cédula o el NIT del titular del negocio (solo números).', $oferta);
        }
        $usuario = Usuario::buscarPorId((int) $contexto['usuario_id']);
        $whatsapp = (string) ($usuario['whatsapp'] ?? '');
        $hashWhatsapp = hash_identidad('whatsapp', $whatsapp);
        $hashDocumento = $documentoEsHash ? $doc : hash_identidad('documento', $doc);
        // Mismo mensaje para todo lo de identidad: no se confirma qué número o
        // documento ya estuvo en Veci.
        if (Identidad::usadaEnOtroNegocio('whatsapp', $hashWhatsapp, $negocioId)
            || Identidad::usadaEnOtroNegocio('documento', $hashDocumento, $negocioId)
            || self::identidadYaCanjeo($hashWhatsapp, $hashDocumento, $negocioId)) {
            return $no('Este código es solo para negocios nuevos en Veci. Si crees que es un error, escríbenos a soporte@tuveci.co.', $oferta);
        }

        return ['ok' => true, 'mensaje' => '', 'oferta' => $oferta, 'documento_hash' => $hashDocumento, 'whatsapp_hash' => $hashWhatsapp];
    }

    /**
     * Aparta un canje para el pago que se va a crear. Debe llamarse dentro
     * de una transacción (la del pago): bloquea la oferta, revisa todo otra
     * vez y anota el canje. ok=false con el mensaje si ya no aplica.
     *
     * @return array{ok: bool, mensaje: string, canje_id: int, descuento: int, codigo: string}
     */
    public static function apartar(array $contexto, string $codigo, string $documentoHash, array $plan, string $ciclo, int $precioMes): array
    {
        $no = fn (string $m) => ['ok' => false, 'mensaje' => $m, 'canje_id' => 0, 'descuento' => 0, 'codigo' => ''];
        if (!in_array($plan['nombre'], self::PLANES_VALIDOS, true) || $ciclo !== 'mensual') {
            return $no('El código de oferta es solo para el primer mes de Barrio o Pro con pago mensual (no se acumula con el pago anual).');
        }
        $pdo = Database::conexion();
        if (!$pdo->inTransaction()) {
            throw new \LogicException('OfertaPlan::apartar va dentro de la transacción del pago.');
        }
        $evaluacion = self::evaluar($contexto, $codigo, $documentoHash, true, true);
        if (!$evaluacion['ok']) {
            return $no($evaluacion['mensaje']);
        }
        $oferta = $evaluacion['oferta'];
        $descuento = self::descuento($oferta, $precioMes);
        $negocioId = (int) $contexto['negocio_id'];
        $pdo->prepare(
            'INSERT INTO ofertas_canjes (oferta_id, negocio_id, plan_id, whatsapp_hash, documento_hash, monto_lista, descuento)
             VALUES (:o, :n, :p, :w, :d, :m, :de)'
        )->execute([
            'o' => (int) $oferta['id'], 'n' => $negocioId, 'p' => (int) $plan['id'],
            'w' => $evaluacion['whatsapp_hash'], 'd' => $evaluacion['documento_hash'],
            'm' => $precioMes, 'de' => $descuento,
        ]);
        $canjeId = (int) $pdo->lastInsertId();
        Identidad::registrar('whatsapp', $evaluacion['whatsapp_hash'], $negocioId);
        Identidad::registrar('documento', $evaluacion['documento_hash'], $negocioId);

        return ['ok' => true, 'mensaje' => '', 'canje_id' => $canjeId, 'descuento' => $descuento, 'codigo' => (string) $oferta['codigo']];
    }

    public static function asignarPago(int $canjeId, int $pagoId): void
    {
        Database::conexion()->prepare('UPDATE ofertas_canjes SET pago_plan_id = :p WHERE id = :id')->execute(['p' => $pagoId, 'id' => $canjeId]);
    }

    /** El pago se confirmó (admin o Wompi): el canje queda usado, aunque la solicitud se hubiera cancelado antes. */
    public static function confirmarPorPago(int $pagoId): void
    {
        Database::conexion()->prepare(
            "UPDATE ofertas_canjes SET estado = 'confirmado', confirmado_en = COALESCE(confirmado_en, NOW()) WHERE pago_plan_id = :p"
        )->execute(['p' => $pagoId]);
    }

    /** La solicitud se canceló o se rechazó sin pagar: el cupo vuelve a la oferta y el negocio puede volver a intentarlo. */
    public static function liberarPorPago(int $pagoId): void
    {
        Database::conexion()->prepare("UPDATE ofertas_canjes SET estado = 'liberado' WHERE pago_plan_id = :p AND estado = 'apartado'")
            ->execute(['p' => $pagoId]);
    }

    public static function canjeActivoDeNegocio(int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.*, o.codigo FROM ofertas_canjes c JOIN ofertas_plan o ON o.id = c.oferta_id
             WHERE c.negocio_id = :n AND c.estado <> 'liberado' ORDER BY c.id DESC LIMIT 1"
        );
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetch() ?: null;
    }

    public static function negocioYaPagoPlan(int $negocioId): bool
    {
        $stmt = Database::conexion()->prepare("SELECT 1 FROM pagos_plan WHERE negocio_id = :n AND concepto = 'plan' AND confirmado_en IS NOT NULL LIMIT 1");
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetchColumn() !== false;
    }

    private static function identidadYaCanjeo(string $hashWhatsapp, string $hashDocumento, int $negocioId): bool
    {
        $stmt = Database::conexion()->prepare(
            "SELECT 1 FROM ofertas_canjes WHERE estado <> 'liberado' AND negocio_id <> :n
               AND (whatsapp_hash = :w OR documento_hash = :d) LIMIT 1"
        );
        $stmt->execute(['n' => $negocioId, 'w' => $hashWhatsapp, 'd' => $hashDocumento]);

        return $stmt->fetchColumn() !== false;
    }

    // ---------- Panel interno ----------

    /** @return array<int, array<string, mixed>> todas, con cuántos canjes ocupan cupo y cuántos se pagaron */
    public static function listar(): array
    {
        return Database::conexion()->query(
            "SELECT o.*,
                    (SELECT COUNT(*) FROM ofertas_canjes c WHERE c.oferta_id = o.id AND c.estado <> 'liberado') AS usos,
                    (SELECT COUNT(*) FROM ofertas_canjes c WHERE c.oferta_id = o.id AND c.estado = 'confirmado') AS pagados
             FROM ofertas_plan o ORDER BY o.activa DESC, o.creado_en DESC"
        )->fetchAll();
    }

    /**
     * Registro de canjes: código, fecha, plan, estado y si el negocio pagó
     * el segundo mes (otro pago de plan confirmado después del de la oferta).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function canjes(int $limite = 200): array
    {
        return Database::conexion()->query(
            "SELECT c.id, c.estado, c.creado_en, c.confirmado_en, c.monto_lista, c.descuento,
                    o.codigo, p.nombre AS plan_nombre, n.id AS negocio_id, n.nombre AS negocio_nombre,
                    EXISTS (SELECT 1 FROM pagos_plan s WHERE s.negocio_id = c.negocio_id AND s.concepto = 'plan'
                              AND s.confirmado_en IS NOT NULL AND s.id > c.pago_plan_id) AS pago_segundo_mes
             FROM ofertas_canjes c
             JOIN ofertas_plan o ON o.id = c.oferta_id
             JOIN planes p ON p.id = c.plan_id
             LEFT JOIN negocios n ON n.id = c.negocio_id
             ORDER BY c.id DESC LIMIT " . max(1, min(1000, $limite))
        )->fetchAll();
    }

    /** Crea o actualiza (por código). @param array{codigo:string, descripcion:string, porcentaje:int, vence_en:?string, cupo_total:?int, activa:bool} $datos */
    public static function guardar(array $datos, ?int $id = null): void
    {
        $valores = [
            'd' => mb_substr($datos['descripcion'], 0, 160),
            'p' => max(1, min(100, $datos['porcentaje'])),
            'v' => $datos['vence_en'],
            'c' => $datos['cupo_total'],
            'a' => $datos['activa'] ? 1 : 0,
        ];
        if ($id !== null) {
            Database::conexion()->prepare('UPDATE ofertas_plan SET descripcion = :d, porcentaje = :p, vence_en = :v, cupo_total = :c, activa = :a WHERE id = :id')
                ->execute($valores + ['id' => $id]);

            return;
        }
        Database::conexion()->prepare('INSERT INTO ofertas_plan (codigo, descripcion, porcentaje, vence_en, cupo_total, activa) VALUES (:codigo, :d, :p, :v, :c, :a)')
            ->execute($valores + ['codigo' => $datos['codigo']]);
    }
}
