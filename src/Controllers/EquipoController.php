<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Adicional;
use App\Models\Comision;
use App\Models\Empleado;
use App\Models\Sede;
use App\Models\Servicio;
use App\Services\Subida;

/**
 * El equipo de un negocio de reservas (belleza, sobre todo): el perfil que
 * ve el cliente para elegir a "su" profesional (foto, especialidad,
 * trabajos), qué servicios hace cada uno y a qué precio, su horario, los
 * adicionales que se suman al reservar y la liquidación de comisiones.
 * Todo esto es del dueño: toca precios, horarios y plata del equipo.
 */
class EquipoController
{
    public function lista(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $empleados = Empleado::listarPorSede((int) $negocio['id']);
        foreach ($empleados as &$empleado) {
            $empleado['fotos'] = count(Empleado::fotos((int) $empleado['id']));
            $empleado['servicios_propios'] = count(Empleado::servicios((int) $empleado['id']));
        }
        unset($empleado);

        ver('panel/empleados', [
            'titulo'    => 'Equipo · Veci',
            'activo'    => 'empleados',
            'negocio'   => $negocio,
            'empleados' => $empleados,
            'ok'        => flash_obtener('ok'),
        ], 'panel');
    }

    public function crear(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if (csrf_verificar() && $nombre !== '') {
            $id = Empleado::crear((int) $negocio['id'], mb_substr($nombre, 0, 120));
            flash_set('ok', "{$nombre} ya está en tu equipo. Ponle foto y especialidad para que tus clientes lo reconozcan.");
            redirigir('/panel/empleados/' . $id);
        }
        redirigir('/panel/empleados');
    }

    public function editar(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $empleado = Empleado::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($empleado === null) {
            redirigir('/panel/empleados');
        }

        ver('panel/empleado_editar', [
            'titulo'     => $empleado['nombre'] . ' · Equipo · Veci',
            'activo'     => 'empleados',
            'negocio'    => $negocio,
            'empleado'   => $empleado,
            'servicios'  => Servicio::listarPorSede((int) $negocio['id']),
            'propios'    => Empleado::servicios((int) $empleado['id']),
            'fotos'      => Empleado::fotos((int) $empleado['id']),
            'horario'    => $empleado['horario_atencion'] !== null ? Sede::horario(['horario_atencion' => $empleado['horario_atencion']]) : Sede::horario($negocio),
            'ok'         => flash_obtener('ok'),
            'error'      => flash_obtener('error'),
        ], 'panel');
    }

