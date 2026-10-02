<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * Avisos de estado al cliente por WhatsApp ("tu pedido está listo", "tu
 * cita quedó confirmada"). Con la API de WhatsApp Business configurada
 * (ver RecordatorioWhatsapp::disponible) salen solos al cambiar el estado;
 * sin ella, el panel muestra "Avisarle" con el mensaje listo en wa.me.
 * Las columnas aviso_estado guardan de qué estado ya se avisó, para no
 * ofrecer (ni mandar) el mismo aviso dos veces.
 *
 * Nota de la API: fuera de la ventana de 24 h desde el último mensaje del
 * cliente, Meta exige plantillas aprobadas; si el envío falla, el aviso
 * queda pendiente y el botón manual sigue disponible.
 */
class AvisoEstado
{
    /** El texto para el cliente, o null si ese estado no amerita aviso. */
    public static function texto(string $tipo, array $registro, array $sede): ?string
    {
        $nombre = explode(' ', trim((string) $registro['cliente_nombre']))[0];
        $negocio = nombre_publico_sede($sede);

        if ($tipo === 'pedido') {
            $numero = '#' . (int) $registro['id'];
            return match ((string) $registro['estado']) {
                'pagado'    => "Hola {$nombre}, recibimos el pago de tu pedido {$numero} en {$negocio}. ¡Gracias!",
                'en_cocina' => "Hola {$nombre}, tu pedido {$numero} ya se está preparando en {$negocio}.",
                'listo'     => match ((string) $registro['tipo_entrega']) {
                    'recoger' => "Hola {$nombre}, tu pedido {$numero} está listo para recoger en {$negocio}" . (!empty($sede['direccion']) ? " ({$sede['direccion']})" : '') . '.',
                    'mesa'    => "Hola {$nombre}, tu pedido {$numero} ya va para tu mesa.",
                    default   => "Hola {$nombre}, tu pedido {$numero} está listo y sale en un momento.",
                },
                'en_camino' => "Hola {$nombre}, tu pedido {$numero} de {$negocio} va en camino" . (!empty($registro['direccion']) ? " a {$registro['direccion']}" : '') . '.',
                'entregado' => "Hola {$nombre}, tu pedido {$numero} quedó entregado. ¡Que lo disfrutes!",
                'cancelado' => "Hola {$nombre}, tu pedido {$numero} en {$negocio} fue cancelado. Si tienes dudas, escríbenos por aquí.",
                default     => null,
            };
        }

        $cuando = date('d/m', strtotime((string) $registro['fecha_hora'])) . ' a las ' . date('g:i a', strtotime((string) $registro['fecha_hora']));
        return match ((string) $registro['estado']) {
            'confirmada' => "Hola {$nombre}, tu cita de {$registro['nombre_servicio']} en {$negocio} quedó confirmada para el {$cuando}.",
            'cancelada'  => "Hola {$nombre}, tu cita de {$registro['nombre_servicio']} del {$cuando} en {$negocio} fue cancelada. Escríbenos si quieres otra hora.",
            default      => null,
        };
    }

    /** ¿Hay un aviso que todavía no se ha dado para el estado actual? */
    public static function pendiente(string $tipo, array $registro, array $sede): bool
    {
        return ($registro['aviso_estado'] ?? null) !== $registro['estado'] && self::texto($tipo, $registro, $sede) !== null;
    }

    public static function enlace(array $registro, string $texto): string
    {
        return 'https://wa.me/57' . preg_replace('/\D+/', '', (string) $registro['cliente_telefono']) . '?text=' . rawurlencode($texto);
    }

    /** Lo manda por la API si está configurada. true = ya quedó avisado. */
    public static function automatico(string $tipo, array $registro, array $sede): bool
    {
        $texto = self::texto($tipo, $registro, $sede);
        if ($texto === null || !RecordatorioWhatsapp::disponible()) {
            return false;
        }
        if (!RecordatorioWhatsapp::enviar($registro, (string) $registro['cliente_telefono'], $texto)) {
            return false;
        }
        self::marcar($tipo, (int) $registro['id'], (string) $registro['estado']);

        return true;
    }

    public static function marcar(string $tipo, int $id, string $estado): void
    {
        $tabla = $tipo === 'pedido' ? 'pedidos' : 'citas';
        Database::conexion()->prepare("UPDATE {$tabla} SET aviso_estado = :e WHERE id = :id")->execute(['e' => $estado, 'id' => $id]);
    }
}
