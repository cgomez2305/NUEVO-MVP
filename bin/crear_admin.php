<?php

declare(strict_types=1);

// Solo consola: si el hosting llegara a exponer bin/ por web, nadie puede
// dispararlo desde un navegador (crear admins, mandar recordatorios...).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Crea una cuenta del panel interno (/admin), el único lugar donde se
 * pueden crear: no existe (a propósito) un formulario público de registro
 * de admins. Corre esto una vez por cada persona del equipo de Veci que
 * necesite entrar.
 *
 * Uso:
 *   php bin/crear_admin.php "Nombre Apellido" correo@tuveci.co
 * La contraseña se pide por teclado (sin mostrarla): escrita como
 * argumento quedaría en el historial de la consola y en `ps`. Para
 * scripts se puede pasar por la entrada estándar.
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Models\Admin;

[$nombre, $correo] = [$argv[1] ?? '', $argv[2] ?? ''];

if ($nombre === '' || $correo === '') {
    fwrite(STDERR, "Uso: php bin/crear_admin.php \"Nombre\" correo@tuveci.co   (la contraseña se pide aparte)\n");
    exit(1);
}
if (isset($argv[3])) {
    fwrite(STDERR, "Por seguridad la contraseña ya no va en la línea de comandos: se pide a continuación.\n");
}

// Sin eco si hay terminal; si viene por tubería (scripts), se lee tal cual.
$interactivo = function_exists('posix_isatty') && posix_isatty(STDIN);
if ($interactivo) {
    fwrite(STDERR, 'Contraseña (no se ve al escribir): ');
    shell_exec('stty -echo');
}
$password = rtrim((string) fgets(STDIN), "\r\n");
if ($interactivo) {
    shell_exec('stty echo');
    fwrite(STDERR, "\n");
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Ese correo no es válido.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Usa una contraseña de al menos 12 caracteres para una cuenta de panel interno.\n");
    exit(1);
}

if (Admin::buscarPorCorreo($correo) !== null) {
    fwrite(STDERR, "Ya existe un admin con ese correo.\n");
    exit(1);
}

$id = Admin::crear($nombre, $correo, $password);
echo "Admin #{$id} creado: {$nombre} <{$correo}>.\n";
echo "Falta el segundo factor: corre ahora  php bin/admin_2fa.php {$correo}  (sin él no puede entrar a /admin).\n";
