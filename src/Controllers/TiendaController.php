<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Adicional;
use App\Models\Bono;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\Fidelidad;
use App\Models\Resena;
use App\Models\ZonaDomicilio;
use App\Models\Empleado;
use App\Models\FechaBloqueada;
use App\Models\Imprevisto;
use App\Models\LimiteTasa;
use App\Models\ListaEspera;
use App\Models\Sede;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Visita;
use App\Services\WebPush;

/**
 * El flujo B de la maqueta: el cliente entra a la tienda y, según el tipo
 * de negocio, arma su carrito y pide por WhatsApp (pedidos) o reserva un
 * horario para un servicio (reservas). El carrito/las citas viven en sesión
 * o en base de datos según corresponda, separados por negocio.
 */
class TiendaController
{
    public function mostrar(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        $metaDescripcion = !empty($negocio['descripcion'])
            ? (string) $negocio['descripcion']
            : "Pide o reserva con {$negocio['nombre']} directo por WhatsApp, sin comisión.";

        if ($negocio['tipo_negocio'] === 'reservas') {
            $horarioSede = Sede::horario($negocio);
            $abiertoAhora = negocio_abierto_ahora($horarioSede);
            $servicios = Servicio::listarPorSede((int) $negocio['id'], true);

            // El "próximo cupo hoy" por servicio solo tiene sentido cuando hay
            // UN solo horario compartido para toda la sede: con empleados cada
            // uno puede tener su propia agenda, y mostrar un cupo "del
            // servicio" sería adivinar cuál empleado lo atendería.
            $disponibilidadHoy = [];
            if (Empleado::listarPorSede((int) $negocio['id'], true) === []) {
                $hoy = date('Y-m-d');
                $ocupadosHoy = Cita::ocupadosEnFecha((int) $negocio['id'], $hoy);
                foreach ($servicios as $servicio) {
                    $slots = Cita::calcularDisponibilidad(
                        $horarioSede,
                        (int) $negocio['intervalo_citas_min'],
                        $hoy,
                        (int) $servicio['duracion_min'],
                        $ocupadosHoy,
                        (int) $negocio['colchon_min']
                    );
                    $disponibilidadHoy[$servicio['id']] = $slots[0] ?? null;
                }
            }

            ver('tienda/servicios', [
                'titulo'           => nombre_publico_sede($negocio) . ' · Veci',
                'negocio'          => $negocio,
                'servicios'        => $servicios,
                'horario'          => horario_resumen($horarioSede),
                'fidelidad'        => Fidelidad::activa((int) $negocio['negocio_id']),
                'resenas'          => $this->resenasParaTienda($negocio),
                'paquetes'         => Bono::paquetes((int) $negocio['id'], true),
                'abiertoAhora'     => $abiertoAhora,
                'proximaApertura'  => $abiertoAhora !== null && !$abiertoAhora['abierto'] ? negocio_proxima_apertura($horarioSede) : null,
                'disponibilidadHoy' => $disponibilidadHoy,
                'equipo'           => Empleado::listarPorSede((int) $negocio['id'], true),
                // A domicilio: las zonas que cubre (con su transporte) en vez de "ven al local".
                'zonas'            => Visita::esDomicilio($negocio) ? ZonaDomicilio::listarPorSede((int) $negocio['id'], true) : [],
                'filaAbierta'      => !Visita::esDomicilio($negocio) && (int) $negocio['fila_abierta'] === 1 && ($abiertoAhora === null || $abiertoAhora['abierto']),
                'metaDescripcion'  => $metaDescripcion,
                'canonicalUrl'     => url_publica('/t/' . $negocio['slug']),
            ], 'tienda');
            return;
        }

        $productos = Producto::listarPorSede((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);
        $horarioSede = Sede::horario($negocio);
        $abiertoAhora = negocio_abierto_ahora($horarioSede);

        ver('tienda/mostrar', [
            'titulo'          => nombre_publico_sede($negocio) . ' · Veci',
            'negocio'         => $negocio,
            'productos'       => $productos,
            'carrito'         => $carrito,
            'horario'         => horario_resumen($horarioSede),
            'fidelidad'       => Fidelidad::activa((int) $negocio['negocio_id']),
            'resenas'         => $this->resenasParaTienda($negocio),
            'zonas'           => ZonaDomicilio::listarPorSede((int) $negocio['id'], true),
            'masPedidos'      => Producto::masPedidos((int) $negocio['id']),
            'abiertoAhora'    => $abiertoAhora,
            'proximaApertura' => $abiertoAhora !== null && !$abiertoAhora['abierto'] ? negocio_proxima_apertura($horarioSede) : null,
            'metaDescripcion' => $metaDescripcion,
            'canonicalUrl'    => url_publica('/t/' . $negocio['slug']),
            // Avisos que mandan aquí: "solo quedan N" sin JS, carrito vacío al pedir.
            'error'           => flash_obtener('error'),
        ], 'tienda');
    }

    public function reservar(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        if ($negocio['tipo_negocio'] !== 'reservas') {
            abortar404();
        }

        $servicio = Servicio::buscar((int) $parametros['servicio'], (int) $negocio['id']);
        if ($servicio === null || (int) $servicio['activo'] !== 1) {
            abortar404();
        }
        if ((int) $servicio['agotado'] === 1) {
            redirigir('/t/' . $negocio['slug']);
        }

        $fecha = (string) ($_GET['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
            $fecha = date('Y-m-d');
        }

        // Con equipo, solo aparecen quienes hacen este servicio. Si hay
        // equipo pero nadie lo hace, el servicio no se puede reservar (antes
        // caía en "sin equipo" y se agendaba contra el negocio entero).
        $hayEquipo = Empleado::listarPorSede((int) $negocio['id'], true) !== [];
        $empleados = $hayEquipo ? Empleado::paraServicio((int) $negocio['id'], (int) $servicio['id']) : [];
        $sinProfesional = $hayEquipo && $empleados === [];
        // A domicilio el cliente no elige técnico: lo asigna el negocio (el
        // primero libre en la franja) y el cliente ve quién va antes de la
        // visita. El precio es el del servicio, no el de quien quede.
        $aDomicilio = Visita::esDomicilio($negocio);
        $tecnicos = $aDomicilio ? $empleados : [];
        if ($aDomicilio) {
            $empleados = [];
        }
        $empleadoId = (int) ($_GET['empleado'] ?? 0);
        $empleadoElegido = null;
        foreach ($empleados as $emp) {
            if ((int) $emp['id'] === $empleadoId) {
                $empleadoElegido = $emp;
                break;
            }
        }

        // Precio y duración con ese profesional, más los adicionales elegidos:
        // los cupos se calculan con el tiempo total.
        $condiciones = Empleado::condiciones($empleadoElegido, $servicio);
        $adicionalesDisponibles = Adicional::paraServicio((int) $negocio['id'], (int) $servicio['id']);
        $adicionalesElegidos = Adicional::elegidos((int) $negocio['id'], (int) $servicio['id'], Adicional::idsDesdeTexto((string) ($_GET['ad'] ?? '')));
        $duracionTotal = $condiciones['duracion_min'] + array_sum(array_map(fn ($a) => (int) $a['duracion_min'], $adicionalesElegidos));
        $precioAdicionales = array_sum(array_map(fn ($a) => (int) $a['precio'], $adicionalesElegidos));

        // Los próximos 14 días, para que el cliente pueda cambiar de fecha sin escribirla a mano.
        $fechasDisponibles = [];
        for ($i = 0; $i < 14; $i++) {
            $fechasDisponibles[] = date('Y-m-d', strtotime("+{$i} days"));
        }

        $bloqueada = false;
        $faltaElegirEmpleado = $empleados !== [] && $empleadoElegido === null;
        if ($sinProfesional) {
            $faltaElegirEmpleado = true;
        }
        $slots = [];
        $cerradoEseDia = false;
        $proximoDisponible = null;
        $horaElegida = (string) ($_GET['hora'] ?? '');
        $slotValido = false;
        $disponibilidadError = false;
        $franjas = [];
        $franjaElegida = null;

        // Todo lo que depende de la base de datos para calcular
        // disponibilidad va envuelto aquí: si algo falla (conexión caída,
        // dato corrupto en horario_atencion), el cliente ve un mensaje
        // honesto con un botón de reintentar en vez de una pantalla en
        // blanco o un error de PHP crudo.
        try {
            $horario = Empleado::horario($empleadoElegido, $negocio);
            $intervalo = (int) $negocio['intervalo_citas_min'];
            $bloqueada = FechaBloqueada::estaBloqueada((int) $negocio['id'], $fecha);

            if ($aDomicilio) {
                // Visitas: se elige una franja (mañana / tarde), no una hora.
                [$franjas, $franjaElegida, $proximoDisponible] = $this->franjasDeVisita($negocio, $fecha, $duracionTotal, $tecnicos, $sinProfesional, $fechasDisponibles);
                $slots = array_values(array_filter(array_column($franjas, 'hora')));
                $horaElegida = $franjaElegida['hora'] ?? '';
                $cerradoEseDia = !$bloqueada && !$sinProfesional && Visita::franjas($horario, $fecha) === [];
                $slotValido = $franjaElegida !== null;
            } else {
                if (!$bloqueada && !$faltaElegirEmpleado) {
                    $ocupados = Cita::ocupadosEnFecha((int) $negocio['id'], $fecha, null, $empleadoElegido['id'] ?? null);
                    $slots = Cita::calcularDisponibilidad($horario, $intervalo, $fecha, $duracionTotal, $ocupados, (int) $negocio['colchon_min']);
                }

                $slotValido = $horaElegida !== '' && in_array($horaElegida, $slots, true);

                // Distingue "este día no se atiende" (horario sin ese día) de
                // "todo ocupado ese día", para no decirle al cliente "sin cupos"
                // cuando en realidad el negocio ni siquiera abre.
                $cerradoEseDia = !$bloqueada && !$faltaElegirEmpleado && !isset($horario[(string) (int) date('N', strtotime($fecha))]);

                // Sin cupos hoy: antes de mandar al cliente directo a la lista de
                // espera, se busca el próximo día con hueco real (mismo horario,
                // misma duración, mismo empleado si aplica) para ofrecerlo como
                // salida principal. Acotado a los mismos 14 días de arriba, así el
                // costo (una consulta de disponibilidad por día) tiene techo.
                if ($slots === [] && !$faltaElegirEmpleado) {
                    foreach ($fechasDisponibles as $opcion) {
                        if ($opcion <= $fecha) {
                            continue;
                        }
                        if (FechaBloqueada::estaBloqueada((int) $negocio['id'], $opcion)) {
                            continue;
                        }
                        $ocupadosOpcion = Cita::ocupadosEnFecha((int) $negocio['id'], $opcion, null, $empleadoElegido['id'] ?? null);
                        $slotsOpcion = Cita::calcularDisponibilidad($horario, $intervalo, $opcion, $duracionTotal, $ocupadosOpcion, (int) $negocio['colchon_min']);
                        if ($slotsOpcion !== []) {
                            $proximoDisponible = ['fecha' => $opcion, 'hora' => $slotsOpcion[0]];
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $disponibilidadError = true;
            $slots = [];
            $franjas = [];
            $franjaElegida = null;
            $proximoDisponible = null;
            $slotValido = false;
        }

        ver('tienda/reservar', [
            'aDomicilio'        => $aDomicilio,
            'franjas'           => $franjas,
            'franjaElegida'     => $franjaElegida,
            'zonas'             => $aDomicilio ? ZonaDomicilio::listarPorSede((int) $negocio['id'], true) : [],
            'titulo'            => 'Reservar ' . $servicio['nombre'] . ' · ' . nombre_publico_sede($negocio),
            'negocio'           => $negocio,
            'servicio'          => $servicio,
            'anticipo'          => Servicio::calcularAnticipo(['precio' => $condiciones['precio'] + $precioAdicionales] + $servicio),
            'condiciones'       => $condiciones,
            'duracionTotal'     => $duracionTotal,
            'precioAdicionales' => $precioAdicionales,
            'adicionalesDisponibles' => $adicionalesDisponibles,
            'adicionalesElegidos'    => $adicionalesElegidos,
            'sinProfesional'    => $sinProfesional,
            'fecha'             => $fecha,
            'fechasDisponibles' => $fechasDisponibles,
            'proximoDisponible' => $proximoDisponible,
            'empleados'         => $empleados,
            'empleadoElegido'   => $empleadoElegido,
            'faltaElegirEmpleado' => $faltaElegirEmpleado,
            'slots'             => $slots,
            'bloqueada'         => $bloqueada,
            'cerradoEseDia'     => $cerradoEseDia,
            'disponibilidadError' => $disponibilidadError,
            'horaElegida'       => $slotValido ? $horaElegida : null,
            'error'             => flash_obtener('error'),
            'ok'                => flash_obtener('ok'),
            // Solo viene con valor justo después de unirse a la lista de
            // espera en esta misma vuelta (ver unirseListaEspera): habilita
            // la tarjeta de confirmación y el botón "Salir de la lista". Una
            // recarga posterior de la URL ya no lo trae — es un flash, no
            // sesión — así que ese botón no queda como una gestión
            // permanente del cupo sin login.
            'listaEsperaId'     => ($idFlash = flash_obtener('lista_espera_id')) !== null ? (int) $idFlash : null,
        ], 'tienda');
    }

    /**
     * Franjas de llegada del día para una visita, la elegida (?franja=) y,
     * si ese día no queda ninguna, la primera franja libre de los próximos
     * días (como "Próximo turno libre" en las citas del local).
     *
     * @return array{0: array<string, array<string, mixed>>, 1: ?array<string, mixed>, 2: ?array<string, string>}
     */
    private function franjasDeVisita(array $negocio, string $fecha, int $duracion, array $tecnicos, bool $sinProfesional, array $fechasDisponibles): array
    {
        if ($sinProfesional) {
            return [[], null, null];
        }
        $franjas = Visita::disponibilidad($negocio, $fecha, $duracion, $tecnicos);
        $clave = (string) ($_GET['franja'] ?? '');
        $elegida = isset($franjas[$clave]) && $franjas[$clave]['hora'] !== null ? $franjas[$clave] : null;
        $proximo = null;
        if (array_filter(array_column($franjas, 'hora')) === []) {
            foreach ($fechasDisponibles as $opcion) {
                if ($opcion <= $fecha) {
                    continue;
                }
                foreach (Visita::disponibilidad($negocio, $opcion, $duracion, $tecnicos) as $franja) {
                    if ($franja['hora'] !== null) {
                        $proximo = ['fecha' => $opcion, 'franja' => $franja['clave'], 'etiqueta' => $franja['etiqueta']];
                        break 2;
                    }
                }
            }
        }

        return [$franjas, $elegida, $proximo];
    }

    /** Perfil público de un profesional: foto, especialidad, sus trabajos y lo que hace (con sus precios). */
    public function profesional(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        $empleado = Empleado::buscar((int) $parametros['empleado'], (int) $negocio['id']);
        if ($negocio['tipo_negocio'] !== 'reservas' || $empleado === null || (int) $empleado['activo'] !== 1) {
            abortar404();
        }
        $servicios = [];
        foreach (Servicio::listarPorSede((int) $negocio['id'], true) as $servicio) {
            $hace = array_filter(Empleado::paraServicio((int) $negocio['id'], (int) $servicio['id']), fn ($e) => (int) $e['id'] === (int) $empleado['id']);
            if ($hace !== []) {
                $servicios[] = ['servicio' => $servicio, 'condiciones' => Empleado::condiciones($empleado, $servicio)];
            }
        }

        ver('tienda/profesional', [
            'titulo'          => $empleado['nombre'] . ' · ' . nombre_publico_sede($negocio),
            'negocio'         => $negocio,
            'empleado'        => $empleado,
            'fotos'           => Empleado::fotos((int) $empleado['id']),
            'servicios'       => $servicios,
            'metaDescripcion' => $empleado['nombre'] . (!empty($empleado['especialidad']) ? ', ' . $empleado['especialidad'] : '') . ' en ' . nombre_publico_sede($negocio) . '. Mira sus trabajos y reserva con su agenda.',
            'canonicalUrl'    => url_publica('/t/' . $negocio['slug'] . '/equipo/' . $empleado['id']),
        ], 'tienda');
    }

    public function crearCita(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        if ($negocio['tipo_negocio'] !== 'reservas') {
            abortar404();
        }

        $servicioId = (int) ($_POST['servicio_id'] ?? 0);
        $servicio = Servicio::buscar($servicioId, (int) $negocio['id']);
        $fecha = (string) ($_POST['fecha'] ?? '');
        $hora = (string) ($_POST['hora'] ?? '');

        $volverAReservar = '/t/' . $negocio['slug'] . '/reservar/' . $servicioId . '?fecha=' . rawurlencode($fecha);

        if (post_demasiado_grande()) {
            // PHP descarta todo el formulario si las fotos superan el límite:
            // se dice qué pasó en vez de volver al inicio sin explicación.
            flash_set('error', 'Las fotos pesan demasiado para enviarlas juntas. Manda menos fotos (o más livianas) y vuelve a intentarlo.');
            $origen = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) . (($q = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_QUERY)) ? '?' . $q : '');
            redirigir(str_starts_with($origen, '/t/' . $negocio['slug'] . '/reservar/') ? $origen : '/t/' . $negocio['slug']);
        }

        if (!csrf_verificar() || $servicio === null || (int) $servicio['agotado'] === 1 || (int) $servicio['activo'] !== 1) {
            redirigir('/t/' . $negocio['slug']);
        }

        $this->exigirTasaPublica('cita', $negocio, $volverAReservar);

        if ($this->limiteDelMesAlcanzado($negocio)) {
            flash_set('error', 'Este negocio ya llegó al número de citas que puede recibir este mes. Escríbele directo por WhatsApp para agendar.');
            redirigir($volverAReservar);
        }

        $aDomicilio = Visita::esDomicilio($negocio);
        // En una visita la hora que viene en el formulario es solo la del
        // momento en que se abrió la página: abajo se recalcula con la franja.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}$/', $hora)
            || ($aDomicilio ? $fecha < date('Y-m-d') : strtotime("{$fecha} {$hora}") < time())) {
            flash_set('error', 'Elige una fecha y una hora válidas.');
            redirigir($volverAReservar);
        }

        if (FechaBloqueada::estaBloqueada((int) $negocio['id'], $fecha)) {
            flash_set('error', 'Ese día no está disponible. Elige otra fecha.');
            redirigir($volverAReservar);
        }

        $empleadoId = null;
        $empleado = null;
        if (!$aDomicilio && Empleado::listarPorSede((int) $negocio['id'], true) !== []) {
            // Solo vale alguien activo que haga este servicio.
            $empleadoPost = (int) ($_POST['empleado_id'] ?? 0);
            foreach (Empleado::paraServicio((int) $negocio['id'], (int) $servicio['id']) as $candidato) {
                if ((int) $candidato['id'] === $empleadoPost) {
                    $empleado = $candidato;
                }
            }
            if ($empleado === null) {
                flash_set('error', 'Elige con quién quieres agendar.');
                redirigir($volverAReservar);
            }
            $empleadoId = (int) $empleado['id'];
        }
        $condiciones = Empleado::condiciones($empleado, $servicio);
        $adicionales = Adicional::elegidos((int) $negocio['id'], (int) $servicio['id'], Adicional::idsDesdeTexto((string) ($_POST['adicionales'] ?? '')));
        $precioAdicionales = array_sum(array_map(fn ($a) => (int) $a['precio'], $adicionales));
        $duracionTotal = $condiciones['duracion_min'] + array_sum(array_map(fn ($a) => (int) $a['duracion_min'], $adicionales));
        $precioTotal = $condiciones['precio'] + $precioAdicionales;
        if ($adicionales !== []) {
            $volverAReservar .= '&ad=' . implode(',', array_column($adicionales, 'id'));
        }
        if ($empleadoId !== null) {
            $volverAReservar .= '&empleado=' . $empleadoId;
        }

        // Dos reservas al mismo tiempo para el mismo día verían el mismo cupo
        // libre (en visitas, todos reciben "el primer turno de la franja"):
        // el candado las pone en fila hasta que la primera queda guardada.
        // Si algo redirige antes, MySQL lo suelta al cerrar la conexión.
        $candado = 'veci_cita_' . (int) $negocio['id'] . '_' . $fecha;
        $st = \App\Database::conexion()->prepare('SELECT GET_LOCK(:k, 10)');
        $st->execute(['k' => $candado]);
        $st->fetchColumn();

        $franja = null;
        if ($aDomicilio) {
            // Visita: se vuelve a buscar el primer turno libre de la franja y
            // el técnico que lo toma (lo que llegó en "hora" no manda).
            $hayEquipo = Empleado::listarPorSede((int) $negocio['id'], true) !== [];
            $tecnicos = $hayEquipo ? Empleado::paraServicio((int) $negocio['id'], (int) $servicio['id']) : [];
            $claveFranja = (string) ($_POST['franja'] ?? '');
            $franja = $hayEquipo && $tecnicos === [] ? null : (Visita::disponibilidad($negocio, $fecha, $duracionTotal, $tecnicos)[$claveFranja] ?? null);
            if ($franja === null || $franja['hora'] === null) {
                flash_set('error', 'Esa franja ya se llenó. Elige otra.');
                redirigir($volverAReservar);
            }
            $hora = $franja['hora'];
            // Pedida para hoy a media franja: la promesa empieza en el turno
            // reservado ("entre 11:30 y 12:00"), no a las 8 que ya pasaron.
            if ($fecha === date('Y-m-d') && $hora > $franja['inicio']) {
                $franja['inicio'] = $hora;
            }
            $empleado = $franja['empleado'];
            $empleadoId = $empleado !== null ? (int) $empleado['id'] : null;
            $volverConTurno = $volverAReservar . '&franja=' . rawurlencode($claveFranja);
        } else {
            // Vuelve a calcular disponibilidad justo antes de guardar, por si alguien más
            // tomó ese horario mientras el cliente llenaba el formulario.
            $horario = Empleado::horario($empleado, $negocio);
            $ocupados = Cita::ocupadosEnFecha((int) $negocio['id'], $fecha, null, $empleadoId);
            $slots = Cita::calcularDisponibilidad(
                $horario,
                (int) $negocio['intervalo_citas_min'],
                $fecha,
                $duracionTotal,
                $ocupados,
                (int) $negocio['colchon_min']
            );

            if (!in_array($hora, $slots, true)) {
                flash_set('error', 'Ese horario ya no está disponible. Elige otro.');
                redirigir($volverAReservar);
            }
            $volverConTurno = $volverAReservar . '&hora=' . rawurlencode($hora);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
        $autorizo = isset($_POST['autorizo_datos']);

        if ($nombre === '' || $telefono === '' || !$autorizo) {
            flash_set('error', 'Escribe tu nombre, tu WhatsApp y autoriza el tratamiento de tus datos para continuar.');
            redirigir($volverConTurno);
        }

        // Salud: el motivo es opcional y es un dato sensible; si lo escribe,
        // tiene que autorizar aparte su tratamiento (no se puede condicionar
        // la cita a darlo: por eso es opcional).
        $motivoConsulta = '';
        if (\App\Models\PlanTratamiento::esSalud($negocio)) {
            $motivoConsulta = trim((string) ($_POST['motivo_consulta'] ?? ''));
            if ($motivoConsulta !== '' && empty($_POST['autorizo_sensibles'])) {
                flash_set('error', 'Para guardar el motivo de tu consulta necesitamos tu autorización para datos de salud. Si prefieres, déjalo en blanco y lo cuentas en la cita.');
                redirigir($volverConTurno);
            }
        }

        // Datos de la visita: a dónde ir y qué pasa. La zona suma su recargo
        // de transporte al valor (se ve antes de reservar).
        $datosVisita = null;
        $recargo = 0;
        if ($aDomicilio) {
            $zonas = ZonaDomicilio::listarPorSede((int) $negocio['id'], true);
            $zona = null;
            if ($zonas !== []) {
                $zona = ZonaDomicilio::buscar((int) ($_POST['zona_id'] ?? 0), (int) $negocio['id']);
                $zona = $zona !== null && (int) $zona['activa'] === 1 ? $zona : null;
            }
            $direccion = trim((string) ($_POST['direccion'] ?? ''));
            $problema = trim((string) ($_POST['problema'] ?? ''));
            if (mb_strlen($direccion) < 5 || mb_strlen($problema) < 3 || ($zonas !== [] && $zona === null)) {
                flash_set('error', 'Escribe la dirección' . ($zonas !== [] ? ', elige tu barrio o zona' : '') . ' y cuéntanos qué pasa.');
                redirigir($volverConTurno);
            }
            $recargo = $zona !== null ? (int) $zona['costo'] : 0;
            $precioTotal += $recargo;
            $datosVisita = [
                'direccion' => $direccion, 'referencia' => trim((string) ($_POST['referencia'] ?? '')),
                'zona' => $zona, 'problema' => $problema, 'franja' => $franja,
                'recordar' => !empty($_POST['recordar_repetir']) && !empty($servicio['repetir_cada_meses']),
            ];
        }

        $clienteId = Cliente::buscarOCrear((int) $negocio['negocio_id'], $nombre, $telefono, true, !empty($_POST['acepta_marketing']), 'reserva');
        // El anticipo y el cupón son sobre el servicio, como se mostraron al
        // reservar: el transporte de la zona no entra en el porcentaje.
        $anticipo = Servicio::calcularAnticipo(['precio' => $precioTotal - $recargo] + $servicio);

        // Bono de sesiones: si el cliente tiene uno vigente de este servicio,
        // la cita va por cuenta del bono (sin anticipo ni cupón). El bono
        // cubre el servicio; los adicionales se pagan aparte.
        $bono = Bono::paraCita((int) $negocio['id'], $clienteId, (int) $servicio['id'], $fecha);
        $codigoCupon = $bono === null ? Cupon::normalizarCodigo((string) ($_POST['cupon'] ?? '')) : '';
        $cuponUsado = null;
        $descuentoCita = 0;
        if ($bono !== null) {
            // El bono se compró al precio del servicio: si el profesional cobra
            // más, la diferencia (y los adicionales) se pagan aparte.
            $descuentoCita = min($condiciones['precio'], (int) $servicio['precio']);
            $anticipo = 0;
        }
        if ($codigoCupon !== '') {
            $cuponUsado = Cupon::buscarPorCodigo((int) $negocio['negocio_id'], $codigoCupon);
            $evaluacion = Cupon::evaluar($cuponUsado, $precioTotal - $recargo, $clienteId);
            if (!$evaluacion['ok']) {
                flash_set('error', 'Cupón ' . $codigoCupon . ': ' . $evaluacion['mensaje']);
                redirigir($volverConTurno);
            }
            $descuentoCita = $evaluacion['descuento'];
        }
        // Con un cupón grande el anticipo podía quedar por encima de lo que
        // el cliente de verdad paga por el servicio (p. ej. 50% de anticipo
        // sobre $80.000 con un cupón de $60.000): nunca se pide más que eso.
        $anticipo = max(0, min($anticipo, $precioTotal - $recargo - $descuentoCita));

        $this->registrarTasaPublica('cita', $negocio);
        $citaId = Cita::crear(
            (int) $negocio['id'],
            $clienteId,
            (int) $servicio['id'],
            $servicio['nombre'],
            $precioTotal,
            "{$fecha} {$hora}:00",
            $duracionTotal,
            null,
            $empleadoId,
            $anticipo,
            $descuentoCita,
            $cuponUsado['codigo'] ?? null,
            $condiciones['precio_tipo'],
            $condiciones['precio_max'] !== null ? $condiciones['precio_max'] + $precioAdicionales + $recargo : null,
        );
        $st = \App\Database::conexion()->prepare('SELECT RELEASE_LOCK(:k)');
        $st->execute(['k' => $candado]);
        $st->fetchColumn();
        if ($adicionales !== []) {
            Adicional::guardarEnCita($citaId, $adicionales);
        }
        if ($motivoConsulta !== '') {
            \App\Models\PlanTratamiento::guardarMotivo($citaId, $motivoConsulta);
        }
        if ($datosVisita === null && !empty($_POST['recordar_repetir']) && !empty($servicio['repetir_cada_meses'])) {
            Visita::pedirRecordatorio($citaId, true);
        }
        if ($datosVisita !== null) {
            Visita::guardarDatos($citaId, $datosVisita);
            Visita::subirFotos($citaId, 'fotos', 'cliente');
        }
        $cita = Cita::buscar($citaId, (int) $negocio['id']);
        if ($cuponUsado !== null && $descuentoCita > 0) {
            Cupon::registrarUso((int) $cuponUsado['id'], $clienteId, $descuentoCita, null, $citaId);
        }
        $usoBono = null;
        if ($bono !== null) {
            Bono::usar((int) $bono['id'], $citaId);
            $usoBono = Bono::usoDeCita($citaId);
        }

        WebPush::notificarSede(
            (int) $negocio['id'],
            'Cita nueva',
            "{$nombre} · {$servicio['nombre']}" . ($adicionales !== [] ? ' + ' . implode(' + ', array_column($adicionales, 'nombre')) : '')
                . ' el ' . fecha_larga($fecha) . ' a las ' . hora_legible($hora),
            '/panel/citas'
        );

        $resumenTexto = "Reserva nueva de {$nombre}:\n"
            . ($datosVisita !== null
                ? "- Visita: {$servicio['nombre']} el " . date('d/m/Y', strtotime($fecha)) . ' ' . Visita::textoFranja($cita) . "\n"
                    . '- Dirección: ' . $datosVisita['direccion'] . ($datosVisita['zona'] !== null ? ' (' . $datosVisita['zona']['nombre'] . ')' : '') . "\n"
                    . '- Qué pasa: ' . mb_substr($datosVisita['problema'], 0, 200) . "\n"
                : "- {$servicio['nombre']} el " . date('d/m/Y', strtotime($fecha)) . " a las {$hora}\n")
            . ($empleado !== null ? "- Con {$empleado['nombre']}\n" : '')
            . ($adicionales !== [] ? '- Adicionales: ' . implode(', ', array_column($adicionales, 'nombre')) . "\n" : '')
            . (precio_es_estimado($cita) ? 'Valor estimado: ' . precio_texto($cita) . ' (se confirma al ver el trabajo)' : 'Valor: ' . pesos($precioTotal - $descuentoCita))
            . ($descuentoCita > 0 && $cuponUsado !== null ? ' (con cupón ' . $cuponUsado['codigo'] . ', -' . pesos($descuentoCita) . ')' : '')
            . ($anticipo > 0 ? "\nAnticipo requerido: " . pesos($anticipo) : '')
            . ($usoBono !== null ? "\nCon bono: sesión {$usoBono['usadas']} de {$usoBono['sesiones_total']}" : '');
        $tarjeta = $this->tarjetaDeSellos($negocio, $clienteId, $precioTotal - $descuentoCita);
        if ($tarjeta !== null) {
            $resumenTexto .= "\n" . $tarjeta['texto'];
        }

        $telefonoNegocio = preg_replace('/\D+/', '', (string) $negocio['whatsapp']) ?? '';
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoNegocio . '?text=' . rawurlencode($resumenTexto);

        ver('tienda/cita_confirmada', [
            'titulo'         => 'Cita reservada · ' . nombre_publico_sede($negocio),
            'negocio'        => $negocio,
            'cita'           => $cita,
            'enlaceWhatsapp' => $enlaceWhatsapp,
            'tarjeta'        => $tarjeta,
            'usoBono'        => $usoBono,
        ], 'tienda');
    }

    public function unirseListaEspera(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        if ($negocio['tipo_negocio'] !== 'reservas') {
            abortar404();
        }

        $servicioId = (int) ($_POST['servicio_id'] ?? 0);
        $servicio = Servicio::buscar($servicioId, (int) $negocio['id']);
        $fecha = (string) ($_POST['fecha'] ?? '');

        $volverAReservar = '/t/' . $negocio['slug'] . '/reservar/' . $servicioId . '?fecha=' . rawurlencode($fecha) . $this->sufijoReserva();

        if (!csrf_verificar() || $servicio === null) {
            redirigir('/t/' . $negocio['slug']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
            flash_set('error', 'Elige una fecha válida.');
            redirigir('/t/' . $negocio['slug'] . '/reservar/' . $servicioId . ($this->sufijoReserva() !== '' ? '?' . ltrim($this->sufijoReserva(), '&') : ''));
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
        $autorizo = isset($_POST['autorizo_datos']);

        if ($nombre === '' || $telefono === '' || !$autorizo) {
            flash_set('error', 'Escribe tu nombre, tu WhatsApp y autoriza el tratamiento de tus datos para continuar.');
            redirigir($volverAReservar);
        }

        $this->exigirTasaPublica('lista_espera', $negocio, $volverAReservar);

        $clienteId = Cliente::buscarOCrear((int) $negocio['negocio_id'], $nombre, $telefono, true, false, 'reserva');
        $this->registrarTasaPublica('lista_espera', $negocio);
        $listaEsperaId = ListaEspera::crear((int) $negocio['id'], $clienteId, (int) $servicio['id'], $servicio['nombre'], $fecha);

        WebPush::notificarSede(
            (int) $negocio['id'],
            'Alguien quiere un cupo',
            "{$nombre} quiere «{$servicio['nombre']}» el " . fecha_larga($fecha),
            '/panel/citas'
        );

        // Solo mientras dura esta misma vuelta (flash, no sesión persistente):
        // permite mostrar "Salir de la lista" justo después de anotarse, sin
        // necesitar login ni un token de gestión como el de citas.
        flash_set('lista_espera_id', (string) $listaEsperaId);
        // Solo quien se anotó (en este navegador) puede sacarse: sin esto,
        // cualquiera borraba entradas ajenas probando ids.
        $_SESSION['listas_espera'] = array_slice(array_merge($_SESSION['listas_espera'] ?? [], [$listaEsperaId]), -20);
        redirigir($volverAReservar);
    }

    /** "&empleado=…&ad=…" del formulario, para volver a la misma reserva. */
    private function sufijoReserva(): string
    {
        $empleado = (int) ($_POST['empleado'] ?? 0);
        $ad = implode(',', array_filter(array_map('intval', explode(',', (string) ($_POST['ad'] ?? ''))), fn (int $id) => $id > 0));

        return ($empleado > 0 ? '&empleado=' . $empleado : '') . ($ad !== '' ? '&ad=' . $ad : '');
    }

    public function salirListaEspera(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        $id = (int) ($_POST['id'] ?? 0);
        $servicioId = (int) ($_POST['servicio_id'] ?? 0);
        $fecha = (string) ($_POST['fecha'] ?? '');
        $volverAReservar = '/t/' . $negocio['slug'] . '/reservar/' . $servicioId . '?fecha=' . rawurlencode($fecha) . $this->sufijoReserva();

        $mias = array_map('intval', $_SESSION['listas_espera'] ?? []);
        if (csrf_verificar() && in_array($id, $mias, true)) {
            ListaEspera::eliminar($id, (int) $negocio['id']);
            $_SESSION['listas_espera'] = array_values(array_diff($mias, [$id]));
            flash_set('ok', 'Listo, saliste de la lista de espera.');
        }

        redirigir($volverAReservar);
    }

    public function agregarAlCarrito(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        if (csrf_verificar()) {
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $producto = Producto::buscar($productoId, (int) $negocio['id']);

            if ($producto !== null && (int) $producto['activo'] === 1 && (int) $producto['agotado'] === 0) {
                $carrito = $this->carritoDeSesion((int) $negocio['id']);
                $nueva = ($carrito[$productoId] ?? 0) + 1;
                // Con inventario no se deja pedir más de lo que hay: el "+"
                // se queda quieto y se dice por qué (sin JS, con un aviso).
                // Por peso se pide por medias libras y el stock va en gramos (ver Producto::unidadesDisponibles).
                $hay = Producto::unidadesDisponibles($producto);
                if ($hay !== null && $nueva > $hay) {
                    if (!$this->esPeticionAjax()) {
                        flash_set('error', 'Solo quedan ' . Producto::cantidadEnLinea($producto, $hay) . ' de ' . $producto['nombre'] . '.');
                    }
                } else {
                    $carrito[$productoId] = $nueva;
                    $this->guardarCarrito((int) $negocio['id'], $carrito);
                }
            }
        }

        if ($this->esPeticionAjax()) {
            $this->responderCarritoJson($negocio);
            return;
        }

        redirigir($this->volverTrasAccionCarrito($negocio['slug']));
    }

    /**
     * El "+" se usa tanto en el catálogo (sin JS, vuelve al catálogo) como
     * en el stepper del propio carrito (sin JS, debe quedarse en el
     * carrito) — un hidden "volver=carrito" en ese segundo form es lo que
     * distingue ambos casos sin duplicar el controller.
     */
    private function volverTrasAccionCarrito(string $slug): string
    {
        return ($_POST['volver'] ?? '') === 'carrito' ? '/t/' . $slug . '/carrito' : '/t/' . $slug;
    }

    /** Baja en 1 la cantidad de una línea; si llega a 0, la línea desaparece (igual que "quitar"). */
    public function restarDelCarrito(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        if (csrf_verificar()) {
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $carrito = $this->carritoDeSesion((int) $negocio['id']);
            if (isset($carrito[$productoId])) {
                $carrito[$productoId]--;
                if ($carrito[$productoId] < 1) {
                    unset($carrito[$productoId]);
                }
                $this->guardarCarrito((int) $negocio['id'], $carrito);
            }
        }

        if ($this->esPeticionAjax()) {
            $this->responderCarritoJson($negocio);
            return;
        }

        redirigir('/t/' . $negocio['slug'] . '/carrito');
    }

    public function quitarDelCarrito(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        if (csrf_verificar()) {
            $productoId = (int) ($_POST['producto_id'] ?? 0);
            $carrito = $this->carritoDeSesion((int) $negocio['id']);
            unset($carrito[$productoId]);
            $this->guardarCarrito((int) $negocio['id'], $carrito);
        }

        if ($this->esPeticionAjax()) {
            $this->responderCarritoJson($negocio);
            return;
        }

        redirigir('/t/' . $negocio['slug'] . '/carrito');
    }

    /**
     * El sitio funciona sin JavaScript (todo formulario normal llega aquí
     * también); esta cabecera solo la manda el fetch() de interacciones.js,
     * así que su presencia distingue "quiero la respuesta en JSON para
     * actualizar la página sin recargar" de una petición de formulario real.
     */
    private function esPeticionAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    private function responderCarritoJson(array $negocio): void
    {
        $productos = Producto::listarPorSede((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);
        // "lineas" va aparte de cantidad/total para que el carrito pueda
        // actualizar la cantidad y el subtotal de UNA fila puntual (el
        // stepper +/-) sin tener que recargar ni volver a pintar las demás.
        $lineas = array_map(fn ($linea) => [
            'producto_id' => (int) $linea['producto']['id'],
            'cantidad'    => $linea['cantidad'],
            // Lo que se muestra: "3", o por peso "1½ libras" (texto) / "1½ lb" (burbuja del catálogo).
            'texto'       => Producto::cantidadEnLinea($linea['producto'], $linea['cantidad']),
            'texto_corto' => Producto::cantidadEnLinea($linea['producto'], $linea['cantidad'], true),
            'subtotal'    => Producto::precioEnLinea($linea['producto'], $linea['cantidad']),
            'tope'        => Producto::unidadesDisponibles($linea['producto']),
        ], $carrito['lineas']);
        // Las líneas de ajuste de la comanda (subtotal, cupón, domicilio) las
        // arma el mismo parcial que la página: así el JS no repite reglas.
        ob_start();
        require __DIR__ . '/../Views/tienda/_comanda_ajustes.php';
        $ajustesHtml = (string) ob_get_clean();
        header('Content-Type: application/json');
        echo json_encode(['cantidad' => $carrito['cantidad'], 'total' => $carrito['total'], 'lineas' => $lineas, 'ajustes_html' => $ajustesHtml]);
    }

    public function verCarrito(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        $productos = Producto::listarPorSede((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);
        $error = flash_obtener('error');
        // Si algo se agotó o bajó de inventario desde que lo agregó, el
        // carrito se ajusta y se le dice qué cambió (no desaparece sin más).
        if ($carrito['recortados'] !== []) {
            $this->guardarCarrito((int) $negocio['id'], array_column(array_map(fn ($l) => [(int) $l['producto']['id'], $l['cantidad']], $carrito['lineas']), 1, 0));
            $error ??= ucfirst(implode('; ', $carrito['recortados'])) . '. Ajustamos tu pedido.';
        }

        ver('tienda/carrito', [
            'titulo'  => 'Tu carrito · ' . nombre_publico_sede($negocio),
            'negocio' => $negocio,
            'carrito' => $carrito,
            'zonas'   => ZonaDomicilio::listarPorSede((int) $negocio['id'], true),
            'error'   => $error,
            'errorCupon'   => flash_obtener('error_cupon'),
            'cuponEscrito' => flash_obtener('cupon_escrito'),
        ], 'tienda');
    }

    public function crearPedido(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        $productos = Producto::listarPorSede((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);

        if (!csrf_verificar()) {
            redirigir('/t/' . $negocio['slug'] . '/carrito');
        }

        if ($carrito['lineas'] === []) {
            flash_set('error', 'Tu carrito está vacío.');
            redirigir('/t/' . $negocio['slug']);
        }

        // El inventario bajó mientras llenaba el formulario: se ajusta el
        // carrito y se le muestra, en vez de mandar menos de lo que pidió.
        if ($carrito['recortados'] !== []) {
            $this->guardarCarrito((int) $negocio['id'], array_column(array_map(fn ($l) => [(int) $l['producto']['id'], $l['cantidad']], $carrito['lineas']), 1, 0));
            flash_set('error', ucfirst(implode('; ', $carrito['recortados'])) . '. Ajustamos tu pedido: revísalo y vuelve a enviar.');
            redirigir('/t/' . $negocio['slug'] . '/carrito');
        }

        $this->exigirTasaPublica('pedido', $negocio, '/t/' . $negocio['slug'] . '/carrito');

        if ($this->limiteDelMesAlcanzado($negocio)) {
            flash_set('error', 'Este negocio ya llegó al número de pedidos que puede recibir este mes. Escríbele directo por WhatsApp para hacer tu pedido.');
            redirigir('/t/' . $negocio['slug'] . '/carrito');
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
        $autorizo = isset($_POST['autorizo_datos']);
        $aceptaMarketing = isset($_POST['acepta_marketing']);
        $metodoPago = (string) ($_POST['metodo_pago'] ?? 'breb');
        $tipoEntrega = (string) ($_POST['tipo_entrega'] ?? 'domicilio');
        // La dirección sigue siendo una sola columna en pedidos; el checkout
        // solo la parte en dos campos (más fáciles de llenar) y se reúne acá.
        $direccionCalle = trim((string) ($_POST['direccion'] ?? ''));
        $direccionReferencia = trim((string) ($_POST['referencia'] ?? ''));
        $direccion = $direccionReferencia === '' ? $direccionCalle : $direccionCalle . ', ' . $direccionReferencia;
        $mesa = trim((string) ($_POST['mesa'] ?? ''));
        $notas = trim((string) ($_POST['notas'] ?? ''));

        if (!in_array($tipoEntrega, ['domicilio', 'recoger', 'mesa'], true) || ($tipoEntrega === 'mesa' && empty($negocio['acepta_mesa']))) {
            $tipoEntrega = 'domicilio';
        }

        if ($nombre === '' || $telefono === '' || !$autorizo) {
            flash_set('error', 'Escribe tu nombre, tu WhatsApp y autoriza el tratamiento de tus datos para continuar.');
            redirigir('/t/' . $negocio['slug'] . '/carrito');
        }

        if ($tipoEntrega === 'domicilio' && $direccionCalle === '') {
            flash_set('error', 'Escribe la dirección donde quieres recibir el domicilio.');
            redirigir('/t/' . $negocio['slug'] . '/carrito');
        }

        if ($tipoEntrega === 'mesa' && $mesa === '') {
            flash_set('error', 'Escribe el número de tu mesa.');
            redirigir('/t/' . $negocio['slug'] . '/carrito');
        }

        if (!in_array($metodoPago, ['breb', 'nequi', 'efectivo'], true)) {
            $metodoPago = 'breb';
        }

        // Pedido mínimo de la sede y, con domicilio, la zona elegida (que
        // pone el costo y puede pedir un mínimo mayor). Se cuenta sobre los
        // productos, antes del cupón: el cupón no debe castigar al cliente.
        $volverAlCarrito = '/t/' . $negocio['slug'] . '/carrito';
        if ($carrito['minimo'] > 0 && $carrito['subtotal'] < $carrito['minimo']) {
            flash_set('error', 'El pedido mínimo es de ' . pesos($carrito['minimo']) . '. Te faltan ' . pesos($carrito['minimo'] - $carrito['subtotal']) . '.');
            redirigir($volverAlCarrito);
        }
        $zona = null;
        if ($tipoEntrega === 'domicilio') {
            $zonas = ZonaDomicilio::listarPorSede((int) $negocio['id'], true);
            if ($zonas !== []) {
                $zona = ZonaDomicilio::buscar((int) ($_POST['zona_id'] ?? 0), (int) $negocio['id']);
                if ($zona === null || (int) $zona['activa'] !== 1) {
                    flash_set('error', 'Elige a qué zona te llevamos el domicilio.');
                    redirigir($volverAlCarrito);
                }
                if ($carrito['subtotal'] < (int) $zona['minimo_pedido']) {
                    flash_set('error', 'Para domicilios a ' . $zona['nombre'] . ' el pedido mínimo es de ' . pesos((int) $zona['minimo_pedido']) . '. Te faltan ' . pesos((int) $zona['minimo_pedido'] - $carrito['subtotal']) . '.');
                    redirigir($volverAlCarrito);
                }
            }
        }

        $clienteId = Cliente::buscarOCrear((int) $negocio['negocio_id'], $nombre, $telefono, $autorizo, $aceptaMarketing, 'pedido');

        // El cupón se revisa otra vez, ahora con el cliente: si es personal
        // de otro número o ya lo usó, se le devuelve al carrito explicando.
        $ajustes = ['descuento' => 0, 'cupon_codigo' => null];
        $cuponUsado = null;
        if ($carrito['cupon'] !== null) {
            $cuponUsado = Cupon::buscarPorCodigo((int) $negocio['negocio_id'], $carrito['cupon']['codigo']);
            $evaluacion = Cupon::evaluar($cuponUsado, $carrito['subtotal'], $clienteId);
            if (!$evaluacion['ok']) {
                unset($_SESSION['cupon'][(int) $negocio['id']]);
                flash_set('error', $evaluacion['mensaje'] . ' Quitamos el cupón: revisa el total y vuelve a enviar.');
                redirigir('/t/' . $negocio['slug'] . '/carrito');
            }
            $ajustes = ['descuento' => $evaluacion['descuento'], 'cupon_codigo' => $cuponUsado['codigo']];
        }
        if ($zona !== null) {
            $ajustes['costo_domicilio'] = (int) $zona['costo'];
            $ajustes['zona_domicilio'] = (string) $zona['nombre'];
        }

        // Por peso va como un renglón con su peso en gramos y el precio de ese
        // peso (cantidad 1): precio × cantidad sigue siendo el subtotal en
        // todas partes, y el inventario se descuenta en gramos.
        $items = array_map(fn ($linea) => Producto::esPorPeso($linea['producto'])
            ? [
                'producto_id' => $linea['producto']['id'],
                'nombre'      => $linea['producto']['nombre'],
                'precio'      => Producto::precioEnLinea($linea['producto'], $linea['cantidad']),
                'cantidad'    => 1,
                'gramos'      => $linea['cantidad'] * Producto::GRAMOS_PASO_EN_LINEA,
                'texto'       => Producto::cantidadEnLinea($linea['producto'], $linea['cantidad']),
                'combo'       => '',
            ]
            : [
                'producto_id' => $linea['producto']['id'],
                'nombre'      => $linea['producto']['nombre'],
                'precio'      => (int) $linea['producto']['precio'],
                'cantidad'    => $linea['cantidad'],
                'gramos'      => null,
                'texto'       => (string) $linea['cantidad'],
                'combo'       => Producto::textoCombo($linea['producto']),
            ], $carrito['lineas']);

        $this->registrarTasaPublica('pedido', $negocio);
        try {
            $pedidoId = Pedido::crear((int) $negocio['id'], $clienteId, $metodoPago, $items, $tipoEntrega, $direccion, $mesa, $notas, $ajustes);
        } catch (\DomainException $e) {
            // Inventario: alguien se llevó las últimas unidades mientras
            // este cliente llenaba el formulario.
            flash_set('error', $e->getMessage());
            redirigir($volverAlCarrito);
        }
        $pedido = Pedido::buscar($pedidoId, (int) $negocio['id']);
        if ($cuponUsado !== null && $ajustes['descuento'] > 0) {
            Cupon::registrarUso((int) $cuponUsado['id'], $clienteId, $ajustes['descuento'], $pedidoId);
        }
        unset($_SESSION['cupon'][(int) $negocio['id']]);

        $etiquetaEntrega = match ($tipoEntrega) {
            'recoger' => 'recoge en el local',
            'mesa'    => "mesa {$mesa}",
            default   => 'domicilio',
        };

        WebPush::notificarSede(
            (int) $negocio['id'],
            'Pedido nuevo',
            "{$nombre} · " . pesos((int) $pedido['total']) . " · {$etiquetaEntrega}",
            '/panel/pedidos'
        );

        // El número del pedido amarra el chat con el panel.
        $resumenTexto = "Pedido #{$pedidoId} de {$nombre}:\n";
        foreach ($items as $item) {
            $resumenTexto .= ($item['gramos'] !== null ? "- {$item['texto']} de {$item['nombre']}" : "- {$item['cantidad']} x {$item['nombre']}")
                . ($item['combo'] !== '' ? " ({$item['combo']})" : '') . "\n";
        }
        if ((int) $pedido['descuento'] > 0) {
            $resumenTexto .= 'Descuento' . ($pedido['cupon_codigo'] ? " (cupón {$pedido['cupon_codigo']})" : '') . ': -' . pesos((int) $pedido['descuento']) . "\n";
        }
        if ($zona !== null) {
            $resumenTexto .= "Domicilio ({$zona['nombre']}): " . ZonaDomicilio::etiquetaCosto((int) $zona['costo']) . "\n";
        }
        $resumenTexto .= 'Total: ' . pesos((int) $pedido['total']) . "\n";
        // Cómo paga: para saber si llevar vueltas o esperar la transferencia (con su referencia).
        $resumenTexto .= 'Pago: ' . match ($metodoPago) {
            'breb'  => 'Bre-B (referencia VECI-P' . $pedidoId . ')',
            'nequi' => 'Nequi',
            default => 'efectivo',
        } . "\n";
        $resumenTexto .= match ($tipoEntrega) {
            'recoger' => 'Recojo en el local',
            'mesa'    => "Para comer en el local, mesa {$mesa}",
            default   => "Domicilio a: {$direccion}",
        };
        if ($notas !== '') {
            $resumenTexto .= "\nNota: {$notas}";
        }
        $tarjeta = $this->tarjetaDeSellos($negocio, $clienteId, (int) $pedido['total']);
        if ($tarjeta !== null) {
            $resumenTexto .= "\n" . $tarjeta['texto'];
        }

        $this->guardarCarrito((int) $negocio['id'], []);

        $telefonoNegocio = preg_replace('/\D+/', '', (string) $negocio['whatsapp']) ?? '';
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoNegocio . '?text=' . rawurlencode($resumenTexto);

        ver('tienda/pedido_confirmado', [
            'titulo'         => 'Pedido listo · ' . nombre_publico_sede($negocio),
            'negocio'        => $negocio,
            'pedido'         => $pedido,
            'items'          => $items,
            'enlaceWhatsapp' => $enlaceWhatsapp,
            'tarjeta'        => $tarjeta,
        ], 'tienda');
    }

    /** @return array{resumen: array, lista: array}|null — null si todavía no hay suficientes para que el promedio diga algo. */
    private function resenasParaTienda(array $negocio): ?array
    {
        $resumen = Resena::resumen((int) $negocio['negocio_id']);
        if ($resumen['total'] < Resena::MINIMO_PARA_MOSTRAR) {
            return null;
        }

        return ['resumen' => $resumen, 'lista' => Resena::paraTienda((int) $negocio['negocio_id'])];
    }

    /** /r/{token}: la página donde el cliente califica su pedido o su cita. */
    public function verResena(array $parametros): void
    {
        $resena = Resena::buscarPorToken((string) $parametros['token']);
        if ($resena === null) {
            abortar404();
        }
        $sede = Sede::buscarPorId((int) $resena['sede_id']);
        $cita = $resena['cita_id'] !== null ? Cita::buscar((int) $resena['cita_id'], (int) $resena['sede_id']) : null;

        ver('tienda/resena', [
            'titulo'  => '¿Cómo te fue? · ' . nombre_publico_sede($sede),
            'negocio' => $sede,
            'resena'  => $resena,
            'que'     => $cita !== null ? 'tu ' . mb_strtolower((string) $cita['nombre_servicio']) : 'tu pedido',
            'error'   => flash_obtener('error'),
        ], 'tienda');
    }

    public function responderResena(array $parametros): void
    {
        $resena = Resena::buscarPorToken((string) $parametros['token']);
        if ($resena === null) {
            abortar404();
        }
        $volver = '/r/' . $resena['token'];
        if (!csrf_verificar()) {
            redirigir($volver);
        }
        $estrellas = (int) ($_POST['estrellas'] ?? 0);
        if ($estrellas < 1 || $estrellas > 5) {
            flash_set('error', 'Toca las estrellas para calificar.');
            redirigir($volver);
        }
        Resena::responder((int) $resena['id'], $estrellas, (string) ($_POST['comentario'] ?? ''));
        redirigir($volver);
    }

    /** /bono/{token}: el cliente ve cuántas sesiones le quedan y reserva la siguiente. */
    public function verBono(array $parametros): void
    {
        $bono = Bono::buscarPorToken((string) $parametros['token']);
        if ($bono === null) {
            abortar404();
        }
        $sede = Sede::buscarPorId((int) $bono['sede_id']);

        ver('tienda/bono', [
            'titulo'  => (!empty($bono['garantia_de']) ? 'Tu garantía · ' : 'Tu bono · ') . nombre_publico_sede($sede),
            'negocio' => $sede,
            'bono'    => $bono,
        ], 'tienda');
    }

    public function gestionarCita(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }

        $sede = Sede::buscarPorId((int) $cita['sede_id']);
        ver('tienda/cita_gestionar', [
            'titulo'  => 'Tu cita · ' . $cita['negocio_nombre'],
            'negocio' => $sede,
            'cita'    => $cita,
            'puedeGestionar' => Imprevisto::clientePuedeGestionar($cita, $sede),
            // Visita a domicilio: quién va (con foto), la cotización y la evidencia.
            'tecnico'    => Visita::esVisita($cita) && $cita['empleado_id'] !== null ? Empleado::buscar((int) $cita['empleado_id'], (int) $sede['id']) : null,
            'cotizacion' => Visita::esVisita($cita) ? \App\Models\Cotizacion::deCita((int) $cita['id']) : null,
            'evidencia'  => Visita::esVisita($cita) ? array_values(array_filter(Visita::fotos((int) $cita['id']), fn ($f) => $f['momento'] !== 'cliente')) : [],
            'cuponAbono' => !empty($cita['cupon_abono_id']) ? Cupon::buscar((int) $cita['cupon_abono_id'], (int) $sede['negocio_id']) : null,
            'error'   => flash_obtener('error'),
            'ok'      => flash_obtener('ok'),
        ], 'tienda');
    }

    public function cancelarCitaCliente(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }

        $sede = Sede::buscarPorId((int) $cita['sede_id']);
        if (csrf_verificar() && $sede !== null && Imprevisto::clientePuedeGestionar($cita, $sede)) {
            $saldo = Imprevisto::cancelacionDelCliente($cita, $sede);
            flash_set('ok', $saldo !== null
                ? 'Tu cita quedó cancelada. Tu anticipo de ' . pesos((int) $saldo['valor']) . ' no se pierde: úsalo en tu próxima reserva con el código ' . $saldo['codigo'] . ' (vale hasta el ' . fecha_larga((string) $saldo['vence_en']) . ').'
                : 'Tu cita quedó cancelada.');
        }

        redirigir('/cita/' . $cita['token_gestion']);
    }

    /** El cliente aprueba (o no) el nuevo valor que propuso el negocio. */
    public function responderAjusteCliente(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }
        if (csrf_verificar() && $this->tasaDeLaCita($cita)) {
            $aprueba = ($_POST['respuesta'] ?? '') === 'aprobar';
            if (Imprevisto::responderAjuste((int) $cita['id'], $aprueba)) {
                WebPush::notificarSede(
                    (int) $cita['sede_id'],
                    $aprueba ? 'Nuevo valor aprobado' : 'Nuevo valor no aprobado',
                    $cita['cliente_nombre'] . ' · ' . $cita['nombre_servicio'] . ' · ' . pesos((int) $cita['ajuste_precio']),
                    '/panel/citas'
                );
                flash_set('ok', $aprueba ? 'Listo: aprobaste el nuevo valor.' : 'Le avisamos al negocio que no apruebas el nuevo valor.');
            }
        }
        redirigir('/cita/' . $cita['token_gestion']);
    }

    /** El cliente avisa que llega tarde: el negocio lo ve en la agenda y le llega una notificación. */
    public function llegoTardeCliente(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }
        $minutos = (int) ($_POST['minutos'] ?? 0);
        if (!Visita::esVisita($cita) && csrf_verificar() && $this->tasaDeLaCita($cita) && Imprevisto::clienteLlegaTarde((int) $cita['id'], $minutos)) {
            WebPush::notificarSede(
                (int) $cita['sede_id'],
                $cita['cliente_nombre'] . " llega {$minutos} min tarde",
                $cita['nombre_servicio'] . ' de las ' . hora_legible(date('H:i', strtotime((string) $cita['fecha_hora']) ?: 0)),
                '/panel/citas'
            );
            flash_set('ok', 'Le avisamos al negocio que llegas unos ' . $minutos . ' minutos tarde.');
        }
        redirigir('/cita/' . $cita['token_gestion']);
    }

    /**
     * Quien tenga el enlace de una cita no puede mandarle notificaciones sin
     * parar al negocio: 6 acciones por hora por cita.
     */
    private function tasaDeLaCita(array $cita): bool
    {
        $clave = 'cita_accion|' . (int) $cita['id'];
        if (LimiteTasa::excedido('cita_accion', $clave, 6, 3600)) {
            flash_set('error', 'Ya le avisaste varias veces al negocio. Si necesitas algo más, escríbele por WhatsApp.');
            return false;
        }
        LimiteTasa::registrar('cita_accion', $clave);

        return true;
    }

    /** El cliente vio el retraso del negocio y dice que espera. */
    public function esperoCliente(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }
        if (csrf_verificar()) {
            Imprevisto::clienteEspera((int) $cita['id']);
            flash_set('ok', '¡Gracias por la paciencia! Te esperamos.');
        }
        redirigir('/cita/' . $cita['token_gestion']);
    }

    public function reprogramarCitaVista(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }

        $negocio = Sede::buscarPorId((int) $cita['sede_id']);
        if (!Imprevisto::clientePuedeGestionar($cita, $negocio)) {
            redirigir('/cita/' . $cita['token_gestion']);
        }

        // Sin ?fecha, se abre en el día que ya tiene la cita (si no pasó):
        // casi siempre se cambia la hora dentro del mismo día o uno cercano,
        // y abrir en "hoy" mostraba un día vacío sin contexto.
        $diaCita = date('Y-m-d', strtotime((string) $cita['fecha_hora']) ?: time());
        $fecha = (string) ($_GET['fecha'] ?? ($diaCita >= date('Y-m-d') ? $diaCita : date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
            $fecha = date('Y-m-d');
        }

        $empleadoId = $cita['empleado_id'] !== null ? (int) $cita['empleado_id'] : null;
        [$horario, $profesionalEnPausa] = $this->horarioDeLaCita($cita, $negocio);
        $bloqueada = FechaBloqueada::estaBloqueada((int) $negocio['id'], $fecha);
        $ocupados = $bloqueada ? [] : Cita::ocupadosEnFecha((int) $negocio['id'], $fecha, (int) $cita['id'], $empleadoId);
        $slots = $bloqueada ? [] : Cita::calcularDisponibilidad($horario, (int) $negocio['intervalo_citas_min'], $fecha, (int) $cita['duracion_min'], $ocupados, (int) $negocio['colchon_min']);

        $fechasDisponibles = [];
        for ($i = 0; $i < 14; $i++) {
            $fechasDisponibles[] = date('Y-m-d', strtotime("+{$i} days"));
        }

        ver('tienda/cita_reprogramar', [
            'titulo'            => 'Reprogramar cita · ' . $cita['negocio_nombre'],
            'negocio'           => $negocio,
            'cita'              => $cita,
            'fecha'             => $fecha,
            'fechasDisponibles' => $fechasDisponibles,
            'slots'             => $slots,
            'bloqueada'         => $bloqueada,
            'profesionalEnPausa' => $profesionalEnPausa,
            'error'             => flash_obtener('error'),
        ], 'tienda');
    }

    /**
     * Horario para mover una cita: el de su profesional. Si esa persona está
     * en pausa (vacaciones, incapacidad) no se ofrece nada: mover la cita a
     * sus días de descanso sería peor que no moverla.
     *
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function horarioDeLaCita(array $cita, array $negocio): array
    {
        if ($cita['empleado_id'] === null) {
            return [Empleado::horario(null, $negocio), false];
        }
        $empleado = Empleado::buscar((int) $cita['empleado_id'], (int) $negocio['id']);
        if ($empleado === null || (int) $empleado['activo'] !== 1) {
            return [[], true];
        }

        return [Empleado::horario($empleado, $negocio), false];
    }

    public function guardarReprogramacion(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }

        $negocio = Sede::buscarPorId((int) $cita['sede_id']);
        $fecha = (string) ($_POST['fecha'] ?? '');
        $hora = (string) ($_POST['hora'] ?? '');
        $volver = '/cita/' . $cita['token_gestion'] . '/reprogramar?fecha=' . rawurlencode($fecha);

        if (!csrf_verificar() || !Imprevisto::clientePuedeGestionar($cita, $negocio)) {
            redirigir('/cita/' . $cita['token_gestion']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}$/', $hora) || $fecha < date('Y-m-d')) {
            flash_set('error', 'Elige una fecha y una hora válidas.');
            redirigir($volver);
        }

        if (FechaBloqueada::estaBloqueada((int) $negocio['id'], $fecha)) {
            flash_set('error', 'Ese día no está disponible. Elige otra fecha.');
            redirigir($volver);
        }

        // El mismo candado que una reserva nueva para ese día: dos personas
        // moviendo su cita al mismo cupo no quedan las dos con la misma
        // profesional. MySQL lo suelta al cerrar la conexión si algo redirige.
        $candado = 'veci_cita_' . (int) $negocio['id'] . '_' . $fecha;
        $st = \App\Database::conexion()->prepare('SELECT GET_LOCK(:k, 10)');
        $st->execute(['k' => $candado]);
        $st->fetchColumn();

        $empleadoId = $cita['empleado_id'] !== null ? (int) $cita['empleado_id'] : null;
        [$horario] = $this->horarioDeLaCita($cita, $negocio);
        $ocupados = Cita::ocupadosEnFecha((int) $negocio['id'], $fecha, (int) $cita['id'], $empleadoId);
        $slots = Cita::calcularDisponibilidad($horario, (int) $negocio['intervalo_citas_min'], $fecha, (int) $cita['duracion_min'], $ocupados, (int) $negocio['colchon_min']);

        if (!in_array($hora, $slots, true)) {
            flash_set('error', 'Ese horario ya no está disponible. Elige otro.');
            redirigir($volver);
        }

        Cita::reprogramar((int) $cita['id'], (int) $negocio['id'], "{$fecha} {$hora}:00");
        if (Visita::esVisita($cita)) {
            Visita::moverFranja((int) $cita['id'], $negocio, $fecha, $hora);
        }
        $st = \App\Database::conexion()->prepare('SELECT RELEASE_LOCK(:k)');
        $st->execute(['k' => $candado]);
        $st->fetchColumn();
        flash_set('ok', 'Tu cita quedó reprogramada.');
        redirigir('/cita/' . $cita['token_gestion']);
    }

    /**
     * Antispam de los formularios públicos (pedido, cita, lista de espera):
     * máximo 5 por hora desde la misma IP en la misma tienda, y 20 por hora
     * desde la misma IP en total. Sin esto, cualquiera podía mandarle 50
     * pedidos falsos a un negocio en el plan Gratis y agotarle el cupo del
     * mes — dejándole la tienda bloqueada para sus clientes reales.
     */
    private function exigirTasaPublica(string $accion, array $negocio, string $volver): void
    {
        $ip = ip_cliente();
        if (
            LimiteTasa::excedido($accion, $ip . '|' . (int) $negocio['id'], 5, 3600)
            || LimiteTasa::excedido($accion . '_global', $ip, 20, 3600)
        ) {
            flash_set('error', 'Recibimos muchas solicitudes seguidas desde tu conexión. Espera un rato o escríbele al negocio directo por WhatsApp.');
            redirigir($volver);
        }
    }

    private function registrarTasaPublica(string $accion, array $negocio): void
    {
        $ip = ip_cliente();
        LimiteTasa::registrar($accion, $ip . '|' . (int) $negocio['id']);
        LimiteTasa::registrar($accion . '_global', $ip);
    }

    /**
     * El plan Gratis limita cuántos pedidos/citas puede recibir un negocio
     * por mes calendario (planes.limite_pedidos_mes; null = ilimitado,
     * como Barrio y Pro). El límite es del NEGOCIO (negocio_id), no de la
     * sede: con varias sedes se cuenta entre todas, porque es ahí donde
     * vive el plan.
     */
    private function limiteDelMesAlcanzado(array $negocio): bool
    {
        $limite = $negocio['limite_pedidos_mes'] ?? null;
        if ($limite === null) {
            return false;
        }

        $negocioId = (int) $negocio['negocio_id'];
        $usados = $negocio['tipo_negocio'] === 'reservas'
            ? Cita::contarEsteMesPorNegocio($negocioId)
            : Pedido::contarEsteMesPorNegocio($negocioId);

        return $usados >= (int) $limite;
    }

    private function negocioOAbortar(string $slug): array
    {
        $negocio = Sede::buscarPorSlugPublicada($slug);
        // Una sede por encima del cupo del plan (el plan bajó) queda en pausa.
        if ($negocio === null || !Sede::dentroDelCupo($negocio)) {
            abortar404();
        }
        // Calculado una sola vez aquí (todo método público pasa por este
        // chokepoint) para que nombre_publico_sede() sepa si debe mostrar
        // "Marca · Sede" o solo "Marca" — la gran mayoría de negocios tiene
        // una sola sede y ahí el nombre de la sede no le dice nada al cliente.
        $negocio['multi_sede'] = Sede::contarPublicadasPorNegocio((int) $negocio['negocio_id']) > 1;
        return $negocio;
    }

    /** @return array<int, int> productoId => cantidad */
    private function carritoDeSesion(int $negocioId): array
    {
        return $_SESSION['carrito'][$negocioId] ?? [];
    }

    /** @param array<int, int> $carrito */
    private function guardarCarrito(int $negocioId, array $carrito): void
    {
        $_SESSION['carrito'][$negocioId] = $carrito;
    }

    /**
     * Cruza el carrito de la sesión con los productos actuales del negocio,
     * así un producto que el dueño borró o desactivó no revienta el carrito.
     *
     * @param array<int, array<string, mixed>> $productos
     * @return array{lineas: array<int, array{producto: array<string, mixed>, cantidad: int}>, cantidad: int, total: int}
     */
    private function resumenCarrito(array $negocio, array $productos): array
    {
        $porId = [];
        foreach ($productos as $producto) {
            $porId[(int) $producto['id']] = $producto;
        }

        $lineas = [];
        $cantidadTotal = 0;
        $total = 0;
        $recortados = [];

        foreach ($this->carritoDeSesion((int) $negocio['id']) as $productoId => $cantidad) {
            if (!isset($porId[$productoId]) || $cantidad < 1) {
                continue;
            }
            $producto = $porId[$productoId];
            if ((int) $producto['agotado'] === 1) {
                // Se agotó mientras estaba en el carrito: se dice, no se borra en silencio.
                $recortados[] = $producto['nombre'] . ' se agotó';
                continue;
            }
            // Si el inventario bajó mientras el producto estaba en el carrito.
            $hay = Producto::unidadesDisponibles($producto);
            if ($hay !== null && $cantidad > $hay) {
                $cantidad = $hay;
                $recortados[] = $cantidad === 1 && !Producto::esPorPeso($producto)
                    ? "solo queda 1 de {$producto['nombre']}"
                    : 'solo quedan ' . Producto::cantidadEnLinea($producto, $cantidad) . " de {$producto['nombre']}";
                if ($cantidad < 1) {
                    continue;
                }
            }
            $lineas[] = ['producto' => $producto, 'cantidad' => $cantidad];
            // Un producto por peso cuenta como uno ("1 libra de carne" es un producto, no dos medias).
            $cantidadTotal += Producto::esPorPeso($producto) ? 1 : $cantidad;
            $total += Producto::precioEnLinea($producto, $cantidad);
        }

        // Cupón aplicado en esta sesión: se revisa con el subtotal actual
        // (si el cliente quita productos y ya no alcanza el mínimo, se le
        // dice por qué dejó de aplicar en vez de quitarlo en silencio).
        $cupon = null;
        $descuento = 0;
        $codigo = $_SESSION['cupon'][(int) $negocio['id']] ?? null;
        if (is_string($codigo) && $codigo !== '' && $lineas !== []) {
            $registro = Cupon::buscarPorCodigo((int) $negocio['negocio_id'], $codigo);
            $evaluacion = Cupon::evaluar($registro, $total);
            $descuento = $evaluacion['descuento'];
            $cupon = [
                'codigo'   => $codigo,
                'etiqueta' => $registro !== null ? Cupon::etiqueta($registro) : '',
                'ok'       => $evaluacion['ok'],
                'mensaje'  => $evaluacion['mensaje'],
            ];
        }

        return [
            'lineas'    => $lineas,
            'cantidad'  => $cantidadTotal,
            'subtotal'  => $total,
            'descuento' => $descuento,
            'cupon'     => $cupon,
            // Sin domicilio: ese depende de la zona que se elige en el
            // formulario (el JS lo suma en vivo; el servidor, al pedir).
            'total'     => $total - $descuento,
            'minimo'    => (int) ($negocio['pedido_minimo'] ?? 0),
            'recortados' => $recortados,
        ];
    }

    /** Aplica un cupón al carrito de esta sesión (se vuelve a validar al pedir, ya con el cliente). */
    /**
     * La tarjeta de sellos del cliente justo después de su compra (ya
     * contada), para la confirmación y para el mensaje al negocio: así el
     * dueño ve en el chat "le toca el premio" sin abrir el panel.
     *
     * @return array{sellos: int, meta: int, premio: string, nuevo: bool, texto: string}|null
     */
    private function tarjetaDeSellos(array $negocio, int $clienteId, int $monto): ?array
    {
        $config = Fidelidad::activa((int) $negocio['negocio_id']);
        if ($config === null) {
            return null;
        }
        $sellos = Fidelidad::sellosDe((int) $negocio['negocio_id'], $config, $clienteId);

        return [
            'sellos' => $sellos,
            'meta'   => (int) $config['meta'],
            'premio' => (string) $config['premio'],
            'nuevo'  => Fidelidad::cuenta($config, $monto),
            'texto'  => Fidelidad::textoProgreso($config, $sellos),
        ];
    }

    public function aplicarCupon(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        if (csrf_verificar()) {
            $codigo = Cupon::normalizarCodigo((string) ($_POST['cupon'] ?? ''));
            $productos = Producto::listarPorSede((int) $negocio['id'], true);
            $subtotal = $this->resumenCarrito($negocio, $productos)['subtotal'];
            $evaluacion = Cupon::evaluar(Cupon::buscarPorCodigo((int) $negocio['negocio_id'], $codigo), $subtotal);
            if ($codigo === '') {
                flash_set('error_cupon', 'Escribe el código del cupón.');
            } elseif (!$evaluacion['ok']) {
                flash_set('error_cupon', $evaluacion['mensaje']);
                flash_set('cupon_escrito', $codigo);
            } else {
                $_SESSION['cupon'][(int) $negocio['id']] = $codigo;
            }
        }

        redirigir('/t/' . $negocio['slug'] . '/carrito');
    }

    public function quitarCupon(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        if (csrf_verificar()) {
            unset($_SESSION['cupon'][(int) $negocio['id']]);
        }
        redirigir('/t/' . $negocio['slug'] . '/carrito');
    }
}