    public function guardarPerfil(array $parametros): void
    {
        [$negocio, $empleado] = $this->empleadoDelPost($parametros);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($nombre === '') {
            flash_set('error', 'El nombre no puede quedar vacío.');
            redirigir('/panel/empleados/' . $empleado['id']);
        }
        $comision = trim((string) ($_POST['comision_pct'] ?? ''));
        if ($comision !== '' && (!ctype_digit($comision) || (int) $comision > 100)) {
            flash_set('error', 'La comisión es un número de 0 a 100 (o vacía si no trabaja por porcentaje).');
            redirigir('/panel/empleados/' . $empleado['id']);
        }
        Empleado::actualizarPerfil(
            (int) $empleado['id'],
            (int) $negocio['id'],
            $nombre,
            trim((string) ($_POST['especialidad'] ?? '')),
            trim((string) ($_POST['bio'] ?? '')),
            $comision !== '' ? (int) $comision : null
        );
        $foto = Subida::imagen($_FILES['foto'] ?? null, 'equipo', 'perfil', 800);
        if ($foto !== null) {
            Subida::borrar($empleado['foto']);
            Empleado::guardarFoto((int) $empleado['id'], (int) $negocio['id'], $foto);
        } elseif (($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            flash_set('error', 'No pudimos usar esa foto: súbela en JPG, PNG o WebP de menos de 8 MB.');
            redirigir('/panel/empleados/' . $empleado['id']);
        }
        if (isset($_POST['quitar_foto']) && $foto === null) {
            Subida::borrar($empleado['foto']);
            Empleado::guardarFoto((int) $empleado['id'], (int) $negocio['id'], null);
        }
        flash_set('ok', 'Perfil guardado.');
        redirigir('/panel/empleados/' . $empleado['id']);
    }

    public function guardarServicios(array $parametros): void
    {
        [$negocio, $empleado] = $this->empleadoDelPost($parametros);
        $porServicio = [];
        foreach ((array) ($_POST['servicio'] ?? []) as $servicioId => $datos) {
            if (!is_array($datos)) {
                continue;
            }
            $precio = trim((string) ($datos['precio'] ?? ''));
            $duracion = trim((string) ($datos['duracion_min'] ?? ''));
            $porServicio[(int) $servicioId] = [
                'hace'         => !empty($datos['hace']),
                'precio'       => $precio !== '' ? dinero_desde_texto($precio) : null,
                'duracion_min' => $duracion !== '' ? (int) $duracion : null,
            ];
        }
        if (Empleado::guardarServicios((int) $empleado['id'], (int) $negocio['id'], $porServicio)) {
            flash_set('ok', 'Servicios de ' . $empleado['nombre'] . ' guardados.');
        } else {
            flash_set('error', 'Marca al menos un servicio. Si ' . $empleado['nombre'] . ' no va a atender por un tiempo, mejor ponlo en pausa abajo.');
        }
        redirigir('/panel/empleados/' . $empleado['id'] . '#servicios');
    }

    public function guardarHorario(array $parametros): void
    {
        [$negocio, $empleado] = $this->empleadoDelPost($parametros);
        if (($_POST['usa_horario'] ?? 'negocio') === 'negocio') {
            Empleado::guardarHorario((int) $empleado['id'], (int) $negocio['id'], null);
            flash_set('ok', $empleado['nombre'] . ' atiende en el horario del negocio.');
        } else {
            $avisos = [];
            $horario = Sede::horarioDesdePost($_POST, $avisos);
            Empleado::guardarHorario((int) $empleado['id'], (int) $negocio['id'], $horario);
            $aviso = aviso_pausas_invalidas($avisos);
            if ($aviso !== null) {
                flash_set('error', $aviso);
            } else {
                flash_set('ok', 'Horario de ' . $empleado['nombre'] . ' guardado.');
            }
        }
        redirigir('/panel/empleados/' . $empleado['id'] . '#horario');
    }

    public function subirFotos(array $parametros): void
    {
        [$negocio, $empleado] = $this->empleadoDelPost($parametros);
        $subidas = 0;
        $rechazadas = 0;
        foreach (Subida::multiples('fotos') as $archivo) {
            if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $ruta = Subida::imagen($archivo, 'equipo', 'trabajo', 1200);
            if ($ruta === null) {
                $rechazadas++;
                continue;
            }
            if (!Empleado::agregarFoto((int) $empleado['id'], $ruta)) {
                Subida::borrar($ruta);
                flash_set('error', 'Ya tiene ' . Empleado::MAX_FOTOS . ' trabajos: quita alguno para subir más.');
                redirigir('/panel/empleados/' . $empleado['id'] . '#trabajos');
            }
            $subidas++;
        }
        if ($rechazadas > 0) {
            flash_set('error', "{$rechazadas} foto" . ($rechazadas === 1 ? '' : 's') . ' no se pudo usar (JPG, PNG o WebP de menos de 8 MB).');
        } elseif ($subidas > 0) {
            flash_set('ok', $subidas === 1 ? 'Trabajo agregado.' : "{$subidas} trabajos agregados.");
        }
        redirigir('/panel/empleados/' . $empleado['id'] . '#trabajos');
    }

    public function eliminarFoto(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $volver = '/panel/empleados';
        if (csrf_verificar()) {
            $ruta = Empleado::eliminarFoto((int) $parametros['foto'], (int) $negocio['id']);
            Subida::borrar($ruta);
            $volver = '/panel/empleados/' . (int) $parametros['id'] . '#trabajos';
        }
        redirigir($volver);
    }

    public function alternar(array $parametros): void
    {
        [$negocio, $empleado] = $this->empleadoDelPost($parametros);
        Empleado::alternarActivo((int) $empleado['id'], (int) $negocio['id']);
        flash_set('ok', (int) $empleado['activo'] === 1
            ? $empleado['nombre'] . ' quedó en pausa: no aparece para reservar (sus citas siguen en la agenda).'
            : $empleado['nombre'] . ' vuelve a aparecer para reservar.');
        redirigir('/panel/empleados/' . $empleado['id']);
    }

    /** Quitar a alguien del equipo: sus citas quedan sin persona asignada (empleado_id NULL). */
    public function eliminar(array $parametros): void
    {
        [$negocio, $empleado] = $this->empleadoDelPost($parametros);
        if (Empleado::tieneCitas((int) $empleado['id'])) {
            // Borrarlo dejaría sus citas sin persona (no bloquean la agenda de
            // nadie) y sacaría lo que atendió de las comisiones del período.
            flash_set('error', $empleado['nombre'] . ' ya tiene citas en la agenda: en vez de quitarlo, ponlo en pausa. Así no aparece para reservar y su historial y comisiones quedan completos.');
            redirigir('/panel/empleados/' . $empleado['id']);
        }
        foreach (Empleado::fotos((int) $empleado['id']) as $foto) {
            Subida::borrar($foto['ruta']);
        }
        Subida::borrar($empleado['foto']);
        Empleado::eliminar((int) $empleado['id'], (int) $negocio['id']);
        flash_set('ok', $empleado['nombre'] . ' salió del equipo.');
        redirigir('/panel/empleados');
    }

    // ---------- Adicionales (se gestionan en la pantalla de servicios) ----------

    public function crearAdicional(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $precio = dinero_desde_texto((string) ($_POST['precio'] ?? ''));
        if (csrf_verificar() && $nombre !== '' && $precio > 0) {
            $servicioId = (int) ($_POST['servicio_id'] ?? 0);
            Adicional::crear((int) $negocio['id'], $nombre, $precio, (int) ($_POST['duracion_min'] ?? 0), $servicioId > 0 ? $servicioId : null);
            flash_set('ok', "«{$nombre}» se ofrece al reservar.");
        }
        redirigir('/panel/servicios#adicionales');
    }

    public function alternarAdicional(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        if (csrf_verificar()) {
            Adicional::alternar((int) $parametros['id'], (int) $negocio['id']);
        }
        redirigir('/panel/servicios#adicionales');
    }

    public function eliminarAdicional(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        if (csrf_verificar()) {
            Adicional::eliminar((int) $parametros['id'], (int) $negocio['id']);
        }
        redirigir('/panel/servicios#adicionales');
    }

    // ---------- Comisiones ----------

    public function comisiones(array $parametros): void
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $periodos = Comision::periodos();
        $clave = (string) ($_GET['periodo'] ?? 'semana');
        $periodo = $periodos[$clave] ?? $periodos['semana'];
        $clave = isset($periodos[$clave]) ? $clave : 'semana';
        $empleadoId = (int) ($_GET['empleado'] ?? 0);
        $empleado = $empleadoId > 0 ? Empleado::buscar($empleadoId, (int) $negocio['id']) : null;

        ver('panel/comisiones', [
            'titulo'      => 'Comisiones · Veci',
            'activo'      => 'comisiones',
            'negocio'     => $negocio,
            'periodos'    => $periodos,
            'clave'       => $clave,
            'periodo'     => $periodo,
            'liquidacion' => Comision::liquidacion((int) $negocio['id'], $periodo['desde'], $periodo['hasta']),
            'empleado'    => $empleado,
            'detalle'     => $empleado !== null ? Comision::detalle((int) $negocio['id'], (int) $empleado['id'], $periodo['desde'], $periodo['hasta']) : [],
        ], 'panel');
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} sede y empleado, o redirige */
    private function empleadoDelPost(array $parametros): array
    {
        $negocio = Auth::exigirSesion();
        Auth::exigirDueno($negocio);
        $empleado = Empleado::buscar((int) $parametros['id'], (int) $negocio['id']);
        if ($empleado === null || !csrf_verificar()) {
            redirigir('/panel/empleados');
        }

        return [$negocio, $empleado];
    }
}
