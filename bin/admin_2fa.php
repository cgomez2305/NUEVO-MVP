<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Activa el segundo factor (código de app autenticadora) de un admin del
 * panel interno. Sin él, /admin/login no deja entrar aunque la contraseña
 * sea correcta.
 *
 * Uso:
 *   php bin/admin_2fa.php correo@tuveci.co              activa o cambia el código
 *   php bin/admin_2fa.php correo@tuveci.co --desactivar  (si se perdió el celular; vuelve a activarlo enseguida)
 *
 * Muestra una clave para escribir en Google Authenticator, Microsoft
 * Authenticator o 1Password ("Ingresar clave de configuración"), y pide un
 * código de la app para confirmar antes de guardarla.
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Models\Admin;
use App\Models\EventoSeguridad;
use App\Totp;

$correo = $argv[1] ?? '';
$admin = $correo !== '' ? Admin::buscarPorCorreo($correo) : null;
if ($admin === null) {
    fwrite(STDERR, "Uso: php bin/admin_2fa.php correo@tuveci.co [--desactivar]\n(No existe un admin con ese correo.)\n");
    exit(1);
}

if (in_array('--desactivar', $argv, true)) {
    Admin::guardarTotp((int) $admin['id'], null);
    echo "Segundo factor desactivado para {$admin['correo']}. No podrá entrar a /admin hasta activarlo de nuevo.\n";
    exit(0);
}

$secreto = Totp::nuevoSecreto();
echo "\n1. En tu app autenticadora elige \"Ingresar clave de configuración\" y escribe:\n\n";
echo "   Cuenta: Veci ({$admin['correo']})\n";
echo "   Clave:  " . implode(' ', str_split($secreto, 4)) . "\n";
echo "   Tipo:   basada en el tiempo\n\n";
echo "   (O convierte este enlace en QR con una herramienta de confianza: " . Totp::uri($secreto, (string) $admin['correo']) . ")\n\n";
fwrite(STDERR, '2. Escribe el código de 6 dígitos que muestra la app: ');
$codigo = trim((string) fgets(STDIN));

if (Totp::verificar($secreto, $codigo, null) === null) {
    fwrite(STDERR, "Ese código no coincide. No se guardó nada; vuelve a correr el comando.\n");
    exit(1);
}

Admin::guardarTotp((int) $admin['id'], $secreto);
EventoSeguridad::registrar('admin_2fa', null, null, 'Segundo factor activado', (int) $admin['id']);
echo "Listo: desde ahora {$admin['correo']} entra a /admin con contraseña + código.\n";
