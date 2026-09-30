<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cita;
use App\Models\Pedido;

/**
 * Recibe la confirmación de pago de un proveedor de pagos Bre-B/PSP y marca
 * el pedido o la cita correspondiente como pagado.
 *
 * Este endpoint es real y queda probado de punta a punta localmente (ver
 * bin/probar_webhook_breb.php), pero conectarlo a un pago Bre-B de verdad
 * necesita credenciales de un participante Bre-B o de un PSP que exponga
 * webhooks sobre Bre-B (hoy no hay una cuenta así en este proyecto). Lo
 * que sí está resuelto: la verificación de firma, la idempotencia y a qué
 * pedido o cita corresponde cada notificación.
 *
 * Formato esperado (JSON):
 *   { "referencia": "VECI-P123", "estado": "aprobado", "monto": 25000 }
 * Cabecera: X-Breb-Signature: HMAC-SHA256(cuerpo, breb_webhook_secret) en hex.
 *
 * "referencia" es el código que el cliente ve en la confirmación de su
 * pedido o cita (VECI-P{id} para pedidos, VECI-C{id} para citas) y que se
 * le pide incluir en el concepto de la transferencia.
 */
class WebhookController
{
    public function breb(): void
    {
        $secreto = config('breb_webhook_secret');
        $cuerpo = file_get_contents('php://input') ?: '';

        if (!is_string($secreto) || $secreto === '') {
            http_response_code(503);
            echo json_encode(['error' => 'breb_webhook_secret no configurado']);
            exit;
        }

        $firmaRecibida = $_SERVER['HTTP_X_BREB_SIGNATURE'] ?? '';
        $firmaEsperada = hash_hmac('sha256', $cuerpo, $secreto);

        if (!is_string($firmaRecibida) || !hash_equals($firmaEsperada, $firmaRecibida)) {
            http_response_code(401);
            echo json_encode(['error' => 'firma inválida']);
            exit;
        }

        $datos = json_decode($cuerpo, true);
        $referencia = is_array($datos) ? (string) ($datos['referencia'] ?? '') : '';
        $estado = is_array($datos) ? (string) ($datos['estado'] ?? '') : '';

        if (!preg_match('/^VECI-([PC])(\d+)$/', $referencia, $m)) {
            http_response_code(400);
            echo json_encode(['error' => 'referencia inválida']);
            exit;
        }

        $aprobado = in_array($estado, ['aprobado', 'approved', 'completado', 'completed'], true);
        $tipo = $m[1];
        $id = (int) $m[2];

        if (!$aprobado) {
            http_response_code(200);
            echo json_encode(['ok' => true, 'nota' => 'estado no aprobado, no se marcó nada']);
            exit;
        }

        if ($tipo === 'P') {
            $this->confirmarPedido($id);
        } else {
            $this->confirmarCita($id);
        }

        http_response_code(200);
        echo json_encode(['ok' => true]);
        exit;
    }

    private function confirmarPedido(int $pedidoId): void
    {
        $pdo = \App\Database::conexion();
        $stmt = $pdo->prepare('SELECT sede_id, estado FROM pedidos WHERE id = :id');
        $stmt->execute(['id' => $pedidoId]);
        $pedido = $stmt->fetch();

        if ($pedido === false || $pedido['estado'] !== 'pendiente') {
            return; // no existe, o ya se procesó antes (idempotencia).
        }

        Pedido::actualizarEstado($pedidoId, (int) $pedido['sede_id'], 'pagado');
    }

    private function confirmarCita(int $citaId): void
    {
        $pdo = \App\Database::conexion();
        $stmt = $pdo->prepare('SELECT sede_id, estado FROM citas WHERE id = :id');
        $stmt->execute(['id' => $citaId]);
        $cita = $stmt->fetch();

        if ($cita === false || $cita['estado'] !== 'pendiente') {
            return;
        }

        Cita::actualizarEstado($citaId, (int) $cita['sede_id'], 'confirmada');
        Cita::marcarAnticipoPagado($citaId, (int) $cita['sede_id']);
    }
}
