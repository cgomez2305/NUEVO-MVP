<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Cita;
use App\Models\Cotizacion;
use App\Models\LimiteTasa;
use App\Models\Sede;
use App\Models\Visita;
use App\Models\ZonaDomicilio;

/**
 * Visitas a domicilio (ver App\Models\Visita y App\Models\Cotizacion): la
 * hoja de cada visita en el panel (dirección, problema, fotos, "voy en
 * camino", cotización, evidencia), las zonas que cubre el negocio, los
 * servicios que toca repetir, y del lado del cliente la cotización y las
 * fotos de su visita.
 */
class VisitaController
{
    // ---------- Panel: hoja de la visita ----------

    public function hoja(array $parametros): void
    {
        [$negocio, $cita] = $this->citaDelPanel($parametros);
        $cotizacion = Cotizacion::deCita((int) $cita['id']);
        ver('panel/visita', [
            'titulo'      => 'Visita de ' . $cita['cliente_nombre'] . ' · Veci',
            'activo'      => 'citas',
            'negocio'     => $negocio,
            'cita'        => $cita,
            'fotos'       => Visita::fotos((int) $cita['id']),
            'cotizacion'  => $cotizacion,
            'aprobada'    => Cotizacion::aprobadaDeCita((int) $cita['id']),
            'anticipoRecibido' => Cotizacion::anticipoYaRecibido((int) $cita['id']),
            'itemsCotizacion' => $cotizacion !== null ? Cotizacion::items((int) $cotizacion['id']) : [],
            'whatsapp'    => flash_obtener('wa_visita'),
            'ok'          => flash_obtener('ok'),
            'error'       => flash_obtener('error'),
        ], 'panel');
    }

    /** Marca la salida y deja listo el WhatsApp con quién va y a qué hora llega. */
    public function enCamino(array $parametros): void
    {
        [$negocio, $cita] = $this->citaDelPost($parametros);
        if (!in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
            flash_set('error', 'Esta visita ya empezó o se cerró.');
            redirigir($this->hojaUrl($cita));
        }
        if (date('Y-m-d', strtotime((string) $cita['fecha_hora']) ?: 0) !== date('Y-m-d')) {
            flash_set('error', '"Voy en camino" es para el día de la visita.');
            redirigir($this->hojaUrl($cita));
        }
        Visita::enCamino((int) $cita['id'], (int) $negocio['id'], (int) ($_POST['minutos'] ?? 30));
        $cita = Cita::buscar((int) $cita['id'], (int) $negocio['id']);
        flash_set('ok', 'Quedó marcado que vas en camino.');
        flash_set('wa_visita', $this->enlaceWhatsapp($cita, Visita::mensajeEnCamino($cita, $negocio)));
        redirigir($this->hojaUrl($cita));
    }

    public function subirFotos(array $parametros): void
    {
        [, $cita] = $this->citaDelPost($parametros);
        $momento = ($_POST['momento'] ?? '') === 'despues' ? 'despues' : 'antes';
        $guardadas = Visita::subirFotos((int) $cita['id'], 'fotos', $momento);
        if ($guardadas > 0) {
            flash_set('ok', $guardadas . ' foto' . ($guardadas === 1 ? '' : 's') . ' de ' . ($momento === 'antes' ? 'antes' : 'después') . ' guardada' . ($guardadas === 1 ? '' : 's') . '.');
        } else {
            flash_set('error', 'No se guardó ninguna foto. Sube JPG, PNG o WEBP de hasta 8 MB (máximo ' . Visita::MAX_FOTOS_EVIDENCIA . ' por momento).');
        }
        redirigir($this->hojaUrl($cita) . '#evidencia');
    }

    public function eliminarFoto(array $parametros): void
    {
        [, $cita] = $this->citaDelPost($parametros);
        $foto = Visita::foto((int) $parametros['foto'], (int) $cita['id']);
        // Las fotos que mandó el cliente no las borra el negocio: son su evidencia.
        if ($foto !== null && $foto['momento'] !== 'cliente') {
            Visita::eliminarFoto((int) $foto['id'], (int) $cita['id']);
        }
        redirigir($this->hojaUrl($cita) . '#evidencia');
    }

    public function fotoPanel(array $parametros): void
    {
        [, $cita] = $this->citaDelPanel($parametros);
        $foto = Visita::foto((int) $parametros['foto'], (int) $cita['id']);
        if ($foto === null) {
            abortar404();
        }
        Visita::servirFoto($foto);
    }

