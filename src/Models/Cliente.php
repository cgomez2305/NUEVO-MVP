<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

class Cliente
{
    /**
     * Crea el cliente o, si ya existe (mismo teléfono en el mismo negocio),
     * actualiza su nombre. $aceptaMarketing es un opt-in APARTE del
     * consentimiento de procesar el pedido y solo SUMA: marcar la casilla
     * otorga el permiso de promociones; no marcarla en un pedido siguiente
     * no lo retira (olvidar una casilla no es retirar un permiso). Retirarlo
     * es explícito: el enlace de preferencias del cliente o el panel.
     * $origen queda en el registro de consentimientos (pedido, reserva, panel).
     *
     * Un formulario público no prueba que quien escribe un número sea su
     * dueño: a un cliente que YA existía solo se le cambia el nombre o se le
     * da permiso de promociones si $clienteDelDispositivo dice que es él (su
     * mismo celular ya pidió antes como ese cliente). Si no, se usa su
     * registro tal cual: nadie le cambia el nombre ni le inventa un permiso
     * a otro tecleando su número. Desde el panel (lo hace el negocio) se pasa
     * $deConfianza = true.
     */
    public static function buscarOCrear(int $negocioId, string $nombre, string $telefono, bool $autorizoDatos, bool $aceptaMarketing = false, string $origen = 'pedido', ?int $clienteDelDispositivo = null, bool $deConfianza = false): int
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare(
            'SELECT id, autorizo_datos FROM clientes WHERE negocio_id = :negocio_id AND telefono = :telefono'
        );
        $stmt->execute(['negocio_id' => $negocioId, 'telefono' => $telefono]);
        $existente = $stmt->fetch();

        if ($existente !== false) {
            $id = (int) $existente['id'];
            $esEl = $deConfianza || $clienteDelDispositivo === $id;
            if ($esEl) {
                $pdo->prepare('UPDATE clientes SET nombre = :nombre WHERE id = :id')->execute(['nombre' => $nombre, 'id' => $id]);
            }
            if ($autorizoDatos && (int) $existente['autorizo_datos'] !== 1) {
                $pdo->prepare('UPDATE clientes SET autorizo_datos = 1, autorizado_en = NOW() WHERE id = :id')->execute(['id' => $id]);
                Consentimiento::registrar($negocioId, $id, 'datos', true, $origen);
            }
            if ($aceptaMarketing && $esEl) {
                Consentimiento::cambiarMarketing($negocioId, $id, true, $origen);
            }

            return $id;
        }

        $crear = $pdo->prepare(
            'INSERT INTO clientes (negocio_id, nombre, telefono, autorizo_datos, autorizado_en, acepta_marketing, marketing_actualizado_en)
             VALUES (:negocio_id, :nombre, :telefono, :autorizo, :autorizado_en, :acepta_marketing, :marketing_en)'
        );
        $crear->execute([
            'negocio_id'       => $negocioId,
            'nombre'           => $nombre,
            'telefono'         => $telefono,
            'autorizo'         => $autorizoDatos ? 1 : 0,
            'autorizado_en'    => $autorizoDatos ? date('Y-m-d H:i:s') : null,
            'acepta_marketing' => $aceptaMarketing ? 1 : 0,
            'marketing_en'     => $aceptaMarketing ? date('Y-m-d H:i:s') : null,
        ]);
        $id = (int) $pdo->lastInsertId();
        if ($autorizoDatos) {
            Consentimiento::registrar($negocioId, $id, 'datos', true, $origen);
        }
        if ($aceptaMarketing) {
            Consentimiento::registrar($negocioId, $id, 'marketing', true, $origen);
        }

