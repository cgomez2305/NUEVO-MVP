<?php

declare(strict_types=1);

namespace App\Models;

/**
 * El formulario de cobro llegó dos veces (doble toque, conexión que
 * reintenta): la venta ya quedó con ese token. Lleva el id de la que sí se
 * registró para mostrarla en vez de cobrar otra vez.
 */
class VentaDuplicada extends \RuntimeException
{
    public function __construct(public readonly int $ventaId)
    {
        parent::__construct('La venta ya estaba registrada.');
    }
}
