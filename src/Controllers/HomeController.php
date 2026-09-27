<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;

class HomeController
{
    public function index(array $parametros): void
    {
        ver('home/index', [
            'titulo'  => 'Veci — vende por WhatsApp sin comisión',
            'negocio' => Auth::negocioActual(),
        ]);
    }
}
