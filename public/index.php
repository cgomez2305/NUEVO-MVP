<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AgendaController;
use App\Controllers\AuthController;
use App\Controllers\CajaController;
use App\Controllers\ComprasController;
use App\Controllers\CrecimientoController;
use App\Controllers\EquipoController;
use App\Controllers\FilaController;
use App\Controllers\FiadoController;
use App\Controllers\HomeController;
use App\Controllers\MostradorController;
use App\Controllers\OnboardingController;
use App\Controllers\PanelController;
use App\Controllers\PreferenciasController;
use App\Controllers\SaludController;
use App\Controllers\TiendaController;
use App\Controllers\VisitaController;
use App\Controllers\WebhookController;
use App\Router;

$router = new Router();

$home = new HomeController();
$auth = new AuthController();
$onboarding = new OnboardingController();
$panel = new PanelController();
$crecimiento = new CrecimientoController();
$caja = new CajaController();
$tienda = new TiendaController();
$webhook = new WebhookController();
$preferencias = new PreferenciasController();
$admin = new AdminController();
$agenda = new AgendaController();
$equipo = new EquipoController();
$fila = new FilaController();
$visita = new VisitaController();
$salud = new SaludController();

// --- Público ---------------------------------------------------------
$router->get('/', [$home, 'index']);

$router->get('/registro', [$auth, 'formularioRegistro']);
$router->post('/registro', [$auth, 'registrar']);
$router->get('/login', [$auth, 'formularioLogin']);
$router->post('/login', [$auth, 'iniciarSesion']);
$router->post('/logout', [$auth, 'cerrarSesion']);
$router->get('/olvide-password', [$auth, 'formularioOlvide']);
$router->post('/olvide-password', [$auth, 'solicitarReset']);
$router->get('/reset-password/{token}', [$auth, 'formularioReset']);
$router->post('/reset-password/{token}', [$auth, 'restablecer']);

// --- Alta del comerciante (flujo A de la maqueta) ---------------------
$router->get('/panel/onboarding', [$onboarding, 'continuar']);
$router->get('/panel/onboarding/foto', [$onboarding, 'mostrarFoto']);
$router->post('/panel/onboarding/foto', [$onboarding, 'subirFoto']);
$router->post('/panel/onboarding/analizar', [$onboarding, 'analizar']);
$router->post('/panel/onboarding/catalogo', [$onboarding, 'guardarCatalogo']);
$router->get('/panel/onboarding/productos', [$onboarding, 'mostrarProductos']);
$router->get('/panel/onboarding/horario', [$onboarding, 'mostrarHorario']);
$router->post('/panel/onboarding/horario', [$onboarding, 'guardarHorario']);
$router->get('/panel/onboarding/pago', [$onboarding, 'mostrarPago']);
$router->post('/panel/onboarding/publicar', [$onboarding, 'publicar']);
$router->get('/panel/onboarding/abierta', [$onboarding, 'mostrarAbierta']);

