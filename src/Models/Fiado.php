<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use App\Services\HorarioCobro;

/**
 * El cuaderno del fiado (ver database/migrations/2026-10-03_22_fiado.sql).
 * Es del negocio, como el cliente. Saldo = cargos − abonos, sin contar lo
 * anulado (la venta fiada que se anuló no se cobra).
 */
class Fiado
{
    public const METODOS_ABONO = ['efectivo' => 'Efectivo', 'nequi' => 'Nequi', 'breb' => 'Bre-B'];

    /** Días que deben pasar entre dos recordatorios al mismo cliente (Ley 2300: uno por semana). */
    public const DIAS_ENTRE_RECORDATORIOS = 7;

    /** Saldo de los movimientos con ese alias de tabla ('' = sin alias). */
    private static function saldoSql(string $t = ''): string
    {
        $p = $t !== '' ? $t . '.' : '';

        return "COALESCE(SUM(CASE WHEN {$p}anulado = 1 THEN 0 WHEN {$p}tipo = 'cargo' THEN {$p}monto ELSE -CAST({$p}monto AS SIGNED) END), 0)";
    }

    public static function saldo(int $negocioId, int $clienteId): int
    {
        $stmt = Database::conexion()->prepare('SELECT ' . self::saldoSql() . ' FROM fiado_movimientos WHERE negocio_id = :n AND cliente_id = :c');
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);

