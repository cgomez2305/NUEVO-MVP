<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cliente;
use App\Models\Consentimiento;
use App\Models\Sede;

/**
 * Página sin login donde el cliente de un negocio activa o retira las
 * promociones por WhatsApp (/preferencias/{token}). El enlace va al pie de
 * cada mensaje del copiloto y en la solicitud de permiso: retirar el
 * permiso tiene que ser tan fácil como darlo (Ley 1581).
 */
class PreferenciasController
{
    public function mostrar(array $parametros): void
    {
        [$cliente, $sede] = $this->clienteOAbortar((string) $parametros['token']);

        ver('tienda/preferencias', [
            'titulo'  => 'Tus mensajes · ' . ($sede['negocio_nombre'] ?? $sede['nombre']),
            'negocio' => $sede,
            'cliente' => $cliente,
            'token'   => (string) $parametros['token'],
            'ok'      => flash_obtener('ok'),
        ], 'tienda');
    }

    public function guardar(array $parametros): void
    {
        $token = (string) $parametros['token'];
        [$cliente] = $this->clienteOAbortar($token);

        if (csrf_verificar()) {
            $acepta = ($_POST['promociones'] ?? '') === 'si';
            $cambio = Consentimiento::cambiarMarketing((int) $cliente['negocio_id'], (int) $cliente['id'], $acepta, 'enlace');
            flash_set('ok', match (true) {
                !$cambio => 'No había nada que cambiar: tu preferencia sigue igual.',
                $acepta  => 'Listo: te avisaremos de promociones y novedades.',
                default  => 'Listo: no te volveremos a escribir con promociones.',
            });
        }

        redirigir('/preferencias/' . $token);
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function clienteOAbortar(string $token): array
    {
        $cliente = Cliente::buscarPorTokenPreferencias($token);
        if ($cliente === null) {
            abortar404();
        }
        // La marca del negocio para la cabecera: su primera sede.
        $sedes = Sede::listarPorNegocio((int) $cliente['negocio_id']);
        if ($sedes === []) {
            abortar404();
        }

        return [$cliente, $sedes[0]];
    }
}