// --- Panel del negocio -------------------------------------------------
$router->get('/panel', [$panel, 'dashboard']);
$router->get('/panel/pedidos', [$panel, 'pedidos']);
$router->post('/panel/pedidos/{id}/estado', [$panel, 'cambiarEstadoPedido']);
$router->get('/panel/pedidos/exportar.csv', [$panel, 'exportarPedidosCsv']);
$router->get('/panel/pedidos/{id}', [$panel, 'detallePedido']);
$router->get('/panel/productos', [$panel, 'productos']);
$router->get('/panel/productos/nuevo', [$panel, 'nuevoProducto']);
$router->post('/panel/productos', [$panel, 'crearProducto']);
$router->get('/panel/productos/{id}/editar', [$panel, 'editarProducto']);
$router->post('/panel/productos/{id}/actualizar', [$panel, 'actualizarProducto']);
$router->post('/panel/productos/{id}/eliminar', [$panel, 'eliminarProducto']);
$router->post('/panel/productos/{id}/agotado', [$panel, 'alternarAgotadoProducto']);
$router->post('/panel/productos/{id}/agotado-hoy', [$panel, 'agotarHoyProducto']);
$router->post('/panel/productos/{id}/visible', [$panel, 'alternarActivoProducto']);
$router->get('/panel/servicios', [$panel, 'servicios']);
$router->post('/panel/servicios', [$panel, 'crearServicio']);
$router->post('/panel/servicios/{id}/actualizar', [$panel, 'actualizarServicio']);
$router->post('/panel/servicios/{id}/eliminar', [$panel, 'eliminarServicio']);
$router->post('/panel/servicios/{id}/agotado', [$panel, 'alternarAgotadoServicio']);
$router->post('/panel/servicios/{id}/deposito', [$panel, 'actualizarDepositoServicio']);
// Equipo: perfil de cada profesional, sus servicios, horario y trabajos (EquipoController)
$router->get('/panel/empleados', [$equipo, 'lista']);
$router->post('/panel/empleados', [$equipo, 'crear']);
$router->get('/panel/empleados/{id}', [$equipo, 'editar']);
$router->post('/panel/empleados/{id}/perfil', [$equipo, 'guardarPerfil']);
$router->post('/panel/empleados/{id}/servicios', [$equipo, 'guardarServicios']);
$router->post('/panel/empleados/{id}/horario', [$equipo, 'guardarHorario']);
$router->post('/panel/empleados/{id}/fotos', [$equipo, 'subirFotos']);
$router->post('/panel/empleados/{id}/fotos/{foto}/eliminar', [$equipo, 'eliminarFoto']);
$router->post('/panel/empleados/{id}/alternar', [$equipo, 'alternar']);
$router->post('/panel/empleados/{id}/eliminar', [$equipo, 'eliminar']);
$router->post('/panel/adicionales', [$equipo, 'crearAdicional']);
$router->post('/panel/adicionales/{id}/alternar', [$equipo, 'alternarAdicional']);
$router->post('/panel/adicionales/{id}/eliminar', [$equipo, 'eliminarAdicional']);
$router->get('/panel/comisiones', [$equipo, 'comisiones']);
// Fila virtual para clientes sin cita (FilaController)
$router->get('/panel/fila', [$fila, 'panel']);
$router->post('/panel/fila/abrir', [$fila, 'abrirCerrar']);
$router->post('/panel/fila/{id}/llamar', [$fila, 'llamar']);
$router->post('/panel/fila/{id}/atender', [$fila, 'atender']);
$router->post('/panel/fila/{id}/se-fue', [$fila, 'seFue']);
$router->get('/panel/citas', [$panel, 'citas']);
$router->post('/panel/citas/{id}/estado', [$panel, 'cambiarEstadoCita']);
$router->post('/panel/lista-espera/{id}/contactado', [$panel, 'marcarContactadoListaEspera']);
$router->post('/panel/citas/{id}/anticipo', [$panel, 'marcarAnticipoPagado']);
$router->get('/panel/citas/exportar.csv', [$panel, 'exportarCitasCsv']);
// Imprevistos de la agenda (AgendaController)
$router->post('/panel/agenda/reglas', [$agenda, 'guardarReglas']);
$router->post('/panel/agenda/retraso', [$agenda, 'retraso']);
$router->post('/panel/agenda/dia-complicado', [$agenda, 'diaComplicado']);
$router->post('/panel/citas/{id}/terminar', [$agenda, 'terminar']);
$router->post('/panel/citas/{id}/no-vino', [$agenda, 'noVino']);
$router->post('/panel/citas/{id}/ajuste', [$agenda, 'ajuste']);
$router->post('/panel/citas/{id}/garantia', [$agenda, 'garantia']);
$router->post('/panel/citas/{id}/aviso-imprevisto', [$agenda, 'avisar']);
$router->post('/panel/servicios/{id}/duracion-real', [$agenda, 'usarDuracionReal']);
$router->get('/panel/clientes/exportar.csv', [$panel, 'exportarClientesCsv']);
$router->get('/panel/horario', [$panel, 'horario']);
$router->post('/panel/horario', [$panel, 'guardarHorario']);
$router->get('/panel/horario/fechas', [$panel, 'fechasBloqueadas']);
$router->post('/panel/horario/fechas', [$panel, 'crearFechaBloqueada']);
$router->post('/panel/horario/fechas/{id}/eliminar', [$panel, 'eliminarFechaBloqueada']);
$router->get('/panel/notificaciones/nuevas', [$panel, 'notificacionesNuevas']);
$router->get('/panel/push/clave-publica', [$panel, 'pushClavePublica']);
$router->post('/panel/push/suscribir', [$panel, 'pushSuscribir']);
$router->post('/panel/push/desuscribir', [$panel, 'pushDesuscribir']);
$router->get('/panel/copiloto', [$panel, 'copiloto']);
$router->get('/panel/copiloto/{cliente}/mensaje', [$panel, 'mensajeCopiloto']);
$router->post('/panel/copiloto/{cliente}/enviar', [$panel, 'registrarEnvioCopiloto']);
$router->post('/panel/copiloto/{cliente}/whatsapp', [$panel, 'abrirWhatsappCopiloto']);
$router->post('/panel/copiloto/{cliente}/permiso', [$panel, 'pedirPermisoCopiloto']);
$router->post('/panel/copiloto/{cliente}/sin-promociones', [$panel, 'quitarPromocionesCopiloto']);
$router->get('/panel/cupones', [$crecimiento, 'cupones']);
$router->post('/panel/cupones', [$crecimiento, 'crearCupon']);
$router->post('/panel/cupones/{id}/alternar', [$crecimiento, 'alternarCupon']);
$router->post('/panel/cupones/{id}/eliminar', [$crecimiento, 'eliminarCupon']);
$router->get('/panel/fidelidad', [$crecimiento, 'fidelidad']);
$router->post('/panel/fidelidad', [$crecimiento, 'guardarFidelidad']);
$router->post('/panel/fidelidad/{cliente}/premio', [$crecimiento, 'entregarPremio']);
$router->get('/panel/referidos', [$crecimiento, 'referidos']);
$router->get('/panel/resenas', [$crecimiento, 'resenas']);
$router->post('/panel/resenas/{id}/comentario', [$crecimiento, 'alternarComentarioResena']);
$router->post('/panel/pedidos/{id}/resena', [$crecimiento, 'pedirResenaPedido']);
$router->post('/panel/pedidos/{id}/avisar', [$panel, 'avisarPedido']);
$router->post('/panel/citas/{id}/avisar', [$panel, 'avisarCita']);
$router->post('/panel/citas/{id}/resena', [$crecimiento, 'pedirResenaCita']);
$router->get('/panel/paquetes', [$crecimiento, 'paquetes']);
$router->post('/panel/paquetes', [$crecimiento, 'crearPaquete']);
$router->post('/panel/paquetes/vender', [$crecimiento, 'venderBono']);
$router->post('/panel/paquetes/{id}/alternar', [$crecimiento, 'alternarPaquete']);
$router->post('/panel/paquetes/{id}/eliminar', [$crecimiento, 'eliminarPaquete']);
$router->get('/panel/plan/pago', [$panel, 'regresoPagoPlan']);
$router->get('/panel/caja', [$caja, 'ver']);
$router->post('/panel/caja', [$caja, 'cerrar']);
$router->get('/panel/domicilios', [$crecimiento, 'domicilios']);
$router->post('/panel/domicilios', [$crecimiento, 'guardarZona']);
$router->post('/panel/domicilios/minimo', [$crecimiento, 'guardarPedidoMinimo']);
$router->post('/panel/domicilios/{id}', [$crecimiento, 'guardarZona']);
$router->post('/panel/domicilios/{id}/alternar', [$crecimiento, 'alternarZona']);
$router->post('/panel/domicilios/{id}/eliminar', [$crecimiento, 'eliminarZona']);
$router->get('/panel/recordatorios', [$panel, 'recordatorios']);
$router->get('/panel/recordatorios/{cita}/mensaje', [$panel, 'mensajeRecordatorio']);
$router->post('/panel/recordatorios/{cita}/enviar', [$panel, 'registrarEnvioRecordatorio']);
$router->get('/panel/sedes', [$panel, 'sedes']);
$router->post('/panel/sedes', [$panel, 'crearSede']);
$router->post('/panel/sedes/extra', [$panel, 'solicitarSedeExtra']);
$router->get('/panel/sedes/{sede}/editar', [$panel, 'editarSede']);
$router->post('/panel/sedes/{sede}/actualizar', [$panel, 'actualizarSede']);
$router->post('/panel/sede/cambiar', [$panel, 'cambiarSede']);
$router->get('/panel/colaboradores', [$panel, 'colaboradores']);
$router->post('/panel/colaboradores', [$panel, 'crearColaborador']);
$router->post('/panel/colaboradores/{id}/sedes', [$panel, 'actualizarSedesColaborador']);
$router->post('/panel/colaboradores/{id}/eliminar', [$panel, 'eliminarColaborador']);
$router->get('/panel/cuenta', [$panel, 'cuenta']);
$router->post('/panel/cuenta/correo', [$panel, 'actualizarCorreo']);
$router->post('/panel/cuenta/password', [$panel, 'actualizarPasswordCuenta']);
$router->get('/panel/plan', [$panel, 'plan']);
$router->post('/panel/plan/solicitar', [$panel, 'solicitarCambioPlan']);
$router->post('/panel/plan/cancelar', [$panel, 'cancelarSolicitudPlan']);
$router->post('/panel/copiloto/{cliente}/eliminar', [$panel, 'eliminarCliente']);

