<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\FechaBloqueada;
use App\Models\LimiteTasa;
use App\Models\ListaEspera;
use App\Models\Sede;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Servicio;
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
                        $ocupadosHoy
                    );
                    $disponibilidadHoy[$servicio['id']] = $slots[0] ?? null;
                }
            }

            ver('tienda/servicios', [
                'titulo'           => $negocio['nombre'] . ' · Veci',
                'negocio'          => $negocio,
                'servicios'        => $servicios,
                'horario'          => horario_resumen($horarioSede),
                'abiertoAhora'     => $abiertoAhora,
                'proximaApertura'  => $abiertoAhora !== null && !$abiertoAhora['abierto'] ? negocio_proxima_apertura($horarioSede) : null,
                'disponibilidadHoy' => $disponibilidadHoy,
                'metaDescripcion'  => $metaDescripcion,
                'canonicalUrl'     => url_publica('/t/' . $negocio['slug']),
            ], 'tienda');
            return;
        }

        $productos = Producto::listarPorSede((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);

        ver('tienda/mostrar', [
            'titulo'          => $negocio['nombre'] . ' · Veci',
            'negocio'         => $negocio,
            'productos'       => $productos,
            'carrito'         => $carrito,
            'metaDescripcion' => $metaDescripcion,
            'canonicalUrl'    => url_publica('/t/' . $negocio['slug']),
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

        $empleados = Empleado::listarPorSede((int) $negocio['id'], true);
        $empleadoId = (int) ($_GET['empleado'] ?? 0);
        $empleadoElegido = null;
        if ($empleados !== []) {
            foreach ($empleados as $emp) {
                if ((int) $emp['id'] === $empleadoId) {
                    $empleadoElegido = $emp;
                    break;
                }
            }
        }

        // Los próximos 14 días, para que el cliente pueda cambiar de fecha sin escribirla a mano.
        $fechasDisponibles = [];
        for ($i = 0; $i < 14; $i++) {
            $fechasDisponibles[] = date('Y-m-d', strtotime("+{$i} days"));
        }

        $bloqueada = false;
        $faltaElegirEmpleado = $empleados !== [] && $empleadoElegido === null;
        $slots = [];
        $cerradoEseDia = false;
        $proximoDisponible = null;
        $horaElegida = (string) ($_GET['hora'] ?? '');
        $slotValido = false;
        $disponibilidadError = false;

        // Todo lo que depende de la base de datos para calcular
        // disponibilidad va envuelto aquí: si algo falla (conexión caída,
        // dato corrupto en horario_atencion), el cliente ve un mensaje
        // honesto con un botón de reintentar en vez de una pantalla en
        // blanco o un error de PHP crudo.
        try {
            $horario = Sede::horario($negocio);
            $intervalo = (int) $negocio['intervalo_citas_min'];
            $bloqueada = FechaBloqueada::estaBloqueada((int) $negocio['id'], $fecha);

            if (!$bloqueada && !$faltaElegirEmpleado) {
                $ocupados = Cita::ocupadosEnFecha((int) $negocio['id'], $fecha, null, $empleadoElegido['id'] ?? null);
                $slots = Cita::calcularDisponibilidad($horario, $intervalo, $fecha, (int) $servicio['duracion_min'], $ocupados);
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
                    $slotsOpcion = Cita::calcularDisponibilidad($horario, $intervalo, $opcion, (int) $servicio['duracion_min'], $ocupadosOpcion);
                    if ($slotsOpcion !== []) {
                        $proximoDisponible = ['fecha' => $opcion, 'hora' => $slotsOpcion[0]];
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $disponibilidadError = true;
            $slots = [];
            $proximoDisponible = null;
            $slotValido = false;
        }

        ver('tienda/reservar', [
            'titulo'            => 'Reservar ' . $servicio['nombre'] . ' · ' . $negocio['nombre'],
            'negocio'           => $negocio,
            'servicio'          => $servicio,
            'anticipo'          => Servicio::calcularAnticipo($servicio),
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
            // Solo viene con valor justo después de unirse a la lista de
            // espera en esta misma vuelta (ver unirseListaEspera): habilita
            // la tarjeta de confirmación y el botón "Salir de la lista". Una
            // recarga posterior de la URL ya no lo trae — es un flash, no
            // sesión — así que ese botón no queda como una gestión
            // permanente del cupo sin login.
            'listaEsperaId'     => ($idFlash = flash_obtener('lista_espera_id')) !== null ? (int) $idFlash : null,
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

        if (!csrf_verificar() || $servicio === null || (int) $servicio['agotado'] === 1) {
            redirigir('/t/' . $negocio['slug']);
        }

        $this->exigirTasaPublica('cita', $negocio, $volverAReservar);

        if ($this->limiteDelMesAlcanzado($negocio)) {
            flash_set('error', 'Este negocio ya llegó al número de citas que puede recibir este mes. Escríbele directo por WhatsApp para agendar.');
            redirigir($volverAReservar);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
            flash_set('error', 'Elige una fecha y una hora válidas.');
            redirigir($volverAReservar);
        }

        if (FechaBloqueada::estaBloqueada((int) $negocio['id'], $fecha)) {
            flash_set('error', 'Ese día no está disponible. Elige otra fecha.');
            redirigir($volverAReservar);
        }

        $empleados = Empleado::listarPorSede((int) $negocio['id'], true);
        $empleadoId = null;
        if ($empleados !== []) {
            $empleadoPost = (int) ($_POST['empleado_id'] ?? 0);
            $empleado = Empleado::buscar($empleadoPost, (int) $negocio['id']);
            if ($empleado === null || (int) $empleado['activo'] !== 1) {
                flash_set('error', 'Elige con quién quieres agendar.');
                redirigir($volverAReservar);
            }
            $empleadoId = (int) $empleado['id'];
        }

        // Vuelve a calcular disponibilidad justo antes de guardar, por si alguien más
        // tomó ese horario mientras el cliente llenaba el formulario.
        $horario = Sede::horario($negocio);
        $ocupados = Cita::ocupadosEnFecha((int) $negocio['id'], $fecha, null, $empleadoId);
        $slots = Cita::calcularDisponibilidad(
            $horario,
            (int) $negocio['intervalo_citas_min'],
            $fecha,
            (int) $servicio['duracion_min'],
            $ocupados
        );

        if (!in_array($hora, $slots, true)) {
            flash_set('error', 'Ese horario ya no está disponible. Elige otro.');
            redirigir($volverAReservar);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
        $autorizo = isset($_POST['autorizo_datos']);

        if ($nombre === '' || $telefono === '' || !$autorizo) {
            flash_set('error', 'Escribe tu nombre, tu WhatsApp y autoriza el tratamiento de tus datos para continuar.');
            redirigir($volverAReservar . '&hora=' . rawurlencode($hora));
        }

        $clienteId = Cliente::buscarOCrear((int) $negocio['negocio_id'], $nombre, $telefono, true);
        $anticipo = Servicio::calcularAnticipo($servicio);

        $this->registrarTasaPublica('cita', $negocio);
        $citaId = Cita::crear(
            (int) $negocio['id'],
            $clienteId,
            (int) $servicio['id'],
            $servicio['nombre'],
            (int) $servicio['precio'],
            "{$fecha} {$hora}:00",
            (int) $servicio['duracion_min'],
            null,
            $empleadoId,
            $anticipo,
        );
        $cita = Cita::buscar($citaId, (int) $negocio['id']);

        WebPush::notificarSede(
            (int) $negocio['id'],
            'Cita nueva',
            "{$nombre} · {$servicio['nombre']} el " . date('d M', strtotime($fecha)) . " a las {$hora}",
            '/panel/citas'
        );

        $resumenTexto = "Reserva nueva de {$nombre}:\n"
            . "- {$servicio['nombre']} el " . date('d/m/Y', strtotime($fecha)) . " a las {$hora}\n"
            . 'Valor: ' . pesos((int) $servicio['precio'])
            . ($anticipo > 0 ? "\nAnticipo requerido: " . pesos($anticipo) : '');

        $telefonoNegocio = preg_replace('/\D+/', '', (string) $negocio['whatsapp']) ?? '';
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoNegocio . '?text=' . rawurlencode($resumenTexto);

        ver('tienda/cita_confirmada', [
            'titulo'         => 'Cita reservada · ' . $negocio['nombre'],
            'negocio'        => $negocio,
            'cita'           => $cita,
            'enlaceWhatsapp' => $enlaceWhatsapp,
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

        $volverAReservar = '/t/' . $negocio['slug'] . '/reservar/' . $servicioId . '?fecha=' . rawurlencode($fecha);

        if (!csrf_verificar() || $servicio === null) {
            redirigir('/t/' . $negocio['slug']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
            flash_set('error', 'Elige una fecha válida.');
            redirigir('/t/' . $negocio['slug'] . '/reservar/' . $servicioId);
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? '')) ?? '';
        $autorizo = isset($_POST['autorizo_datos']);

        if ($nombre === '' || $telefono === '' || !$autorizo) {
            flash_set('error', 'Escribe tu nombre, tu WhatsApp y autoriza el tratamiento de tus datos para continuar.');
            redirigir($volverAReservar);
        }

        $this->exigirTasaPublica('lista_espera', $negocio, $volverAReservar);

        $clienteId = Cliente::buscarOCrear((int) $negocio['negocio_id'], $nombre, $telefono, true);
        $this->registrarTasaPublica('lista_espera', $negocio);
        $listaEsperaId = ListaEspera::crear((int) $negocio['id'], $clienteId, (int) $servicio['id'], $servicio['nombre'], $fecha);

        WebPush::notificarSede(
            (int) $negocio['id'],
            'Alguien quiere un cupo',
            "{$nombre} quiere «{$servicio['nombre']}» el " . date('d M', strtotime($fecha)),
            '/panel/citas'
        );

        // Solo mientras dura esta misma vuelta (flash, no sesión persistente):
        // permite mostrar "Salir de la lista" justo después de anotarse, sin
        // necesitar login ni un token de gestión como el de citas.
        flash_set('lista_espera_id', (string) $listaEsperaId);
        redirigir($volverAReservar);
    }

    public function salirListaEspera(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);

        $id = (int) ($_POST['id'] ?? 0);
        $servicioId = (int) ($_POST['servicio_id'] ?? 0);
        $fecha = (string) ($_POST['fecha'] ?? '');
        $volverAReservar = '/t/' . $negocio['slug'] . '/reservar/' . $servicioId . '?fecha=' . rawurlencode($fecha);

        if (csrf_verificar()) {
            ListaEspera::eliminar($id, (int) $negocio['id']);
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
                $carrito[$productoId] = ($carrito[$productoId] ?? 0) + 1;
                $this->guardarCarrito((int) $negocio['id'], $carrito);
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
            'subtotal'    => (int) $linea['producto']['precio'] * $linea['cantidad'],
        ], $carrito['lineas']);
        header('Content-Type: application/json');
        echo json_encode(['cantidad' => $carrito['cantidad'], 'total' => $carrito['total'], 'lineas' => $lineas]);
    }

    public function verCarrito(array $parametros): void
    {
        $negocio = $this->negocioOAbortar($parametros['slug']);
        $productos = Producto::listarPorSede((int) $negocio['id'], true);
        $carrito = $this->resumenCarrito($negocio, $productos);

        ver('tienda/carrito', [
            'titulo'  => 'Tu carrito · ' . nombre_publico_sede($negocio),
            'negocio' => $negocio,
            'carrito' => $carrito,
            'error'   => flash_obtener('error'),
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

        $clienteId = Cliente::buscarOCrear((int) $negocio['negocio_id'], $nombre, $telefono, $autorizo, $aceptaMarketing);

        $items = array_map(fn ($linea) => [
            'producto_id' => $linea['producto']['id'],
            'nombre'      => $linea['producto']['nombre'],
            'precio'      => (int) $linea['producto']['precio'],
            'cantidad'    => $linea['cantidad'],
        ], $carrito['lineas']);

        $this->registrarTasaPublica('pedido', $negocio);
        $pedidoId = Pedido::crear((int) $negocio['id'], $clienteId, $metodoPago, $items, $tipoEntrega, $direccion, $mesa, $notas);
        $pedido = Pedido::buscar($pedidoId, (int) $negocio['id']);

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

        $resumenTexto = "Pedido nuevo de {$nombre}:\n";
        foreach ($items as $item) {
            $resumenTexto .= "- {$item['cantidad']} x {$item['nombre']}\n";
        }
        $resumenTexto .= 'Total: ' . pesos((int) $pedido['total']) . "\n";
        $resumenTexto .= match ($tipoEntrega) {
            'recoger' => 'Recojo en el local',
            'mesa'    => "Para comer en el local, mesa {$mesa}",
            default   => "Domicilio a: {$direccion}",
        };
        if ($notas !== '') {
            $resumenTexto .= "\nNota: {$notas}";
        }

        $this->guardarCarrito((int) $negocio['id'], []);

        $telefonoNegocio = preg_replace('/\D+/', '', (string) $negocio['whatsapp']) ?? '';
        $enlaceWhatsapp = 'https://wa.me/57' . $telefonoNegocio . '?text=' . rawurlencode($resumenTexto);

        ver('tienda/pedido_confirmado', [
            'titulo'         => 'Pedido listo · ' . $negocio['nombre'],
            'negocio'        => $negocio,
            'pedido'         => $pedido,
            'items'          => $items,
            'enlaceWhatsapp' => $enlaceWhatsapp,
        ], 'tienda');
    }

    public function gestionarCita(array $parametros): void
    {
        $cita = Cita::buscarPorToken((string) $parametros['token']);
        if ($cita === null) {
            abortar404();
        }

        ver('tienda/cita_gestionar', [
            'titulo'  => 'Tu cita · ' . $cita['negocio_nombre'],
            'negocio' => Sede::buscarPorId((int) $cita['sede_id']),
            'cita'    => $cita,
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

        if (csrf_verificar() && $cita['estado'] !== 'cancelada') {
            Cita::actualizarEstado((int) $cita['id'], (int) $cita['sede_id'], 'cancelada');
            flash_set('ok', 'Tu cita quedó cancelada.');
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

        $fecha = (string) ($_GET['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
            $fecha = date('Y-m-d');
        }

        $horario = Sede::horario($negocio);
        $bloqueada = FechaBloqueada::estaBloqueada((int) $negocio['id'], $fecha);
        $empleadoId = $cita['empleado_id'] !== null ? (int) $cita['empleado_id'] : null;
        $ocupados = $bloqueada ? [] : Cita::ocupadosEnFecha((int) $negocio['id'], $fecha, (int) $cita['id'], $empleadoId);
        $slots = $bloqueada ? [] : Cita::calcularDisponibilidad($horario, (int) $negocio['intervalo_citas_min'], $fecha, (int) $cita['duracion_min'], $ocupados);

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
            'error'             => flash_obtener('error'),
        ], 'tienda');
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

        if (!csrf_verificar()) {
            redirigir('/cita/' . $cita['token_gestion']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
            flash_set('error', 'Elige una fecha y una hora válidas.');
            redirigir($volver);
        }

        if (FechaBloqueada::estaBloqueada((int) $negocio['id'], $fecha)) {
            flash_set('error', 'Ese día no está disponible. Elige otra fecha.');
            redirigir($volver);
        }

        $horario = Sede::horario($negocio);
        $empleadoId = $cita['empleado_id'] !== null ? (int) $cita['empleado_id'] : null;
        $ocupados = Cita::ocupadosEnFecha((int) $negocio['id'], $fecha, (int) $cita['id'], $empleadoId);
        $slots = Cita::calcularDisponibilidad($horario, (int) $negocio['intervalo_citas_min'], $fecha, (int) $cita['duracion_min'], $ocupados);

        if (!in_array($hora, $slots, true)) {
            flash_set('error', 'Ese horario ya no está disponible. Elige otro.');
            redirigir($volver);
        }

        Cita::reprogramar((int) $cita['id'], (int) $negocio['id'], "{$fecha} {$hora}:00");
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
        if ($negocio === null) {
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

        foreach ($this->carritoDeSesion((int) $negocio['id']) as $productoId => $cantidad) {
            if (!isset($porId[$productoId]) || $cantidad < 1) {
                continue;
            }
            $producto = $porId[$productoId];
            if ((int) $producto['agotado'] === 1) {
                continue;
            }
            $lineas[] = ['producto' => $producto, 'cantidad' => $cantidad];
            $cantidadTotal += $cantidad;
            $total += (int) $producto['precio'] * $cantidad;
        }

        return ['lineas' => $lineas, 'cantidad' => $cantidadTotal, 'total' => $total];
    }
}