        return $id;
    }

    /**
     * Enlace sin login para que el cliente active o retire las promociones
     * de este negocio. Se crea la primera vez que hace falta.
     */
    public static function tokenPreferencias(int $id, int $negocioId): ?string
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT token_preferencias FROM clientes WHERE id = :id AND negocio_id = :n');
        $stmt->execute(['id' => $id, 'n' => $negocioId]);
        $token = $stmt->fetchColumn();
        if ($token === false) {
            return null;
        }
        if (is_string($token) && $token !== '') {
            return $token;
        }
        $nuevo = bin2hex(random_bytes(16));
        // Solo si sigue vacío: dos pestañas a la vez no deben dejar dos tokens.
        $pdo->prepare('UPDATE clientes SET token_preferencias = :t WHERE id = :id AND token_preferencias IS NULL')
            ->execute(['t' => $nuevo, 'id' => $id]);
        $stmt->execute(['id' => $id, 'n' => $negocioId]);

        return (string) $stmt->fetchColumn();
    }

    public static function buscarPorTokenPreferencias(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = Database::conexion()->prepare('SELECT * FROM clientes WHERE token_preferencias = :t');
        $stmt->execute(['t' => $token]);

        return $stmt->fetch() ?: null;
    }

    /** ¿Se le pueden mandar promociones? Permiso vigente y un WhatsApp al que escribir. */
    public static function contactable(array $cliente): bool
    {
        return (int) ($cliente['acepta_marketing'] ?? 0) === 1 && !empty($cliente['telefono']);
    }

    /**
     * El negocio le pide permiso UNA vez a quien no lo ha dado: después de
     * eso, insistir sería justo el contacto no autorizado que la ley castiga.
     * Devuelve false si ya se le pidió.
     */
    public static function marcarPermisoPedido(int $id, int $negocioId): bool
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE clientes SET permiso_pedido_en = NOW()
             WHERE id = :id AND negocio_id = :n AND permiso_pedido_en IS NULL AND acepta_marketing = 0'
        );
        $stmt->execute(['id' => $id, 'n' => $negocioId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Para el panel: el cliente con ese WhatsApp si ya existe (sin tocarle el
     * nombre ni sus permisos), o uno nuevo con la autorización que el
     * negocio confirmó en persona.
     */
    public static function buscarOCrearDesdePanel(int $negocioId, string $nombre, string $telefono): int
    {
        $stmt = Database::conexion()->prepare('SELECT id FROM clientes WHERE negocio_id = :n AND telefono = :t');
        $stmt->execute(['n' => $negocioId, 't' => $telefono]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        Database::conexion()->prepare(
            'INSERT INTO clientes (negocio_id, nombre, telefono, autorizo_datos, autorizado_en) VALUES (:n, :nombre, :t, 1, NOW())'
        )->execute(['n' => $negocioId, 'nombre' => mb_substr($nombre, 0, 120), 't' => $telefono]);
        $nuevo = (int) Database::conexion()->lastInsertId();
        Consentimiento::registrar($negocioId, $nuevo, 'datos', true, 'panel');

        return $nuevo;
    }

    public static function buscar(int $id, int $negocioId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM clientes WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarPorNegocio(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM clientes WHERE negocio_id = :negocio_id ORDER BY nombre ASC'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetchAll();
    }

    /**
     * Derecho de suprimir datos (Ley 1581 de 2012): borra al cliente y, por
     * el ON DELETE CASCADE del esquema, todo su historial (pedidos, citas,
     * mensajes del copiloto). Es definitivo.
     */
    public static function eliminar(int $id, int $negocioId): void
    {
        // Borrar al cliente (derecho de supresión, Ley 1581) también borra
        // las fotos de sus visitas del disco, no solo sus filas.
        $fotos = Database::conexion()->prepare(
            'SELECT f.archivo FROM cita_fotos f JOIN citas c ON c.id = f.cita_id JOIN clientes cl ON cl.id = c.cliente_id
             WHERE cl.id = :id AND cl.negocio_id = :n'
        );
        $fotos->execute(['id' => $id, 'n' => $negocioId]);
        foreach ($fotos->fetchAll(\PDO::FETCH_COLUMN) as $archivo) {
            $ruta = \App\Services\Subida::rutaPrivada((string) $archivo);
            if ($ruta !== null && is_file($ruta)) {
                @unlink($ruta);
            }
        }
        $stmt = Database::conexion()->prepare(
            'DELETE FROM clientes WHERE id = :id AND negocio_id = :negocio_id'
        );
        $stmt->execute(['id' => $id, 'negocio_id' => $negocioId]);
    }
}