// Tiendas (fase 4): mostrador con lector, fiado, compras a proveedor y el
// catálogo compartido de códigos de barras. Solo negocios de pedidos.
$mostrador = new MostradorController();
$fiado = new FiadoController();
$compras = new ComprasController();
$router->get('/panel/mostrador', [$mostrador, 'ver']);
$router->get('/panel/mostrador/catalogo', [$mostrador, 'catalogo']);
$router->post('/panel/mostrador/escanear', [$mostrador, 'escanear']);
$router->post('/panel/mostrador/agregar', [$mostrador, 'agregar']);
$router->post('/panel/mostrador/linea', [$mostrador, 'linea']);
$router->post('/panel/mostrador/carrito', [$mostrador, 'sincronizar']);
$router->post('/panel/mostrador/vaciar', [$mostrador, 'vaciar']);
$router->post('/panel/mostrador/cobrar', [$mostrador, 'cobrar']);
$router->get('/panel/mostrador/ventas/{id}', [$mostrador, 'tiquete']);
$router->post('/panel/mostrador/ventas/{id}/anular', [$mostrador, 'anular']);
$router->get('/panel/fiado', [$fiado, 'lista']);
$router->post('/panel/fiado/clientes', [$fiado, 'crearCliente']);
$router->get('/panel/fiado/{cliente}', [$fiado, 'detalle']);
$router->post('/panel/fiado/{cliente}/abono', [$fiado, 'abonar']);
$router->post('/panel/fiado/{cliente}/cargo', [$fiado, 'cargar']);
$router->post('/panel/fiado/{cliente}/limite', [$fiado, 'limite']);
$router->post('/panel/fiado/{cliente}/recordatorio', [$fiado, 'recordar']);
$router->post('/panel/fiado/{cliente}/movimientos/{movimiento}/anular', [$fiado, 'anularMovimiento']);
$router->get('/panel/compras', [$compras, 'ver']);
$router->post('/panel/compras', [$compras, 'enviar']);
$router->post('/panel/compras/agregar', [$compras, 'agregar']);
$router->post('/panel/compras/producto', [$compras, 'crearProducto']);
$router->get('/panel/compras/{id}', [$compras, 'detalle']);
$router->get('/panel/codigos/{codigo}', [$compras, 'consultarCodigo']);

