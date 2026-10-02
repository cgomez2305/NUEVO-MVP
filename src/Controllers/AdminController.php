<?php

declare(strict_types=1);

namespace App\Controllers;

use App\AdminAuth;
use App\Database;
use App\Models\LimiteTasa;
use App\Models\Negocio;
use App\Models\PagoPlan;
use App\Models\Plan;
use App\Models\Sede;
use App\Models\Usuario;

/**
 * Panel interno del equipo de Veci (no de un negocio): ver todos los
 * negocios, suspender/reactivar cuentas y generar un enlace de
 * recuperación de contraseña a mano cuando alguien se queda bloqueado y
 * no tiene correo (o no llega el correo). Requiere AdminAuth, que es una
 * sesión completamente separada de la de un negocio (App\Auth).
 */
class AdminController
{
    public function formularioLogin(array $parametros): void
    {
        if (AdminAuth::adminActual() !== null) {
            redirigir('/admin');
        }

        ver('admin/login', [
            'titulo' => 'Panel interno · Veci',
            'error'  => flash_obtener('error'),
        ], 'auth');
    }

    public function iniciarSesion(array $parametros): void
    {
        if (!csrf_verificar()) {
            flash_set('error', 'El formulario expiró, intenta de nuevo.');
            redirigir('/admin/login');
        }

        $correo = trim((string) ($_POST['correo'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $ip = ip_cliente();
        if (AdminAuth::estaBloqueado($correo) || LimiteTasa::excedido('login_admin', $ip, 10, 15 * 60)) {
            flash_set('error', 'Demasiados intentos fallidos. Espera unos minutos e intenta de nuevo.');
            redirigir('/admin/login');
        }

        if (!AdminAuth::intentarLogin($correo, $password)) {
            LimiteTasa::registrar('login_admin', $ip);
            flash_set('error', 'Correo o contraseña incorrectos.');
            redirigir('/admin/login');
        }

        redirigir('/admin');
    }

    public function cerrarSesion(array $parametros): void
    {
        if (csrf_verificar()) {
            AdminAuth::cerrarSesion();
        }
        redirigir('/admin/login');
    }

    public function dashboard(array $parametros): void
    {
        $admin = AdminAuth::exigirSesion();
        $busqueda = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $filtro = (string) ($_GET['filtro'] ?? 'todos');
        if (!array_key_exists($filtro, Negocio::FILTROS_ADMIN)) {
            $filtro = 'todos';
        }

        ver('admin/dashboard', [
            'titulo'          => 'Negocios · Panel interno · Veci',
            'admin'           => $admin,
            'negocios'        => Negocio::listarTodos($busqueda, $filtro),
            'busqueda'        => $busqueda,
            'filtro'          => $filtro,
            'resumen'         => Negocio::resumenAdmin(),
            'pagosPendientes' => PagoPlan::listarPendientes(),
            'ok'              => flash_obtener('ok'),
            'error'           => flash_obtener('error'),
        ], 'admin');
    }

    public function verNegocio(array $parametros): void
    {
        $admin = AdminAuth::exigirSesion();
        $negocio = Negocio::buscarPorId((int) $parametros['id']);

        if ($negocio === null) {
            flash_set('error', 'Ese negocio no existe.');
            redirigir('/admin');
        }

        $stmtNegocioId = (int) $negocio['id'];

        ver('admin/negocio', [
            'titulo'       => $negocio['nombre'] . ' · Panel interno · Veci',
            'admin'        => $admin,
            'negocio'      => $negocio,
            'plan'         => Plan::buscarPorId((int) $negocio['plan_id']),
            'pagosPlan'    => PagoPlan::listarPorNegocio($stmtNegocioId),
            'sedes'        => Sede::listarPorNegocio($stmtNegocioId),
            'usuarios'     => $this->usuariosDelNegocio($stmtNegocioId),
            'ok'           => flash_obtener('ok'),
            'error'        => flash_obtener('error'),
            'resetEnlace'  => flash_obtener('reset_enlace'),
            'resetUsuario' => flash_obtener('reset_usuario'),
        ], 'admin');
    }

    /** Confirma un cobro manual pendiente: activa/extiende el plan pago del negocio (ver PagoPlan::confirmar). */
    public function confirmarPago(array $parametros): void
    {
        $admin = AdminAuth::exigirSesion();
        $pago = PagoPlan::buscarPorId((int) $parametros['id']);

        if ($pago === null) {
            redirigir('/admin');
        }

        if (csrf_verificar()) {
            // El admin escribe el monto que vio llegar en su Bre-B, en vez
            // de un clic a ciegas: si no coincide exacto con lo esperado, no
            // se activa nada (transferencia parcial, plan equivocado, typo).
            $montoRecibido = dinero_desde_texto((string) ($_POST['monto_recibido'] ?? ''));

            if ($pago['confirmado_en'] !== null) {
                flash_set('error', 'Ese pago ya estaba confirmado.');
            } elseif ($montoRecibido !== (int) $pago['monto']) {
                flash_set('error', 'El monto recibido (' . pesos($montoRecibido) . ') no coincide con el esperado (' . pesos((int) $pago['monto']) . '). No se activó el plan.');
            } elseif (!PagoPlan::confirmar((int) $pago['id'], (int) $admin['id'])) {
                flash_set('error', 'Ese pago ya estaba confirmado.');
            } else {
                flash_set('ok', 'Pago confirmado: el plan de ' . $this->nombreNegocio((int) $pago['negocio_id']) . ' ya quedó activo.');
            }
        }

        redirigir($this->volver((int) $pago['negocio_id']));
    }

    /** Descarta una solicitud de cambio de plan que nunca se pagó, para que el dueño pueda volver a pedir. */
    public function rechazarPago(array $parametros): void
    {
        AdminAuth::exigirSesion();
        $pago = PagoPlan::buscarPorId((int) $parametros['id']);

        if ($pago === null) {
            redirigir('/admin');
        }

        if (csrf_verificar()) {
            if (PagoPlan::rechazar((int) $pago['id'])) {
                flash_set('ok', 'Solicitud descartada.');
            } else {
                flash_set('error', 'Ese pago ya estaba confirmado; no se puede descartar.');
            }
        }

        redirigir($this->volver((int) $pago['negocio_id']));
    }

    public function suspender(array $parametros): void
    {
        AdminAuth::exigirSesion();

        if (csrf_verificar()) {
            Negocio::suspender((int) $parametros['id']);
            flash_set('ok', 'Cuenta suspendida. Nadie de ese negocio puede entrar ni su tienda pública responde.');
        }

        redirigir('/admin/negocios/' . (int) $parametros['id']);
    }

    public function reactivar(array $parametros): void
    {
        AdminAuth::exigirSesion();

        if (csrf_verificar()) {
            Negocio::reactivar((int) $parametros['id']);
            flash_set('ok', 'Cuenta reactivada.');
        }

        redirigir('/admin/negocios/' . (int) $parametros['id']);
    }

    /** Genera un enlace de recuperación de contraseña para un usuario y lo muestra una sola vez, para que el admin lo copie y lo mande por WhatsApp. */
    public function generarReset(array $parametros): void
    {
        AdminAuth::exigirSesion();
        $usuario = Usuario::buscarPorId((int) $parametros['usuario']);

        if ($usuario === null) {
            redirigir('/admin');
        }

        if (csrf_verificar()) {
            $token = Usuario::generarTokenReset((int) $usuario['id']);
            flash_set('reset_enlace', url_publica('/reset-password/' . $token));
            flash_set('reset_usuario', $usuario['nombre'] . ' (' . $usuario['whatsapp'] . ')');
        }

        redirigir('/admin/negocios/' . (int) $usuario['negocio_id']);
    }

    /**
     * Los pagos se confirman desde la lista (/admin) o desde la ficha del
     * negocio: se vuelve a donde se hizo, para no perder el hilo cuando hay
     * varios por confirmar.
     */
    private function volver(int $negocioId): string
    {
        return ($_POST['volver'] ?? '') === '/admin' ? '/admin' : '/admin/negocios/' . $negocioId;
    }

    private function nombreNegocio(int $negocioId): string
    {
        return (string) (Negocio::buscarPorId($negocioId)['nombre'] ?? 'el negocio');
    }

    /** @return array<int, array<string, mixed>> */
    private function usuariosDelNegocio(int $negocioId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM usuarios WHERE negocio_id = :negocio_id ORDER BY rol ASC, nombre ASC'
        );
        $stmt->execute(['negocio_id' => $negocioId]);
        return $stmt->fetchAll();
    }
}