        return (int) $stmt->fetchColumn();
    }

    /** Lo que le deben al negocio en total (sin restar los saldos a favor de los clientes). */
    public static function totalPorCobrar(int $negocioId): int
    {
        return array_sum(array_map(fn ($c) => max(0, (int) $c['saldo']), self::clientesConSaldo($negocioId)));
    }

    /**
     * Clientes que deben, con desde cuándo: los abonos pagan primero lo más
     * viejo (como en el cuaderno), y "debe desde" es la fecha del cargo más
     * viejo que sigue sin pagar. Ordenados: lo más viejo primero y, a la
     * misma fecha, el que más debe.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function clientesConSaldo(int $negocioId, string $filtro = ''): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT c.id, c.nombre, c.telefono, c.fiado_limite, ' . self::saldoSql('m') . ' AS saldo,
                    (SELECT MAX(r.enviado_en) FROM fiado_recordatorios r WHERE r.cliente_id = c.id AND r.negocio_id = c.negocio_id) AS ultimo_recordatorio
             FROM clientes c JOIN fiado_movimientos m ON m.cliente_id = c.id AND m.negocio_id = c.negocio_id
             WHERE c.negocio_id = :n' . $filtro . '
             GROUP BY c.id, c.nombre, c.telefono, c.fiado_limite
             HAVING saldo <> 0'
        );
        $stmt->execute(['n' => $negocioId]);
        $clientes = $stmt->fetchAll();
        foreach ($clientes as &$cliente) {
            $pendientes = self::pendientes($negocioId, (int) $cliente['id']);
            $cliente['debe_desde'] = $pendientes !== [] ? $pendientes[0]['creado_en'] : null;
        }
        unset($cliente);
        usort($clientes, fn ($a, $b) => [(string) $a['debe_desde'], -(int) $a['saldo']] <=> [(string) $b['debe_desde'], -(int) $b['saldo']]);

        return $clientes;
    }

    /**
     * Movimientos del cliente en orden del cuaderno (del más viejo al más
     * nuevo), cada uno con el saldo que quedó después. Si son muchos, los
     * últimos $limite y el saldo con que arrancan ("viene de antes").
     *
     * @return array{movimientos: array<int, array<string, mixed>>, saldo_anterior: int}
     */
    public static function libreta(int $negocioId, int $clienteId, int $limite = 60): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT m.*, u.nombre AS usuario_nombre, s.nombre AS sede_nombre
             FROM fiado_movimientos m
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             LEFT JOIN sedes s ON s.id = m.sede_id
             WHERE m.negocio_id = :n AND m.cliente_id = :c
             ORDER BY m.creado_en ASC, m.id ASC'
        );
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);
        $todos = $stmt->fetchAll();
        $saldo = 0;
        foreach ($todos as &$mov) {
            if ((int) $mov['anulado'] === 0) {
                $saldo += $mov['tipo'] === 'cargo' ? (int) $mov['monto'] : -(int) $mov['monto'];
            }
            $mov['saldo_despues'] = $saldo;
        }
        unset($mov);
        $recorte = max(0, count($todos) - $limite);
        $anterior = $recorte > 0 ? (int) $todos[$recorte - 1]['saldo_despues'] : 0;

        return ['movimientos' => array_slice($todos, $recorte), 'saldo_anterior' => $anterior];
    }

    /**
     * Cargos que siguen sin pagar (los abonos van pagando del más viejo al
     * más nuevo), con lo que falta de cada uno. Sirve para "debe desde" y
     * para el detalle del recordatorio.
     *
     * @return array<int, array{creado_en:string, monto:int, falta:int, venta_id:?int, nota:?string}>
     */
    public static function pendientes(int $negocioId, int $clienteId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT tipo, monto, creado_en, venta_id, nota FROM fiado_movimientos
             WHERE negocio_id = :n AND cliente_id = :c AND anulado = 0 ORDER BY creado_en ASC, id ASC'
        );
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);
        $cargos = [];
        $abonado = 0;
        foreach ($stmt->fetchAll() as $mov) {
            if ($mov['tipo'] === 'cargo') {
                $cargos[] = ['creado_en' => (string) $mov['creado_en'], 'monto' => (int) $mov['monto'], 'falta' => (int) $mov['monto'],
                    'venta_id' => $mov['venta_id'] !== null ? (int) $mov['venta_id'] : null, 'nota' => $mov['nota']];
            } else {
                $abonado += (int) $mov['monto'];
            }
        }
        foreach ($cargos as &$cargo) {
            $paga = min($abonado, $cargo['falta']);
            $cargo['falta'] -= $paga;
            $abonado -= $paga;
        }
        unset($cargo);

        return array_values(array_filter($cargos, fn ($c) => $c['falta'] > 0));
    }

    /** Un cargo a mano (lo que estaba en el cuaderno de papel, por ejemplo). No mueve la caja. */
    public static function cargar(int $negocioId, int $sedeId, int $clienteId, int $monto, string $nota, ?int $usuarioId): void
    {
        if ($monto <= 0) {
            throw new \DomainException('Escribe cuánto le vas a cargar.');
        }
        if (trim($nota) === '') {
            throw new \DomainException('Escribe una nota que diga qué es (por ejemplo, "lo del cuaderno").');
        }
        Database::conexion()->prepare(
            "INSERT INTO fiado_movimientos (negocio_id, sede_id, cliente_id, tipo, monto, nota, usuario_id)
             VALUES (:n, :s, :c, 'cargo', :m, :nota, :u)"
        )->execute(['n' => $negocioId, 's' => $sedeId, 'c' => $clienteId, 'm' => $monto, 'nota' => mb_substr(trim($nota), 0, 160), 'u' => $usuarioId]);
    }

    /**
     * Abono del cliente. No puede pasar de lo que debe (no hay "saldo a
     * favor" en el cuaderno: lo que sobre se devuelve en vueltas). El cliente
     * se bloquea mientras tanto: dos abonos a la vez no se pasan juntos.
     */
    public static function abonar(int $negocioId, int $sedeId, int $clienteId, int $monto, string $metodo, string $nota, ?int $usuarioId): int
    {
        if (!isset(self::METODOS_ABONO[$metodo])) {
            throw new \DomainException('Elige cómo te pagó el abono.');
        }
        if ($monto <= 0) {
            throw new \DomainException('Escribe cuánto abonó.');
        }
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id = :c AND negocio_id = :n FOR UPDATE');
            $stmt->execute(['c' => $clienteId, 'n' => $negocioId]);
            if ($stmt->fetch() === false) {
                throw new \DomainException('Ese cliente no existe en tu negocio.');
            }
            $saldo = self::saldo($negocioId, $clienteId);
            if ($saldo <= 0) {
                throw new \DomainException('Este cliente no debe nada.');
            }
            if ($monto > $saldo) {
                throw new \DomainException('Debe ' . pesos($saldo) . ': el abono no puede ser mayor. Si te pagó más, dale las vueltas.');
            }
            $pdo->prepare(
                "INSERT INTO fiado_movimientos (negocio_id, sede_id, cliente_id, tipo, monto, metodo, nota, usuario_id)
                 VALUES (:n, :s, :c, 'abono', :m, :metodo, :nota, :u)"
            )->execute([
                'n' => $negocioId, 's' => $sedeId, 'c' => $clienteId, 'm' => $monto, 'metodo' => $metodo,
                'nota' => trim($nota) !== '' ? mb_substr(trim($nota), 0, 160) : null, 'u' => $usuarioId,
            ]);
            $pdo->commit();

            return $saldo - $monto;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Anula un abono o un cargo a mano (solo el dueño, lo revisa quien llama).
     * Un abono en efectivo solo el mismo día: ya está contado en la caja y
     * anularlo después descuadraría un cierre cerrado. El cargo de una venta
     * no se anula aquí: se anula la venta (devuelve el inventario).
     */
    public static function anularMovimiento(int $negocioId, int $clienteId, int $movimientoId): array
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM fiado_movimientos WHERE id = :id AND negocio_id = :n AND cliente_id = :c FOR UPDATE');
            $stmt->execute(['id' => $movimientoId, 'n' => $negocioId, 'c' => $clienteId]);
            $mov = $stmt->fetch();
            if ($mov === false) {
                throw new \DomainException('Ese movimiento no es de esta cuenta.');
            }
            if ((int) $mov['anulado'] === 1) {
                throw new \DomainException('Ese movimiento ya estaba anulado.');
            }
            if ($mov['venta_id'] !== null) {
                throw new \DomainException('Ese cargo es de una venta del mostrador: anula la venta (así también vuelve el inventario).');
            }
            if ($mov['tipo'] === 'abono' && $mov['metodo'] === 'efectivo' && substr((string) $mov['creado_en'], 0, 10) !== date('Y-m-d')) {
                throw new \DomainException('Un abono en efectivo solo se anula el mismo día: ya está contado en el cierre de caja de ese día.');
            }
            $pdo->prepare('UPDATE fiado_movimientos SET anulado = 1 WHERE id = :id')->execute(['id' => $movimientoId]);
            $pdo->commit();

            return $mov;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** ¿Se puede anular desde el cuaderno? (Misma regla que anularMovimiento, para pintar el botón.) */
    public static function anulable(array $mov): bool
    {
        return (int) $mov['anulado'] === 0 && $mov['venta_id'] === null
            && !($mov['tipo'] === 'abono' && $mov['metodo'] === 'efectivo' && substr((string) $mov['creado_en'], 0, 10) !== date('Y-m-d'));
    }

    /**
     * ¿Se puede borrar el cliente (Ley 1581, desde el Copiloto)? No mientras
     * tenga saldo en el cuaderno (a favor o en contra) ni movimientos de hoy:
     * borrarlo se llevaría su cuenta y descuadraría la caja del día.
     */
    public static function razonParaNoBorrar(int $negocioId, int $clienteId): ?string
    {
        $saldo = self::saldo($negocioId, $clienteId);
        if ($saldo > 0) {
            return 'No se puede borrar: debe ' . pesos($saldo) . ' en el fiado. Cuando quede a paz y salvo, sí.';
        }
        if ($saldo < 0) {
            return 'No se puede borrar: tiene un saldo a favor de ' . pesos(-$saldo) . ' en el fiado. Devuélveselo o úsalo primero.';
        }
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM fiado_movimientos WHERE negocio_id = :n AND cliente_id = :c AND creado_en >= CURDATE()');
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return 'No se puede borrar hoy: tiene movimientos de fiado de hoy que cuentan en el cierre de caja. Mañana sí.';
        }

        return null;
    }

    public static function establecerLimite(int $negocioId, int $clienteId, ?int $limite): void
    {
        Database::conexion()->prepare('UPDATE clientes SET fiado_limite = :l WHERE id = :c AND negocio_id = :n')
            ->execute(['l' => $limite !== null ? max(0, $limite) : null, 'c' => $clienteId, 'n' => $negocioId]);
    }

    /**
     * El cliente al que se le va a fiar, DENTRO de la transacción de quien
     * llama (la venta o el alta en el cuaderno): si algo falla después, el
     * cliente nuevo tampoco queda creado.
     *
     * - Si el WhatsApp ya es de un cliente con el mismo nombre (o el mismo
     *   primer nombre), es él: no se le cambia nada.
     * - Si es de alguien guardado con otro nombre, no se usa en silencio:
     *   lanza ClienteDeOtroNombre, salvo que ya se haya confirmado ese id.
     * - Si no existe, se crea solo con la autorización (Ley 1581 de 2012).
     *
     * @return array{id: int, nuevo: bool, nombre: string}
     */
    public static function resolverCliente(\PDO $pdo, int $negocioId, string $nombre, string $telefono, bool $autorizo, ?int $confirmadoId = null): array
    {
        $nombre = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $nombre)), 0, 120);
        $escrito = trim($telefono);
        $telefono = self::telefonoValido($telefono);
        if ($nombre === '') {
            throw new \DomainException('Escribe el nombre del cliente.');
        }
        if ($telefono === null && $escrito !== '') {
            throw new \DomainException('Ese WhatsApp no se ve bien: 10 dígitos que empiecen por 3. Si el cliente no tiene, déjalo vacío.');
        }
        if ($telefono === null) {
            // Sin WhatsApp (se puede fiar igual; solo no hay a dónde mandarle
            // el recordatorio). Se reconoce por el nombre completo exacto
            // entre los que tampoco tienen: "Rosa" y "Rosa Pinzón" sin
            // número que los distinga podrían ser dos personas.
            $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE negocio_id = :n AND telefono IS NULL FOR UPDATE');
            $stmt->execute(['n' => $negocioId]);
            foreach ($stmt->fetchAll() as $sinTelefono) {
                if ($confirmadoId === (int) $sinTelefono['id'] || self::nombreNormalizado($nombre) === self::nombreNormalizado((string) $sinTelefono['nombre'])) {
                    return ['id' => (int) $sinTelefono['id'], 'nuevo' => false, 'nombre' => (string) $sinTelefono['nombre']];
                }
            }
            $existente = false;
        } else {
            $stmt = $pdo->prepare('SELECT id, nombre FROM clientes WHERE negocio_id = :n AND telefono = :t FOR UPDATE');
            $stmt->execute(['n' => $negocioId, 't' => $telefono]);
            $existente = $stmt->fetch();
        }
        if ($existente !== false) {
            if ($confirmadoId === (int) $existente['id'] || self::mismaPersona($nombre, (string) $existente['nombre'])) {
                return ['id' => (int) $existente['id'], 'nuevo' => false, 'nombre' => (string) $existente['nombre']];
            }
            throw new ClienteDeOtroNombre((int) $existente['id'], (string) $existente['nombre']);
        }
        if (!$autorizo) {
            throw new \DomainException('Marca que el cliente autorizó guardar su nombre y número (Ley 1581): sin eso no se puede crear.');
        }
        $pdo->prepare(
            'INSERT INTO clientes (negocio_id, nombre, telefono, autorizo_datos, autorizado_en, acepta_marketing)
             VALUES (:n, :nombre, :t, 1, NOW(), 0)'
        )->execute(['n' => $negocioId, 'nombre' => $nombre, 't' => $telefono]);
        $id = (int) $pdo->lastInsertId();
        Consentimiento::registrar($negocioId, $id, 'datos', true, 'panel');

        return ['id' => $id, 'nuevo' => true, 'nombre' => $nombre];
    }

    /** Sin tildes, mayúsculas ni signos, con un solo espacio entre palabras. */
    private static function nombreNormalizado(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9 ]+/', '', $t)));
    }

    /** "Rosa" y "Rosa Elvira Pinzón" son la misma; "Rosa" y "Carlos", no. Sin tildes ni mayúsculas. */
    public static function mismaPersona(string $a, string $b): bool
    {
        $a = self::nombreNormalizado($a);
        $b = self::nombreNormalizado($b);
        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b || explode(' ', $a)[0] === explode(' ', $b)[0];
    }

    /**
     * Alta de un cliente desde el cuaderno, con lo que ya debía en papel y su
     * límite, todo junto (o nada). Si el WhatsApp ya es de alguien, NO se le
     * carga ni se le cambia el límite: se devuelve su cuenta para revisarla.
     *
     * @return array{id: int, nuevo: bool, nombre: string}
     */
    public static function crearCuenta(int $negocioId, int $sedeId, string $nombre, string $telefono, bool $autorizo, int $saldoInicial, ?int $limite, ?int $usuarioId): array
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            try {
                $cliente = self::resolverCliente($pdo, $negocioId, $nombre, $telefono, $autorizo);
            } catch (ClienteDeOtroNombre $e) {
                $cliente = ['id' => $e->clienteId, 'nuevo' => false, 'nombre' => $e->nombre];
            }
            if ($cliente['nuevo']) {
                if ($saldoInicial > 0) {
                    $pdo->prepare(
                        "INSERT INTO fiado_movimientos (negocio_id, sede_id, cliente_id, tipo, monto, nota, usuario_id)
                         VALUES (:n, :s, :c, 'cargo', :m, 'Lo que debía en el cuaderno', :u)"
                    )->execute(['n' => $negocioId, 's' => $sedeId, 'c' => $cliente['id'], 'm' => $saldoInicial, 'u' => $usuarioId]);
                }
                if ($limite !== null) {
                    $pdo->prepare('UPDATE clientes SET fiado_limite = :l WHERE id = :c AND negocio_id = :n')
                        ->execute(['l' => max(0, $limite), 'c' => $cliente['id'], 'n' => $negocioId]);
                }
            }
            $pdo->commit();

            return $cliente;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Celular colombiano de 10 dígitos que empieza por 3 (acepta +57, espacios y guiones). */
    public static function telefonoValido(string $telefono): ?string
    {
        $digitos = (string) preg_replace('/\D+/', '', $telefono);
        if (strlen($digitos) === 12 && str_starts_with($digitos, '57')) {
            $digitos = substr($digitos, 2);
        }

        return preg_match('/^3\d{9}$/', $digitos) === 1 ? $digitos : null;
    }

    /**
     * Para un colaborador, solo los clientes de SUS sedes: los que compraron,
     * reservaron o fiaron en alguna de ellas, o los que todavía no tienen
     * movimiento en ninguna (recién creados en el panel). Antes un
     * colaborador de una sede veía nombre, teléfono y deuda de los clientes
     * de todas las sedes del negocio. Fragmento SQL ('' para el dueño); los
     * ids salen de la base y van como enteros.
     */
    public static function filtroSedes(array $contexto, string $alias = 'c'): string
    {
        if (($contexto['rol'] ?? '') === 'dueno') {
            return '';
        }
        $ids = implode(',', array_map('intval', Usuario::sedeIdsAsignadas((int) $contexto['usuario_id']))) ?: '0';
        $actividad = fn (string $en) => "EXISTS (SELECT 1 FROM ventas v WHERE v.cliente_id = {$alias}.id{$en})
            OR EXISTS (SELECT 1 FROM pedidos p WHERE p.cliente_id = {$alias}.id" . str_replace('v.', 'p.', $en) . ")
            OR EXISTS (SELECT 1 FROM citas ct WHERE ct.cliente_id = {$alias}.id" . str_replace('v.', 'ct.', $en) . ")
            OR EXISTS (SELECT 1 FROM fiado_movimientos fm WHERE fm.cliente_id = {$alias}.id" . str_replace('v.', 'fm.', $en) . ')';

        return ' AND ((' . $actividad(" AND v.sede_id IN ({$ids})") . ') OR NOT (' . $actividad('') . '))';
    }

    /** ¿Ese cliente lo puede ver este usuario? (ver filtroSedes) */
    public static function clienteVisible(array $contexto, int $clienteId): bool
    {
        $stmt = Database::conexion()->prepare('SELECT 1 FROM clientes c WHERE c.id = :id AND c.negocio_id = :n' . self::filtroSedes($contexto));
        $stmt->execute(['id' => $clienteId, 'n' => (int) $contexto['negocio_id']]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Clientes para elegir al fiar: primero los que ya tienen cuenta (con su
     * saldo y límite), luego el resto por nombre.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function clientesParaElegir(int $negocioId, int $limite = 300, string $filtro = ''): array
    {
        $limite = max(1, min(1000, $limite));
        $stmt = Database::conexion()->prepare(
            'SELECT c.id, c.nombre, c.telefono, c.fiado_limite,
                    (SELECT ' . self::saldoSql('m') . '
                       FROM fiado_movimientos m WHERE m.cliente_id = c.id AND m.negocio_id = c.negocio_id) AS saldo,
                    EXISTS(SELECT 1 FROM fiado_movimientos m2 WHERE m2.cliente_id = c.id) AS con_cuenta
             FROM clientes c WHERE c.negocio_id = :n' . $filtro . '
             ORDER BY con_cuenta DESC, c.nombre ASC LIMIT ' . $limite
        );
        $stmt->execute(['n' => $negocioId]);

        return $stmt->fetchAll();
    }

    public static function ultimoRecordatorio(int $negocioId, int $clienteId): ?string
    {
        $stmt = Database::conexion()->prepare('SELECT MAX(enviado_en) FROM fiado_recordatorios WHERE negocio_id = :n AND cliente_id = :c');
        $stmt->execute(['n' => $negocioId, 'c' => $clienteId]);
        $fecha = $stmt->fetchColumn();

        return $fecha ? (string) $fecha : null;
    }

    /**
     * ¿Se le puede mandar el recordatorio ya? Junta el horario de la ley
     * (HorarioCobro), el "uno por semana" y lo básico: que deba algo y que
     * tenga un WhatsApp al que escribirle.
     *
     * @return array{permitido: bool, razon: ?string}
     */
    public static function puedeRecordar(int $negocioId, array $cliente, int $saldo, ?\DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new \DateTimeImmutable('now', new \DateTimeZone(HorarioCobro::ZONA));
        if ($saldo <= 0) {
            return ['permitido' => false, 'razon' => 'No debe nada: no hay qué recordarle.'];
        }
        if (self::telefonoValido((string) $cliente['telefono']) === null) {
            return ['permitido' => false, 'razon' => 'Este cliente no tiene un WhatsApp válido guardado.'];
        }
        $ultimo = self::ultimoRecordatorio($negocioId, (int) $cliente['id']);
        if ($ultimo !== null) {
            $desde = new \DateTimeImmutable($ultimo, new \DateTimeZone(HorarioCobro::ZONA));
            $habilita = $desde->modify('+' . self::DIAS_ENTRE_RECORDATORIOS . ' days');
            if ($ahora < $habilita) {
                // El próximo, ya en un momento permitido (no un domingo ni un festivo).
                $proximo = HorarioCobro::proximoPermitido($habilita);

                return ['permitido' => false, 'razon' => 'Ya le enviaste un recordatorio el ' . fecha_larga($desde->format('Y-m-d'))
                    . ': la ley permite uno por semana. El próximo se puede desde el ' . fecha_larga($proximo->format('Y-m-d'))
                    . ' a las ' . hora_legible($proximo->format('H:i'))];
            }
        }
        $horario = HorarioCobro::evaluar($ahora);
        if (!$horario['permitido']) {
            $proximo = $horario['proximo'];
            $cuando = $proximo->format('Y-m-d') === $ahora->format('Y-m-d') ? 'hoy' : 'el ' . fecha_larga($proximo->format('Y-m-d'));

            return ['permitido' => false, 'razon' => $horario['razon'] . ' Lo puedes enviar ' . $cuando . ' desde las ' . hora_legible($proximo->format('H:i'))]; // ya termina en "a. m." o "p. m."
        }

        return ['permitido' => true, 'razon' => null];
    }

    public static function registrarRecordatorio(int $negocioId, int $clienteId, ?int $usuarioId, int $saldo): void
    {
        Database::conexion()->prepare(
            'INSERT INTO fiado_recordatorios (negocio_id, cliente_id, usuario_id, saldo) VALUES (:n, :c, :u, :s)'
        )->execute(['n' => $negocioId, 'c' => $clienteId, 'u' => $usuarioId, 's' => max(0, $saldo)]);
    }

    /**
     * El mensaje del recordatorio: amable, sin amenazas, con el saldo y de
     * qué es (los cargos pendientes, hasta 8).
     */
    public static function mensajeRecordatorio(array $negocio, array $cliente, int $saldo, array $pendientes): string
    {
        $nombre = explode(' ', trim((string) $cliente['nombre']))[0];
        $lineas = [];
        foreach (array_slice($pendientes, 0, 8) as $cargo) {
            $detalle = $cargo['venta_id'] !== null ? 'compra' : (string) ($cargo['nota'] ?? 'cargo');
            $lineas[] = '• ' . self::diaCorto((string) $cargo['creado_en']) . ': ' . $detalle . ' ' . pesos($cargo['falta']);
        }
        if (count($pendientes) > 8) {
            $lineas[] = '• y ' . (count($pendientes) - 8) . ' más';
        }

        return "Hola {$nombre}, te saludamos de " . nombre_publico_sede($negocio) . ". "
            . 'Te escribimos para recordarte, con cariño, que tienes una cuenta pendiente de ' . pesos($saldo) . ":\n"
            . implode("\n", $lineas) . "\n"
            . 'Cuando puedas, pasa por la tienda o avísanos si prefieres pagar por Nequi o Bre-B. '
            . 'Si ya pagaste, no tengas en cuenta este mensaje. ¡Gracias por tu confianza!';
    }

    /** "3 oct" (con el año si no es este): la fecha de un renglón del cuaderno. */
    public static function diaCorto(string $fechaHora): string
    {
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $ts = strtotime($fechaHora) ?: 0;

        return date('j', $ts) . ' ' . $meses[(int) date('n', $ts) - 1] . (date('Y', $ts) !== date('Y') ? ' ' . date('Y', $ts) : '');
    }

    /**
     * Abonos que entraron en la sede ese rango, por método (para el cierre
     * de caja: el abono en efectivo es plata que entró al cajón).
     *
     * @return array<string, array{abonos:int, total:int}>
     */
    public static function abonosDelRango(int $sedeId, string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT metodo, COUNT(*) AS abonos, COALESCE(SUM(monto), 0) AS total FROM fiado_movimientos
             WHERE sede_id = :s AND tipo = 'abono' AND anulado = 0 AND creado_en >= :desde AND creado_en < :hasta
             GROUP BY metodo"
        );
        $stmt->execute(['s' => $sedeId, 'desde' => $desde, 'hasta' => $hasta]);
        $porMetodo = array_fill_keys(array_keys(self::METODOS_ABONO), ['abonos' => 0, 'total' => 0]);
        foreach ($stmt->fetchAll() as $fila) {
            $porMetodo[(string) $fila['metodo']] = ['abonos' => (int) $fila['abonos'], 'total' => (int) $fila['total']];
        }

        return $porMetodo;
    }
}
