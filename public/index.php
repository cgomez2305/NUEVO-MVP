<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\OnboardingController;
use App\Controllers\PanelController;
use App\Controllers\TiendaController;
use App\Router;

$router = new Router();

$home = new HomeController();
$auth = new AuthController();
$onboarding = new OnboardingController();
$panel = new PanelController();
$tienda = new TiendaController();

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
$router->get('/panel/onboarding/pago', [$onboarding, 'mostrarPago']);
$router->post('/panel/onboarding/publicar', [$onboarding, 'publicar']);

// --- Panel del negocio -------------------------------------------------
$router->get('/panel', [$panel, 'dashboard']);
$router->get('/panel/pedidos', [$panel, 'pedidos']);
$router->post('/panel/pedidos/{id}/estado', [$panel, 'cambiarEstadoPedido']);
$router->get('/panel/productos', [$panel, 'productos']);
$router->post('/panel/productos', [$panel, 'crearProducto']);
$router->post('/panel/productos/{id}/actualizar', [$panel, 'actualizarProducto']);
$router->post('/panel/productos/{id}/eliminar', [$panel, 'eliminarProducto']);
$router->get('/panel/copiloto', [$panel, 'copiloto']);
$router->get('/panel/copiloto/{cliente}/mensaje', [$panel, 'mensajeCopiloto']);
$router->post('/panel/copiloto/{cliente}/enviar', [$panel, 'registrarEnvioCopiloto']);

// --- Tienda pública del cliente (flujo B de la maqueta) ----------------
$router->get('/t/{slug}', [$tienda, 'mostrar']);
$router->post('/t/{slug}/carrito/agregar', [$tienda, 'agregarAlCarrito']);
$router->post('/t/{slug}/carrito/quitar', [$tienda, 'quitarDelCarrito']);
$router->get('/t/{slug}/carrito', [$tienda, 'verCarrito']);
$router->post('/t/{slug}/pedido', [$tienda, 'crearPedido']);

$router->despachar($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