    public function cotizar(array $parametros): void
    {
        [$negocio, $cita] = $this->citaDelPost($parametros);
        if (!Cotizacion::sePuedeCotizar($cita)) {
            flash_set('error', 'Esta visita ya se cerró: no se puede cotizar.');
            redirigir($this->hojaUrl($cita));
        }
        ['items' => $items, 'total' => $total] = Cotizacion::itemsDesdeFormulario((array) ($_POST['items'] ?? []));
        if ($items === [] || $total <= 0) {
            flash_set('error', $items === [] ? 'Escribe al menos un ítem con su valor.' : 'Revisa los valores: el total no cuadra (¿un cero de más?).');
            redirigir($this->hojaUrl($cita) . '#cotizacion');
        }
        $token = Cotizacion::crear(
            $cita,
            $items,
            $total,
            dinero_desde_texto((string) ($_POST['anticipo'] ?? '')),
            (int) ($_POST['garantia_dias'] ?? 0),
            (int) ($_POST['validez_dias'] ?? 8),
            trim((string) ($_POST['nota'] ?? ''))
        );
        $cotizacion = Cotizacion::buscarPorToken($token);
        flash_set('ok', 'Cotización por ' . pesos($total) . ' lista. Mándasela para que la apruebe.');
        flash_set('wa_visita', $this->enlaceWhatsapp($cita, Cotizacion::mensaje($cita, $cotizacion, $negocio)));
        redirigir($this->hojaUrl($cita) . '#cotizacion');
    }

    public function anticipoCotizacion(array $parametros): void
    {
        [$negocio, $cita] = $this->citaDelPost($parametros);
        $cotizacion = Cotizacion::aprobadaDeCita((int) $cita['id']);
        if ($cotizacion !== null) {
            Cotizacion::marcarAnticipoPagado((int) $cotizacion['id'], (int) $negocio['id']);
            flash_set('ok', 'Anticipo de materiales marcado como recibido.');
        }
        redirigir($this->hojaUrl($cita) . '#cotizacion');
    }

    // ---------- Panel: zonas que cubre ----------

    public function cobertura(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        ver('panel/cobertura', [
            'titulo'  => 'Zonas que cubres · Veci',
            'activo'  => 'cobertura',
            'negocio' => $negocio,
            'zonas'   => ZonaDomicilio::listarPorSede((int) $negocio['id']),
            'ok'      => flash_obtener('ok'),
            'error'   => flash_obtener('error'),
        ], 'panel');
    }

    public function guardarZona(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if (csrf_verificar() && $nombre !== '') {
            $id = isset($parametros['id']) ? (int) $parametros['id'] : null;
            if ($id === null || ZonaDomicilio::buscar($id, (int) $negocio['id']) !== null) {
                ZonaDomicilio::guardar((int) $negocio['id'], $id, $nombre, dinero_desde_texto((string) ($_POST['costo'] ?? '')), 0);
                flash_set('ok', "«{$nombre}» guardada.");
            }
        }
        redirigir('/panel/cobertura');
    }

    public function alternarZona(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        if (csrf_verificar()) {
            ZonaDomicilio::alternarActiva((int) $parametros['id'], (int) $negocio['id']);
        }
        redirigir('/panel/cobertura');
    }

    public function eliminarZona(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        if (csrf_verificar()) {
            ZonaDomicilio::eliminar((int) $parametros['id'], (int) $negocio['id']);
        }
        redirigir('/panel/cobertura');
    }

    // ---------- Panel: servicios que toca repetir ----------

    public function porRepetir(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        ver('panel/repetir', [
            'titulo'  => 'Toca repetir · Veci',
            'activo'  => 'repetir',
            'negocio' => $negocio,
            'filas'   => Visita::porRepetir((int) $negocio['id']),
        ], 'panel');
    }

