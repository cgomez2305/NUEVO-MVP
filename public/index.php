<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\OnboardingController;
use App\Controllers\PanelController;
use App\Controllers\TiendaController;
use App\Controllers\WebhookController;
use App\Router;

$router = new Router();

$home = new HomeController();
$auth = new AuthController();
$onboarding = new OnboardingController();
$panel = new PanelController();
$tienda = new TiendaController();
$webhook = new WebhookController();

// --- Público ---------------------------------------------------------
$router->get('/', [$home, 'index']);

$router->get('/registro', [$auth, 'formularioRegistro']);
$router->post('/registro', [$auth, 'registrar']);
$router->get('/login', [$auth, 'formularioLogin']);
$router->post('/login', [$auth, 'iniciarSesion']);
$router->post('/logout', [$auth, 'cerrarSesion']);

// --- Alta del comerciante (flujo A de la maqueta) ---------------------
$router->get('/panel/onboarding/foto', [$onboarding, 'mostrarFoto']);
$router->post('/panel/onboarding/foto', [$onboarding, 'subirFoto']);
$router->post('/panel/onboarding/analizar', [$onboarding, 'analizar']);
$router->get('/panel/onboarding/productos', [$onboarding, 'mostrarProductos']);
$router->get('/panel/onboarding/horario', [$onboarding, 'mostrarHorario']);
$router->post('/panel/onboarding/horario', [$onboarding, 'guardarHorario']);
$router->get('/panel/onboarding/pago', [$onboarding, 'mostrarPago']);
$router->post('/panel/onboarding/publicar', [$onboarding, 'publicar']);

// --- Panel del negocio -------------------------------------------------
$router->get('/panel', [$panel, 'dashboard']);
$router->get('/panel/pedidos', [$panel, 'pedidos']);
$router->post('/panel/pedidos/{id}/estado', [$panel, 'cambiarEstadoPedido']);
$router->get('/panel/pedidos/exportar.csv', [$panel, 'exportarPedidosCsv']);
$router->get('/panel/productos', [$panel, 'productos']);
$router->post('/panel/productos', [$panel, 'crearProducto']);
$router->post('/panel/productos/{id}/actualizar', [$panel, 'actualizarProducto']);
$router->post('/panel/productos/{id}/eliminar', [$panel, 'eliminarProducto']);
$router->post('/panel/productos/{id}/agotado', [$panel, 'alternarAgotadoProducto']);
$router->get('/panel/servicios', [$panel, 'servicios']);
$router->post('/panel/servicios', [$panel, 'crearServicio']);
$router->post('/panel/servicios/{id}/actualizar', [$panel, 'actualizarServicio']);
$router->post('/panel/servicios/{id}/eliminar', [$panel, 'eliminarServicio']);
$router->post('/panel/servicios/{id}/agotado', [$panel, 'alternarAgotadoServicio']);
$router->post('/panel/servicios/{id}/deposito', [$panel, 'actualizarDepositoServicio']);
$router->get('/panel/empleados', [$panel, 'empleados']);
$router->post('/panel/empleados', [$panel, 'crearEmpleado']);
$router->post('/panel/empleados/{id}/eliminar', [$panel, 'eliminarEmpleado']);
$router->get('/panel/citas', [$panel, 'citas']);
$router->post('/panel/citas/{id}/estado', [$panel, 'cambiarEstadoCita']);
$router->post('/panel/citas/{id}/anticipo', [$panel, 'marcarAnticipoPagado']);
$router->get('/panel/citas/exportar.csv', [$panel, 'exportarCitasCsv']);
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
$router->get('/panel/recordatorios', [$panel, 'recordatorios']);
$router->get('/panel/recordatorios/{cita}/mensaje', [$panel, 'mensajeRecordatorio']);
$router->post('/panel/recordatorios/{cita}/enviar', [$panel, 'registrarEnvioRecordatorio']);
$router->get('/panel/sedes', [$panel, 'sedes']);
$router->post('/panel/sedes', [$panel, 'crearSede']);
$router->post('/panel/sede/cambiar', [$panel, 'cambiarSede']);
$router->get('/panel/colaboradores', [$panel, 'colaboradores']);
$router->post('/panel/colaboradores', [$panel, 'crearColaborador']);
$router->post('/panel/colaboradores/{id}/sedes', [$panel, 'actualizarSedesColaborador']);
$router->post('/panel/colaboradores/{id}/eliminar', [$panel, 'eliminarColaborador']);

// --- Tienda pública del cliente (flujo B de la maqueta) ----------------
$router->get('/t/{slug}', [$tienda, 'mostrar']);
$router->post('/t/{slug}/carrito/agregar', [$tienda, 'agregarAlCarrito']);
$router->post('/t/{slug}/carrito/quitar', [$tienda, 'quitarDelCarrito']);
$router->get('/t/{slug}/carrito', [$tienda, 'verCarrito']);
$router->post('/t/{slug}/pedido', [$tienda, 'crearPedido']);

// --- Tienda pública del cliente, negocios de tipo reservas --------------
$router->get('/t/{slug}/reservar/{servicio}', [$tienda, 'reservar']);
$router->post('/t/{slug}/cita', [$tienda, 'crearCita']);

// --- Gestión de cita por el cliente, sin login (enlace con token) -------
$router->get('/cita/{token}', [$tienda, 'gestionarCita']);
$router->post('/cita/{token}/cancelar', [$tienda, 'cancelarCitaCliente']);
$router->get('/cita/{token}/reprogramar', [$tienda, 'reprogramarCitaVista']);
$router->post('/cita/{token}/reprogramar', [$tienda, 'guardarReprogramacion']);

// --- Webhooks de proveedores externos -----------------------------------
$router->post('/webhooks/breb', [$webhook, 'breb']);

$router->despachar($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
