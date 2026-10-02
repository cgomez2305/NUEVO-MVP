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
 *   php bin/crear_admin.php "Nombre Apellido" correo@tuveci.co "contraseña"
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Models\Admin;

[$nombre, $correo, $password] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];

if ($nombre === '' || $correo === '' || $password === '') {
    fwrite(STDERR, "Uso: php bin/crear_admin.php \"Nombre\" correo@tuveci.co \"contraseña\"\n");
    exit(1);
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Ese correo no es válido.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Usa una contraseña de al menos 8 caracteres para una cuenta de panel interno.\n");
    exit(1);
}

if (Admin::buscarPorCorreo($correo) !== null) {
    fwrite(STDERR, "Ya existe un admin con ese correo.\n");
    exit(1);
}

$id = Admin::crear($nombre, $correo, $password);
echo "Admin #{$id} creado: {$nombre} <{$correo}>. Ya puede entrar en /admin/login.\n";