    /** Marca como recordado y abre WhatsApp con el mensaje (en otra pestaña). */
    public function recordar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        if (!csrf_verificar()) {
            redirigir('/panel/repetir');
        }
        foreach (Visita::porRepetir((int) $negocio['id']) as $fila) {
            if ((int) $fila['id'] === (int) $parametros['id'] && Visita::marcarRecordado((int) $fila['id'], (int) $negocio['id'])) {
                header('Location: https://wa.me/57' . preg_replace('/\D+/', '', (string) $fila['cliente_telefono']) . '?text=' . rawurlencode(Visita::mensajeRepetir($fila, $negocio)));
                exit;
            }
        }
        redirigir('/panel/repetir');
    }

    // ---------- Cliente ----------

    public function cotizacionCliente(array $parametros): void
    {
        [$cotizacion, $cita, $sede] = $this->cotizacionPublica($parametros);
        ver('tienda/cotizacion', [
            'titulo'     => 'Cotización · ' . nombre_publico_sede($sede),
            'negocio'    => $sede,
            'cita'       => $cita,
            'cotizacion' => $cotizacion,
            'items'      => Cotizacion::items((int) $cotizacion['id']),
            'vencida'    => Cotizacion::vencida($cotizacion),
            'anticipoRecibido' => Cotizacion::anticipoYaRecibido((int) $cita['id']),
            'ok'         => flash_obtener('ok'),
            'error'      => flash_obtener('error'),
        ], 'tienda');
    }

    public function responderCotizacion(array $parametros): void
    {
        [$cotizacion] = $this->cotizacionPublica($parametros);
        $volver = '/cotizacion/' . $cotizacion['token'];
        if (!csrf_verificar()) {
            redirigir($volver);
        }
        $clave = 'cotizacion|' . $cotizacion['id'];
        if (LimiteTasa::excedido('cotizacion', $clave, 6, 3600)) {
            flash_set('error', 'Demasiados intentos. Escríbele al negocio por WhatsApp.');
            redirigir($volver);
        }
        LimiteTasa::registrar('cotizacion', $clave);
        $aprobar = ($_POST['respuesta'] ?? '') === 'aprobar';
        if (Cotizacion::responder($cotizacion, $aprobar)) {
            flash_set('ok', $aprobar ? '¡Listo! Aprobaste la cotización. El negocio ya puede seguir con el trabajo.' : 'Le avisamos al negocio que por ahora no la apruebas.');
            \App\Services\WebPush::notificarSede(
                (int) $cotizacion['sede_id'],
                $aprobar ? 'Cotización aprobada' : 'Cotización no aprobada',
                pesos((int) $cotizacion['total']),
                '/panel/visitas/' . (int) $cotizacion['cita_id']
            );
        } else {
            flash_set('error', 'Esta cotización ya no se puede responder (venció, cambió o ya la respondiste).');
        }
        redirigir($volver);
    }

    /** El cliente retira el permiso para el recordatorio del próximo servicio. */
    public function noRecordar(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }
        if (csrf_verificar()) {
            Visita::pedirRecordatorio((int) $cita['id'], false);
            flash_set('ok', 'Listo: no te escribiremos para recordarte el próximo.');
        }
        redirigir('/cita/' . $cita['token_gestion']);
    }

    public function fotoCliente(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }
        $foto = Visita::foto((int) $parametros['foto'], (int) $cita['id']);
        if ($foto === null) {
            abortar404();
        }
        Visita::servirFoto($foto);
    }

    // ---------- Ayudas ----------

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function citaDelPanel(array $parametros): array
    {
        $negocio = Auth::exigirSesion();
        $cita = Cita::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($cita === null || !Visita::esVisita($cita)) {
            abortar404();
        }

        return [$negocio, $cita];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function citaDelPost(array $parametros): array
    {
        [$negocio, $cita] = $this->citaDelPanel($parametros);
        if (post_demasiado_grande()) {
            flash_set('error', 'Las fotos pesan demasiado para subirlas juntas. Sube menos a la vez.');
            redirigir($this->hojaUrl($cita) . '#evidencia');
        }
        if (!csrf_verificar()) {
            redirigir($this->hojaUrl($cita));
        }

        return [$negocio, $cita];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function cotizacionPublica(array $parametros): array
    {
        $cotizacion = Cotizacion::buscarPorToken((string) $parametros['token']);
        if ($cotizacion === null || $cotizacion['estado'] === 'reemplazada') {
            abortar404();
        }
        $sede = Sede::buscarPorId((int) $cotizacion['sede_id']);
        $cita = $sede !== null ? Cita::buscar((int) $cotizacion['cita_id'], (int) $sede['id']) : null;
        if ($sede === null || $cita === null) {
            abortar404();
        }

        return [$cotizacion, $cita, $sede];
    }

    private function hojaUrl(array $cita): string
    {
        return '/panel/visitas/' . (int) $cita['id'];
    }

    private function enlaceWhatsapp(array $cita, string $texto): string
    {
        return 'https://wa.me/57' . preg_replace('/\D+/', '', (string) $cita['cliente_telefono']) . '?text=' . rawurlencode($texto);
    }
}