// --- Tienda pública del cliente (flujo B de la maqueta) ----------------
$router->get('/t/{slug}', [$tienda, 'mostrar']);
$router->post('/t/{slug}/carrito/agregar', [$tienda, 'agregarAlCarrito']);
$router->post('/t/{slug}/carrito/restar', [$tienda, 'restarDelCarrito']);
$router->post('/t/{slug}/carrito/quitar', [$tienda, 'quitarDelCarrito']);
$router->post('/t/{slug}/carrito/cupon', [$tienda, 'aplicarCupon']);
$router->post('/t/{slug}/carrito/cupon/quitar', [$tienda, 'quitarCupon']);
$router->get('/t/{slug}/carrito', [$tienda, 'verCarrito']);
$router->post('/t/{slug}/pedido', [$tienda, 'crearPedido']);

// --- Tienda pública del cliente, negocios de tipo reservas --------------
$router->get('/t/{slug}/reservar/{servicio}', [$tienda, 'reservar']);
$router->get('/t/{slug}/equipo/{empleado}', [$tienda, 'profesional']);
$router->get('/t/{slug}/fila', [$fila, 'formulario']);
$router->post('/t/{slug}/fila', [$fila, 'anotarse']);
// Salud: planes de tratamiento (fase 5)
$router->get('/panel/planes', [$salud, 'lista']);
$router->get('/panel/planes/nuevo', [$salud, 'nuevo']);
$router->post('/panel/planes', [$salud, 'crear']);
$router->get('/panel/planes/{id}', [$salud, 'ver']);
$router->post('/panel/planes/{id}/aprobar-consultorio', [$salud, 'aprobarEnConsultorio']);
$router->post('/panel/planes/{id}/abonos', [$salud, 'abonar']);
$router->post('/panel/planes/{id}/abonos/{abono}/anular', [$salud, 'anularAbono']);
$router->post('/panel/planes/{id}/vincular', [$salud, 'vincular']);
$router->post('/panel/planes/{id}/citas/{cita}/desvincular', [$salud, 'desvincular']);
$router->post('/panel/planes/{id}/cerrar', [$salud, 'cerrar']);
$router->post('/panel/planes/{id}/recordar', [$salud, 'recordarSaldo']);
$router->get('/plan/{token}', [$salud, 'verPaciente']);
$router->post('/plan/{token}', [$salud, 'responder']);
// Visitas a domicilio (fase 3)
$router->get('/panel/visitas/{id}', [$visita, 'hoja']);
$router->post('/panel/visitas/{id}/en-camino', [$visita, 'enCamino']);
$router->post('/panel/visitas/{id}/fotos', [$visita, 'subirFotos']);
$router->post('/panel/visitas/{id}/fotos/{foto}/eliminar', [$visita, 'eliminarFoto']);
$router->get('/panel/visitas/{id}/fotos/{foto}', [$visita, 'fotoPanel']);
$router->post('/panel/visitas/{id}/cotizar', [$visita, 'cotizar']);
$router->post('/panel/visitas/{id}/anticipo', [$visita, 'anticipoCotizacion']);
$router->get('/panel/cobertura', [$visita, 'cobertura']);
$router->post('/panel/cobertura', [$visita, 'guardarZona']);
$router->post('/panel/cobertura/{id}', [$visita, 'guardarZona']);
$router->post('/panel/cobertura/{id}/alternar', [$visita, 'alternarZona']);
$router->post('/panel/cobertura/{id}/eliminar', [$visita, 'eliminarZona']);
$router->get('/panel/repetir', [$visita, 'porRepetir']);
$router->post('/panel/repetir/{id}', [$visita, 'recordar']);
$router->get('/cotizacion/{token}', [$visita, 'cotizacionCliente']);
$router->post('/cotizacion/{token}', [$visita, 'responderCotizacion']);
$router->get('/cita/{token}/fotos/{foto}', [$visita, 'fotoCliente']);
$router->post('/cita/{token}/no-recordar', [$visita, 'noRecordar']);
$router->get('/fila/{token}', [$fila, 'estado']);
$router->post('/fila/{token}/salir', [$fila, 'salir']);
$router->post('/t/{slug}/cita', [$tienda, 'crearCita']);
$router->post('/t/{slug}/lista-espera', [$tienda, 'unirseListaEspera']);
$router->post('/t/{slug}/lista-espera/salir', [$tienda, 'salirListaEspera']);

