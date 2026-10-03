<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Al fiarle a un "cliente nuevo", el WhatsApp escrito ya es de otra persona
 * guardada con otro nombre. No se le carga en silencio: se pregunta si es
 * la misma ("Ese WhatsApp es de X").
 */
class ClienteDeOtroNombre extends \DomainException
{
    public function __construct(public readonly int $clienteId, public readonly string $nombre)
    {
        parent::__construct("Ese WhatsApp ya está guardado a nombre de «{$nombre}». Si es la misma persona, confírmalo; si no, revisa el número.");
    }
}
