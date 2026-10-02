<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cupon;

/**
 * Herramientas para que el negocio venda más y que el cliente vuelva:
 * cupones (y, en sus propias secciones, fidelidad, reseñas y referidos).
 * Todo es del NEGOCIO (no de una sede): un cupón sirve en todas sus sedes,
 * como el cliente es el mismo en todas. Solo el dueño las administra.
 */
class CrecimientoController
{
    public function cupones(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        $negocioId = (int) $negocio['negocio_id'];

        ver('panel/cupones', [
            'titulo'      => 'Cupones · Veci',
            'activo'      => 'cupones',
            'negocio'     => $negocio,
            'cupones'     => Cupon::listarPorNegocio($negocioId, 'panel'),
            'personales'  => array_slice(Cupon::listarPorNegocio($negocioId, 'copiloto'), 0, 20),
            'sugerido'    => $this->codigoSugerido($negocioId, (string) $negocio['negocio_nombre']),
            'ok'          => flash_obtener('ok'),
            'error'       => flash_obtener('error'),
            'abierto'     => isset($_GET['nuevo']),
        ], 'panel');
    }

    public function crearCupon(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        $negocioId = (int) $negocio['negocio_id'];

        if (!csrf_verificar()) {
            redirigir('/panel/cupones');
        }

        $codigo = Cupon::normalizarCodigo((string) ($_POST['codigo'] ?? ''));
        $tipo = ($_POST['tipo'] ?? '') === 'monto' ? 'monto' : 'porcentaje';
        $valor = dinero_desde_texto((string) ($_POST['valor'] ?? ''));
        $minimo = dinero_desde_texto((string) ($_POST['minimo_compra'] ?? ''));
        $vence = (string) ($_POST['vence_en'] ?? '');
        $usos = (int) ($_POST['usos_maximos'] ?? 0);

        $error = match (true) {
            strlen($codigo) < 3                                         => 'El código necesita al menos 3 letras o números.',
            Cupon::existeCodigo($negocioId, $codigo)                    => 'Ya tienes un cupón con el código ' . $codigo . '. Usa otro.',
            $tipo === 'porcentaje' && ($valor < 1 || $valor > 90)       => 'El porcentaje va de 1% a 90%.',
            $tipo === 'monto' && $valor < 100                           => 'Escribe cuántos pesos descuenta el cupón.',
            $vence !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vence) || $vence < date('Y-m-d')) => 'La fecha de vencimiento ya pasó.',
            default                                                     => null,
        };
        if ($error !== null) {
            flash_set('error', $error);
            redirigir('/panel/cupones?nuevo=1');
        }

        Cupon::crear($negocioId, [
            'codigo'              => $codigo,
            'tipo'                => $tipo,
            'valor'               => $valor,
            'minimo_compra'       => $minimo,
            'vence_en'            => $vence !== '' ? $vence : null,
            'usos_maximos'        => $usos > 0 ? $usos : null,
            'una_vez_por_cliente' => isset($_POST['una_vez_por_cliente']),
        ]);
        flash_set('ok', 'Cupón ' . $codigo . ' creado. Compártelo en tu estado de WhatsApp.');
        redirigir('/panel/cupones');
    }

    public function alternarCupon(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        if (csrf_verificar()) {
            Cupon::alternarActivo((int) $parametros['id'], (int) $negocio['negocio_id']);
        }
        redirigir('/panel/cupones');
    }

    public function eliminarCupon(array $parametros): void
    {
        $negocio = $this->exigirDueno();
        if (csrf_verificar()) {
            if (Cupon::eliminar((int) $parametros['id'], (int) $negocio['negocio_id'])) {
                flash_set('ok', 'Cupón eliminado.');
            } else {
                flash_set('error', 'Ese cupón ya se usó: no se borra para que tus pedidos lo sigan nombrando. Puedes pausarlo.');
            }
        }
        redirigir('/panel/cupones');
    }

    private function exigirDueno(): array
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);

        return $negocio;
    }

    /** Un código fácil de dictar a partir del nombre del negocio: "Doña María" → "DONAMARIA10". */
    private function codigoSugerido(int $negocioId, string $nombre): string
    {
        $ascii = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        $base = substr(strtoupper((string) preg_replace('/[^A-Za-z]/', '', $ascii)), 0, 12);
        $base = $base !== '' ? $base : 'VECI';
        $codigo = $base . '10';

        return Cupon::existeCodigo($negocioId, $codigo) ? Cupon::codigoAleatorio($base) : $codigo;
    }
}