// --- Gestión de cita por el cliente, sin login (enlace con token) -------
$router->get('/cita/{token}', [$tienda, 'gestionarCita']);
$router->get('/r/{token}', [$tienda, 'verResena']);
$router->get('/bono/{token}', [$tienda, 'verBono']);
$router->post('/r/{token}', [$tienda, 'responderResena']);
$router->post('/cita/{token}/cancelar', [$tienda, 'cancelarCitaCliente']);
$router->post('/cita/{token}/ajuste', [$tienda, 'responderAjusteCliente']);
$router->post('/cita/{token}/tarde', [$tienda, 'llegoTardeCliente']);
$router->post('/cita/{token}/espero', [$tienda, 'esperoCliente']);
$router->get('/cita/{token}/reprogramar', [$tienda, 'reprogramarCitaVista']);
$router->post('/cita/{token}/reprogramar', [$tienda, 'guardarReprogramacion']);

// --- Preferencias de mensajes del cliente, sin login (token) ------------
$router->get('/preferencias/{token}', [$preferencias, 'mostrar']);
$router->post('/preferencias/{token}', [$preferencias, 'guardar']);

// --- Webhooks de proveedores externos -----------------------------------
$router->post('/webhooks/breb', [$webhook, 'breb']);
$router->post('/webhooks/wompi', [$webhook, 'wompi']);

// --- Panel interno del equipo de Veci (no de un negocio) ---------------
$router->get('/admin/login', [$admin, 'formularioLogin']);
$router->post('/admin/login', [$admin, 'iniciarSesion']);
$router->post('/admin/logout', [$admin, 'cerrarSesion']);
$router->get('/admin', [$admin, 'dashboard']);
$router->get('/admin/negocios/{id}', [$admin, 'verNegocio']);
$router->post('/admin/negocios/{id}/suspender', [$admin, 'suspender']);
$router->post('/admin/negocios/{id}/reactivar', [$admin, 'reactivar']);
$router->post('/admin/usuarios/{usuario}/generar-reset', [$admin, 'generarReset']);
$router->post('/admin/pagos/{id}/confirmar', [$admin, 'confirmarPago']);
$router->post('/admin/pagos/{id}/rechazar', [$admin, 'rechazarPago']);

$router->despachar($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
